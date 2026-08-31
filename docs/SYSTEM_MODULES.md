# SB-Tech — System Modules & Features (Full Reference)

> **Purpose:** single-source catalog of every module in the SB-Tech system (public website + Office Management System), with features and all details. Builders implement from here; PM/QA verify against it.
>
> **Related docs:** `docs/SB_TECH_SYSTEM_ANALYSIS.md` (architecture/design/flows/dataflow), `docs/PRD.md` (user stories + acceptance criteria with IDs).

---

## 1. System overview

One deployable system with two faces sharing one database and one login domain:

| Face | Path | Audience | Purpose |
|---|---|---|---|
| **Public website** | `/` (frontend) | Visitors, prospects, job seekers | Marketing, portfolio, contact/quote capture, careers |
| **OMS (admin)** | `/admin` | Staff (role-based) | Run the office: HR, attendance, leave, tasks, meetings, leads, finance, communication |

**Module map (admin sidebar, adapted from reference nav):**

```
MAIN      Dashboard
OFFICE    My Office (HR Care: Profile · Tasks · Meetings · Speak Up · Attendance · Leaves ·
          Expense Claims) · Office Calendar · Office Spaces
STAFF     Staffs · Daily Tasks · Leave Management · Module Permission · Staff History · Terminated Staffs
SALES     Leads · Clients
FINANCE   Posting (Vouchers) · Ledger · Account Reports · Expense Claims · Fiscal Years · Bank Reconciliation
OFFICE    Office Setup (Profile · Departments · Designations · Holidays · Bank Details · Meeting Halls · Documents)
COMMS     Email/SMS · Templates · Logs
WEBSITE   Website CMS (Home · Services · Projects · Team · News · Notices · Careers · Contact · Setup)
SETTINGS  Office Profile · Permissions · Users
```

**Cross-cutting capabilities (apply to every module):**
- Role-based access (module + submodule + special permissions)
- Search, filters, pagination (50/page), CSV export
- BS/AD dual calendar (config-driven)
- File uploads (whitelisted types, `user_uploads/<module>/`)
- Notifications (in-app + email/SMS) on workflow events
- Audit trail (actor + timestamp on every create/update/approve)

---

## 2. Module catalog (summary)

| # | Module | Purpose | Key features |
|---|---|---|---|
| 1 | Dashboard & Reports | Office health at a glance | KPI cards, drill-down widgets, report center, CSV exports |
| 2 | Office Setup | Configure office identity & structure | Profile, departments, designations, holidays, bank accounts, meeting halls, office spaces |
| 3 | Staff Management | HR master data & lifecycle | Staff CRUD, documents, social links, daily tasks, history, permissions, termination |
| 4 | Attendance | Track presence & hours | Check-in/out, late/early calc, working hours, monthly report |
| 5 | Leave Management | Plan & control absences | Leave types, yearly allocations, apply → verify → approve, balances, reports |
| 6 | Tasks & Follow-up | Assign and track work | Task CRUD, multi-assignee, statuses, updates + files, filters, overdue badges |
| 7 | Meetings & Events | Schedule and share | Event/meeting creation, multi-schedule, privacy, venues, office calendar |
| 8 | Speak Up (Grievances) | Raise and resolve concerns | Grievance submit, assignee + deadline, statuses, files, updates |
| 9 | Office Documents | Central document store | Categories, files, renew dates, Public/Private access |
| 10 | Lead Management | Sales pipeline | Auto-capture from website, stages, ownership, activities, follow-ups, dedupe, conversion to client |
| 11 | Finance | Money in/out & bookkeeping | Fiscal years, chart of accounts, vouchers, ledger, bank reconciliation, expense claims, TDS, reports |
| 12 | Communication | Notify & campaign | Email/SMS templates, workflow notifications, delivery logs |
| 13 | Authentication & RBAC | Secure access | Login, sessions, permissions, login attempts, devices |
| 14 | Website CMS | Public site content | Pages, projects, team, news, notices, careers, contact, template setup |
| 15 | Public Website | Visitor experience | Template packages, SEO, contact/quote/careers forms → OMS |

---

## 3. Module details

### 3.1 Dashboard & Reports

- **Purpose:** one screen showing what needs attention today; reports across all modules.
- **Features:**
  - KPI cards: active staff, present today, pending leaves, open tasks, overdue tasks, leads by stage, claims pending payment, FY cash in/out.
  - Each widget links to the underlying filtered list.
  - Report center: attendance monthly, leave usage, task completion, pipeline value, expense by category, voucher register.
  - CSV export on all list reports.
- **Permissions:** dashboard visible to all; report center sections gated by module permission.
- **Data sources:** aggregation queries over staff/attendance/leave/task/lead/voucher tables.

---

### 3.2 Office Setup

- **Purpose:** office identity and structural reference data everything else depends on.

#### Office profile
- Fields: name, acronym, logo (upload), address 1/2, email, phone 1/2, VAT no, website, calendar mode (BS/AD), leave-year mode, payment QR, backup/OTP email, allow-IPs.
- Exactly one active profile; used as header on vouchers, reports, certificates, website footer.
- **Table:** `tbl_office_profiles`.

#### Departments & designations
- Departments: title + sort position. Designations: title + position.
- Used by: staff records, task assignment, meeting privacy (public-to-department), leave substitute picker.
- Delete blocked while staff are assigned.
- **Tables:** `tbl_office_departments`, `tbl_office_designation`.

#### Holidays
- Title, from/to date, optional department scope, gender scope (Male/Female/Both), remarks.
- Holiday dates do not consume leave balance; attendance defaults to `holiday`.
- **Table:** `tbl_office_holidays`.

#### Bank details
- Bank name, account name, branch, account number, account type, SWIFT, other details. Referenced by payment vouchers and website payment info.
- **Table:** `tbl_office_bank_details`.

#### Meeting halls
- Hall name + occupancy. Selectable when creating meetings/events (venue = In Office).
- **Table:** `tbl_office_meeting_hall_setup`.

#### Office spaces
- List/manage office rooms/areas (from reference `office_spaces.php`).
- **Table:** `tbl_office_spaces`.

---

### 3.3 Staff Management

- **Purpose:** HR master data and the staff lifecycle from join to termination.

**Features:**
- **Staff CRUD:** fullname, gender, DOB, contacts, address, photo, citizenship, marital status, physically-challenged flag, education, skills; employment: staff id, join date, staff type (Admin/Service), department, designation, daily working hours, off day, PAN, bank (name/account no/name), SSF no, PF no, CIT no; login: username, email, phone, status (Active/Block/Terminated), termination date.
- **Staff documents:** multiple uploads (title, document type, file, size, remarks) per staff.
- **Social media:** title + link per staff.
- **Module permissions UI:** checkboxes for modules/submodules + special permissions; JSON stored on user row; Super Admin bypass.
- **Daily tasks:** staff records free-text tasks per date; admin sees all.
- **Staff history:** employment events timeline (join, changes, termination) with actor + date.
- **Termination:** requires date + reason; terminated staff locked out, excluded from active pickers, preserved in historical records; separate "Terminated staffs" list.
- **Registered devices (optional):** device enrollment for attendance/login enforcement.
- **Tables:** `tbl_users_login`, `tbl_user_profiles`, `tbl_staff_documents`, `tbl_staff_social_medias`, `tbl_daily_tasks`, `tbl_user_registered_devices`.

---

### 3.4 Attendance

- **Purpose:** accurate daily presence with punctuality metrics.

**Features:**
- **Check-in/check-out:** one row per user/date; stamps time; config times per staff; computes `checkin_delay` (late-in minutes), `checkout_early` (early-out minutes), `working_hours` (float).
- **Reasons:** optional text for late check-in and early checkout.
- **Proxy check-in:** `allow_checkin_by_other` permits a colleague to record on behalf.
- **Status:** present / absent / leave / holiday — auto or admin-adjusted.
- **Views:** staff see own records; admin sees all staff (with name column) + badges (danger = late >10 min, primary = early).
- **Monthly report:** present/late/absent counts, total working hours, CSV export.
- **Table:** `tbl_staff_attendances` (FK `user_id → tbl_users_login`).

---

### 3.5 Leave Management

- **Purpose:** policy-enforced leave with balance tracking and approvals.

**Leave types (setup):**
- Title, max allowed days, requires-approval flag, gender-specific documentation flag, carry-forward + max carry-forward, leave-year description, active flag.
- **Table:** `tbl_office_leave_configs`.

**Yearly allocations:**
- Per staff × leave type × leave year: allocated / used / remaining days. Leave-year mode (BS/AD) from office profile.
- **Table:** `tbl_office_staff_leave_allocation`.

**Apply (employee self-service):**
- Pick leave type (only types with remaining > 0 shown), substitute staff (required), from/to dates, half-day (first/second half), reason.
- Live day-count + balance warning; submit blocked if exceeding remaining.
- Edit/delete allowed only while Pending.

**Approval workflow:**
- `Pending → Verified → Approved | Rejected`
- Verify and Approve are separate actions; both record actor + time.
- Rejection requires a reason.
- On approval, `used_days` increments on the allocation.
- Approvers notified in-app + email/SMS.

**Reports:**
- By staff / type / year; balance summary; pending queue; CSV export.
- **Tables:** `tbl_staff_leave_applications`, `tbl_office_staff_leave_allocation`, `tbl_office_leave_configs`.

---

### 3.6 Tasks & Follow-up

- **Purpose:** assign work, track progress, never lose a deadline.

**Features:**
- Create: title, description (rich text), deadline, department → staff multi-select (AJAX), file attachments.
- Statuses: Pending → In Progress → Done; Rejected; Cancelled.
- Assignee experience: "Assigned to me" filter, same-day "new" blinking badge, "Past Due" badge when deadline passed.
- Author/admin: edit/delete within 7-day window; delete confirmed.
- Updates: assignee posts progress updates with files (update history preserved); status change notifies author.
- Filters: keyword, status, assigned-by, assigned-to; non-admin scoped to own authored/assigned tasks unless granted office module.
- **Tables:** `tbl_office_tasks`, `tbl_office_task_files` (improve: child `tbl_office_task_assignees` for real FKs + per-assignee status).

---

### 3.7 Meetings & Events / Office Calendar

- **Purpose:** schedule, share, and see what's happening.

**Create event/meeting:**
- Title, type (Event/Meeting), one or more date+time schedules (recurring via multiple schedule rows), privacy, venue, details.
- **Privacy:** Public → all employees or a single department; Private → specific invited staff (+ optional "other attendees" text, e.g., external guests).
- **Venue:** In Office → pick meeting hall (from setup) + occupancy; Out of Office → free-text location.
- **Free-staff picker:** for Private events, picker excludes staff already booked in another meeting at the same slot.
- Edit/delete: creator within 7 days; private-event delete requires creator or Super Admin.

**Office calendar:**
- Month view, BS/AD toggle, prev/next navigation, today's marker, day cells list events.
- Visibility SQL: own events + Public-all + Public-my-department + Private-invited.
- Upcoming panel: next events (type, date, title, attendees).
- **Tables:** `tbl_office_events`, `tbl_office_event_schedules`.

---

### 3.8 Speak Up (Grievances)

- **Purpose:** safe channel for concerns with visible resolution.

**Features:**
- Submit: title, description, attachments.
- Admin: assign to staff + deadline; statuses Pending / In Progress / Done / Rejected / Acknowledged.
- Author can post update files; status changes notify author + assignee.
- **Tables:** `tbl_office_grievances`, `tbl_office_grievance_files`.

---

### 3.9 Office Documents

- **Purpose:** central, categorized document store.

**Features:**
- Categories CRUD.
- Document: title, file(s) (multiple per document), type, size, renew date, access type (**Public**/**Private**), category.
- Private documents gated by `access_private_documents` special permission.
- Renew-date flags in list; document register export.
- **Tables:** `tbl_office_document_category`, `tbl_office_documents`, `tbl_office_document_files`.

---

### 3.10 Lead Management

- **Purpose:** capture every inquiry and drive it to a client.

**Capture:**
- Website contact/quote forms auto-create leads (source = Website) + raw message kept in website inbox; sales owner/group notified.
- Manual entry: phone, email, walk-in, referral, social.
- Careers applications → separate careers inbox.

**Pipeline:**
- Stages: **New → Contacted → Qualified → Proposal → Won / Lost**; list or kanban view.
- Fields: company, contact name, email, phone, service interest, priority (Hot/Warm/Cold), estimated value (NPR), assigned owner, source, lost reason.

**Ownership & activity:**
- Claim/assign from shared queue; reassignment recorded.
- Activity timeline: calls, emails, notes, meetings, status changes (type + note + actor + time); file attachments.

**Follow-up:**
- Create follow-up → task in Tasks module with deadline + reminder, linked to lead.
- Aging report: leads inactive N days (config, default 7).

**Dedupe & conversion:**
- Duplicate flag on same email/phone vs existing non-lost lead; merge by owner.
- Lost leads reopenable without data loss.
- Won → create client record (name, contact, address, PAN); optional project record (title, value, start/end, status) later.

**Reports:** pipeline value by stage, conversion by source, aging, owner performance; CSV export.
- **Tables:** `tbl_leads`, `tbl_lead_activities`, `tbl_lead_files`, `tbl_clients`, `tbl_client_projects` (v2), `tbl_cms_contacts_us`.

---

### 3.11 Finance

- **Purpose:** track money with double-entry bookkeeping and reimbursable expenses.

#### Fiscal years
- Title, starting/ending date, status Open/Closed. One active FY for postings; closed FY read-only.
- **Table:** `tbl_fiscal_years`.

#### Chart of accounts
- 4-level: groups → sub-groups → terminals → sub-terminals; seeded for a services company; delete blocked when in use.
- **Tables:** `tbl_account_groups`, `tbl_account_sub_groups`, `tbl_account_terminals`, `tbl_account_sub_terminals`.

#### Vouchers (double-entry)
- Types: Journal, Receipt, Payment, Contra, Purchase, Sales.
- Fields: FY, unique voucher no (per FY), date, reference no, narration, description, amount, discount, tax, total, currency + FX rate, entry type (Manual/Auto), status, file attachment.
- Entry lines (ledger particulars): debit/credit per terminal with denormalized account titles; **must balance (debits = credits) before save**.
- Status: **Pending → Approved** (`approve_vouchers` permission); approval records actor + time.
- Edits to Approved vouchers require audited un-approve or a correcting entry.
- **Tables:** `tbl_journal_vouchers`, `tbl_receipt_vouchers`, `tbl_payment_vouchers`, `tbl_contra_vouchers`, `tbl_purchase_vouchers`, `tbl_sales_vouchers`, `tbl_ledger_particulars`, `tbl_sub_ledger_particulars`, `tbl_voucher_logs`.

#### Ledger & reports
- Account ledger per terminal, cashbook, daybook, trial balance, balance sheet, cash flow, purchase/sales books; drill-down to source voucher.

#### Bank reconciliation
- Mark ledger lines reconciled (reconcile_ref) against statement; status per line.
- **Table:** `tbl_bank_reconciliation` / reconcile flags on particulars.

#### TDS / tax
- TDS types + report entries; DR/CR notes; confirmation letters.
- **Tables:** `tbl_account_tds_types`, `tbl_account_tds_report_entries`, `tbl_account_dr_cr_notes`, `tbl_account_confirmation_letters`.

#### Expense claims (NEW — reference has an empty stub)
- Staff submits: category, expense date(s), description, amount, receipt files (required), optional project/client link.
- Statuses: **Draft → Submitted → Approved/Rejected (reason) → Paid**; approval level configurable (supervisor → finance, or finance only).
- Edit/delete only while Draft or Rejected.
- Final approval auto-creates a Payment voucher (Pending) linked to the claim; when voucher approved → claim = Paid with voucher no shown.
- Reports: claims by staff/category/month, outstanding vs paid; CSV export.
- **Tables:** `tbl_expense_claims`, `tbl_expense_claim_files`.

---

### 3.12 Communication

- **Purpose:** timely notifications and bulk outreach.

**Features:**
- Email (SMTP/PHPMailer) + SMS (provider e.g. Sparrow) with editable templates (subject/body + placeholders).
- Workflow event wiring: new lead, leave submitted/approved/rejected, task assigned/updated, grievance assigned/updated, expense claim approved/rejected, voucher approved.
- Per-event enable/disable; delivery log; failure never blocks the workflow action (logged, continue).
- Campaigns + send history (optional extension).
- **Tables:** communication templates/campaigns/logs (port from reference).

---

### 3.13 Authentication & RBAC

- **Purpose:** secure, role-scoped access.

**Features:**
- Login with username/password (hashed + salt; upgrade path to bcrypt/argon2).
- Session keys: `userId`, `username`, `fullname`; status gates (Block/Terminated).
- Failed-attempt throttling via `tbl_login_attempts`.
- Permission checks on every page: module → submodule → special permission; Super Admin bypass.
- Optional registered-device enforcement.
- **Tables:** `tbl_users_login`, `tbl_login_attempts`, `tbl_user_registered_devices`, `tbl_notifications`.

---

### 3.14 Website CMS

- **Purpose:** edit everything the public sees without code.

**Features:**
- Sections: Home (hero, highlights, stats), Services (+ details), Projects/Portfolio (+ details/gallery), Team (staff cards + details, from staff records), News (+ detail), Notices, Careers (open positions + application intake), Testimonials, Contact (info + map), Gallery.
- Per-page SEO: meta title/description/keywords.
- Setup: site title, template selection, colors, map embed, contact email.
- **Tables:** `tbl_cms_*` (home, services, about, projects, team, news, notices, testimonials, contact, careers).

### 3.15 Public Website

- **Purpose:** visitor experience and the system's front door.

**Features:**
- Template packages (`frontend/sites/*`) — swappable look without data changes.
- Routes: `/`, `/about`, `/services`, `/projects`, `/team`, `/news`, `/notices`, `/careers`, `/contact`, `/404`.
- Forms: contact + quote/service inquiry → auto-create lead (see 3.10); careers application → careers inbox.
- SEO fields, responsive layouts, Google Maps embed.
- **Tables:** CMS tables (read), `tbl_cms_contacts_us` (write), `tbl_leads` (write).

---

## 4. Cross-cutting details

| Concern | Detail |
|---|---|
| **Dates** | AD default; BS optional via office profile + `tbl_calendar` + shared datepicker (`app_date_input_placeholder`, `app_format_date_view`). |
| **Files** | Allowed: jpg/jpeg/png/pdf/doc/docx/xls/xlsx/pptx/txt; max 10 MB; stored under `user_uploads/<module>/`. |
| **Money** | DECIMAL(18,4); NPR default; currency + FX columns on vouchers. |
| **Notifications** | In-app (`tbl_notifications`) + email/SMS (Communication). |
| **Audit** | `added_by/updated_by/added_on/updated_on` on all tables; voucher + approval + termination + permission logs. |
| **Security** | Prepared statements, CSRF on POST, session timeout, upload whitelist, login throttling. |
| **Exports** | CSV on: staff directory, attendance monthly, leave report, tasks, leads, claims, vouchers. |
| **Search/UI** | Filter bars per list, pagination 50/page, select2 dropdowns, datepickers, tabbed module shells, AdminLTE theme. |

---

## 5. Data model (tables by module)

See `docs/SB_TECH_SYSTEM_ANALYSIS.md` §6.4 for the full ER-style listing. Summary:

- **Setup:** `tbl_office_profiles`, `tbl_office_departments`, `tbl_office_designation`, `tbl_office_holidays`, `tbl_office_bank_details`, `tbl_office_meeting_hall_setup`, `tbl_office_spaces`
- **Staff:** `tbl_users_login`, `tbl_user_profiles`, `tbl_staff_documents`, `tbl_staff_social_medias`, `tbl_daily_tasks`, `tbl_user_registered_devices`
- **Attendance:** `tbl_staff_attendances`
- **Leave:** `tbl_office_leave_configs`, `tbl_office_staff_leave_allocation`, `tbl_staff_leave_applications`
- **Tasks:** `tbl_office_tasks`, `tbl_office_task_files` (+ `tbl_office_task_assignees` improvement)
- **Meetings:** `tbl_office_events`, `tbl_office_event_schedules`
- **Grievances:** `tbl_office_grievances`, `tbl_office_grievance_files`
- **Documents:** `tbl_office_document_category`, `tbl_office_documents`, `tbl_office_document_files`
- **Leads:** `tbl_leads`, `tbl_lead_activities`, `tbl_lead_files`, `tbl_clients`, `tbl_client_projects` (v2), `tbl_cms_contacts_us`
- **Finance:** `tbl_fiscal_years`, `tbl_account_groups/sub_groups/terminals/sub_terminals`, `tbl_{journal,receipt,payment,contra,purchase,sales}_vouchers`, `tbl_ledger_particulars`, `tbl_sub_ledger_particulars`, `tbl_voucher_logs`, `tbl_bank_reconciliation`, `tbl_account_tds_types`, `tbl_account_tds_report_entries`, `tbl_account_dr_cr_notes`, `tbl_account_confirmation_letters`, `tbl_expense_claims`, `tbl_expense_claim_files`
- **Communication:** templates, campaigns, logs (ported)
- **Auth/notifications:** `tbl_login_attempts`, `tbl_notifications`, `tbl_calendar`
- **CMS:** `tbl_cms_*` (home, services, projects, team, news, notices, testimonials, contact, careers)

---

## 6. Feature gaps vs reference (build new)
1. **Lead management** — absent in reference (only raw contact inbox).
2. **Expense claims** — empty stub in reference; designed from scratch (see 3.11).
3. **Careers intake** — website job posts → applications inbox.
4. **Client/project registry** — after lead Won.
5. **Finance reports depth** — port as-is where present; add claim-vs-voucher linkage reporting.

---

## 7. Build order (from analysis §7)
1. Foundation (config, DB layer, migrations, auth/RBAC, theme) → 2. Setup + Staff → 3. Attendance + Leave → 4. Office ops (tasks, meetings, speak-up, documents) → 5. Finance (FY, COA, vouchers, ledger, expense claims) → 6. Leads + Website → 7. Communication + Reports → 8. Hardening (tests, FKs, prepared-statement audit).
