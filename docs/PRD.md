# SB-Tech Office Management System — Product Requirements Document (PRD)

> **Version:** 1.0 draft
> **Status:** For review
> **Related:** `docs/SB_TECH_SYSTEM_ANALYSIS.md` (architecture, features, flows, data model), reference codebase `../smart-school` (myOffice, staff_management, school_management, accounts, communication, webcms modules)
>
> **Scope of this PRD:** the **Office Management System (OMS)** — the internal `/admin` application — **including lead management and finance**. The public website's content requirements are summarized in §2.3 as an input channel; its full PRD can be a follow-up document.

---

## 1. Product overview

### 1.1 Purpose
Give SB-Tech one internal system to run the office: staff & HR operations (attendance, leave, tasks, meetings), office setup, sales pipeline (leads → clients), and finance (expense claims → vouchers → ledger), with the public website feeding leads in.

### 1.2 Goals
1. Replace manual/spreadsheet tracking of attendance, leave, tasks, and expenses.
2. Capture and convert every website inquiry into a tracked, owned lead.
3. Track money in/out (claims, receipts, payments) with an audit trail and basic bookkeeping.
4. Single login, role-based access, one source of truth for staff data.

### 1.3 Target users & roles

| Role | Description | Typical access |
|---|---|---|
| **Super Admin** | Founder/owner. Full access, user management, system setup. | All modules + all special permissions |
| **Admin / Manager** | Department heads. Manage staff, view everything in their scope, approve leave/tasks. | Staff mgmt, office ops, reports; manage_staff_leaves |
| **HR / Office** | Runs HR operations and office setup. | Staff mgmt, attendance mgmt, office setup |
| **Finance / Accountant** | Bookkeeping and expense/payment approval. | Accounts module; approve_vouchers, approve_expense_claims |
| **Sales / BD** | Owns and works the lead pipeline. | Lead management |
| **Employee** | Self-service for own data. | Own profile, attendance, leave, tasks, meetings |
| **Public visitor** | No login. | Public website only (contact/quote/careers forms) |

### 1.4 Permission model
- Module + submodule allowlists per user (JSON on the user row, as in the reference).
- Special permissions (granular actions): `manage_staff_leaves`, `approve_vouchers`, `approve_expense_claims`, `manage_leads`, `access_private_documents`, `audit`, `view_all_attendance`.
- Super Admin bypasses all checks.

---

## 2. Scope

### 2.1 In scope (OMS modules)
1. Authentication & RBAC
2. Office setup (profile, departments, designations, holidays, bank details, meeting halls)
3. Staff management (CRUD, documents, permissions, history, termination)
4. Attendance
5. Leave management
6. Tasks
7. Meetings & events + office calendar
8. Speak Up (grievances)
9. Office documents
10. Lead management (capture, pipeline, activities, conversion)
11. Finance (fiscal years, chart of accounts, vouchers, ledger, reports, expense claims)
12. Communication (email/SMS notifications on workflow events)
13. Reports & dashboard

### 2.2 Out of scope (this PRD)
- Student/parent portal, academics, exams, fees, library, transport (school-only; dropped)
- Payroll/salary processing (expense claims + ledger only)
- Full public website content PRD (see §2.3 for integration points)
- Mobile app (future; reuses same data/API endpoints)

### 2.3 Website → OMS integration points (requirements only)
- Contact form and quote/service-inquiry form POST to the OMS lead capture endpoint → creates a lead (see LE module).
- Careers form POSTs to a careers inbox (staff-visible).
- No OMS data is exposed publicly except curated CMS content.

---

## 3. Cross-cutting requirements

| ID | Requirement |
|---|---|
| X-01 | All write actions require a valid session + module permission; CSRF token on every POST. |
| X-02 | Every create/update records `added_by/updated_by` + timestamps; destructive actions require confirmation. |
| X-03 | File uploads allowed types: jpg/jpeg/png/pdf/doc/docx/xls/xlsx/pptx/txt; size cap 10 MB; stored under `user_uploads/<module>/`. |
| X-04 | Lists paginate (50/page default), searchable, filterable, CSV export where noted. |
| X-05 | Dates support AD and (if enabled in office profile) BS calendars via a shared datepicker + conversion helper. |
| X-06 | Workflow state changes (leave, task, grievance, expense claim, lead, voucher) generate an in-app notification + optional email/SMS to involved users. |
| X-07 | All money amounts stored as DECIMAL(18,4), currency NPR default, with currency + FX rate columns on vouchers. |
| X-08 | Audit log: voucher/approval/termination/permission actions logged (actor, action, entity, timestamp). |
| X-09 | The system must never allow a numeric balance (leave, claims) to go negative through the UI. |
| X-10 | All DB writes use prepared statements; no string-concatenated SQL. |

---

## 4. Module requirements — user stories & acceptance criteria

### 4.1 Authentication & RBAC (AUTH)

**US-AUTH-01** — As a staff member, I want to log in with my username/password so that I can access only what my role allows.
- AC-AUTH-01.1: Login validates username (case-insensitive lookup) + hashed password; failed attempts recorded (`tbl_login_attempts`).
- AC-AUTH-01.2: Blocked/Terminated users are rejected with a clear message.
- AC-AUTH-01.3: On success, session stores `userId`, `username`, `fullname`; sidebar renders only permitted modules/submodules.
- AC-AUTH-01.4: Every protected page re-checks module + submodule + special permission before rendering; unauthorized access shows a denial message, not a crash.

**US-AUTH-02** — As a Super Admin, I want to manage user accounts and permissions so that access matches responsibilities.
- AC-AUTH-02.1: Create/edit staff login (username, email, phone, role, status Active/Block/Terminated, module + submodule checkboxes, special permissions).
- AC-AUTH-02.2: Permission changes take effect on the user's next request (no re-login required).

### 4.2 Office setup (SET)

**US-SET-01** — As an admin, I want to configure the office profile so that documents, reports, and the website use correct identity info.
- AC-SET-01.1: Fields: name, acronym, logo (upload), address, email, phones, website, VAT/PAN, calendar mode (BS/AD), leave-year mode, payment QR.
- AC-SET-01.2: Exactly one active profile; changes reflect in generated documents (voucher headers, reports).

**US-SET-02** — As an admin, I want to manage departments and designations so that staff are organized and reports can group by them.
- AC-SET-02.1: CRUD departments (title, sort position) and designations (title, position).
- AC-SET-02.2: Deleting a department/designation is blocked if staff are assigned to it.

**US-SET-03** — As an admin, I want to define office holidays so that attendance and leave calculations account for them.
- AC-SET-03.1: Holiday CRUD: title, from/to date, optional department and gender scope, remarks.
- AC-SET-03.2: Holiday dates do not consume leave balance; attendance for those dates defaults to `holiday`.

**US-SET-04** — As an admin, I want to maintain office bank accounts and meeting halls so that payments and meetings reference them.
- AC-SET-04.1: Bank details CRUD (bank, account name, branch, account no., type, SWIFT, notes).
- AC-SET-04.2: Meeting hall CRUD (name, occupancy). Halls are selectable when creating meetings.

### 4.3 Staff management (STF)

**US-STF-01** — As an HR admin, I want to add a staff member with employment + personal details so that they can log in and be assigned work.
- AC-STF-01.1: Fields: name, gender, DOB, contact, address, photo, join date, department, designation, staff type, PAN/bank details, SSF/PF numbers, daily working hours, off day, login credentials.
- AC-STF-01.2: Username must be unique; default status Active.
- AC-STF-01.3: Created staff appears immediately in: team directory, task assignee picker, meeting attendee picker, leave allocation list.

**US-STF-02** — As an HR admin, I want to attach documents and social links to a staff record so that records are complete and audit-ready.
- AC-STF-02.1: Upload multiple documents (title, type, file) per staff; list with download; delete with confirmation.
- AC-STF-02.2: Social media links CRUD (title + URL).

**US-STF-03** — As an HR admin, I want to view staff history and terminate staff so that offboarding is controlled.
- AC-STF-03.1: Staff history shows employment events (join, designation/department changes, termination) with dates and actors.
- AC-STF-03.2: Termination requires a date + reason; terminated staff cannot log in, cannot be assigned new tasks/meetings, and are excluded from active pickers.
- AC-STF-03.3: Terminated staff remain visible in a "Terminated staffs" list and in historical records (leave, tasks, attendance).

**US-STF-04** — As a staff member, I want to record my daily tasks so that managers can see what I did.
- AC-STF-04.1: Daily task entry: date + free-text tasks; editable same day; visible to admin/manager; monthly view.

### 4.4 Attendance (ATT)

**US-ATT-01** — As a staff member, I want to check in and check out so that my attendance is recorded accurately.
- AC-ATT-01.1: One attendance row per user per date (`user_id, date` unique).
- AC-ATT-01.2: Check-in stamps current time; if `checkin > config_checkin`, `checkin_delay` (minutes) and `late_checkin` flag are computed; optional reason field.
- AC-ATT-01.3: Check-out stamps time, computes `checkout_early` and `working_hours`; optional reason field.
- AC-ATT-01.4: A user can check in/out only once per day unless admin adjusts; `allow_checkin_by_other` (per staff) permits a colleague to record on their behalf.
- AC-ATT-01.5: Status auto-set: present (checked in), absent (no check-in by EOD), leave (approved leave covers date), holiday (office holiday).

**US-ATT-02** — As an admin, I want to view all attendance so that I can monitor punctuality and hours.
- AC-ATT-02.1: Admin view lists all staff with date, check-in/out, late-in/early-out (hrs+min), status, working hours; staff view only own records.
- AC-ATT-02.2: Monthly report with totals (present days, late days, absent days, working hours); CSV export.

### 4.5 Leave management (LV)

**US-LV-01** — As an HR admin, I want to configure leave types and yearly allocations so that leave policy is enforced automatically.
- AC-LV-01.1: Leave type CRUD: title, max allowed days, requires approval flag, gender-specific documentation flag, carry-forward + max carry-forward, active flag.
- AC-LV-01.2: Allocate days per staff per leave year (allocated/used/remaining); remaining = allocated − used.
- AC-LV-01.3: Leave-year mode (BS/AD per office profile) determines the allocation year boundary.

**US-LV-02** — As a staff member, I want to apply for leave so that my absence is planned and approved.
- AC-LV-02.1: Application: leave type (only types with remaining > 0), substitute staff (required), from/to dates, half-day option (first/second half), reason.
- AC-LV-02.2: UI shows live day count and warns (and blocks submit) if days exceed remaining balance.
- AC-LV-02.3: Submitting creates a record with status **Pending**; staff can edit/delete only while Pending.
- AC-LV-02.4: Approver(s) notified in-app + email/SMS.

**US-LV-03** — As an approver, I want to verify and approve/reject leave so that absences are controlled.
- AC-LV-03.1: Workflow: **Pending → Verified → Approved/Rejected**. Verify and Approve are separate actions; both record who/when.
- AC-LV-03.2: Rejection requires a reason (shown to staff).
- AC-LV-03.3: On approval, `used_days` increments on the staff's allocation for that leave year; on rejection, no change.
- AC-LV-03.4: Staff sees updated balance + status immediately on My Leaves page.

**US-LV-04** — As an HR admin, I want leave reports so that I can monitor usage.
- AC-LV-04.1: Reports: leave by staff/type/year, balance summary, pending approvals queue; CSV export.

### 4.6 Tasks (TSK)

**US-TSK-01** — As a manager, I want to create and assign tasks so that work is tracked to completion.
- AC-TSK-01.1: Create task: title, description, deadline, department → staff multi-select (AJAX), file attachments.
- AC-TSK-01.2: Task statuses: Pending, In Progress, Done, Rejected, Cancelled.
- AC-TSK-01.3: Assignees see the task under "Assigned to me" with a same-day "new" badge; overdue tasks show a "Past Due" badge.
- AC-TSK-01.4: Author/admin can edit/delete within 7 days of creation; delete requires confirmation.

**US-TSK-02** — As an assignee, I want to update my tasks with progress and files so that the author can track status.
- AC-TSK-02.1: Assignee updates status and can post an update with files (update history preserved).
- AC-TSK-02.2: Status changes notify the author.

**US-TSK-03** — As a manager, I want to filter tasks so that I can review workloads.
- AC-TSK-03.1: Filters: keyword (title/description), status, assigned-by, assigned-to; non-admin users see only tasks they authored or are assigned (unless granted office module access).

### 4.7 Meetings & events / calendar (MTG)

**US-MTG-01** — As a staff member, I want to schedule a meeting/event so that attendees are informed.
- AC-MTG-01.1: Create: title, one or more date+time schedules, privacy (Public = all/dept; Private = invited staff), venue (meeting hall from setup or out-of-office location + address), other attendees, remarks.
- AC-MTG-01.2: For Private meetings, a free-staff picker excludes staff already booked in another meeting at the same slot.
- AC-MTG-01.3: Creator can edit/delete within 7 days; private meetings can only be deleted by creator or Super Admin.

**US-MTG-02** — As a staff member, I want an office calendar so that I can see what's scheduled.
- AC-MTG-02.1: Month-view calendar with BS/AD toggle; day cells show meetings/events.
- AC-MTG-02.2: Visibility rules: own events, Public-to-all, Public-to-my-department, Private events where I'm invited.
- AC-MTG-02.3: "Upcoming" panel lists next events with date, type, title, attendees.

### 4.8 Speak Up / grievances (GRV)

**US-GRV-01** — As a staff member, I want to raise a grievance so that issues reach the right person.
- AC-GRV-01.1: Submit: title, description, optional file attachments; author recorded.
- AC-GRV-01.2: Statuses: Pending, In Progress, Done, Rejected, Acknowledged; optional assignee + deadline set by admin.
- AC-GRV-01.3: Author can attach update files; status changes notify author and assignee.

### 4.9 Office documents (DOC)

**US-DOC-01** — As an admin, I want to store office documents so that staff can find them.
- AC-DOC-01.1: Document categories CRUD; document record: title, file(s), type, size, renew date, access type **Public/Private**, category.
- AC-DOC-01.2: Public documents visible to all staff; Private documents only to users with `access_private_documents`.
- AC-DOC-01.3: Documents nearing renew date flagged in list; CSV export of document register.

### 4.10 Lead management (LE)

**US-LE-01** — As a business, I want website inquiries to become leads automatically so that no inquiry is lost.
- AC-LE-01.1: Contact/quote form POST creates a lead: source = Website, stage = New, service interest + message from the form, contact name/email/phone copied.
- AC-LE-01.2: Raw message also saved to the website inbox (retained as source of truth).
- AC-LE-01.3: Sales owner/group notified (in-app + email) on new lead.

**US-LE-02** — As a sales rep, I want to manage the pipeline so that I can convert leads.
- AC-LE-02.1: Stages: New → Contacted → Qualified → Proposal → Won/Lost; stage change from list or kanban.
- AC-LE-02.2: Fields: company, contact name, email, phone, service interest, priority (Hot/Warm/Cold), estimated value (NPR), assigned owner, source, lost reason.
- AC-LE-02.3: Claim/assign: unassigned leads show in a shared queue; reassignment recorded.
- AC-LE-02.4: Activity timeline: log calls, emails, notes, meetings, status changes (type + note + actor + time); attach files to a lead.

**US-LE-03** — As a sales rep, I want follow-ups tracked so that leads don't go cold.
- AC-LE-03.1: Creating a follow-up creates a task (reuses TSK module) with deadline + reminder, linked to the lead.
- AC-LE-03.2: Leads without activity for N days (config, default 7) appear in an "aging" report.

**US-LE-04** — As a sales rep, I want to avoid duplicates so that pipeline numbers stay clean.
- AC-LE-04.1: New lead with same email/phone as an existing non-lost lead is flagged as duplicate; merge allowed by owner.
- AC-LE-04.2: Lost leads can be reopened (stage → Contacted) without data loss.

**US-LE-05** — As a manager, I want pipeline reports so that I can forecast and coach.
- AC-LE-05.1: Reports: pipeline value by stage, conversion rate by source, lead aging, owner performance; CSV export.

**US-LE-06** — As a sales rep, I want to convert a won lead so that it becomes a client.
- AC-LE-06.1: Won requires a client record (name, contact, address, PAN optional); creating the client is one step from the lead.
- AC-LE-06.2: Client visible in a clients list; optional project record (title, value, start/end, status) in a later phase.

### 4.11 Finance (FIN)

**US-FIN-01** — As a finance user, I want to manage fiscal years so that books are periodized.
- AC-FIN-01.1: Fiscal year CRUD: title, starting/ending date, status Open/Closed.
- AC-FIN-01.2: Only one active (Open) FY for new postings; closing an FY blocks new vouchers in it (read-only).

**US-FIN-02** — As a finance user, I want a chart of accounts so that entries are categorized.
- AC-FIN-02.1: Hierarchy groups → sub-groups → terminals → sub-terminals; seeded defaults for a services company; CRUD with delete-blocked-when-in-use.

**US-FIN-03** — As a finance user, I want to enter vouchers so that transactions are recorded.
- AC-FIN-03.1: Voucher types: Journal, Receipt, Payment, Contra, Purchase, Sales. Fields: FY, unique voucher no (auto per FY), date, reference no, narration, amounts (discount/tax/total), currency + FX, attached file.
- AC-FIN-03.2: Each voucher has debit lines = credit lines (must balance) before save; unbalanced entry is blocked with a clear message.
- AC-FIN-03.3: Status **Pending** → approve (with `approve_vouchers` permission) → **Approved**; approval records who/when.
- AC-FIN-03.4: Voucher number cannot be reused within an FY; edits to Approved vouchers require un-approve (audited) or a correcting entry.

**US-FIN-04** — As a finance user, I want ledger reports so that I can see account balances.
- AC-FIN-04.1: Reports from ledger particulars: account ledger (per terminal), cashbook, daybook, trial balance, balance sheet, cash flow, purchase/sales books.
- AC-FIN-04.2: Reports filter by FY + date range; drill-down from report line to source voucher.

**US-FIN-05** — As a finance user, I want bank reconciliation so that statements match books.
- AC-FIN-05.1: Mark ledger lines as reconciled (reconcile_ref) against a statement; reconciliation status visible per line.

**US-FIN-06** — As a staff member, I want to claim expenses so that I get reimbursed.
- AC-FIN-06.1: New claim: category, expense date(s), description, amount, receipt files (required), optional project/client link.
- AC-FIN-06.2: Statuses: Draft → Submitted → Approved/Rejected (with reason); Approved by supervisor then final-Approved by finance (configurable single- or two-level).
- AC-FIN-06.3: Editing/deleting allowed only while Draft or Rejected.

**US-FIN-07** — As a finance user, I want approved claims to become payments so that books stay consistent.
- AC-FIN-07.1: Final approval auto-creates a Payment voucher (Pending, linked to the claim) — or finance chooses manual creation; either way the claim must reference its payment voucher.
- AC-FIN-07.2: Once the voucher is approved, claim status → **Paid** (linked voucher no shown).

**US-FIN-08** — As a finance user, I want expense reports so that I can control spending.
- AC-FIN-08.1: Reports: claims by staff/category/month, outstanding vs paid; CSV export.

**US-FIN-09** — As an auditor, I want voucher and approval logs so that changes are traceable.
- AC-FIN-09.1: Every voucher create/edit/approve/void logged with actor + timestamp (voucher logs table); audit view available to `audit` permission.

### 4.12 Communication (COM)

**US-COM-01** — As an admin, I want email/SMS notifications on workflow events so that people act in time.
- AC-COM-01.1: Events wired: new lead, leave submitted/approved/rejected, task assigned/updated, grievance assigned/updated, expense claim approved/rejected, voucher approved.
- AC-COM-01.2: Templates editable (subject/body, placeholders); per-event enable/disable; delivery log stored.
- AC-COM-01.3: Failure of an email/SMS never breaks the underlying workflow action (logged, continue).

### 4.13 Dashboard & reports (RPT)

**US-RPT-01** — As a manager, I want a dashboard so that I can see office health at a glance.
- AC-RPT-01.1: KPI cards: staff active count, present today, pending leaves, open tasks, overdue tasks, leads by stage, claims pending payment, FY cash in/out.
- AC-RPT-01.2: Widgets link through to the underlying lists (no dead ends).

**US-RPT-02** — As a manager, I want exports so that data leaves the system cleanly.
- AC-RPT-02.1: CSV export for: staff directory, attendance monthly, leave report, task list, lead pipeline, claims, voucher register.

---

## 5. Non-functional requirements

| ID | Requirement |
|---|---|
| N-01 | **Performance:** list pages < 2 s with 10k rows (indexed queries, pagination); dashboard < 3 s. |
| N-02 | **Security:** prepared statements everywhere; password hashing (sha512+salt or upgrade to bcrypt/argon2); CSRF on all POSTs; upload MIME/extension whitelist; session timeout (config, default 8 h); failed-login throttling. |
| N-03 | **Reliability:** daily DB backup (cron); file storage under `user_uploads/`; migrations run via `artisan` with rollback where defined. |
| N-04 | **Maintainability:** module-per-folder layout (mirrors reference); business rules in `functions/` services; no page-script SQL concatenation. |
| N-05 | **Testing:** PHPUnit suite covering leave balance math, attendance late/early math, voucher balancing, lead dedupe, expense-claim → voucher linkage. |
| N-06 | **Localization:** Asia/Kathmandu timezone, NPR currency, optional BS/AD calendar (config-driven). |

---

## 6. Success metrics

| Metric | Target (v1) |
|---|---|
| Staff daily attendance recorded electronically | ≥ 95% of working days |
| Leave applications processed fully in system (no email/spreadsheet) | 100% |
| Website inquiries auto-captured as leads | 100% |
| Leads with a recorded owner within 24 h | ≥ 90% |
| Expense claims paid within N days of approval (config) | ≥ 80% |
| Vouchers entered without manual ledger double-entry | 100% |
| System uptime | ≥ 99% |

---

## 7. Non-goals (v1)
- Payroll/salary computation and payslips (claims + ledger only)
- Multi-tenant SaaS (single office per install; `tenant_id`-ready design only if productizing)
- Real-time chat / video meetings
- Mobile app (data/API ready for a later phase)
- Public website deep PRD (separate document; §2.3 integration points only)

---

## 8. Open questions for stakeholders
1. Leave approval: single-step (Approve) vs two-step (Verify → Approve) — default: two-step per reference.
2. Expense claim approval: one-level (finance only) vs two-level (supervisor → finance) — configurable.
3. Lead auto-capture: all website messages create leads, or only quote/service forms (recommended) with manual promote for generic contact?
4. Finance depth: full double-entry in v1 or cash book + expense claims first (see analysis Open Decision #4).
5. BS/AD calendar: keep Nepali calendar support in v1?
