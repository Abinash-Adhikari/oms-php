# SB-Tech — Database Schema Reference

> **Author:** Principal Engineer (20-yr full-stack veteran)
> **Status:** Reference. Derived from `database/migration/*.php`.
> **Engine:** InnoDB · `utf8mb4` · `utf8mb4_unicode_ci` everywhere.

---

## 1. Conventions

| Convention | Rule |
|---|---|
| **Table prefix** | All application tables use `tbl_`. The `tbl_migrations` table (infrastructure) is the only exception to the "domain table" rule. |
| **Charset** | `utf8mb4_unicode_ci` on every table (`.sql` files all use `DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`). |
| **PK** | Always `` `id` INT NOT NULL AUTO_INCREMENT `` (or `BIGINT` for audit/journal lines). `PRIMARY KEY (id)`. |
| **Timestamps** | `` `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP ``; `` `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP `` on every mutable table. |
| **Audit columns** | `` `added_by` INT `` → `tbl_users_login(id)` (SET NULL on delete); `` `updated_by` `` same pattern. |
| **Soft-delete** | Not present. Use `status` ENUMs instead (`Active|Block|Terminated`, `Pending|Approved|Rejected`, `In Stock|Out of Stock`). |
| **Money** | `DECIMAL(18,4)` everywhere. Currency in a separate `CHAR(3)` column. |
| **FKs** | `ON DELETE RESTRICT` for fiscal/calendar links (must not orphan); `ON DELETE SET NULL` for actor/user links (history must survive). |

**84 tables** total, grouped into 9 domains below. Arrows (→) denote FK
references.

---

## 2. Domain Map (by Bounded Context)

### 2.1 Identity & Access (core)

```
tbl_users_login          ← master account / staff record
tbl_user_profiles        — extended profile (avatar, bio)
tbl_user_registered_devices — device trust (session tokens)
tbl_login_attempts       — failed-login log (for RateLimiter evidence)
tbl_audit_log            — every significant action (immutable)
```

**`tbl_users_login`** — the central entity. Every module FKs back to it.

| Column | Type | Notes |
|---|---|---|
| `id` | INT AI PK | |
| `username` | VARCHAR(191) | login key (BINARY match in Auth) |
| `email` | VARCHAR(191) | for notifications |
| `password` | VARCHAR(191) | bcrypt (60 chars) or legacy sha512 |
| `salt` | VARCHAR(255) | legacy field, empty after bcrypt upgrade |
| `role` | VARCHAR(100) | Admin / Manager / Finance / Sales / Employee |
| `staff_type` | ENUM('Admin','Service') | |
| `permitted_modules` | JSON | `['leads','accounts',…]` or `'All'` (Super Admin) |
| `permitted_submodules` | JSON | `{'my_office':['tasks','meetings'],…}` |
| `special_permission` | JSON | `['approve_vouchers','audit',…]` |
| `fullname` | VARCHAR(255) | display name |
| `department_id` | INT → `tbl_office_departments` | |
| `designation_id` | INT → `tbl_office_designation` | |
| `status` | ENUM('Active','Block','Terminated') | default 'Active' |
| `checkin`, `checkout` | TIME | daily work window |

### 2.2 Office Setup (configuration / reference data)

```
tbl_office_profiles            ← singleton (id=1), org-wide settings
tbl_office_departments         → tbl_users_login (staff roster)
tbl_office_designation         → tbl_users_login
tbl_office_bank_details        → tbl_office_profiles
tbl_office_holidays            → tbl_office_departments  (dept-scoped)
tbl_office_spaces              ← meeting rooms / desks
tbl_office_meeting_hall_setup  → tbl_office_spaces
tbl_office_leave_configs       ← leave type definitions
tbl_office_staff_leave_allocation → tbl_users_login, tbl_office_leave_configs
```

**`tbl_office_profiles`** (singleton, seeded by `ensure_tbl_office_profiles_id_1`):
the configuration nucleus. `allow_ips` (IP allow-list), `use_date` (AD|BS),
`leave_year_mode` (AD|BS), `plan_name`, `logo`, `payment_qr_code`, SMTP
credentials are in `tbl_communication_settings` instead.

**`tbl_office_leave_configs`** defines leave types (Casual, Sick, Earned…).
`tbl_office_staff_leave_allocation` is the per-staff balance ledger.

### 2.3 Staff Operations (HR)

```
tbl_staff_attendances        → tbl_users_login
tbl_staff_documents          → tbl_users_login
tbl_staff_social_medias      → tbl_users_login
tbl_staff_history            → tbl_users_login (employment changes)
tbl_office_tasks             → tbl_users_login
tbl_office_task_assignees    → tbl_office_tasks, tbl_users_login
tbl_office_task_files        → tbl_office_tasks
tbl_office_events            → tbl_users_login
tbl_office_event_schedules   → tbl_office_events
tbl_office_grievances        → tbl_users_login
tbl_office_grievance_files   → tbl_office_grievances
```

**`tbl_staff_attendances`** — the daily punch record (AC-HR-02.1):
`checkin`, `checkout`, `status` (present/absent/half-day),
`late_checkin` TINYINT, `early_checkout` TINYINT. Computed by
`computeAttendanceMetrics()` in `functions/hr.php`.

**`tbl_office_tasks`** — task lifecycle `status`: `Pending|In Progress|
Completed|Blocked|Cancelled`. `taskScopeSql()` in `functions/office.php`
filters by ownership or `canSeeAllTasks`.

### 2.4 Leave Management

```
tbl_staff_leave_applications  → tbl_users_login (staff), tbl_office_leave_configs
```

**`tbl_staff_leave_applications`** — the approval workflow:
`status` ENUM('Pending','Approved','Rejected') + `verified_by` →
`tbl_users_login` + `approved_by` → `tbl_users_login`.
`is_half_day`, `from_date`, `to_date`, `reason`.

### 2.5 Accounting / Finance (double-entry core)

```
tbl_account_groups            ← COA top level (Assets, Liabilities, …)
tbl_account_sub_groups        → tbl_account_groups  ("Services" seed)
tbl_account_sub_terminals     → tbl_account_sub_groups
tbl_account_terminals         → tbl_account_sub_groups  (leaf accounts)
tbl_account_tds_types         ← tax deduction types
tbl_fiscal_years              ← financial year bounds + status (Open|Closed)
tbl_payment_vouchers          → tbl_fiscal_years, tbl_users_login (approved_by)
tbl_receipt_vouchers          → tbl_fiscal_years
tbl_contra_vouchers           → tbl_fiscal_years
tbl_journal_vouchers          → tbl_fiscal_years
tbl_ledger_particulars        → tbl_account_terminals, tbl_fiscal_years
tbl_sub_ledger_particulars    → tbl_ledger_particulars
tbl_account_confirmation_letters → tbl_fiscal_years
tbl_account_dr_cr_notes       → tbl_account_terminals
tbl_account_tds_report_entries → tbl_account_tds_types
tbl_ledger_closings           → tbl_fiscal_years, tbl_account_terminals
tbl_expense_claims            → tbl_users_login
tbl_expense_claim_files       → tbl_expense_claims
tbl_bank_reconciliation       → tbl_account_terminals (bank)
tbl_voucher_logs              → tbl_users_login  (audit of voucher mutations)
```

**`tbl_account_terminals`** is the **chart of accounts** leaf node. The COA
hierarchy is: `groups` (Assets/Liabilities/Revenue/Expense) → `sub_groups`
(Accounts/Revenue-from-Sales/…) → `sub_terminals` (detail ledgers) →
`terminals` (postable accounts). Seeded defaults: `seed-table-account_groups`,
`seed-table-account_sub_groups_services`, `seed-table-account_terminals_services`.

**`tbl_payment_vouchers`** keys: `UNIQUE(fiscal_year_id, voucher_no)`,
`status` ENUM('Pending','Approved','Rejected'), `amount DECIMAL(18,4)`,
`entry_type` ENUM('Manual','Auto').

**`tbl_ledger_particulars`** — the transaction-line table. Each voucher posts
a set of **balanced** debit/credit lines. `accountingParseVoucherLines()`
enforces debits == credits within a `0.01` tolerance (ACOUNTS-02).

**`tbl_fiscal_years`** — `status` ENUM('Open','Closed'). `ON DELETE RESTRICT`
from all voucher tables: you cannot delete a FY in use.

### 2.6 Sales / Leads

```
tbl_leads             ← primary sales entity
tbl_lead_files        → tbl_leads
tbl_lead_activities   → tbl_leads
tbl_clients             ← leads converted to clients
tbl_client_projects     → tbl_clients
tbl_quotations          → tbl_clients (optionally tbl_leads)
tbl_sales_vouchers      → tbl_fiscal_years, tbl_clients
```

**`tbl_leads`** — the funnel entry point (AC-SALES-01). Columns: `source`
(Website Form, Call, Referral), `status` (New|Contacted|Qualified|Converted|
Lost), `assigned_to` → `tbl_users_login`, `converted_to_client` (INT→clients).
The public website's contact/quote forms write here.

### 2.7 Inventory

```
tbl_inv_categories        ← category tree (parent_id self-ref)
tbl_inv_items             → tbl_inv_categories, tbl_users_login
tbl_inv_suppliers         → tbl_users_login
tbl_inv_stock             → tbl_inv_items
tbl_inv_stock_movements   → tbl_inv_items, tbl_inv_suppliers, tbl_users_login
tbl_inv_assets            → tbl_inv_items
tbl_inv_asset_logs        → tbl_inv_assets
tbl_inv_purchase_requisitions       → tbl_users_login
tbl_inv_purchase_requisition_items → tbl_inv_purchase_requisitions, tbl_inv_items
```

**`tbl_inv_items`** — master SKU. `sku`, `barcode`, `unit`, `cost_price DECIMAL`,
`selling_price DECIMAL`, `reorder_level`, `track_warehouse` (BOOL).

**`tbl_inv_stock`** — current count per item (denormalized; updated by
`inventoryRecordMovement()`). `quantity_on_hand`, `reserved_qty`.

**`tbl_inv_stock_movements`** — immutable log. Every stock change (purchase,
issue, adjustment) is a row with `movement_type` ENUM('IN','OUT','ADJ'),
`quantity`, `rate DECIMAL(18,4)`, `reference_type` (PO / Issue / Adj).

### 2.8 Communication (Email / SMS / Notifications)

```
tbl_communication_settings    ← SMTP + SMS gateway config (password AES-encrypted)
tbl_communication_templates   ← per-event templates (welcome, lead_assigned, …)
tbl_communication_signatures  → tbl_users_login (sender signature)
tbl_communication_campaigns   → tbl_communication_templates
tbl_communication_logs        → tbl_communication_campaigns
tbl_notifications             → tbl_users_login (receiver)
```

**`tbl_communication_settings`** — single row. `encrypted_smtp_password`,
`host`, `port`, `smtp_user`, `sms_gateway_url`. Encryption uses
`CommunicationService::encrypt()` (AES-256-CBC with app key). Key is
`config('app_encryption_key')`.

**`tbl_communication_templates`** — `event_key` (e.g. `lead_assigned`,
`expense_approved`), `subject`, `body` (with `{{placeholder}}` tokens),
`channels` (EMAIL|SMS|BOTH). `is_event_wired()` checks this.

### 2.9 Website CMS (public site content)

```
tbl_cms_setup             ← site config (logo, favicon, SEO, socials)
tbl_cms_hero              ← hero banners
tbl_cms_abouts            ← about-section blocks
tbl_cms_services          ← service cards
tbl_cms_projects          → tbl_cms_services (optional)
tbl_cms_testimonials      ← testimonial carousel
tbl_cms_news              ← blog/news posts
tbl_cms_notices           ← public notices (PDF links)
tbl_cms_careers           ← job postings
tbl_cms_career_applications → tbl_cms_careers
tbl_cms_galleries         → tbl_cms_gallery_categories
tbl_cms_gallery_categories
tbl_cms_messages          ← contact-form submissions
tbl_cms_contacts_us       ← legacy contact submissions
tbl_cms_staffs            ← team-member profile cards
tbl_cms_home              ← deprecated (home is now composite query)
```

**`tbl_cms_setup`** (singleton, id=1) — the source for `website/includes/
site.php`'s `siteSetup()`. Drives every public page.

**`tbl_cms_hero`** has `is_active` + `position` — `siteRows()` orders by
`position, id` with `WHERE is_active = 1`. This is the only filtered query path
the public site uses.

### 2.10 Documents & Files

```
tbl_document_settings      ← header/footer templates, CSS
tbl_office_documents       → tbl_office_document_category
tbl_office_document_category
tbl_office_document_files  → tbl_office_documents
```

`tbl_document_settings` holds the print-template shell
(`documentShellStart()` / `documentShellEnd()` in `functions/documents.php`).
The CSS is inlined as a PHP heredoc (see PED TD-01).

### 2.11 Supporting / Infrastructure

| Table | Purpose |
|---|---|
| `tbl_calendar` | BS (Vikram Samvat) calendar: `nepali_year`, `month_code`, `eng_start_date`, `eng_end_date`. Seeded with ~200 years. Powers `adToBs()` / `bsMonthName()`. |
| `tbl_migrations` | `filename`, `executed_on`. The `artisan` registry table. Created first. |
| `tbl_office_spaces` | Generic resource — used by both meeting-hall booking and desk booking. |

---

## 3. Relationship Cardinality (Key Paths)

```
tbl_users_login (1)
   ├─< tbl_staff_attendances (daily log, 1 per day)
   ├─< tbl_staff_leave_applications (workflow)
   ├─< tbl_office_tasks (owned / assigned via task_assignees)
   ├─< tbl_office_tasks >─(assignees)─> tbl_users_login  (many-to-many via tbl_office_task_assignees)
   ├─< tbl_leads (owned)
   ├─< tbl_expense_claims
   ├─< tbl_notifications (receiver)
   ├─< tbl_audit_log (actor)
   └─< tbl_login_attempts

tbl_account_groups (1)
   ├─< tbl_account_sub_groups
       ├─< tbl_account_sub_terminals
       ├─< tbl_account_terminals
           ├─< tbl_ledger_particulars (debit/credit lines)
           ├─< tbl_journal_vouchers
           └─< tbl_ledger_closings (year-end)

tbl_fiscal_years (1)
   ├─< tbl_payment_vouchers
   ├─< tbl_receipt_vouchers
   ├─< tbl_contra_vouchers
   ├─< tbl_journal_vouchers
   ├─< tbl_ledger_particulars
   ├─< tbl_account_confirmation_letters
   └─< tbl_ledger_closings
```

### 3.1 The Double-Entry Flow (ACCOUNTS)

```
User submits a payment voucher (expense claim)
  │
  ├─ accountingParseVoucherLines() validates debit == credit ±0.01
  ├─ $db->transaction(fn →
  │       ├─ $db->insert('tbl_payment_vouchers', header)
  │       ├─ foreach line: $db->insert('tbl_ledger_particulars', debit|credit)
  │       ├─ accountingLogVoucher() → tbl_voucher_logs
  │       └─ auditLog('accounts', 'create', 'payment_voucher', $id, …)
  └─ rollback on any failure (atomic)
```

Every ledger line references **one** `tbl_account_terminals` (the GL account)
**and** one `tbl_fiscal_years` (the accounting period). Period status is
checked (`ENUM('Open','Closed')`) before allow-writing.

---

## 4. Seeding Strategy

| Seed migration | Table(s) | Contents |
|---|---|---|
| `seed-table-users_login` | `tbl_users_login` | 1 Super Admin (id=1) |
| `seed-table-account_groups` | `tbl_account_groups` | Assets, Liabilities, Equity, Revenue, Expense (5 rows) |
| `seed-table-account_sub_groups_services` | `tbl_account_sub_groups` | "Services" sub-group under Revenue |
| `seed-table-account_terminals_services` | `tbl_account_terminals` | Sales, Service Revenue, Accounts Receivable |
| `seed-table-calendar` | `tbl_calendar` | ~200 years of BS↔AD mappings |
| `seed-table-communication_settings` | `tbl_communication_settings` | Defaults: SMTP relay = smtp-relay.brevo.com:587 |
| `seed-table-fiscal_years` | `tbl_fiscal_years` | Current FY opened by default |
| `seed-table-account_sub_groups_services` | `tbl_office_leave_configs` | Annual, Casual, Sick, Maternity leave types |

> Seeds run **after** schema tables are created (ordered in `migrations.php`).
> Re-seeding is idempotent (`ON DUPLICATE KEY UPDATE` or `DELETE` + re-`INSERT`).

---

## 5. Indexing Strategy (perf notes)

MySQL auto-creates an index for every `PRIMARY KEY` and `FOREIGN KEY`.
Intentional secondary indexes, derived from `EXPLAIN`-worthy query paths:

| Table | Index | Used by |
|---|---|---|
| `tbl_payment_vouchers` | `uniq_fy_payment_voucher_no(fiscal_year_id, voucher_no)` | voucher lookup by FY |
| `tbl_payment_vouchers` | `idx_pv_date(voucher_date)`, `idx_pv_status(status)` | report filtering |
| `tbl_ledger_particulars` | `idx_lp_voucher_type(voucher_type, voucher_type_id)` | posting retrieval |
| `tbl_staff_attendances` | `idx_sa_date(date)`, `idx_sa_staff_id(staff_id, date)` | attendance report |
| `tbl_staff_leave_applications` | `idx_sla_staff_date(staff_id, from_date)` | leave balance |
| `tbl_leads` | `idx_leads_status(status)`, `idx_leads_assigned(assigned_to)` | sales funnel |
| `tbl_inv_stock_movements` | `idx_invm_item_date(item_id, created_at)` | stock history |
| `tbl_cms_news`, `tbl_cms_notices` | `idx_active_position(is_active, position)` | `siteRows()` public query |
| `tbl_notifications` | `idx_notif_receiver(receiver, viewed)` | notification client |

The `add-index-cms-active-columns` migration backfills indexes on every CMS
`is_active` column — the public site's `siteRows()` filters on it in every
query.

---

## 6. Reading This Document

- **Adding a column:** add a new `create-table-foo` migration? No — create
  `alter-table-foo-add-bar-column.php` and **append** to `migrations.php`.
- **Adding a table:** same pattern. Keep FK order correct (parents first).
- **Renaming:** never rename in place. Create new, migrate data, drop old
  (3-step migration). MySQL `RENAME TABLE` breaks `tbl_migrations` rollback.
- **Need the full SQL DDL?** Every table's `CREATE TABLE` is in its migration
  file under `database/migration/`. This document is the *narrative* map.

---

*This schema supports one deployable system. It is not normalized for a
university textbook — nullable columns, denormalized counts, and singleton
rows are deliberate trade-offs for operational simplicity on constrained
hosting. The integrity rules (FKs, DECIMAL money, ENUM statuses) are what
protect data quality week after week.*