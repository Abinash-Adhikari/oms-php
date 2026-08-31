#!/usr/bin/env bash
#
# SB-Tech Phase 0 smoke test — exercises the foundation end-to-end:
#   login page render, CSRF enforcement, failed login, successful login,
#   dashboard render, module placeholder, and RBAC denial for a restricted user.
#
# Usage: bash scripts/smoke_test.sh
# Requires: php, curl, mysql (with the credentials from config/setup.php).
set -u

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PORT="${PORT:-8092}"
BASE="http://127.0.0.1:$PORT"
JAR="$(mktemp)"
PASS=0
FAIL=0

DB_USER="$(php -r 'require $argv[1]; echo config("db_username");' "$ROOT/config/setup.php")"
DB_PASS="$(php -r 'require $argv[1]; echo config("db_password");' "$ROOT/config/setup.php")"
DB_NAME="$(php -r 'require $argv[1]; echo config("db_name");' "$ROOT/config/setup.php")"
DB_SOCKET="$(php -r 'require $argv[1]; echo config("db_socket") ?: "";' "$ROOT/config/setup.php")"
MYSQL_ARGS=(-u "$DB_USER" -p"$DB_PASS" "$DB_NAME")
[ -n "$DB_SOCKET" ] && MYSQL_ARGS+=(--socket="$DB_SOCKET")

check() { # check <description> <expected> <actual>
    if [ "$2" = "$3" ]; then
        echo "  PASS: $1"
        PASS=$((PASS + 1))
    else
        echo "  FAIL: $1 (expected [$2], got [$3])"
        FAIL=$((FAIL + 1))
    fi
}

cleanup() {
    [ -n "${SRV:-}" ] && kill "$SRV" 2>/dev/null
    rm -f "$JAR"
    # Remove the smoke-test user if it exists.
    mysql "${MYSQL_ARGS[@]}" -e "DELETE FROM tbl_users_login WHERE username = 'smoketest';" 2>/dev/null
}
trap cleanup EXIT

echo "== Starting dev server on :$PORT =="
php -S "127.0.0.1:$PORT" -t "$ROOT" > /tmp/sbtech_smoke.log 2>&1 &
SRV=$!
sleep 1

echo "== 1. Login page renders =="
# Single request: the session cookie and CSRF token must come from the SAME
# page load (a second request without -b would create a new session).
HTTP_CODE="$(curl -s -o /tmp/sb_body.html -w '%{http_code}' -c "$JAR" "$BASE/admin/login.php")"
BODY="$(cat /tmp/sb_body.html)"
check "HTTP 200" "200" "$HTTP_CODE"
echo "$BODY" | grep -q 'name="csrf_token"' && { echo "  PASS: CSRF field present"; PASS=$((PASS+1)); } || { echo "  FAIL: CSRF field missing"; FAIL=$((FAIL+1)); }

TOKEN="$(echo "$BODY" | grep -oP 'name="csrf_token" value="\K[^"]+')"
[ -n "$TOKEN" ] || { echo "  FAIL: could not extract CSRF token"; exit 1; }

echo "== 2. CSRF enforcement =="
CODE="$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -d "userId=admin&password=admin" "$BASE/admin/loginOperation.php")"
check "POST without token rejected (419)" "419" "$CODE"

echo "== 3. Wrong password =="
curl -s -o /dev/null -b "$JAR" -d "userId=admin&password=wrong&csrf_token=$TOKEN" "$BASE/admin/loginOperation.php"
BODY="$(curl -s -b "$JAR" "$BASE/admin/login.php")"
echo "$BODY" | grep -q "Invalid UserId or Password" && { echo "  PASS: error message shown"; PASS=$((PASS+1)); } || { echo "  FAIL: no error message"; FAIL=$((FAIL+1)); }

echo "== 4. Successful login → dashboard =="
LOC="$(curl -s -o /dev/null -w '%{redirect_url}' -b "$JAR" -c "$JAR" -d "userId=admin&password=admin&csrf_token=$TOKEN" "$BASE/admin/loginOperation.php")"
echo "$LOC" | grep -q "show_page.php?module=dashboard" && { echo "  PASS: redirected to dashboard ($LOC)"; PASS=$((PASS+1)); } || { echo "  FAIL: redirect was [$LOC]"; FAIL=$((FAIL+1)); }

DASH="$(curl -s -b "$JAR" "$BASE/admin/show_page.php?module=dashboard&page=home")"
echo "$DASH" | grep -q "Active Staff" && { echo "  PASS: dashboard KPI rendered"; PASS=$((PASS+1)); } || { echo "  FAIL: dashboard KPI missing"; FAIL=$((FAIL+1)); }
echo "$DASH" | grep -q "Super Admin" && { echo "  PASS: sidebar shows logged-in user"; PASS=$((PASS+1)); } || { echo "  FAIL: user name missing in navbar"; FAIL=$((FAIL+1)); }
echo "$DASH" | grep -qE ">(MAIN|OFFICE|STAFF|SALES|FINANCE|COMMS|WEBSITE|SETTINGS)<" && { echo "  PASS: sidebar sections rendered"; PASS=$((PASS+1)); } || { echo "  FAIL: sidebar sections missing"; FAIL=$((FAIL+1)); }

echo "== 5. Unbuilt module placeholder =="
BODY="$(curl -s -b "$JAR" "$BASE/admin/show_page.php?module=communication&page=email_sms")"
echo "$BODY" | grep -q "Page not installed" && { echo "  PASS: placeholder shown for unbuilt module"; PASS=$((PASS+1)); } || { echo "  FAIL: placeholder missing"; FAIL=$((FAIL+1)); }

echo "== 6. RBAC: restricted user =="
# Create a user with only dashboard + my_office/hr_care grants.
HASH="$(php -r "echo hash('sha512', hash('sha512', 'test123') . 'smoke-salt');")"
mysql "${MYSQL_ARGS[@]}" -e "INSERT INTO tbl_users_login (username, email, password, salt, fullname, permitted_modules, permitted_submodules, special_permission, role, status) VALUES ('smoketest', 'smoke@local', '$HASH', 'smoke-salt', 'Smoke Test', '[\"dashboard\",\"my_office\"]', '{\"my_office\":[\"hr_care\",\"office_calendar\"]}', '[]', 'Staff', 'Active');"

JAR2="$(mktemp)"
BODY="$(curl -s -c "$JAR2" "$BASE/admin/login.php")"
T2="$(echo "$BODY" | grep -oP 'name="csrf_token" value="\K[^"]+')"
curl -s -o /dev/null -b "$JAR2" -c "$JAR2" -d "userId=smoketest&password=test123&csrf_token=$T2" "$BASE/admin/loginOperation.php"

RESTRICTED="$(curl -s -b "$JAR2" "$BASE/admin/show_page.php?module=dashboard&page=home")"
echo "$RESTRICTED" | grep -q "Staff Management" && { echo "  FAIL: hidden module leaked into sidebar"; FAIL=$((FAIL+1)); } || { echo "  PASS: restricted sidebar hides unpermitted modules"; PASS=$((PASS+1)); }
echo "$RESTRICTED" | grep -q "HR Care" && { echo "  PASS: granted submodule visible"; PASS=$((PASS+1)); } || { echo "  FAIL: granted submodule missing"; FAIL=$((FAIL+1)); }

DENIED="$(curl -s -b "$JAR2" "$BASE/admin/show_page.php?module=accounts&page=ledger")"
echo "$DENIED" | grep -q "Access denied" && { echo "  PASS: unpermitted page shows Access denied"; PASS=$((PASS+1)); } || { echo "  FAIL: Access denied not shown"; FAIL=$((FAIL+1)); }
CODE="$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR2" -d "module=accounts&page=ledger&csrf_token=$T2" "$BASE/admin/operation.php")"
check "POST to unpermitted operation blocked (403)" "403" "$CODE"

rm -f "$JAR2"

# ---------------------------------------------------------------------------
# Phase 1: Setup + Staff CRUD (admin session in $JAR, token in $TOKEN)
# ---------------------------------------------------------------------------
post() { # post <url-path> <data> → follows redirect, prints body
    curl -s -L -b "$JAR" -d "csrf_token=$TOKEN&$2" "$BASE$1"
}

echo "== 7. Office profile update =="
BODY="$(post '/admin/operation.php?module=office_setup&page=office_profile' 'id=1&name=SB-Tech%20Test%20Office&accronym=SB-TEST')"
echo "$BODY" | grep -q 'value="SB-Tech Test Office"' && { echo "  PASS: profile name saved + reflected"; PASS=$((PASS+1)); } || { echo "  FAIL: profile update failed"; FAIL=$((FAIL+1)); }

echo "== 8. Department CRUD =="
BODY="$(post '/admin/operation.php?module=office_setup&page=departments' 'title=Engineering&position=1')"
echo "$BODY" | grep -q '>Engineering<' && { echo "  PASS: department created + listed"; PASS=$((PASS+1)); } || { echo "  FAIL: department create failed"; FAIL=$((FAIL+1)); }
BODY="$(post '/admin/operation.php?module=office_setup&page=departments' 'title=Engineering&position=2')"
echo "$BODY" | grep -q 'already exists' && { echo "  PASS: duplicate department blocked"; PASS=$((PASS+1)); } || { echo "  FAIL: duplicate not blocked"; FAIL=$((FAIL+1)); }
DEP_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_office_departments WHERE title='Engineering' LIMIT 1;" | head -1)"
BODY="$(post '/admin/operation.php?module=office_setup&page=departments' "action=delete&id=$DEP_ID")"
echo "$BODY" | grep -q 'Department deleted' && { echo "  PASS: department deleted"; PASS=$((PASS+1)); } || { echo "  FAIL: department delete failed"; FAIL=$((FAIL+1)); }

echo "== 9. Designation CRUD =="
BODY="$(post '/admin/operation.php?module=office_setup&page=designations' 'title=Senior+Engineer&position=1')"
echo "$BODY" | grep -q '>Senior Engineer<' && { echo "  PASS: designation created + listed"; PASS=$((PASS+1)); } || { echo "  FAIL: designation create failed"; FAIL=$((FAIL+1)); }
DESIG_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_office_designation WHERE title='Senior Engineer' LIMIT 1;" | head -1)"
post '/admin/operation.php?module=office_setup&page=designations' "action=delete&id=$DESIG_ID" > /dev/null
[ -z "$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_office_designation WHERE id=$DESIG_ID;")" ] && { echo "  PASS: designation deleted"; PASS=$((PASS+1)); } || { echo "  FAIL: designation delete failed"; FAIL=$((FAIL+1)); }

echo "== 10. Holiday CRUD =="
BODY="$(post '/admin/operation.php?module=office_setup&page=holidays' 'title=Dashain&from_date=2026-10-01&to_date=2026-10-03&gender_to=Both')"
echo "$BODY" | grep -q '>Dashain<' && { echo "  PASS: holiday created + listed"; PASS=$((PASS+1)); } || { echo "  FAIL: holiday create failed"; FAIL=$((FAIL+1)); }
HOL_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_office_holidays WHERE title='Dashain' LIMIT 1;" | head -1)"
post '/admin/operation.php?module=office_setup&page=holidays' "action=delete&id=$HOL_ID" > /dev/null
[ -z "$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_office_holidays WHERE id=$HOL_ID;")" ] && { echo "  PASS: holiday deleted"; PASS=$((PASS+1)); } || { echo "  FAIL: holiday delete failed"; FAIL=$((FAIL+1)); }

# Recreate Engineering dept for the staff tests (deleted above).
post '/admin/operation.php?module=office_setup&page=departments' 'title=Engineering&position=1' > /dev/null
DEP_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_office_departments WHERE title='Engineering' LIMIT 1;" | head -1)"
post '/admin/operation.php?module=office_setup&page=designations' 'title=Senior+Engineer&position=1' > /dev/null
DESIG_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_office_designation WHERE title='Senior Engineer' LIMIT 1;" | head -1)"

FULLNAME_ENC="SB+Tech+Staff"

echo "== 11. Staff create + validation =="
BODY="$(post '/admin/operation.php?module=staff_management&page=add_staff' "fullname=$FULLNAME_ENC&username=smokestaff&password=test123&staff_type=Admin&department_id=$DEP_ID&designation_id=$DESIG_ID&join_date=2026-01-05&status=Active")"
echo "$BODY" | grep -q 'Staff created' && { echo "  PASS: staff created"; PASS=$((PASS+1)); } || { echo "  FAIL: staff create failed"; FAIL=$((FAIL+1)); }
BODY="$(post '/admin/operation.php?module=staff_management&page=add_staff' "fullname=Duplicate&username=smokestaff&password=test123")"
echo "$BODY" | grep -q 'Username already taken' && { echo "  PASS: duplicate username blocked"; PASS=$((PASS+1)); } || { echo "  FAIL: duplicate username not blocked"; FAIL=$((FAIL+1)); }
STAFF_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_users_login WHERE username='smokestaff' LIMIT 1;" | head -1)"
BODY="$(curl -s -L -b "$JAR" "$BASE/admin/show_page.php?module=staff_management&page=staff_history&id=$STAFF_ID")"
echo "$BODY" | grep -q 'Joined' && { echo "  PASS: staff history 'Joined' event recorded"; PASS=$((PASS+1)); } || { echo "  FAIL: history event missing"; FAIL=$((FAIL+1)); }

USER_PROFILE_COUNT="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT COUNT(*) FROM tbl_user_profiles WHERE user_id=$STAFF_ID;" | head -1)"
[ "$USER_PROFILE_COUNT" = "1" ] && { echo "  PASS: user profile row created"; PASS=$((PASS+1)); } || { echo "  FAIL: user profile missing"; FAIL=$((FAIL+1)); }

# Restore office profile name.
post '/admin/operation.php?module=office_setup&page=office_profile' 'id=1&name=SB-Tech&accronym=SB-TECH' > /dev/null

# ---------------------------------------------------------------------------
# Phase 2: Attendance + Leave (admin session in $JAR, token in $TOKEN)
# ---------------------------------------------------------------------------
YEAR="$(date +%Y)"
ADMIN_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_users_login WHERE username='admin' LIMIT 1;" | head -1)"
SMOKE_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_users_login WHERE username='smoketest' LIMIT 1;" | head -1)"

echo "== 12. HR Care attendance tab renders =="
BODY="$(curl -s -b "$JAR" "$BASE/admin/show_page.php?module=my_office&page=hr_care&tab=attendance")"
echo "$BODY" | grep -q "Check In" && { echo "  PASS: attendance tab renders check-in card"; PASS=$((PASS+1)); } || { echo "  FAIL: attendance tab missing"; FAIL=$((FAIL+1)); }
echo "$BODY" | grep -q "Monthly report" && { echo "  PASS: monthly report card renders"; PASS=$((PASS+1)); } || { echo "  FAIL: monthly report missing"; FAIL=$((FAIL+1)); }

echo "== 13. Check-in / check-out =="
curl -s -L -o /dev/null -b "$JAR" -d "csrf_token=$TOKEN&action=checkin" "$BASE/admin/operation.php?module=my_office&page=hr_care"
ROW="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT checkin FROM tbl_staff_attendances WHERE user_id=$ADMIN_ID AND date=CURDATE();" | head -1)"
[ -n "$ROW" ] && { echo "  PASS: check-in recorded ($ROW)"; PASS=$((PASS+1)); } || { echo "  FAIL: check-in not recorded"; FAIL=$((FAIL+1)); }
BODY="$(curl -s -L -b "$JAR" -d "csrf_token=$TOKEN&action=checkin" "$BASE/admin/operation.php?module=my_office&page=hr_care")"
echo "$BODY" | grep -q "Already checked in" && { echo "  PASS: duplicate check-in blocked"; PASS=$((PASS+1)); } || { echo "  FAIL: duplicate check-in allowed"; FAIL=$((FAIL+1)); }
sleep 1
curl -s -L -o /dev/null -b "$JAR" -d "csrf_token=$TOKEN&action=checkout" "$BASE/admin/operation.php?module=my_office&page=hr_care"
CO="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT checkout FROM tbl_staff_attendances WHERE user_id=$ADMIN_ID AND date=CURDATE();" | head -1)"
[ -n "$CO" ] && [ "$CO" != "NULL" ] && { echo "  PASS: check-out recorded ($CO)"; PASS=$((PASS+1)); } || { echo "  FAIL: check-out not recorded"; FAIL=$((FAIL+1)); }
WH="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT working_hours FROM tbl_staff_attendances WHERE user_id=$ADMIN_ID AND date=CURDATE();" | head -1)"
[ -n "$WH" ] && [ "$WH" != "NULL" ] && [ "$WH" != "0" ] && [ "$WH" != "0.00" ] && { echo "  PASS: working hours computed ($WH)"; PASS=$((PASS+1)); } || { echo "  FAIL: working hours not computed"; FAIL=$((FAIL+1)); }

echo "== 14. Leave type CRUD =="
BODY="$(post '/admin/operation.php?module=staff_management&page=leave_management' 'action=save_type&title=Annual+Leave&max_allowed=20&gender_specific=Both&is_active=1&requires_approval=1')"
echo "$BODY" | grep -q "Leave type created" && { echo "  PASS: leave type created"; PASS=$((PASS+1)); } || { echo "  FAIL: leave type create failed"; FAIL=$((FAIL+1)); }
BODY="$(post '/admin/operation.php?module=staff_management&page=leave_management' 'action=save_type&title=Annual+Leave&max_allowed=20&gender_specific=Both&is_active=1&requires_approval=1')"
echo "$BODY" | grep -q "already exists" && { echo "  PASS: duplicate leave type blocked"; PASS=$((PASS+1)); } || { echo "  FAIL: duplicate leave type allowed"; FAIL=$((FAIL+1)); }
LEAVE_TYPE_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_office_leave_configs WHERE title='Annual Leave' LIMIT 1;" | head -1)"

echo "== 15. Allocate leave to smoketest =="
BODY="$(post '/admin/operation.php?module=staff_management&page=leave_management' "action=save_allocations&year=$YEAR&alloc%5B$SMOKE_ID%5D%5B$LEAVE_TYPE_ID%5D=10&carry%5B$SMOKE_ID%5D%5B$LEAVE_TYPE_ID%5D=0")"
echo "$BODY" | grep -q "Allocations saved" && { echo "  PASS: allocation saved"; PASS=$((PASS+1)); } || { echo "  FAIL: allocation save failed"; FAIL=$((FAIL+1)); }
ALLOC_USED="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT used_days FROM tbl_office_staff_leave_allocation WHERE staff_id=$SMOKE_ID AND leave_id=$LEAVE_TYPE_ID AND year=$YEAR;" | head -1)"
[ "$ALLOC_USED" = "0" ] || [ "$ALLOC_USED" = "0.0" ] && { echo "  PASS: allocation starts with 0 used"; PASS=$((PASS+1)); } || { echo "  FAIL: allocation used_days not 0 ($ALLOC_USED)"; FAIL=$((FAIL+1)); }

echo "== 16. Staff applies for leave (self-service) =="
JAR3="$(mktemp)"
BODY="$(curl -s -c "$JAR3" "$BASE/admin/login.php")"
T3="$(echo "$BODY" | grep -oP 'name="csrf_token" value="\K[^"]+')"
curl -s -o /dev/null -b "$JAR3" -c "$JAR3" -d "userId=smoketest&password=test123&csrf_token=$T3" "$BASE/admin/loginOperation.php"
FROM1="$(date -d '+3 days' +%F)"
TO1="$(date -d '+4 days' +%F)"
BODY="$(curl -s -L -b "$JAR3" -d "csrf_token=$T3&action=save_leave&leave_type_id=$LEAVE_TYPE_ID&absence_filler=$ADMIN_ID&from_date=$FROM1&to_date=$TO1&reason=Vacation" "$BASE/admin/operation.php?module=my_office&page=hr_care")"
echo "$BODY" | grep -q "submitted" && { echo "  PASS: leave application submitted"; PASS=$((PASS+1)); } || { echo "  FAIL: leave apply failed"; FAIL=$((FAIL+1)); }
LEAVE_APP_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_staff_leave_applications WHERE staff_id=$SMOKE_ID ORDER BY id DESC LIMIT 1;" | head -1)"
APP_DAYS="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT leave_days FROM tbl_staff_leave_applications WHERE id=$LEAVE_APP_ID;" | head -1)"
[ "$APP_DAYS" = "2" ] || [ "$APP_DAYS" = "2.0" ] && { echo "  PASS: leave days counted as 2"; PASS=$((PASS+1)); } || { echo "  FAIL: leave days = $APP_DAYS (expected 2)"; FAIL=$((FAIL+1)); }

echo "== 17. Over-balance blocked =="
FROM2="$(date -d '+10 days' +%F)"
TO2="$(date -d '+29 days' +%F)"
BODY="$(curl -s -L -b "$JAR3" -d "csrf_token=$T3&action=save_leave&leave_type_id=$LEAVE_TYPE_ID&absence_filler=$ADMIN_ID&from_date=$FROM2&to_date=$TO2&reason=Too+long" "$BASE/admin/operation.php?module=my_office&page=hr_care")"
echo "$BODY" | grep -q "exceed" && { echo "  PASS: over-balance application blocked"; PASS=$((PASS+1)); } || { echo "  FAIL: over-balance not blocked"; FAIL=$((FAIL+1)); }

echo "== 18. Reject requires reason =="
BODY="$(post '/admin/operation.php?module=staff_management&page=leave_management' "action=update_status&id=$LEAVE_APP_ID&status=Rejected")"
echo "$BODY" | grep -q "reason is required" && { echo "  PASS: rejection without reason blocked"; PASS=$((PASS+1)); } || { echo "  FAIL: rejection without reason allowed"; FAIL=$((FAIL+1)); }

echo "== 19. Verify -> Approve syncs used_days =="
BODY="$(post '/admin/operation.php?module=staff_management&page=leave_management' "action=update_status&id=$LEAVE_APP_ID&status=Verified")"
echo "$BODY" | grep -q "verified" && { echo "  PASS: leave verified"; PASS=$((PASS+1)); } || { echo "  FAIL: verify failed"; FAIL=$((FAIL+1)); }
BODY="$(post '/admin/operation.php?module=staff_management&page=leave_management' "action=update_status&id=$LEAVE_APP_ID&status=Approved")"
echo "$BODY" | grep -q "approved" && { echo "  PASS: leave approved"; PASS=$((PASS+1)); } || { echo "  FAIL: approve failed"; FAIL=$((FAIL+1)); }
USED="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT used_days FROM tbl_office_staff_leave_allocation WHERE staff_id=$SMOKE_ID AND leave_id=$LEAVE_TYPE_ID AND year=$YEAR;" | head -1)"
[ "$USED" = "2" ] || [ "$USED" = "2.0" ] && { echo "  PASS: used_days synced to 2"; PASS=$((PASS+1)); } || { echo "  FAIL: used_days = $USED (expected 2)"; FAIL=$((FAIL+1)); }
BODY="$(curl -s -b "$JAR3" "$BASE/admin/show_page.php?module=my_office&page=hr_care&tab=leaves")"
echo "$BODY" | grep -q "Approved" && { echo "  PASS: staff sees Approved status"; PASS=$((PASS+1)); } || { echo "  FAIL: staff status not shown"; FAIL=$((FAIL+1)); }
rm -f "$JAR3"

echo "== 20. Leave report tab renders =="
BODY="$(curl -s -b "$JAR" "$BASE/admin/show_page.php?module=staff_management&page=leave_management&tab=leave_report")"
echo "$BODY" | grep -q "Leave usage" && { echo "  PASS: leave report renders"; PASS=$((PASS+1)); } || { echo "  FAIL: leave report missing"; FAIL=$((FAIL+1)); }

echo "== 21. RBAC: leave mgmt blocked for non-manager =="
JAR4="$(mktemp)"
BODY="$(curl -s -c "$JAR4" "$BASE/admin/login.php")"
T4="$(echo "$BODY" | grep -oP 'name="csrf_token" value="\K[^"]+')"
curl -s -o /dev/null -b "$JAR4" -c "$JAR4" -d "userId=smoketest&password=test123&csrf_token=$T4" "$BASE/admin/loginOperation.php"
BODY="$(curl -s -b "$JAR4" "$BASE/admin/show_page.php?module=staff_management&page=leave_management")"
echo "$BODY" | grep -q "Access denied" && { echo "  PASS: leave mgmt page denied for non-manager"; PASS=$((PASS+1)); } || { echo "  FAIL: leave mgmt not denied"; FAIL=$((FAIL+1)); }
rm -f "$JAR4"

# Cleanup Phase 2 data (leave app, type, allocation, today's attendance, notifications).
mysql "${MYSQL_ARGS[@]}" -e "DELETE FROM tbl_staff_leave_applications WHERE id=$LEAVE_APP_ID; DELETE FROM tbl_office_staff_leave_allocation WHERE staff_id=$SMOKE_ID AND leave_id=$LEAVE_TYPE_ID AND year=$YEAR; DELETE FROM tbl_office_leave_configs WHERE id=$LEAVE_TYPE_ID; DELETE FROM tbl_staff_attendances WHERE date=CURDATE(); DELETE FROM tbl_notifications WHERE type='leave';"

# ---------------------------------------------------------------------------
# Phase 3: Tasks + Meetings + Calendar (admin session in $JAR, token in $TOKEN)
# ---------------------------------------------------------------------------
echo "== 22. Tasks tab renders =="
BODY="$(curl -s -b "$JAR" "$BASE/admin/show_page.php?module=my_office&page=hr_care&tab=tasks")"
echo "$BODY" | grep -q "New task" && { echo "  PASS: tasks tab renders"; PASS=$((PASS+1)); } || { echo "  FAIL: tasks tab missing"; FAIL=$((FAIL+1)); }

echo "== 23. Create task with assignee =="
DEADLINE="$(date -d '+5 days' +%Y-%m-%dT%H:%M)"
BODY="$(curl -s -L -b "$JAR" -d "csrf_token=$TOKEN&action=save_task&title=Build+report&description=Compile+Q3&deadline=$DEADLINE&assignees%5B%5D=$SMOKE_ID" "$BASE/admin/operation.php?module=my_office&page=hr_care")"
echo "$BODY" | grep -q "Task created" && { echo "  PASS: task created"; PASS=$((PASS+1)); } || { echo "  FAIL: task create failed"; FAIL=$((FAIL+1)); }
TASK_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_office_tasks WHERE title='Build report' ORDER BY id DESC LIMIT 1;" | head -1)"
ASSIGNEE_COUNT="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT COUNT(*) FROM tbl_office_task_assignees WHERE task_id=$TASK_ID;" | head -1)"
[ "$ASSIGNEE_COUNT" = "1" ] && { echo "  PASS: 1 assignee row created"; PASS=$((PASS+1)); } || { echo "  FAIL: assignee rows = $ASSIGNEE_COUNT"; FAIL=$((FAIL+1)); }
BODY="$(curl -s -b "$JAR" "$BASE/admin/show_page.php?module=my_office&page=hr_care&tab=tasks")"
echo "$BODY" | grep -q "Build report" && { echo "  PASS: task visible in list"; PASS=$((PASS+1)); } || { echo "  FAIL: task not in list"; FAIL=$((FAIL+1)); }
echo "$BODY" | grep -q "badge-new" && { echo "  PASS: same-day New badge shown"; PASS=$((PASS+1)); } || { echo "  FAIL: New badge missing"; FAIL=$((FAIL+1)); }

echo "== 24. Post update with status change =="
BODY="$(curl -s -L -b "$JAR" -d "csrf_token=$TOKEN&action=post_update&task_id=$TASK_ID&status=In+Progress&update_text=Started+work" "$BASE/admin/operation.php?module=my_office&page=hr_care")"
echo "$BODY" | grep -q "updated to In Progress" && { echo "  PASS: status update posted"; PASS=$((PASS+1)); } || { echo "  FAIL: post_update failed"; FAIL=$((FAIL+1)); }
UPDATE_COUNT="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT COUNT(*) FROM tbl_office_task_files WHERE ref_id=$TASK_ID AND type='Update';" | head -1)"
[ "$UPDATE_COUNT" = "1" ] && { echo "  PASS: update history row saved"; PASS=$((PASS+1)); } || { echo "  FAIL: update rows = $UPDATE_COUNT"; FAIL=$((FAIL+1)); }

echo "== 25. Delete task =="
BODY="$(post '/admin/operation.php?module=my_office&page=hr_care' "action=delete_task&task_id=$TASK_ID")"
echo "$BODY" | grep -q "Task deleted" && { echo "  PASS: task deleted"; PASS=$((PASS+1)); } || { echo "  FAIL: task delete failed"; FAIL=$((FAIL+1)); }
TASK_LEFT="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT COUNT(*) FROM tbl_office_tasks WHERE id=$TASK_ID;" | head -1)"
[ "$TASK_LEFT" = "0" ] && { echo "  PASS: task removed from DB"; PASS=$((PASS+1)); } || { echo "  FAIL: task still present"; FAIL=$((FAIL+1)); }

echo "== 26. Meetings tab renders =="
BODY="$(curl -s -b "$JAR" "$BASE/admin/show_page.php?module=my_office&page=hr_care&tab=meetings")"
echo "$BODY" | grep -q "Schedule meeting" && { echo "  PASS: meetings tab renders"; PASS=$((PASS+1)); } || { echo "  FAIL: meetings tab missing"; FAIL=$((FAIL+1)); }

echo "== 27. Create public meeting =="
MTG_DATE="$(date -d '+2 days' +%F)"
BODY="$(curl -s -L -b "$JAR" -d "csrf_token=$TOKEN&action=save_event&title=Weekly+sync&type=Meeting&privacy=Public&venue_type=In+Office&venue_location=&date%5B%5D=$MTG_DATE&from_time%5B%5D=10:00&to_time%5B%5D=11:00&remarks=Standup" "$BASE/admin/operation.php?module=my_office&page=hr_care")"
echo "$BODY" | grep -q "Meeting scheduled" && { echo "  PASS: public meeting created"; PASS=$((PASS+1)); } || { echo "  FAIL: meeting create failed"; FAIL=$((FAIL+1)); }
EVENT_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_office_events WHERE title='Weekly sync' ORDER BY id DESC LIMIT 1;" | head -1)"
SCHED_COUNT="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT COUNT(*) FROM tbl_office_event_schedules WHERE event_id=$EVENT_ID;" | head -1)"
[ "$SCHED_COUNT" = "1" ] && { echo "  PASS: 1 schedule row created"; PASS=$((PASS+1)); } || { echo "  FAIL: schedule rows = $SCHED_COUNT"; FAIL=$((FAIL+1)); }

echo "== 28. Private meeting + conflict block =="
BODY="$(curl -s -L -b "$JAR" -d "csrf_token=$TOKEN&action=save_event&title=1on1&type=Meeting&privacy=Private&attendees%5B%5D=$SMOKE_ID&venue_type=Out+of+Office&venue_location_text=Cafe&date%5B%5D=$MTG_DATE&from_time%5B%5D=14:00&to_time%5B%5D=15:00" "$BASE/admin/operation.php?module=my_office&page=hr_care")"
echo "$BODY" | grep -q "Meeting scheduled" && { echo "  PASS: private meeting created"; PASS=$((PASS+1)); } || { echo "  FAIL: private meeting create failed"; FAIL=$((FAIL+1)); }
BODY="$(curl -s -L -b "$JAR" -d "csrf_token=$TOKEN&action=save_event&title=Double+book&type=Meeting&privacy=Private&attendees%5B%5D=$SMOKE_ID&venue_type=Out+of+Office&venue_location_text=Cafe&date%5B%5D=$MTG_DATE&from_time%5B%5D=14:30&to_time%5B%5D=15:30" "$BASE/admin/operation.php?module=my_office&page=hr_care")"
echo "$BODY" | grep -q "already booked" && { echo "  PASS: double-booking blocked"; PASS=$((PASS+1)); } || { echo "  FAIL: double-booking not blocked"; FAIL=$((FAIL+1)); }
BODY="$(curl -s -L -b "$JAR" -d "csrf_token=$TOKEN&action=save_event&title=Admin+only&type=Meeting&privacy=Private&attendees%5B%5D=$ADMIN_ID&venue_type=In+Office&date%5B%5D=$MTG_DATE&from_time%5B%5D=16:00&to_time%5B%5D=17:00" "$BASE/admin/operation.php?module=my_office&page=hr_care")"
echo "$BODY" | grep -q "Meeting scheduled" && { echo "  PASS: admin-only meeting created"; PASS=$((PASS+1)); } || { echo "  FAIL: admin-only meeting create failed"; FAIL=$((FAIL+1)); }

echo "== 29. Calendar visibility (smoketest) =="
JAR5="$(mktemp)"
BODY="$(curl -s -c "$JAR5" "$BASE/admin/login.php")"
T5="$(echo "$BODY" | grep -oP 'name="csrf_token" value="\K[^"]+')"
curl -s -o /dev/null -b "$JAR5" -c "$JAR5" -d "userId=smoketest&password=test123&csrf_token=$T5" "$BASE/admin/loginOperation.php"
BODY="$(curl -s -b "$JAR5" "$BASE/admin/show_page.php?module=my_office&page=office_calendar")"
echo "$BODY" | grep -q "Weekly sync" && { echo "  PASS: public meeting visible to staff"; PASS=$((PASS+1)); } || { echo "  FAIL: public meeting hidden"; FAIL=$((FAIL+1)); }
echo "$BODY" | grep -q "1on1" && { echo "  PASS: invited private meeting visible"; PASS=$((PASS+1)); } || { echo "  FAIL: invited meeting hidden"; FAIL=$((FAIL+1)); }
if echo "$BODY" | grep -q "Admin only"; then echo "  FAIL: uninvited private meeting leaked"; FAIL=$((FAIL+1)); else echo "  PASS: uninvited private meeting hidden"; PASS=$((PASS+1)); fi
echo "$BODY" | grep -q "Upcoming" && { echo "  PASS: upcoming panel renders"; PASS=$((PASS+1)); } || { echo "  FAIL: upcoming panel missing"; FAIL=$((FAIL+1)); }
rm -f "$JAR5"

echo "== 30. Daily tasks log =="
TODAY="$(date +%F)"
BODY="$(curl -s -L -b "$JAR" -d "csrf_token=$TOKEN&action=save&staff_id=$ADMIN_ID&date=$TODAY&tasks=Reviewed+code" "$BASE/admin/operation.php?module=staff_management&page=staff_daily_tasks")"
echo "$BODY" | grep -q "Daily tasks logged" && { echo "  PASS: daily task logged"; PASS=$((PASS+1)); } || { echo "  FAIL: daily task save failed"; FAIL=$((FAIL+1)); }
BODY="$(curl -s -b "$JAR" "$BASE/admin/show_page.php?module=staff_management&page=staff_daily_tasks&date=$TODAY")"
echo "$BODY" | grep -q "Reviewed code" && { echo "  PASS: daily task visible in list"; PASS=$((PASS+1)); } || { echo "  FAIL: daily task not listed"; FAIL=$((FAIL+1)); }

# Cleanup Phase 3 data (tasks, meetings, daily tasks, notifications).
mysql "${MYSQL_ARGS[@]}" -e "DELETE FROM tbl_office_task_assignees WHERE task_id IN (SELECT id FROM tbl_office_tasks WHERE title='Build report'); DELETE FROM tbl_office_task_files WHERE ref_id IN (SELECT id FROM tbl_office_tasks WHERE title='Build report'); DELETE FROM tbl_office_tasks WHERE title='Build report'; DELETE FROM tbl_office_event_schedules WHERE event_id IN (SELECT id FROM tbl_office_events WHERE title IN ('Weekly sync','1on1','Admin only')); DELETE FROM tbl_office_events WHERE title IN ('Weekly sync','1on1','Admin only'); DELETE FROM tbl_daily_tasks WHERE date='$TODAY'; DELETE FROM tbl_notifications WHERE type IN ('Task','Meeting');"

# ---------------------------------------------------------------------------
# Phase 3.5: Speak Up (grievances) + Office Documents
# ---------------------------------------------------------------------------
echo "== 31. Speak Up tab renders =="
BODY="$(curl -s -b "$JAR" "$BASE/admin/show_page.php?module=my_office&page=hr_care&tab=speak_up")"
echo "$BODY" | grep -q "Raise a concern" && { echo "  PASS: speak up tab renders"; PASS=$((PASS+1)); } || { echo "  FAIL: speak up tab missing"; FAIL=$((FAIL+1)); }

echo "== 32. Staff submits grievance =="
JAR6="$(mktemp)"
BODY="$(curl -s -c "$JAR6" "$BASE/admin/login.php")"
T6="$(echo "$BODY" | grep -oP 'name="csrf_token" value="\K[^"]+')"
curl -s -o /dev/null -b "$JAR6" -c "$JAR6" -d "userId=smoketest&password=test123&csrf_token=$T6" "$BASE/admin/loginOperation.php"
BODY="$(curl -s -L -b "$JAR6" -d "csrf_token=$T6&action=save_grievance&title=No+printer+ink&description=Out+of+ink+for+3+days" "$BASE/admin/operation.php?module=my_office&page=hr_care")"
echo "$BODY" | grep -q "submitted" && { echo "  PASS: grievance submitted by staff"; PASS=$((PASS+1)); } || { echo "  FAIL: grievance submit failed"; FAIL=$((FAIL+1)); }
GRV_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_office_grievances WHERE title='No printer ink' ORDER BY id DESC LIMIT 1;" | head -1)"
BODY="$(curl -s -b "$JAR6" "$BASE/admin/show_page.php?module=my_office&page=hr_care&tab=speak_up")"
echo "$BODY" | grep -q "No printer ink" && { echo "  PASS: author sees own grievance"; PASS=$((PASS+1)); } || { echo "  FAIL: grievance not visible to author"; FAIL=$((FAIL+1)); }
rm -f "$JAR6"

echo "== 33. Admin assigns + updates status =="
BODY="$(curl -s -L -b "$JAR" -d "csrf_token=$TOKEN&action=admin_update_grievance&id=$GRV_ID&assigned=$SMOKE_ID&status=In+Progress&deadline=" "$BASE/admin/operation.php?module=my_office&page=hr_care")"
echo "$BODY" | grep -q "Concern updated" && { echo "  PASS: admin assigned + status set"; PASS=$((PASS+1)); } || { echo "  FAIL: admin grievance update failed"; FAIL=$((FAIL+1)); }
GRV_STATUS="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT status FROM tbl_office_grievances WHERE id=$GRV_ID;" | head -1)"
[ "$GRV_STATUS" = "In Progress" ] && { echo "  PASS: status persisted"; PASS=$((PASS+1)); } || { echo "  FAIL: status = $GRV_STATUS"; FAIL=$((FAIL+1)); }

echo "== 34. Staff cannot manage others' grievances =="
JAR7="$(mktemp)"
BODY="$(curl -s -c "$JAR7" "$BASE/admin/login.php")"
T7="$(echo "$BODY" | grep -oP 'name="csrf_token" value="\K[^"]+')"
curl -s -o /dev/null -b "$JAR7" -c "$JAR7" -d "userId=smoketest&password=test123&csrf_token=$T7" "$BASE/admin/loginOperation.php"
# smoketest is the assignee, so posting an update is allowed — verify it works, then verify the *admin-only* action is blocked for them.
CODE="$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR7" -d "csrf_token=$T7&action=admin_update_grievance&id=$GRV_ID&status=Done" "$BASE/admin/operation.php?module=my_office&page=hr_care")"
check "non-admin admin_update blocked (403)" "403" "$CODE"
rm -f "$JAR7"

echo "== 35. Documents page renders =="
BODY="$(curl -s -b "$JAR" "$BASE/admin/show_page.php?module=office_setup&page=documents")"
echo "$BODY" | grep -q "Document register" && { echo "  PASS: documents page renders"; PASS=$((PASS+1)); } || { echo "  FAIL: documents page missing"; FAIL=$((FAIL+1)); }

echo "== 36. Category + document with file upload =="
BODY="$(post '/admin/operation.php?module=office_setup&page=documents' 'action=save_category&title=Contracts')"
echo "$BODY" | grep -q "Category created" && { echo "  PASS: category created"; PASS=$((PASS+1)); } || { echo "  FAIL: category create failed"; FAIL=$((FAIL+1)); }
CAT_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_office_document_category WHERE title='Contracts' LIMIT 1;" | head -1)"
printf 'dummy-pdf-content' > /tmp/sb_doc.txt
RENEW="$(date -d '+10 days' +%F)"
BODY="$(curl -s -L -b "$JAR" -F "csrf_token=$TOKEN" -F 'action=save_document' -F 'title=Office_lease' -F "category_id=$CAT_ID" -F "renew_date=$RENEW" -F 'access_type=Public' -F 'doc_files[]=@/tmp/sb_doc.txt;filename=lease.txt' "$BASE/admin/operation.php?module=office_setup&page=documents")"
echo "$BODY" | grep -q "Document saved" && { echo "  PASS: document saved with file"; PASS=$((PASS+1)); } || { echo "  FAIL: document save failed"; FAIL=$((FAIL+1)); }
DOC_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_office_documents WHERE title='Office_lease' ORDER BY id DESC LIMIT 1;" | head -1)"
FILE_COUNT="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT COUNT(*) FROM tbl_office_document_files WHERE document_id=$DOC_ID;" | head -1)"
[ "$FILE_COUNT" = "1" ] && { echo "  PASS: 1 file row stored"; PASS=$((PASS+1)); } || { echo "  FAIL: file rows = $FILE_COUNT"; FAIL=$((FAIL+1)); }
BODY="$(curl -s -b "$JAR" "$BASE/admin/show_page.php?module=office_setup&page=documents")"
echo "$BODY" | grep -q "Renew soon" && { echo "  PASS: renew-date flag shown"; PASS=$((PASS+1)); } || { echo "  FAIL: renew flag missing"; FAIL=$((FAIL+1)); }

# Private document (only visible to privileged users).
BODY="$(curl -s -L -b "$JAR" -F "csrf_token=$TOKEN" -F 'action=save_document' -F 'title=HR_salaries' -F 'access_type=Private' -F 'doc_files[]=@/tmp/sb_doc.txt;filename=salaries.txt' "$BASE/admin/operation.php?module=office_setup&page=documents")"
echo "$BODY" | grep -q "Document saved" && { echo "  PASS: private document saved"; PASS=$((PASS+1)); } || { echo "  FAIL: private document save failed"; FAIL=$((FAIL+1)); }

echo "== 37. Private document hidden from non-privileged user =="
HASH="$(php -r "echo hash('sha512', hash('sha512', 'test123') . 'doc-salt');")"
mysql "${MYSQL_ARGS[@]}" -e "INSERT INTO tbl_users_login (username, email, password, salt, fullname, permitted_modules, permitted_submodules, special_permission, role, status) VALUES ('doctester', 'doc@local', '$HASH', 'doc-salt', 'Doc Tester', '[\"office_setup\"]', '{\"office_setup\":[\"documents\"]}', '[]', 'Staff', 'Active');"
JAR8="$(mktemp)"
BODY="$(curl -s -c "$JAR8" "$BASE/admin/login.php")"
T8="$(echo "$BODY" | grep -oP 'name="csrf_token" value="\K[^"]+')"
curl -s -o /dev/null -b "$JAR8" -c "$JAR8" -d "userId=doctester&password=test123&csrf_token=$T8" "$BASE/admin/loginOperation.php"
BODY="$(curl -s -b "$JAR8" "$BASE/admin/show_page.php?module=office_setup&page=documents")"
echo "$BODY" | grep -q "Office_lease" && { echo "  PASS: public document visible to regular user"; PASS=$((PASS+1)); } || { echo "  FAIL: public document hidden"; FAIL=$((FAIL+1)); }
if echo "$BODY" | grep -q "HR_salaries"; then echo "  FAIL: private document leaked"; FAIL=$((FAIL+1)); else echo "  PASS: private document hidden without access_private_documents"; PASS=$((PASS+1)); fi
rm -f "$JAR8"

# Cleanup Phase 3.5 data.
mysql "${MYSQL_ARGS[@]}" -e "DELETE FROM tbl_office_grievance_files WHERE ref_id=$GRV_ID; DELETE FROM tbl_office_grievances WHERE id=$GRV_ID; DELETE FROM tbl_office_document_files WHERE document_id IN (SELECT id FROM tbl_office_documents WHERE title IN ('Office_lease','HR_salaries')); DELETE FROM tbl_office_documents WHERE title IN ('Office_lease','HR_salaries'); DELETE FROM tbl_office_document_category WHERE id=$CAT_ID; DELETE FROM tbl_users_login WHERE username='doctester'; DELETE FROM tbl_notifications WHERE type='Grievance';"
rm -f /tmp/sb_doc.txt

# ---------------------------------------------------------------------------
# Phase 4: Leads + Website (admin session in $JAR, token in $TOKEN)
# ---------------------------------------------------------------------------
echo "== 38. Leads page renders =="
BODY="$(curl -s -b "$JAR" "$BASE/admin/show_page.php?module=leads&page=leads")"
echo "$BODY" | grep -q "Pipeline" && { echo "  PASS: leads page renders"; PASS=$((PASS+1)); } || { echo "  FAIL: leads page missing"; FAIL=$((FAIL+1)); }

echo "== 39. Create lead =="
BODY="$(post '/admin/operation.php?module=leads&page=leads' 'action=save_lead&company=Acme+Corp&contact_name=John+Doe&email=john%40acme.test&phone=9800000001&service_interest=Website&priority=Hot&stage=New&estimated_value=250000')"
echo "$BODY" | grep -q "Lead created" && { echo "  PASS: lead created"; PASS=$((PASS+1)); } || { echo "  FAIL: lead create failed"; FAIL=$((FAIL+1)); }
LEAD_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_leads WHERE email='john@acme.test' ORDER BY id DESC LIMIT 1;" | head -1)"
ACT_COUNT="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT COUNT(*) FROM tbl_lead_activities WHERE lead_id=$LEAD_ID;" | head -1)"
[ "$ACT_COUNT" = "1" ] && { echo "  PASS: creation activity logged"; PASS=$((PASS+1)); } || { echo "  FAIL: activities = $ACT_COUNT"; FAIL=$((FAIL+1)); }

echo "== 40. Activity + stage change =="
BODY="$(post '/admin/operation.php?module=leads&page=leads' "action=add_activity&id=$LEAD_ID&type=Call&note=Initial+call+done")"
echo "$BODY" | grep -q "Activity logged" && { echo "  PASS: activity logged"; PASS=$((PASS+1)); } || { echo "  FAIL: activity add failed"; FAIL=$((FAIL+1)); }
BODY="$(post '/admin/operation.php?module=leads&page=leads' "action=update_lead&id=$LEAD_ID&stage=Contacted")"
echo "$BODY" | grep -q "Lead updated" && { echo "  PASS: stage changed to Contacted"; PASS=$((PASS+1)); } || { echo "  FAIL: stage change failed"; FAIL=$((FAIL+1)); }
ACT_COUNT="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT COUNT(*) FROM tbl_lead_activities WHERE lead_id=$LEAD_ID;" | head -1)"
[ "$ACT_COUNT" = "3" ] && { echo "  PASS: status change logged (3 activities)"; PASS=$((PASS+1)); } || { echo "  FAIL: activities = $ACT_COUNT"; FAIL=$((FAIL+1)); }

echo "== 41. Follow-up creates task =="
DEADLINE2="$(date -d '+3 days' +%Y-%m-%dT%H:%M)"
BODY="$(post '/admin/operation.php?module=leads&page=leads' "action=create_followup&id=$LEAD_ID&deadline=$DEADLINE2&assigned_to=$ADMIN_ID&note=Send+proposal")"
echo "$BODY" | grep -q "Follow-up task created" && { echo "  PASS: follow-up task created"; PASS=$((PASS+1)); } || { echo "  FAIL: follow-up failed"; FAIL=$((FAIL+1)); }
TASK_COUNT="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT COUNT(*) FROM tbl_office_tasks WHERE title LIKE 'Follow-up%';" | head -1)"
[ "$TASK_COUNT" = "1" ] && { echo "  PASS: follow-up task row exists"; PASS=$((PASS+1)); } || { echo "  FAIL: task rows = $TASK_COUNT"; FAIL=$((FAIL+1)); }

echo "== 42. Won -> convert to client =="
post '/admin/operation.php?module=leads&page=leads' "action=update_lead&id=$LEAD_ID&stage=Won" > /dev/null
BODY="$(post '/admin/operation.php?module=leads&page=leads' "action=convert_lead&id=$LEAD_ID&name=Acme+Corp+PVT&contact_person=John+Doe&address=Kathmandu")"
echo "$BODY" | grep -q "Client created" && { echo "  PASS: client created from lead"; PASS=$((PASS+1)); } || { echo "  FAIL: convert failed"; FAIL=$((FAIL+1)); }
CLIENT_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_clients WHERE name='Acme Corp PVT' ORDER BY id DESC LIMIT 1;" | head -1)"
WON_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT won_client_id FROM tbl_leads WHERE id=$LEAD_ID;" | head -1)"
[ "$WON_ID" = "$CLIENT_ID" ] && { echo "  PASS: lead linked to client"; PASS=$((PASS+1)); } || { echo "  FAIL: won_client_id = $WON_ID"; FAIL=$((FAIL+1)); }

echo "== 43. Dedupe + merge =="
post '/admin/operation.php?module=leads&page=leads' 'action=save_lead&company=Dup+Base&contact_name=Base&email=dupbase%40test.com&phone=9811111111&stage=New' > /dev/null
BASE2_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_leads WHERE company='Dup Base' ORDER BY id DESC LIMIT 1;" | head -1)"
post '/admin/operation.php?module=leads&page=leads' 'action=save_lead&company=Duplicate+Co&contact_name=Jane&email=dupbase%40test.com&phone=9811111111&stage=New' > /dev/null
DUP_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_leads WHERE company='Duplicate Co' ORDER BY id DESC LIMIT 1;" | head -1)"
BODY="$(curl -s -b "$JAR" "$BASE/admin/show_page.php?module=leads&page=leads&id=$DUP_ID")"
echo "$BODY" | grep -q "Possible duplicate" && { echo "  PASS: duplicate flagged"; PASS=$((PASS+1)); } || { echo "  FAIL: duplicate not flagged"; FAIL=$((FAIL+1)); }
BODY="$(post '/admin/operation.php?module=leads&page=leads' "action=merge_leads&keep_id=$DUP_ID&merge_id=$BASE2_ID")"
echo "$BODY" | grep -q "Duplicate merged" && { echo "  PASS: merge executed"; PASS=$((PASS+1)); } || { echo "  FAIL: merge failed"; FAIL=$((FAIL+1)); }
LEAD_GONE="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT COUNT(*) FROM tbl_leads WHERE id=$BASE2_ID;" | head -1)"
[ "$LEAD_GONE" = "0" ] && { echo "  PASS: merged duplicate removed"; PASS=$((PASS+1)); } || { echo "  FAIL: duplicate still present"; FAIL=$((FAIL+1)); }

echo "== 44. Reopen lost =="
post '/admin/operation.php?module=leads&page=leads' "action=update_lead&id=$DUP_ID&stage=Lost&lost_reason=Pricing" > /dev/null
BODY="$(post '/admin/operation.php?module=leads&page=leads' "action=reopen_lead&id=$DUP_ID")"
echo "$BODY" | grep -q "Lead reopened" && { echo "  PASS: lost lead reopened"; PASS=$((PASS+1)); } || { echo "  FAIL: reopen failed"; FAIL=$((FAIL+1)); }
REOPENED="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT stage FROM tbl_leads WHERE id=$DUP_ID;" | head -1)"
[ "$REOPENED" = "Contacted" ] && { echo "  PASS: stage reset to Contacted"; PASS=$((PASS+1)); } || { echo "  FAIL: stage = $REOPENED"; FAIL=$((FAIL+1)); }

echo "== 45. Website contact form -> lead =="
JAR9="$(mktemp)"
BODY="$(curl -s -c "$JAR9" "$BASE/contact.php")"
echo "$BODY" | grep -q "Contact us" && { echo "  PASS: public contact page renders"; PASS=$((PASS+1)); } || { echo "  FAIL: contact page missing"; FAIL=$((FAIL+1)); }
T9="$(echo "$BODY" | grep -oP 'name="csrf_token" value="\K[^"]+')"
BODY="$(curl -s -L -b "$JAR9" -d "csrf_token=$T9&name=Website+Visitor&email=visitor%40test.com&phone=9800111222&subject=Website&message=Please+call+me" "$BASE/contact.php")"
echo "$BODY" | grep -q "Thank you" && { echo "  PASS: website form accepted"; PASS=$((PASS+1)); } || { echo "  FAIL: website form rejected"; FAIL=$((FAIL+1)); }
LEAD_FROM_WEB="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT COUNT(*) FROM tbl_leads WHERE source='Website' AND email='visitor@test.com';" | head -1)"
[ "$LEAD_FROM_WEB" = "1" ] && { echo "  PASS: lead auto-created from website"; PASS=$((PASS+1)); } || { echo "  FAIL: website lead missing"; FAIL=$((FAIL+1)); }
INQ_COUNT="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT COUNT(*) FROM tbl_cms_contacts_us WHERE email='visitor@test.com';" | head -1)"
[ "$INQ_COUNT" = "1" ] && { echo "  PASS: inquiry saved as source of truth"; PASS=$((PASS+1)); } || { echo "  FAIL: inquiry missing"; FAIL=$((FAIL+1)); }
MSG_COUNT="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT COUNT(*) FROM tbl_cms_messages WHERE email='visitor@test.com';" | head -1)"
[ "$MSG_COUNT" = "1" ] && { echo "  PASS: inbox message saved"; PASS=$((PASS+1)); } || { echo "  FAIL: inbox message missing"; FAIL=$((FAIL+1)); }
rm -f "$JAR9"

echo "== 46. CMS service + public render =="
BODY="$(post '/admin/operation.php?module=webcms&page=services' 'action=save&section=service&title=Web+Development&short_description=Custom+websites&position=1&is_active=1')"
echo "$BODY" | grep -q "Service added" && { echo "  PASS: CMS service saved"; PASS=$((PASS+1)); } || { echo "  FAIL: CMS service save failed"; FAIL=$((FAIL+1)); }
BODY="$(curl -s "$BASE/services.php")"
echo "$BODY" | grep -q "Web Development" && { echo "  PASS: service renders on public site"; PASS=$((PASS+1)); } || { echo "  FAIL: public services render failed"; FAIL=$((FAIL+1)); }

echo "== 47. Public home + setup render =="
BODY="$(curl -s "$BASE/")"
echo "$BODY" | grep -q "Our Services" && { echo "  PASS: public home renders"; PASS=$((PASS+1)); } || { echo "  FAIL: public home missing"; FAIL=$((FAIL+1)); }
BODY="$(post '/admin/operation.php?module=webcms&page=webcms_setup' 'action=save_setup&site_title=SB-Tech+Test&tagline=Build+with+us')"
echo "$BODY" | grep -q "settings saved" && { echo "  PASS: site settings saved"; PASS=$((PASS+1)); } || { echo "  FAIL: setup save failed"; FAIL=$((FAIL+1)); }

# Cleanup Phase 4 data.
mysql "${MYSQL_ARGS[@]}" -e "DELETE FROM tbl_lead_activities WHERE lead_id IN (SELECT id FROM tbl_leads WHERE company IN ('Acme Corp','Dup Base','Duplicate Co')); DELETE FROM tbl_lead_files WHERE lead_id IN (SELECT id FROM tbl_leads WHERE company IN ('Acme Corp','Dup Base','Duplicate Co')); DELETE FROM tbl_leads WHERE company IN ('Acme Corp','Dup Base','Duplicate Co') OR email='visitor@test.com'; DELETE FROM tbl_clients WHERE name='Acme Corp PVT'; DELETE FROM tbl_office_tasks WHERE title LIKE 'Follow-up%'; DELETE FROM tbl_cms_contacts_us WHERE email='visitor@test.com'; DELETE FROM tbl_cms_messages WHERE email='visitor@test.com'; DELETE FROM tbl_cms_services WHERE title='Web Development'; DELETE FROM tbl_cms_setup; DELETE FROM tbl_notifications WHERE type IN ('Lead','Career','Task','Meeting','Grievance','leave');"

# ============================================================
# Phase 5 — Finance: fiscal years, COA, vouchers, ledger,
# expense claims → payment voucher → Paid, reconciliation.
# ============================================================

echo "== 48. Fiscal years page + create =="
BODY="$(curl -s -b "$JAR" "$BASE/admin/show_page.php?module=accounts&page=fiscal_years")"
echo "$BODY" | grep -q "2026/27" && { echo "  PASS: seeded FY listed"; PASS=$((PASS+1)); } || { echo "  FAIL: seeded FY missing"; FAIL=$((FAIL+1)); }
BODY="$(post '/admin/operation.php?module=accounts&page=fiscal_years' 'action=save_fy&title=2025/26&starting_date=2025-07-16&ending_date=2026-07-15&closing=Open')"
echo "$BODY" | grep -q "Fiscal year 2025/26 created" && { echo "  PASS: FY created"; PASS=$((PASS+1)); } || { echo "  FAIL: FY create failed"; FAIL=$((FAIL+1)); }
FY26="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_fiscal_years WHERE title='2025/26';" | head -1)"
[ -n "$FY26" ] && { echo "  PASS: FY 2025/26 in DB"; PASS=$((PASS+1)); } || { echo "  FAIL: FY missing"; FAIL=$((FAIL+1)); }

echo "== 49. Voucher in 2025/26 uses per-FY numbering =="
CASH_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_account_terminals WHERE title='Cash in Hand' LIMIT 1;" | head -1)"
CONS_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_account_terminals WHERE title='Consulting Income' LIMIT 1;" | head -1)"
BANK_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_account_terminals WHERE title='Bank Accounts' LIMIT 1;" | head -1)"
BODY="$(post '/admin/operation.php?module=accounts&page=postings' "action=save_voucher&type=journal&date=2025-10-01&reference_no=REF-OLD&narration=Old+FY+test&account_terminal_id%5B%5D=$CASH_ID&debit%5B%5D=500&credit%5B%5D=0&account_terminal_id%5B%5D=$CONS_ID&debit%5B%5D=0&credit%5B%5D=500")"
echo "$BODY" | grep -q "Voucher created" && { echo "  PASS: 2025/26 voucher created"; PASS=$((PASS+1)); } || { echo "  FAIL: 2025/26 voucher failed"; FAIL=$((FAIL+1)); }
OLDV_NO="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT voucher_no FROM tbl_journal_vouchers WHERE fiscal_year_id=$FY26 ORDER BY id DESC LIMIT 1;" | head -1)"
[ "$OLDV_NO" = "JV-0001" ] && { echo "  PASS: per-FY voucher no JV-0001"; PASS=$((PASS+1)); } || { echo "  FAIL: voucher no = $OLDV_NO"; FAIL=$((FAIL+1)); }
OLDV_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_journal_vouchers WHERE voucher_no='JV-0001' AND fiscal_year_id=$FY26 LIMIT 1;" | head -1)"

echo "== 50. Closed FY blocks postings (AC-FIN-01.2) =="
post '/admin/operation.php?module=accounts&page=fiscal_years' "action=close_fy&id=$FY26" > /dev/null
BODY="$(post '/admin/operation.php?module=accounts&page=postings' "action=save_voucher&type=journal&date=2025-11-01&reference_no=REF-BLOCKED&narration=Blocked&account_terminal_id%5B%5D=$CASH_ID&debit%5B%5D=10&credit%5B%5D=0&account_terminal_id%5B%5D=$CONS_ID&debit%5B%5D=0&credit%5B%5D=10")"
echo "$BODY" | grep -q "closed fiscal years are read-only" && { echo "  PASS: closed FY blocks voucher"; PASS=$((PASS+1)); } || { echo "  FAIL: closed FY did not block"; FAIL=$((FAIL+1)); }
BLOCKED_CNT="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT COUNT(*) FROM tbl_journal_vouchers WHERE fiscal_year_id=$FY26 AND reference_no='REF-BLOCKED';" | head -1)"
[ "$BLOCKED_CNT" = "0" ] && { echo "  PASS: blocked voucher not created"; PASS=$((PASS+1)); } || { echo "  FAIL: blocked voucher exists"; FAIL=$((FAIL+1)); }
post '/admin/operation.php?module=accounts&page=fiscal_years' "action=open_fy&id=$FY26" > /dev/null

echo "== 51. Chart of accounts render + add/delete terminal =="
BODY="$(curl -s -b "$JAR" "$BASE/admin/show_page.php?module=accounts&page=chart_of_account&group_id=1&subgroup_id=1")"
echo "$BODY" | grep -q "Cash in Hand" && { echo "  PASS: COA renders seeded terminals"; PASS=$((PASS+1)); } || { echo "  FAIL: COA render failed"; FAIL=$((FAIL+1)); }
BODY="$(post '/admin/operation.php?module=accounts&page=chart_of_account' "action=save_terminal&group_id=4&subgroup_id=7&title=Test+Licenses&position=99")"
echo "$BODY" | grep -q "Terminal added" && { echo "  PASS: terminal added"; PASS=$((PASS+1)); } || { echo "  FAIL: terminal add failed"; FAIL=$((FAIL+1)); }
TST_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_account_terminals WHERE title='Test Licenses' LIMIT 1;" | head -1)"
[ -n "$TST_ID" ] && { echo "  PASS: terminal in DB"; PASS=$((PASS+1)); } || { echo "  FAIL: terminal missing"; FAIL=$((FAIL+1)); }
BODY="$(post '/admin/operation.php?module=accounts&page=chart_of_account' "action=delete_terminal&id=$TST_ID&group_id=4&subgroup_id=7")"
echo "$BODY" | grep -q "Terminal deleted" && { echo "  PASS: unused terminal deleted"; PASS=$((PASS+1)); } || { echo "  FAIL: terminal delete failed"; FAIL=$((FAIL+1)); }
TST_GONE="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT COUNT(*) FROM tbl_account_terminals WHERE id=$TST_ID;" | head -1)"
[ "$TST_GONE" = "0" ] && { echo "  PASS: terminal removed"; PASS=$((PASS+1)); } || { echo "  FAIL: terminal remains"; FAIL=$((FAIL+1)); }

echo "== 52. Balanced journal voucher + ledger =="
BODY="$(post '/admin/operation.php?module=accounts&page=postings' "action=save_voucher&type=journal&date=2026-08-18&reference_no=REF-1&narration=Consulting+fee+recorded&account_terminal_id%5B%5D=$CASH_ID&debit%5B%5D=100&credit%5B%5D=0&account_terminal_id%5B%5D=$CONS_ID&debit%5B%5D=0&credit%5B%5D=100")"
echo "$BODY" | grep -q "Voucher created" && { echo "  PASS: balanced JV created"; PASS=$((PASS+1)); } || { echo "  FAIL: balanced JV failed"; FAIL=$((FAIL+1)); }
JV_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_journal_vouchers WHERE reference_no='REF-1' ORDER BY id DESC LIMIT 1;" | head -1)"
JV_NO="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT voucher_no FROM tbl_journal_vouchers WHERE id=$JV_ID;" | head -1)"
LP_CNT="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT COUNT(*) FROM tbl_ledger_particulars WHERE voucher_type='Journal' AND voucher_type_id=$JV_ID;" | head -1)"
[ "$LP_CNT" = "2" ] && { echo "  PASS: 2 ledger lines posted"; PASS=$((PASS+1)); } || { echo "  FAIL: lines = $LP_CNT"; FAIL=$((FAIL+1)); }

echo "== 53. Unbalanced voucher blocked (AC-FIN-03.2) =="
BODY="$(post '/admin/operation.php?module=accounts&page=postings' "action=save_voucher&type=journal&date=2026-08-18&reference_no=REF-UNBAL&narration=Unbalanced&account_terminal_id%5B%5D=$CASH_ID&debit%5B%5D=100&credit%5B%5D=0&account_terminal_id%5B%5D=$CONS_ID&debit%5B%5D=0&credit%5B%5D=90")"
echo "$BODY" | grep -q "does not balance" && { echo "  PASS: unbalanced JV blocked"; PASS=$((PASS+1)); } || { echo "  FAIL: unbalanced JV accepted"; FAIL=$((FAIL+1)); }
UNBAL_CNT="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT COUNT(*) FROM tbl_journal_vouchers WHERE reference_no='REF-UNBAL';" | head -1)"
[ "$UNBAL_CNT" = "0" ] && { echo "  PASS: no unbalanced row saved"; PASS=$((PASS+1)); } || { echo "  FAIL: unbalanced row exists"; FAIL=$((FAIL+1)); }

echo "== 54. Approve voucher -> ledger status + log (AC-FIN-03.3/09.1) =="
BODY="$(post '/admin/operation.php?module=accounts&page=postings' "action=approve_voucher&type=journal&id=$JV_ID")"
echo "$BODY" | grep -q "approved" && { echo "  PASS: JV approved"; PASS=$((PASS+1)); } || { echo "  FAIL: approve failed"; FAIL=$((FAIL+1)); }
JV_ST="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT status FROM tbl_journal_vouchers WHERE id=$JV_ID;" | head -1)"
[ "$JV_ST" = "Approved" ] && { echo "  PASS: voucher Approved"; PASS=$((PASS+1)); } || { echo "  FAIL: status = $JV_ST"; FAIL=$((FAIL+1)); }
LP_ST="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT DISTINCT voucher_status FROM tbl_ledger_particulars WHERE voucher_type='Journal' AND voucher_type_id=$JV_ID;" | head -1)"
[ "$LP_ST" = "Approved" ] && { echo "  PASS: ledger lines Approved"; PASS=$((PASS+1)); } || { echo "  FAIL: line status = $LP_ST"; FAIL=$((FAIL+1)); }
LOG_CNT="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT COUNT(*) FROM tbl_voucher_logs WHERE voucher_type='journal' AND voucher_type_id=$JV_ID AND action='Approve';" | head -1)"
[ "$LOG_CNT" = "1" ] && { echo "  PASS: approve logged"; PASS=$((PASS+1)); } || { echo "  FAIL: log missing"; FAIL=$((FAIL+1)); }
BODY="$(curl -s -b "$JAR" "$BASE/admin/show_page.php?module=accounts&page=postings&tab=voucher_logs")"
echo "$BODY" | grep -q "Approve" && { echo "  PASS: logs tab renders"; PASS=$((PASS+1)); } || { echo "  FAIL: logs tab missing"; FAIL=$((FAIL+1)); }

echo "== 55. In-use terminal delete blocked (AC-FIN-02.1) =="
BODY="$(post '/admin/operation.php?module=accounts&page=chart_of_account' "action=delete_terminal&id=$CASH_ID")"
echo "$BODY" | grep -q "Cannot delete" && { echo "  PASS: in-use terminal delete blocked"; PASS=$((PASS+1)); } || { echo "  FAIL: in-use terminal deleted"; FAIL=$((FAIL+1)); }

TODAY="2026-08-18"
echo "== 56. Ledger + trial balance render =="
BODY="$(curl -s -b "$JAR" "$BASE/admin/show_page.php?module=accounts&page=ledger&terminal_id=$CASH_ID&from=2026-07-16&to=2027-07-15")"
echo "$BODY" | grep -q "$JV_NO" && { echo "  PASS: ledger shows voucher"; PASS=$((PASS+1)); } || { echo "  FAIL: ledger missing voucher"; FAIL=$((FAIL+1)); }
BODY="$(curl -s -b "$JAR" "$BASE/admin/show_page.php?module=accounts&page=account_reports&tab=trial_balance&from=2026-07-16&to=2027-07-15")"
echo "$BODY" | grep -q "Consulting Income" && { echo "  PASS: trial balance shows income"; PASS=$((PASS+1)); } || { echo "  FAIL: trial balance missing"; FAIL=$((FAIL+1)); }
BODY="$(curl -s -b "$JAR" "$BASE/admin/show_page.php?module=accounts&page=account_reports&tab=balance_sheet&from=2026-07-16&to=2027-07-15")"
echo "$BODY" | grep -q "Balanced" && { echo "  PASS: balance sheet balanced"; PASS=$((PASS+1)); } || { echo "  FAIL: balance sheet unbalanced"; FAIL=$((FAIL+1)); }

echo "== 57. Expense claim: submit with receipt (AC-FIN-06) =="
printf '%%PDF-1.4 test receipt\n' > /tmp/sbtech_receipt.pdf
CLAIM_BODY="$(curl -s -L -b "$JAR" -F "csrf_token=$TOKEN" -F "action=save_claim" -F "submit_now=1" -F "category=Travel" -F "expense_date=2026-08-17" -F "description=Client_visit_taxi" -F "amount=1200" -F "receipt_files[]=@/tmp/sbtech_receipt.pdf;type=application/pdf" "$BASE/admin/operation.php?module=accounts&page=expense_claims")"
echo "$CLAIM_BODY" | grep -q "submitted" && { echo "  PASS: claim submitted"; PASS=$((PASS+1)); } || { echo "  FAIL: claim submit failed"; FAIL=$((FAIL+1)); }
CLAIM_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_expense_claims WHERE description='Client_visit_taxi' ORDER BY id DESC LIMIT 1;" | head -1)"
CLAIM_NO="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT claim_no FROM tbl_expense_claims WHERE id=$CLAIM_ID;" | head -1)"
CLAIM_ST="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT status FROM tbl_expense_claims WHERE id=$CLAIM_ID;" | head -1)"
[ "$CLAIM_ST" = "Submitted" ] && { echo "  PASS: claim status Submitted"; PASS=$((PASS+1)); } || { echo "  FAIL: status = $CLAIM_ST"; FAIL=$((FAIL+1)); }
CF_CNT="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT COUNT(*) FROM tbl_expense_claim_files WHERE claim_id=$CLAIM_ID;" | head -1)"
[ "$CF_CNT" = "1" ] && { echo "  PASS: receipt file saved"; PASS=$((PASS+1)); } || { echo "  FAIL: receipt missing"; FAIL=$((FAIL+1)); }

echo "== 58. Submit without receipt blocked (AC-FIN-06.1) =="
BODY="$(post '/admin/operation.php?module=accounts&page=expense_claims' 'action=save_claim&submit_now=1&category=Office&expense_date=2026-08-16&description=No+receipt+test&amount=50')"
echo "$BODY" | grep -q "receipt file is required" && { echo "  PASS: no-receipt submit blocked"; PASS=$((PASS+1)); } || { echo "  FAIL: no-receipt submit accepted"; FAIL=$((FAIL+1)); }
NOREC_CNT="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT COUNT(*) FROM tbl_expense_claims WHERE description='No receipt test';" | head -1)"
[ "$NOREC_CNT" = "0" ] && { echo "  PASS: claim not created"; PASS=$((PASS+1)); } || { echo "  FAIL: claim created without receipt"; FAIL=$((FAIL+1)); }

echo "== 59. Approve claim -> auto payment voucher (AC-FIN-07.1) =="
BODY="$(post '/admin/operation.php?module=accounts&page=expense_claims' "action=approve_claim&id=$CLAIM_ID")"
echo "$BODY" | grep -q "Payment voucher created" && { echo "  PASS: claim approved + voucher created"; PASS=$((PASS+1)); } || { echo "  FAIL: approve failed"; FAIL=$((FAIL+1)); }
CLAIM_ST="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT status FROM tbl_expense_claims WHERE id=$CLAIM_ID;" | head -1)"
[ "$CLAIM_ST" = "Approved" ] && { echo "  PASS: claim Approved"; PASS=$((PASS+1)); } || { echo "  FAIL: status = $CLAIM_ST"; FAIL=$((FAIL+1)); }
PV_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT payment_voucher_id FROM tbl_expense_claims WHERE id=$CLAIM_ID;" | head -1)"
PV_NO="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT voucher_no FROM tbl_payment_vouchers WHERE id=$PV_ID;" | head -1)"
[ -n "$PV_NO" ] && { echo "  PASS: payment voucher linked ($PV_NO)"; PASS=$((PASS+1)); } || { echo "  FAIL: payment voucher missing"; FAIL=$((FAIL+1)); }
PV_ST="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT status FROM tbl_payment_vouchers WHERE id=$PV_ID;" | head -1)"
[ "$PV_ST" = "Pending" ] && { echo "  PASS: auto voucher Pending"; PASS=$((PASS+1)); } || { echo "  FAIL: voucher status = $PV_ST"; FAIL=$((FAIL+1)); }

echo "== 60. Approve payment voucher -> claim Paid (AC-FIN-07.2) =="
BODY="$(post '/admin/operation.php?module=accounts&page=postings' "action=approve_voucher&type=payment&id=$PV_ID")"
echo "$BODY" | grep -q "approved" && { echo "  PASS: payment voucher approved"; PASS=$((PASS+1)); } || { echo "  FAIL: payment approve failed"; FAIL=$((FAIL+1)); }
CLAIM_ST="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT status FROM tbl_expense_claims WHERE id=$CLAIM_ID;" | head -1)"
[ "$CLAIM_ST" = "Paid" ] && { echo "  PASS: claim Paid"; PASS=$((PASS+1)); } || { echo "  FAIL: claim status = $CLAIM_ST"; FAIL=$((FAIL+1)); }

# Bank reconciliation needs an Approved line on the Bank Accounts terminal.
echo "== 61. Second JV on Bank Accounts =="
BODY="$(post '/admin/operation.php?module=accounts&page=postings' "action=save_voucher&type=journal&date=$TODAY&reference_no=REF-2&narration=Bank+deposit+recorded&account_terminal_id%5B%5D=$BANK_ID&debit%5B%5D=250&credit%5B%5D=0&account_terminal_id%5B%5D=$CONS_ID&debit%5B%5D=0&credit%5B%5D=250")"
echo "$BODY" | grep -q "Voucher created" && { echo "  PASS: bank JV created"; PASS=$((PASS+1)); } || { echo "  FAIL: bank JV failed"; FAIL=$((FAIL+1)); }
JV2_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_journal_vouchers WHERE reference_no='REF-2' ORDER BY id DESC LIMIT 1;" | head -1)"
post '/admin/operation.php?module=accounts&page=postings' "action=approve_voucher&type=journal&id=$JV2_ID" > /dev/null
BANK_LINE="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_ledger_particulars WHERE account_terminal_id=$BANK_ID AND voucher_type_id=$JV2_ID ORDER BY id DESC LIMIT 1;" | head -1)"

echo "== 62. Bank reconciliation match (AC-FIN-05.1) =="
BODY="$(curl -s -b "$JAR" "$BASE/admin/show_page.php?module=accounts&page=bank_reconciliation")"
echo "$BODY" | grep -q "New reconciliation" && { echo "  PASS: reconciliation page renders"; PASS=$((PASS+1)); } || { echo "  FAIL: reconciliation page missing"; FAIL=$((FAIL+1)); }
BODY="$(post '/admin/operation.php?module=accounts&page=bank_reconciliation' "action=create_session&account_terminal_id=$BANK_ID&statement_ref=STMT-0726&statement_date=2026-08-18&opening_balance=0&total_statement_amount=250")"
echo "$BODY" | grep -q "session created" && { echo "  PASS: session created"; PASS=$((PASS+1)); } || { echo "  FAIL: session create failed"; FAIL=$((FAIL+1)); }
RECON_ID="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT id FROM tbl_bank_reconciliation WHERE statement_ref='STMT-0726' ORDER BY id DESC LIMIT 1;" | head -1)"
BODY="$(post '/admin/operation.php?module=accounts&page=bank_reconciliation' "action=match_line&id=$RECON_ID&line_id=$BANK_LINE")"
echo "$BODY" | grep -q "Line matched" && { echo "  PASS: line matched"; PASS=$((PASS+1)); } || { echo "  FAIL: match failed"; FAIL=$((FAIL+1)); }
REC_ST="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT status FROM tbl_bank_reconciliation WHERE id=$RECON_ID;" | head -1)"
[ "$REC_ST" = "Matched" ] && { echo "  PASS: session Matched"; PASS=$((PASS+1)); } || { echo "  FAIL: status = $REC_ST"; FAIL=$((FAIL+1)); }
REC_REF="$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT reconcile_ref FROM tbl_ledger_particulars WHERE id=$BANK_LINE;" | head -1)"
[ "$REC_REF" = "STMT-0726" ] && { echo "  PASS: line carries reconcile_ref"; PASS=$((PASS+1)); } || { echo "  FAIL: reconcile_ref = $REC_REF"; FAIL=$((FAIL+1)); }

# Cleanup Phase 5 data.
mysql "${MYSQL_ARGS[@]}" -e "DELETE FROM tbl_ledger_particulars WHERE (voucher_type='Journal' AND voucher_type_id IN (${OLDV_ID:-0},${JV_ID:-0},${JV2_ID:-0})) OR (voucher_type='Payment' AND voucher_type_id=${PV_ID:-0}); DELETE FROM tbl_journal_vouchers WHERE id IN (${OLDV_ID:-0},${JV_ID:-0},${JV2_ID:-0}); DELETE FROM tbl_payment_vouchers WHERE id=${PV_ID:-0}; DELETE FROM tbl_expense_claim_files WHERE claim_id=${CLAIM_ID:-0}; DELETE FROM tbl_expense_claims WHERE id=${CLAIM_ID:-0} OR description IN ('Client_visit_taxi','Client+visit+taxi'); DELETE FROM tbl_voucher_logs WHERE voucher_type_id IN (${OLDV_ID:-0},${JV_ID:-0},${JV2_ID:-0},${PV_ID:-0}); DELETE FROM tbl_bank_reconciliation WHERE id=${RECON_ID:-0}; DELETE FROM tbl_fiscal_years WHERE id=${FY26:-0}; DELETE FROM tbl_account_terminals WHERE title='Test Licenses'; DELETE FROM tbl_notifications WHERE type='Expense';"
rm -f /tmp/sbtech_receipt.pdf

# Restore original admin profile fields for repeatability
mysql "${MYSQL_ARGS[@]}" -e "DELETE FROM tbl_users_login WHERE username='smokestaff'; DELETE FROM tbl_office_departments WHERE title='Engineering'; DELETE FROM tbl_office_designation WHERE title='Senior Engineer';"

echo
echo "== RESULT: $PASS passed, $FAIL failed =="
[ "$FAIL" -eq 0 ] && exit 0 || exit 1
