# SB-Tech Product Review — Website + Office Management System

> **Date:** 2026-08-16
> **Basis:** `docs/SYSTEM_MODULES.md` (15 modules), `docs/PRD.md` (stories + ACs), `docs/SB_TECH_SYSTEM_ANALYSIS.md` (architecture), `database/migration/` (85 files, 78 tables, verified against MariaDB).
> **Purpose:** honest review of what we are about to build — what is strong, what is at risk, what is missing, and what to actually ship first.

---

## 1. What the product is

One PHP+MySQL modular monolith with two faces:

1. **Public website** — CMS-driven marketing site (home, services, projects, team, news, notices, careers, contact) with swappable themes and lead-capturing forms.
2. **Office Management System (OMS)** — internal admin for: office setup, staff/HR, attendance, leave, tasks, meetings/calendar, grievances, documents, **leads**, **finance** (double-entry), and communication.

Staff, leads, and finance bridge the two: website forms feed leads; staff records feed the team page; approved expense claims feed payment vouchers.

**Feature inventory (built against spec, all verified):** 15 modules · 78 tables · 75 FKs · 4 seeded reference tables · 13 workflows documented (login → onboarding → attendance → leave → tasks → meetings → speak-up → website → vouchers → expense claims → leads).

---

## 2. What is genuinely strong

| Strength | Evidence |
|---|---|
| **Spec-consistent** | Table diff vs SYSTEM_MODULES.md §5 is clean (only the `tbl_cms_*` glob remains, which maps to concrete tables) |
| **Schema is real** | 78 tables execute on MariaDB, 75 FKs, no ordering violations; seeds verified (admin user, office profile, FY, chart of accounts) |
| **Fixes the reference's debt** | Prepared statements mandated, real FKs, task-assignee child table, no string-concatenated SQL |
| **Clear approval workflows** | Leave (Pending→Verified→Approved/Rejected), vouchers (Pending→Approved), expense claims (Draft→Submitted→Approved→Paid), each with balance/integrity guards |
| **Money handled correctly** | DECIMAL(18,4), per-FY unique voucher numbers, debit=credit balancing enforced |
| **Leads bridge the website** | Contact/quote forms → auto lead → pipeline → Won → client — the classic missing piece in the reference |
| **Traceability** | PRD ACs have IDs (US-*/AC-*); audit columns + voucher logs everywhere |

---

## 3. What is wrong or at risk (the honest part)

### R1. Scope is too big for one v1
15 modules, 78 tables, 13 workflows. A founder cannot build and maintain this as a first release. The docs already sequence it (8 phases), but the *product decision* isn't made yet — the PRD lists everything as in-scope. **Risk: 6–12 months to a partial, buggy whole instead of 6 weeks to a solid core.**

### R2. Duplicate concepts that will confuse users and data
- **Two "staff" records:** `tbl_users_login` (OMS staff) vs `tbl_cms_staffs` (website team cards). Currently separate — editing one won't update the other. Decision needed: make the team page **read from staff records** (recommended) or keep CMS cards as intentional marketing content.
- **Two inboxes:** `tbl_cms_messages` and `tbl_cms_contacts_us` do the same job. Keep one (recommended: `tbl_cms_contacts_us` + lead link; drop or merge the other).

### R3. `tbl_calendar` is empty — BS mode would break
The BS/AD calendar table exists but has **no seed data** (the reference seeds ~90 years of Nepali dates). If `use_date = BS` is enabled, every date-dependent flow (leave, attendance, calendar) breaks. Either seed it from smart-school or drop BS support from v1 and keep AD-only.

### R4. No first-run/onboarding experience
Fresh install = login as `admin/admin` → empty office profile → no departments, no leave types, no chart-of-account entries beyond seeds, no fiscal year beyond one seed. Nothing guides setup. A small **setup wizard** (profile → departments → admin user → first FY) would dramatically improve adoption.

### R5. Finance depth is a bet, not a decision
Full double-entry (6 voucher types + ledger + balance sheet) is heavy for a small services office. PRD decision #4 recommends cashbook-first, but the schema + features build the full thing. Building it is fine if the office accountant will use it; otherwise it's the most expensive module for the least daily use.

### R6. No seed/test data strategy
The scratch-DB verification proves schema correctness, but there is **no sample dataset** (a few departments, staff, leave types, one journal voucher, a couple of leads) — so the first real UI build will be tested against an empty system, which hides wiring bugs.

### R7. Security gaps vs the PRD's own promises
- Passwords: legacy sha512+salt seeded (fine for v1) — the PRD promises an upgrade path; that's an open item, not done.
- No 2FA / IP allow-list enforcement (office profile has `allow_ips` column but nothing enforces it yet).
- Audit log is defined (X-08) but no audit table beyond `tbl_voucher_logs` — permission changes and terminations have no dedicated log table.

### R8. Some promised features have no home yet
- **NFR/security features** (session timeout, upload whitelist, CSV export) are requirements, not implemented — fine, but they must not be forgotten in phase 0.
- `last_activity_on` on leads needs a touch-up trigger (it's a column with no rule defined for when it updates).

---

## 4. Missing features worth an explicit decision (not silently skipped)

| Feature | Why it matters | Recommendation |
|---|---|---|
| **Project time tracking / timesheets** | Services company bills by effort; currently projects have value but no hours | v2 — unless billing is a core need, then move earlier |
| **Client invoicing** | Sales vouchers exist, but no per-client invoice → project → payment trail | v2; tie Won leads → client → invoice later |
| **Meeting minutes/agenda/action items** | Meetings are scheduled but nothing is recorded after | Nice v1.5 — small win, high perceived value |
| **Document versioning** | Documents support multiple files but no revision history | v1.5 |
| **Recruitment workflow** | Careers intake exists (New→Shortlisted→Interview→Offer) | Already in schema; keep, it's cheap |
| **Payroll / salary slips** | Explicitly out of scope | Keep out of v1; expense claims + ledger cover cash |
| **Mobile app** | Documented as future | Keep out; AJAX/API-ready |

---

## 5. Recommended v1 (the honest MVP)

Ship **one release** that covers daily office reality — cut everything else to v2:

| # | Module | Why in v1 |
|---|---|---|
| 1 | Auth + RBAC + dashboard | Non-negotiable |
| 2 | Office setup (profile, departments, designations, holidays) | Everything references it |
| 3 | Staff management | People are the product |
| 4 | Attendance + leave | Highest daily usage, most spreadsheet pain |
| 5 | Tasks | Second-highest daily usage |
| 6 | Meetings + calendar | Third-highest; also shows BS/AD need early |
| 7 | Lead capture → simple pipeline | The website's whole point; keep stages but no kanban/aging extras |
| 8 | Website (home/services/projects/team/news/contact) | Public face |
| 9 | Expense claims → **cashbook** (not full double-entry) | Real money out; skip vouchers/ledger/COA in v1 |
| 10 | Email/SMS notifications on the above | Workflows live or die on notifications |

**Explicitly defer to v2:** full double-entry accounting (vouchers, ledger, balance sheet, TDS), grievances, office documents register, office spaces, communication campaigns, gallery/testimonials/SEO depth, sub-ledgers, bank reconciliation UI, device registration.

**Fix before coding:** R2 (decide staff/team + single inbox), R3 (seed or drop BS), R4 (setup wizard), R6 (seed dataset).

---

## 6. Open decisions that block nothing but shape everything

1. **BS/AD calendar:** seed from smart-school (~90 yrs) or AD-only v1? (R3)
2. **Finance depth:** cashbook + claims first (recommended) vs full double-entry now? (R5)
3. **Team page source:** staff records vs CMS cards? (R2)
4. **Lead capture:** auto-create for quote/contact forms, manual promote otherwise? (PRD open Q3)
5. **Leave approval:** two-step (Verify→Approve) vs single-step — schema supports both; UI must pick.

---

## 7. Verdict

The design is **technically sound and internally consistent** — the schema is real, verified, and FK-clean, and the feature set covers a genuine services-office need (HR ops + leads + money out + a public face). The product risk is **not correctness, it's size**: as specced, it's a 12-month build; as an MVP it's a 2-month build with 60% of the daily value. Make the §5 cut, fix the R2–R4 items before coding, and the result is a product a small office will actually use every day.
