# SB-Tech — Office Website + Office Management System

## Design Analysis (grounded in the `smart-school` reference codebase)

> Scope: two connected products built under one roof —
> **A. Public office website** (marketing / brochure site, CMS-driven)
> **B. Office Management System (OMS)** (internal staff + office operations CRM)
>
> Reference: `../smart-school` (PHP + MySQL modular monolith) — its `myOffice`,
> `school_management`, `staff_management`, `communication`, `accounts`, and `webcms`
> modules already implement 80% of what SB-Tech needs. This document analyzes
> those patterns and defines the SB-Tech design on top of them.

---

## 1. Executive summary

| Question | Answer |
|---|---|
| What are we building? | One deployable system with two faces: a public website (`/frontend`) and a staff admin app (`/admin`), sharing one database. |
| Recommended architecture | **Modular monolith** — PHP + MySQL, module-per-folder, page-script + operation-handler controllers, thin DB layer. Same DNA as smart-school, so code/patterns can be reused and the team already knows it. |
| Core design patterns | Front Controller (single router), Page Controller + Post/Redirect/Get, module + submodule permission gates, feature flags (plan), JSON ACL, tab-based module pages, template packages. |
| Core domains | Office setup, staff (HR), attendance, leave, tasks, meetings/events, documents, grievances, **lead management (new for SB-Tech)**, finance (vouchers/ledger/expense claims), communication, website CMS. |
| Fastest path | Port smart-school's office modules as-is, rebrand to SB-Tech, drop school-specific modules (academics, exam, fees, library, transport), add office-specific extras (projects/clients, expense claims, assets). |

---

## 2. Reference analysis — what smart-school already gives us

### 2.1 Module map (what maps 1:1 to SB-Tech)

| smart-school module | What it does | SB-Tech equivalent |
|---|---|---|
| `setup/office_profile` | Office identity: name, logo, address, phones, VAT, website, calendar mode (BS/AD), leave-year mode, certificate/regd numbers, payment QR | **Office profile** (keep) |
| `school_management/office_*` | Departments, designations, holidays (gender/department aware), bank details, meeting halls, leave types/setup, office documents + categories, school documents | **Office Setup** (keep) |
| `myOffice` (HR Care) | Tabs: Profile, Tasks, Meetings, Speak Up (grievances), Attendance, Leaves; plus Office Calendar, Office Spaces, Expense Claim, My Stocks | **My Office / Employee self-service** (keep) |
| `staff_management` | Staff CRUD + docs + social, daily tasks, leave management (application/report/setup/allocations), module permissions, registered devices, staff history, terminated staffs | **Staff / HR Management** (keep) |
| `communication` | SMS (Sparrow) + email (PHPMailer), templates, campaigns, logs | **Communication** (keep) |
| `webcms` | CMS: home/hero, services, about, gallery, courses→projects, staff, notice, message, news, testimonial, contact, setup (template + colors) | **Website CMS** (keep, rename courses→projects) |
| `frontend/` + `frontend/sites/*` | Public website with swappable template packages (`classic`, `modern`, `academic`, `vibrant`, `website1-4`), SEO fields, contact forms | **Public website** (keep) |
| `accounts`, `reports`, `ai_assistant` | Vouchers/ledger, analytics, AI chat | Optional for SB-Tech v1 |

### 2.2 What to drop (school-only)

Academics (classes/students/exams), fees, library, transport, CAS, student/parent portal.

### 2.3 What smart-school is MISSING (build new for SB-Tech)

**Lead management.** The reference has zero lead/CRM tracking — its only capture point is the
`webcms` contact form (`tbl_cms_contacts_us`) and `message` inbox. For a services company that
sells projects, every website inquiry, quote request, and referral is revenue — so SB-Tech gets a
dedicated lead module that sits between the public website and the OMS (see §4-C, §5.7, §6.4).

**Expense claims.** `admin/modules/myOffice/expense_claim.php` is an **empty stub** in the
reference (tab exists in HR Care, page renders nothing) — SB-Tech must design it from scratch
(see §4-D, §5.10).

Other gaps worth building (not in reference):
- Careers application intake (website job posts → applications inbox)
- Client/project registry (won lead → client with associated projects/contracts)
- Activity timeline for staff records (reference has basic history only)

### 2.4 Anti-patterns to fix in the SB-Tech build

From `ARCHITECTURE.md` (verified in code):

1. **Raw SQL string concatenation** everywhere (`escape_data()` + `"WHERE id = '$id'"`) → use prepared statements / a `dbSqlQuery` API with bound params.
2. **Dual DB APIs** (mysqli for admin, PDO for frontend) → one thin repository layer.
3. **Fat page scripts** mixing HTML + SQL + business rules → split view / handler / service.
4. **Few real FKs** → define FKs on all new tables.
5. **Permission action params unused** → enforce action-level permissions.
6. **No automated tests** → add a PHPUnit suite for core flows (attendance calc, leave balance).
7. **Copy-paste backups** (`*_backup.php`, `* copy.php`) → keep the tree clean.

---

## 3. Design pattern

### 3.1 Recommended: Modular Monolith (PHP + MySQL)

```
┌────────────────────────────── Apache + PHP (one deploy) ─────────────────────────────┐
│                                                                                       │
│  /                      public website (front controller → template package)          │
│  /admin/show_page.php   staff app  (front controller → module/page → render)          │
│  /admin/operation.php   write path (form POST → operation handler → redirect)         │
│  /admin/ajax.php        read-path AJAX (calendars, dependent dropdowns)               │
│  /artisan               migration runner + CLI repair                                 │
│                                                                                       │
│  ┌───────────────┐  ┌───────────────┐  ┌───────────────┐  ┌───────────────┐          │
│  │ myOffice      │  │ staff_management│ │ office_setup  │  │ webcms        │  modules │
│  │  pages/       │  │  pages/        │  │  pages/       │  │  pages/       │          │
│  │  operation/   │  │  operation/    │  │  operation/   │  │  operation/   │          │
│  └───────────────┘  └───────────────┘  └───────────────┘  └───────────────┘          │
│        │                    │                  │                    │                 │
│        └────────────────────┴──────────────────┴────────────────────┘                 │
│                                        │                                              │
│                     config/bootstrap.php (session, constants, helpers)                │
│                                        │                                              │
│                     classes/ (db wrapper)   functions/ (shared helpers)               │
│                                        │                                              │
│                                     MySQL (sb_tech)                                   │
│                                        │                                              │
│                        user_uploads/ (photos, docs, task files)                       │
└───────────────────────────────────────────────────────────────────────────────────────┘
```

### 3.2 Patterns used (name → where in smart-school → how SB-Tech applies it)

| Pattern | Reference evidence | SB-Tech application |
|---|---|---|
| **Front Controller** | `admin/show_page.php?module=X&page=Y`; `frontend/index.php` route map | Single router for admin pages and website routes (`/about`, `/projects`, `/careers`) |
| **Page Controller + PRG** | page PHP renders; `operation.php?module=..&page=..` handles POST then redirects back | Every create/edit = form → operation handler → flash message → redirect |
| **Module/Submodule ACL** | `checkModulePermision()`, `checkSubModulePermision()`, `checkSpecialPermission()`, JSON `permitted_modules` blob on user | RBAC: role + module + submodule + special action permissions |
| **Feature flags** | `PLAN = PRO \| WEBSITE` changes nav + routes | `PLAN = OFFICE \| WEBSITE` — website-only installs vs full OMS |
| **Tabbed module shell** | `hr_care.php` (Profile/Tasks/Meetings/Speak Up/Attendance/Leaves), `leave_management.php` (Applications/Report/Setup/Allocations) | Every complex module = shell page + tab includes → consistent UX + per-tab permissions |
| **Service helpers** | `getStaffLeaveAllocationsWithBalance()`, BS/AD calendar helpers, `app_format_date_view()` | Business rules live in `functions/` so page scripts stay thin |
| **Template packages** | `frontend/sites/{template}/pages/*`, template resolved from CMS settings | SB-Tech ships 2–3 website themes, swappable without data changes |
| **Operation registry** | `*_operation.php` per module, routed through `operation.php` | Same, with centralized CSRF + permission check |
| **Migration runner** | `php artisan migrate`, ordered PHP SQL arrays, `tbl_migrations` | Same; one command, versioned schema |
| **Event/notification hooks** | `tbl_notifications`, communication service on leave/task events | Notify approvers/assignees on status changes (in-app + email/SMS) |

### 3.3 Why NOT a different stack (and when to reconsider)

- **Laravel / Node / Django** would be cleaner OOP but slower to ship for a solo founder, and nothing in smart-school transfers. Reconsider only if you plan heavy real-time features (chat, presence) — then a thin Node/WebSocket sidecar beside the PHP app, or Laravel Reverb.
- **Multi-tenant SaaS** (many offices in one DB): smart-school is single-tenant per DB (`tbl_office_profiles` with one row). For SB-Tech v1 keep single-tenant; if you later sell to other offices, add a `tenant_id` column strategy then (shared schema + tenant filter), not schema-per-tenant.
- **Headless REST API**: not needed for v1; the AJAX endpoints + operation handlers cover everything. Add a proper API only for a future mobile app.

---

## 4. Feature list

### A. Public website (`/` + `/frontend`)

| Area | Features |
|---|---|
| **Home** | Hero slider, intro/services highlights, stats (projects, clients, team), latest news/notices, testimonials, contact CTA |
| **Company** | About (mission, history, team culture), Why us |
| **Services** | Service catalog with details pages |
| **Projects / Portfolio** | Project cards, filters, project detail with gallery |
| **Team** | Staff directory + individual profiles (reuse `tbl_cms_staffs` pattern) |
| **News & Notices** | Blog/news list + detail; notice board |
| **Careers** | Open positions (reuse office designation/department), application form → inbox |
| **Contact** | Contact form (→ admin inbox + email), Google Map embed, office hours |
| **SEO/admin extras** | Per-page meta (title/description/keywords), sitemap, social links, gallery |
| **CMS control** | All of the above editable from `/admin` webcms module; template + color picker |

### B. Office Management System (`/admin`)

**Setup & Office identity**
- Office profile (name, acronym, logo, address, phones, email, website, VAT/PAN, QR, calendar mode)
- Departments, designations (ordered), holidays (date ranges, department- or gender-scoped), bank details, meeting halls

**Staff / HR**
- Staff CRUD (personal, contact, employment: join date, designation, department, PAN/bank/SSF/PF, image, docs)
- Staff documents (title, type, file), social links, staff history timeline, termination flow
- Module/submodule/special permissions per staff
- Registered devices (optional: enforce login device), daily task entries

**Attendance**
- Check-in / check-out with config times, late-in / early-out minutes, reasons, working-hours calc
- Status: present / absent / leave / holiday; monthly report
- Admin can view all, staff view own

**Leave management**
- Leave types (config: max allowed, carry-forward, requires approval, gender-specific docs, leave-year mode)
- Yearly allocation per staff (allocated/used/remaining), BS/AD leave year
- Apply (from/to, half-day first/second half, substitute staff, reason) → **Pending → Verified → Approved/Rejected**
- Balance guard (cannot exceed remaining), leave report

**Tasks & follow-up**
- Create task (title, description, deadline, department → staff picker, files), statuses Pending/In Progress/Done/Rejected
- Filter by status/keyword/assignee; author can edit/delete within window; assignee sees "assigned to me"
- Task files + updates

**Meetings & events / calendar**
- Office calendar (BS/AD toggle, month view, day view with schedules)
- Create meeting/event: title, one or more date+time schedules, privacy (**Public** = all/dept vs **Private** = invited staff), venue (meeting hall or out-of-office location), other attendees, remarks
- Free-staff picker (checks staff not busy in another meeting at that slot), upcoming list

**Speak Up (grievances)**
- Anonymous/attributed grievance → assignee + deadline + status (Pending/In Progress/Done/Rejected/Acknowledged), file attachments, updates

**Documents**
- Document categories; office documents (title, file(s), type, size, renew date, **Public/Private** access), private access gated by `access_private_document` permission

**Extras (port from myOffice)**
- Office spaces, expense claims, my stocks, my salary (optional)

**Communication**
- Email (SMTP/PHPMailer) + SMS (Sparrow) with templates, campaigns, logs — wired to leave approval, task assignment, grievances

**Reports & dashboard**
- Attendance summary, leave report, task completion, staff directory export (CSV), activity log

### C. Lead management (NEW — not in smart-school)

| Area | Features |
|---|---|
| **Capture** | Auto-create leads from website contact/quote forms (source = Website), plus manual entry (phone, email, walk-in, referral, social); careers applications → separate intake inbox |
| **Pipeline** | Stages: **New → Contacted → Qualified → Proposal → Won / Lost**; list or kanban view; drag/drop stage changes |
| **Ownership** | Assign lead to staff owner; claim unassigned leads; reassignment history |
| **Follow-up** | Schedule follow-up task (reuse tasks module) with deadline + reminder; auto task on stage change; activity timeline (calls, emails, notes, files) per lead |
| **Qualification** | Lead score/priority (Hot/Warm/Cold), service interest, estimated value (NPR), source tracking |
| **Conversion** | Won → create client + (optional) project record; Lost → required lost-reason |
| **Reports** | Pipeline value, conversion rate by source, lead aging, owner performance, CSV export |
| **Dedupe** | Block duplicate leads on same email/phone; merge option |

### D. Accounts & Finance (port from reference `accounts` module; expense claims NEW)

| Area | Features |
|---|---|
| **Fiscal years** | FY setup (title, start/end, Open/Closed); all vouchers scoped to an FY; voucher numbers unique per FY |
| **Chart of accounts** | 4-level hierarchy: **groups → sub-groups → terminals → sub-terminals**, seeded for a services company (income, expenses, assets, liabilities, capital, TDS) |
| **Vouchers (double-entry)** | Journal, Receipt, Payment, Contra, Purchase, Sales; each: FY, unique voucher no, date, reference, narration, amounts (discount/tax/total), currency + FX rate, attached file, status **Pending → Approved** (approved_by recorded), `entry_type` Manual/Auto |
| **Ledger** | `tbl_ledger_particulars` = the double-entry lines (debit/credit per terminal, date, FY, denormalized account titles, reconcile ref) → drives ledger, cashbook, daybook, trial balance, balance sheet, cash flow |
| **Sub-ledger** | Optional per-terminal sub-ledger tracking (per-client or per-staff accounts) |
| **Bank** | Bank reconciliation (statement vs ledger via `reconcile_ref`), office bank accounts from setup |
| **TDS / tax** | TDS types + entries, DR/CR notes, confirmation letters (from reference) |
| **Expense claims (NEW)** | Staff submits claim (category, dates, description, amount, receipt files, optional project/client) → **Draft → Submitted → Approved/Rejected** (multi-level: supervisor → finance) → approved claim auto-creates a payment voucher |
| **Reports** | Ledger, cashbook, daybook, trial balance, balance sheet, cash flow, bank reconciliation, purchase/sales books, expense-by-category, claims outstanding vs paid |

---

## 5. Working flows (key journeys)

### 5.1 Login & authorization
```
Staff → /admin/login → verify password (sha512+salt) → session(userId, username, fullname)
     → load permitted_modules / permitted_submodules / special_permission from user row
     → sidebar renders only permitted modules
     → every request re-checks: checkModulePermision(module) + checkSubModulePermision(page) + checkSpecialPermission(action)
```
Admin user bypasses checks; terminated/blocked users rejected at login.

### 5.2 Staff onboarding
```
Admin → Staff Management → Add Staff (personal + employment + login credentials)
  → upload documents & photo → assign designation/department
  → grant module permissions (permissions UI)
  → (optional) register device
  → staff appears in: team directory, task assignee picker, meeting attendee picker, leave allocation target
```

### 5.3 Attendance check-in / check-out
```
Staff (My Office → Attendance) → Check-in
  → server stamps checkin time, compares vs config checkin → checkin_delay (late if > threshold)
  → on check-out: stamps checkout, computes checkout_early and working_hours
  → row status = present | absent | leave | holiday (leave/holiday may be auto-derived from approvals + office holidays)
  → staff sees own history; admin sees all with late/early badges + reasons
```

### 5.4 Leave application → approval (the core workflow)
```
Staff → My Office → Leaves → Add leave
  → pick leave type (options filtered to types with remaining balance)
  → pick substitute staff → from/to dates (or half-day first/second half)
  → live day-count + balance check (exceeds-remaining warning)
  → submit → row created: status = Pending

Approver (permission: manage_staff_leaves) → Leave Management → Applications
  → Verify → status = Verified  (verified_by recorded)
  → Approve → status = Approved (approved_by recorded; used_days incremented on allocation)
  → Reject  → status = Rejected (reject_reason recorded)
  → staff notified (in-app + email/SMS); balance updates visible in My Leaves
```
Guards: cannot apply for more than remaining days; can edit/delete only while Pending.

### 5.5 Task assignment
```
Manager → My Office → Tasks (or HR Care → Tasks) → Add task
  → select department → AJAX loads staff of that dept → multi-select assignees
  → set title, description, deadline, attach files → save
Assignee → My Office → Tasks → sees "Assigned to me" (highlighted), "new" badge same-day
  → updates status, posts update + files; author/admin can edit/delete within 7-day window
Overdue → "Past Due" badge when deadline < now
```

### 5.6 Meeting / event creation
```
Staff → My Office → Office Calendar (or HR Care → Meetings) → Create
  → title + one or more date/time schedules
  → privacy: Public (All employees or one department) | Private (pick individual staff)
  → venue: meeting hall (from setup) or out-of-office location; optional other attendees
  → if Private: free-staff picker excludes staff already booked that slot
  → saved: tbl_office_events (master) + tbl_office_event_schedules (each date/time)
  → calendar shows only events user can see (privacy SQL: own / public-all / public-dept / private-invited)
```

### 5.7 Lead lifecycle (website inquiry → client)
```
Visitor ─ contact/quote form on website
  → process_contact_handler: save raw message (webcms inbox) + create lead
      (source=Website, status=New, service interest from form) → notify sales owner/group (email/SMS)

Sales rep ─ Lead Management
  → claim/assign → status=Contacted → schedule follow-up task (deadline + reminder)
  → call/email → log activity (type + note) → qualify (Hot/Warm/Cold, estimated value)
  → Proposal (attach quote doc, deadline) → status=Proposal
  → Won  → create Client (+ Project) record; pipeline value recorded
  → Lost → required lost reason; archived with report visibility

Manager ─ Pipeline view: value by stage, aging, conversion by source; CSV export
```

### 5.8 Website content publishing
```
Admin → Website CMS → Home/Services/Projects/Team/News/... → edit content (rich text, images, SEO meta)
  → frontend reads CMS tables + template setting → renders selected site package
  → visitor → /contact → form POST → process_contact_handler → saves + emails admin
```

### 5.9 Voucher entry & approval
```
Accountant → Accounts → Fiscal Year (active FY required)
  → Chart of Accounts (seeded; add terminals as needed)
  → Posting → select voucher type (Journal/Receipt/Payment/Contra/Purchase/Sales)
  → enter date, reference, narration, amount(s), attach file
  → entry lines → ledger particulars (debit = credit, each line linked to a terminal)
  → status = Pending → approver (permission) approves → Approved, approved_by recorded
  → reports (ledger, cashbook, daybook, trial balance, balance sheet) read from particulars
  → reconciliation: mark reconcile_ref against bank statement; closed FY accepts no new postings
```

### 5.10 Expense claim (design for SB-Tech — reference page is an empty stub)
```
Staff → My Office → Expense Claim → New claim
  → category, expense date(s), description, amount, attach receipts, optional project/client
  → submit → status = Submitted
Supervisor → review → Approve (forward to finance) or Reject (reason)
Finance → final approve → system auto-creates a Payment voucher (Pending)
  → voucher approved in Accounts → claim marked Paid, linked to voucher
Reports: claims by staff/category/month, outstanding vs paid; CSV export
```

---

## 6. Data flow & database design

### 6.1 Admin request flow (read)
```
Browser ─ GET /admin/show_page.php?module=X&page=Y ─► show_page.php
  1. config/bootstrap.php → session, constants, DB, helpers
  2. auth gate (session) → redirect /admin/login if not logged in
  3. load nav from includes/varriables.php; filter by permissions
  4. checkModulePermision + checkSubModulePermision
  5. include admin/modules/X/Y.php  → page queries via DB layer → renders HTML
```

### 6.2 Admin write flow (mutate)
```
Browser ─ POST /admin/operation.php?module=X&page=Y_operation ─► operation handler
  1. CSRF + permission check
  2. escape/sanitize inputs (→ prepared statement in SB-Tech build)
  3. insert/update/delete via DB layer
  4. optional notification (leave/task/grievance events)
  5. flash message (success/error) + redirect back to show_page.php?module=X&page=Y
```

### 6.3 Frontend website flow
```
GET /about ─► .htaccess rewrite ─► frontend/index.php
  1. app_bootstrap loads CMS settings (template, colors, office profile)
  2. route map resolves path → pages/{page}.php under the active site package
  3. page queries CMS tables (PDO) → renders; contact POST → process_contact_handler
```

### 6.4 Core data model (SB-Tech, derived from smart-school schema)

```
tbl_office_profiles (1 row)  ── office identity
tbl_office_departments ◄──┐
tbl_office_designation ◄─┐ │
                         │ │
tbl_users_login (staff) ─┘ └─ department_id, designation
  ├─ tbl_user_profiles (bio, emergency contact, skills)
  ├─ tbl_staff_documents
  ├─ tbl_staff_social_medias
  ├─ tbl_staff_attendances        (user_id, date, checkin/out, delays, working_hours, status)
  ├─ tbl_office_staff_leave_allocation (year, leave_id, staff_id, allocated/used)
  ├─ tbl_staff_leave_applications (staff, type, dates, days, substitute, status, verified/approved_by)
  ├─ tbl_office_tasks             (author, assigned[], deadline, status) + tbl_office_task_files
  ├─ tbl_office_grievances        (author, assigned, deadline, status)  + tbl_office_grievance_files
  └─ tbl_daily_tasks

tbl_office_leave_configs ── leave types (max days, carry-forward, gender-specific, requires approval)
tbl_office_holidays        ── holiday calendar (dept/gender scoped)
tbl_office_meeting_hall_setup ── venues
tbl_office_events ◄── tbl_office_event_schedules (multi date/time)
tbl_office_document_categories ◄── tbl_office_documents ◄── tbl_office_document_files
tbl_office_bank_details
tbl_office_spaces / expense claims / stocks  (port from myOffice)

CMS: tbl_cms_* (home, services, about, projects, team, news, notices, testimonials, contact)
Auth: tbl_users_login + tbl_login_attempts + tbl_notifications
Calendar: tbl_calendar (BS/AD) if Nepal calendar support is kept

Lead management (NEW):
```
tbl_cms_contacts_us (raw website inbox, unchanged from webcms)
tbl_leads            (source, company, contact_name, email, phone, service_interest,
                     priority, estimated_value, stage[New/Contacted/Qualified/Proposal/Won/Lost],
                     assigned_to, won→client_id, lost_reason, added_by/on)
tbl_lead_activities  (lead_id, type[call/email/note/meeting/status_change], note, added_by/on)
tbl_lead_files       (lead_id, file, added_by/on)
tbl_clients          (won leads → name, contact, address, PAN, notes)
tbl_client_projects  (client_id, title, value, start/end, status)   [optional v2]
```

Accounts & finance (port from reference, expense claims NEW):
```
tbl_fiscal_years                 (title, start/end date, Open/Closed)
tbl_account_groups ◄── tbl_account_sub_groups ◄── tbl_account_terminals ◄── tbl_account_sub_terminals
tbl_journal_vouchers / tbl_receipt_vouchers / tbl_payment_vouchers / tbl_contra_vouchers /
tbl_purchase_vouchers / tbl_sales_vouchers
    (fiscal_year_id FK, voucher_no UNIQUE per FY, date, narration, amounts, currency/fx,
     status Pending/Approved, approved_by, file)
tbl_ledger_particulars           (voucher_type + voucher_type_id, date, FY, terminal refs +
                                 denormalized titles, debit/credit, reconcile_ref, status)
tbl_sub_ledger_particulars       (optional per-terminal sub-ledgers)
tbl_voucher_logs, tbl_ledger_closings, tbl_bank_reconciliation
tbl_account_tds_types + tbl_account_tds_report_entries, tbl_account_dr_cr_notes,
tbl_account_confirmation_letters
tbl_expense_claims (NEW)         (staff_id FK, category, date(s), amount, status
                                 Draft/Submitted/Approved/Rejected/Paid, payment_voucher_id)
tbl_expense_claim_files (NEW)    (claim_id FK, receipt files)
```

Key relationships (logical FKs, enforced in SB-Tech build):
- `tbl_leads.assigned_to → tbl_users_login.id` (nullable; unassigned = queue)
- `tbl_leads.won → tbl_clients.id` (set on conversion)
- `tbl_lead_activities.lead_id → tbl_leads.id (CASCADE)`
- `tbl_lead_files.lead_id → tbl_leads.id (CASCADE)`
- `tbl_users_login.department_id → tbl_office_departments.id`
- `tbl_users_login.designation → tbl_office_designation.id`
- `tbl_staff_attendances.user_id → tbl_users_login.id (CASCADE)`
- `tbl_staff_leave_applications.staff_id → tbl_users_login.id`, `leave_type_id → tbl_office_leave_configs.id`
- `tbl_office_staff_leave_allocation.(staff_id, leave_id, year)` unique
- `tbl_office_tasks.assigned` = comma-separated staff ids (smart-school) → **improve**: child `tbl_office_task_assignees` table for real FKs + per-assignee status
- `tbl_office_event_schedules.event_id → tbl_office_events.id (CASCADE)`
- `tbl_*_vouchers.fiscal_year_id → tbl_fiscal_years.id (RESTRICT)`; `voucher_no` unique per FY
- `tbl_ledger_particulars` → `(voucher_type, voucher_type_id)` → owning voucher row; lines reference `tbl_account_terminals`
- `tbl_expense_claims.staff_id → tbl_users_login.id`; `payment_voucher_id → tbl_payment_vouchers.id` (set when paid)
- `tbl_expense_claim_files.claim_id → tbl_expense_claims.id (CASCADE)`

### 6.5 Storage & integrations
- File uploads → `user_uploads/{office_documents, task_files, staff_documents, speakup_files, webcms, vouchers, expense_claims}/`
- Email: PHPMailer/SMTP; SMS: Sparrow (or any provider via a `CommunicationService`)
- PDF/Word: Dompdf + PhpOffice (reports, offer letters, ID cards optional)
- Cron: monthly leave-year rollover / carry-forward recalculation, attendance finalization

---

## 7. Build roadmap

| Phase | Deliverable |
|---|---|
| **0. Foundation** | Repo layout (mirror smart-school), config/bootstrap, DB wrapper (prepared statements), migration runner, auth + RBAC, base AdminLTE theme, front controller |
| **1. Setup + Staff** | Office profile, departments, designations, holidays, bank details; staff CRUD + docs + permissions; dashboard |
| **2. Attendance + Leave** | Check-in/out, attendance report; leave types, allocations, apply→verify→approve workflow, balances, report |
| **3. Office ops** | Tasks, meetings/events + calendar, Speak Up, documents, office spaces |
| **4. Finance** | Fiscal years, chart of accounts (seeded), vouchers (journal/receipt/payment/contra/purchase/sales) + approval, ledger & reports, bank reconciliation, expense claims (submit → approve → auto payment voucher) |
| **5. Leads + website** | Lead module (pipeline, activities, conversion) wired to contact/quote forms + webcms module (home/services/projects/team/news/notices/contact) + 2 site packages + careers + SEO |
| **6. Communication + reports** | Email/SMS templates + campaign wiring to workflows; finance/attendance/leave/task reports, CSV export |
| **7. Hardening** | Tests (PHPUnit) for leave balance + attendance calc + voucher balancing, FKs, prepared statements audit, backups |

---

## 8. Open decisions (founder)

1. **Stack**: keep PHP modular monolith (fastest, reuses smart-school DNA) vs migrate to Laravel/Node (cleaner, slower). *Recommendation: PHP monolith for v1.*
2. **Nepali calendar (BS/AD)** and NPR/timezone: keep only if SB-Tech operates in Nepal.
3. **Multi-office tenancy now or later**: single-tenant v1; design with a `tenant_id`-friendly layer only if you plan to productize for other offices.
4. **Accounts depth**: full double-entry (chart of accounts, 6 voucher types, ledger/cashbook/balance sheet — ports almost 1:1 from the reference) vs a simple income/expense tracker + expense claims for v1. *Recommendation: expense claims + cash book in v1; add full double-entry when the office accountant needs it.*
5. **Mobile**: portal-style employee app (attendance, leave) — future phase via existing AJAX/API endpoints.
6. **Lead capture behavior**: auto-create a lead for every website contact message (low friction, more noise) vs keep raw inbox and "promote to lead" manually (cleaner pipeline). *Recommendation: auto-create for quote/service forms, manual promote for generic contact messages.*
