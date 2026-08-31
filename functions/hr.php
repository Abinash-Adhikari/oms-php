<?php

/**
 * SB-Tech — HR service helpers (attendance + leave business rules).
 * Loaded by config/bootstrap.php after functions/helpers.php.
 *
 * Business rules live here so module pages stay thin (X-10; the reference
 * keeps the same rules in admin/functions/smart_school_function.php).
 */

/** Current leave year (int) per the office profile leave_year_mode. */
function currentLeaveYear(): int
{
    static $year = null;
    if ($year !== null) {
        return $year;
    }
    $profile = Database::instance()->selectOne(
        'SELECT `leave_year_mode` FROM `tbl_office_profiles` WHERE `id` = 1'
    );
    $mode = strtoupper((string) ($profile['leave_year_mode'] ?? 'AD'));
    if ($mode === 'BS') {
        // Look up today's BS year from the seeded tbl_calendar.
        $today = date('Y-m-d');
        $cal = Database::instance()->selectOne(
            'SELECT `nepali_year` FROM `tbl_calendar`
             WHERE `eng_start_date` <= ? AND `eng_end_date` >= ? LIMIT 1',
            [$today, $today]
        );
        if ($cal) {
            $year = (int) $cal['nepali_year'];
            return $year;
        }
    }
    // Default to AD year.
    $year = (int) date('Y');
    return $year;
}

/** Is $date (Y-m-d) an office holiday scoped to $userId (dept/gender)? */
function isOfficeHoliday(string $date, ?int $userId = null): bool
{
    $db = Database::instance();
    $user = null;
    if ($userId) {
        $user = $db->selectOne(
            'SELECT `department_id`, `gender` FROM `tbl_users_login` WHERE `id` = ?',
            [(int) $userId]
        );
    }
    $holidays = $db->select(
        'SELECT * FROM `tbl_office_holidays` WHERE `from_date` <= ? AND `to_date` >= ?',
        [$date, $date]
    );
    foreach ($holidays as $h) {
        if (!empty($h['department_id']) && $user
            && (int) $h['department_id'] !== (int) ($user['department_id'] ?? 0)) {
            continue;
        }
        $gender = (string) ($h['gender_to'] ?? 'Both');
        if ($gender !== 'Both' && $user
            && strcasecmp($gender, (string) ($user['gender'] ?? '')) !== 0) {
            continue;
        }
        return true;
    }
    return false;
}

/**
 * Count leave days for a range (inclusive), excluding office holidays
 * (AC-SET-03.2: holiday dates do not consume leave balance). A half-day
 * application is 0.5 regardless of range.
 *
 * Fetches holidays for the range once (1 query) instead of per-day (N queries).
 */
function countLeaveDays(string $from, string $to, bool $halfDay, ?int $userId = null): float
{
    if ($halfDay) {
        return 0.5;
    }
    $start = strtotime($from);
    $end = strtotime($to);
    if (!$start || !$end || $end < $start) {
        return 0.0;
    }

    // Batch-fetch holidays for the range + user context (1 query instead of N).
    $holidayDates = [];
    $db = Database::instance();
    $user = null;
    if ($userId) {
        $user = $db->selectOne(
            'SELECT `department_id`, `gender` FROM `tbl_users_login` WHERE `id` = ?',
            [(int) $userId]
        );
    }
    $fromDate = date('Y-m-d', $start);
    $toDate = date('Y-m-d', $end);
    $holidays = $db->select(
        'SELECT `from_date`, `to_date`, `department_id`, `gender_to` FROM `tbl_office_holidays` WHERE `from_date` <= ? AND `to_date` >= ?',
        [$toDate, $fromDate]
    );
    foreach ($holidays as $h) {
        if (!empty($h['department_id']) && $user
            && (int) $h['department_id'] !== (int) ($user['department_id'] ?? 0)) {
            continue;
        }
        $gender = (string) ($h['gender_to'] ?? 'Both');
        if ($gender !== 'Both' && $user
            && strcasecmp($gender, (string) ($user['gender'] ?? '')) !== 0) {
            continue;
        }
        // Expand the holiday range into individual dates.
        $hStart = max(strtotime($h['from_date']), $start);
        $hEnd = min(strtotime($h['to_date']), $end);
        for ($ts = $hStart; $ts <= $hEnd; $ts += 86400) {
            $holidayDates[date('Y-m-d', $ts)] = true;
        }
    }

    $days = 0.0;
    for ($ts = $start; $ts <= $end; $ts += 86400) {
        $d = date('Y-m-d', $ts);
        if (!isset($holidayDates[$d])) {
            $days += 1.0;
        }
    }
    return $days;
}

/**
 * Allocations for a staff member with used (approved) + pending days and a
 * true remaining balance (allocated + carry − used − pending), so the UI can
 * never over-book a leave type (X-09).
 */
function getStaffLeaveAllocationsWithBalance(int $staffId, bool $onlyWithRemaining = false, ?int $year = null): array
{
    $year = $year ?? currentLeaveYear();
    $db = Database::instance();
    $rows = $db->select(
        'SELECT la.*, lc.title AS leave_title, lc.max_allowed, lc.is_active,
                (la.allocated_days + la.carry_forward_days - la.used_days) AS balance
         FROM `tbl_office_staff_leave_allocation` la
         JOIN `tbl_office_leave_configs` lc ON lc.id = la.leave_id
         WHERE la.staff_id = ? AND la.year = ?
         ORDER BY lc.title',
        [(int) $staffId, (int) $year]
    );
    $pending = [];
    $pRows = $db->select(
        "SELECT `leave_type_id`, COALESCE(SUM(`leave_days`), 0) AS days
         FROM `tbl_staff_leave_applications`
         WHERE `staff_id` = ? AND `status` IN ('Pending','Verified')
         GROUP BY `leave_type_id`",
        [(int) $staffId]
    );
    foreach ($pRows as $p) {
        $pending[(int) $p['leave_type_id']] = (float) $p['days'];
    }
    $out = [];
    foreach ($rows as $r) {
        $r['pending_days'] = (float) ($pending[(int) $r['leave_id']] ?? 0);
        $r['remaining'] = round((float) $r['balance'] - $r['pending_days'], 1);
        if ($onlyWithRemaining && $r['remaining'] <= 0) {
            continue;
        }
        $out[] = $r;
    }
    return $out;
}

/** Allocation row for one staff/type/year, or null. */
function getStaffLeaveAllocation(int $staffId, int $leaveTypeId, ?int $year = null)
{
    $year = $year ?? currentLeaveYear();
    return Database::instance()->selectOne(
        'SELECT * FROM `tbl_office_staff_leave_allocation`
         WHERE `staff_id` = ? AND `leave_id` = ? AND `year` = ?',
        [(int) $staffId, (int) $leaveTypeId, (int) $year]
    );
}

/**
 * Recompute used_days on the allocation from Approved applications
 * (reference pattern — keeps balance aligned when approvals are
 * granted or revoked, instead of incrementing).
 */
function syncStaffLeaveAllocationUsedDays(int $staffId, int $leaveTypeId): void
{
    $db = Database::instance();
    $row = $db->selectOne(
        "SELECT COALESCE(SUM(`leave_days`), 0) AS used
         FROM `tbl_staff_leave_applications`
         WHERE `staff_id` = ? AND `leave_type_id` = ? AND `status` = 'Approved'",
        [(int) $staffId, (int) $leaveTypeId]
    );
    $used = round((float) ($row['used'] ?? 0), 1);
    $db->update(
        'tbl_office_staff_leave_allocation',
        ['used_days' => $used],
        '`staff_id` = ? AND `leave_id` = ?',
        [(int) $staffId, (int) $leaveTypeId]
    );
}

/** In-app notification + optional email/SMS via CommunicationService. */
function notifyUser(?int $receiver, string $details, string $type = 'general', $refId = null, ?int $actor = null): void
{
    if (!$receiver) {
        return;
    }
    try {
        // Derive a short title from the type + first line of details
        $title = ucwords(str_replace('_', ' ', $type));
        if (preg_match('/^(.+?)(?:\n|$)/', $details, $m)) {
            $title = $m[1];
        }
        Database::instance()->insert('tbl_notifications', [
            'title'    => mb_substr($title, 0, 150),
            'details'  => $details,
            'ref_id'   => $refId === null ? null : (string) $refId,
            'receiver' => (int) $receiver,
            'type'     => $type,
            'url'      => null,
            'viewed'   => 0,
            'added_by' => $actor,
        ]);
    } catch (Throwable $e) {
        // A notification failure must never break the main flow.
    }
    // Wire email/SMS via CommunicationService (non-blocking).
    if (class_exists('CommunicationService')) {
        try {
            CommunicationService::sendWorkflowNotification($type, $receiver, $details, $refId, $actor);
        } catch (Throwable $e) {
            // Never break the main flow.
        }
    }
}

/** Notify every active user holding a special permission key.
 *  Uses DB-level filtering to avoid fetching + looping ALL active users.
 */
function notifyPermissionHolders(string $permissionKey, string $details, string $type = 'general', $refId = null, ?int $actor = null): void
{
    try {
        $db = Database::instance();
        // Super admins (permitted_modules='All' or role Super Admin) + users with the key.
        $escapedKey = '%' . $db->escapeLike('"' . $permissionKey . '"') . '%';
        $users = $db->select(
            'SELECT `id` FROM `tbl_users_login`
             WHERE `status` = ?
               AND (
                   `permitted_modules` = ?
                   OR LOWER(`role`) IN (?, ?)
                   OR `special_permission` LIKE ?
               )',
            ['Active', 'All', 'super admin', 'superadmin', $escapedKey]
        );
        foreach ($users as $u) {
            notifyUser((int) $u['id'], $details, $type, $refId, $actor);
        }
    } catch (Throwable $e) {
        // ignore
    }
}

/**
 * Derive the computed attendance fields from raw times.
 * Returns: checkin_delay, late_checkin, late_checkin_minutes,
 *          checkout_early, early_checkout, working_hours.
 */
function computeAttendanceMetrics(?string $checkin, ?string $checkout, ?string $configCheckin, ?string $configCheckout): array
{
    $out = [
        'checkin_delay'       => null,
        'late_checkin'        => 0,
        'late_checkin_minutes'=> 0,
        'checkout_early'      => null,
        'early_checkout'      => 0,
        'working_hours'       => null,
    ];
    $ci = $checkin ? strtotime($checkin) : null;
    $co = $checkout ? strtotime($checkout) : null;
    if ($ci && $configCheckin) {
        $cfg = strtotime($configCheckin);
        if ($cfg) {
            $delay = (int) round(($ci - $cfg) / 60);
            $out['checkin_delay'] = $delay;
            $out['late_checkin'] = $delay > 0 ? 1 : 0;
            $out['late_checkin_minutes'] = $delay > 0 ? $delay : 0;
        }
    }
    if ($co && $configCheckout) {
        $cfg = strtotime($configCheckout);
        if ($cfg) {
            $early = (int) round(($cfg - $co) / 60);
            $out['checkout_early'] = $early;
            $out['early_checkout'] = $early > 0 ? 1 : 0;
        }
    }
    if ($ci && $co && $co > $ci) {
        $out['working_hours'] = round(($co - $ci) / 3600, 4);
    }
    return $out;
}

/**
 * Auto-status for a date: approved leave > office holiday > present/absent
 * (AC-ATT-01.5, AC-SET-03.2).
 */
function autoAttendanceStatus(int $userId, string $date, bool $checkedIn): string
{
    $db = Database::instance();
    $leave = $db->selectOne(
        "SELECT `id` FROM `tbl_staff_leave_applications`
         WHERE `staff_id` = ? AND `status` = 'Approved' AND `from_date` <= ? AND `to_date` >= ?",
        [(int) $userId, $date, $date]
    );
    if ($leave) {
        return 'leave';
    }
    if (isOfficeHoliday($date, $userId)) {
        return 'holiday';
    }
    return $checkedIn ? 'present' : 'absent';
}

/** "2 hr 5 min" style formatting for minute deltas. */
function formatMinutes(int $minutes): string
{
    $minutes = (int) $minutes;
    $sign = $minutes < 0 ? '-' : '';
    $abs = abs($minutes);
    $h = (int) floor($abs / 60);
    $m = $abs % 60;
    return $sign . ($h > 0 ? $h . ' hr ' : '') . $m . ' min';
}
