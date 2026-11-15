# Darta & Chalani Module — Plan

> **Status:** P1 built (2026-10-16) — both registers, per-FY auto-numbering,
> dispose/file/cancel, reserve → dispatch, closed-FY and open-darta rules,
> BS date input. P2 built (2026-10-16) — landscape register print +
> single-entry slips, attachments (`tbl_correspondence_files`), dispatch-time
> dispose offer. P3 built (2026-10-16) — Devanagari digit toggle on print,
> assign/dispose notifications, dashboard tile. PDF/Word export (2026-10-25) —
> `?pdf=1` / `?word=1` on both registers and slips (same
> `includes/register_print.php` as the print views; ledger landscape, slip
> portrait, paper size from Document Setup; Word uses the document-setup
> letterhead pipeline). `composer check` green. §2
> decisions D1–D9 still await the office's confirmation; the proposed
> defaults were built.
> **Owner decision needed:** §2 decisions D1–D9 (defaults proposed; change any
> before P1 starts). D7–D9 need the office's answer, not ours.
> **Scope note:** This adds a new admin module + 4 tables (2 registers + 1
> number counter + 1 attachment store) + operation handlers. No changes to
> Accounts, Payroll, or Sales.

## 1. Goal

Digitise the two registers every Nepali office keeps by hand:

- **Darta (दर्ता / Inward)** — every incoming letter is registered with a
  दर्ता number, tracked to disposal (कारबाही) or filing (तामेली).
- **Chalani (चलानी / Outward)** — every outgoing letter gets a चलानी number,
  mode of dispatch, recipient, and copy recipients (बोधार्थ).

**v1 is done when** a registration clerk can log an incoming letter in 30
seconds (darta no. auto-assigned, BS date typed directly), mark its disposal,
take a chalani number for a reply *before* printing the letter, search both
registers by our number / the sender's reference number / date / party /
subject, and print either register in the traditional ledger layout. Super
Admin sees everything; a Service staffer without the grant sees nothing.

## 2. Decisions

Proposed defaults — confirm or override before P1. (Style: payroll plan §2.)

| # | Question | Proposed decision |
|---|---|---|
| D1 | Module or submodule? | **New module `correspondence`** with two submodules `darta`, `chalani`, sidebar section MY OFFICE. Separate from the existing `communication` module (Email/SMS, MARKETING), which is outreach, not official registers. |
| D2 | Who can register? | **Submodule grant = full access** to that register (create, edit, dispose, cancel, print). No new special permission and no extra per-user checks in v1. One special key (`manage_correspondence`) is reserved for P3 if the office wants disposed entries locked from clerks. |
| D3 | Numbering | **Per fiscal year, sequential, one counter per register.** Display format `D-12/2082-83` (darta) and `C-7/2082-83` (chalani), where `2082-83` is `tbl_fiscal_years.title`. Number allocated atomically from `tbl_correspondence_counters` (§4.3) inside the save transaction; never editable by hand; reprint shows the same number. |
| D4 | Digits | **Latin digits in DB** (voucher convention). Optional Devanagari rendering (`१२/२०८२-८३`) is a P3 display toggle, never stored. |
| D5 | Attachments | **Yes, P2** — scanned letter / dispatch copy in a `tbl_correspondence_files` table, same pattern as `tbl_expense_claim_files`, via `validateUpload()` + `storeUpload()`. PDF/JPG/PNG, 10 MB cap (existing `upload_max_bytes`). |
| D6 | Visibility | **Register-wide for grant holders** — everyone with the darta grant sees all darta entries (it is an office register, like the paper book on the desk). No per-user scoping in v1. |
| D7 | *(ask office)* Chalani order of work | **Proposed: number first.** Clerk saves a chalani as `Reserved` → gets the number → writes it on the letter → prints/sends → marks `Dispatched` (sets `dispatched_on`). Matches paper practice where the चलानी नं. is on the letter itself. |
| D8 | *(ask office)* Darta ↔ chalani cardinality | **Proposed: one darta → many chalanis** (acknowledgement, then final reply), each chalani optionally linked to **one** darta via `ref_darta_id`. If the office needs one chalani answering several dartas, swap `ref_darta_id` for a `tbl_correspondence_links(darta_id, chalani_id)` table — decide before P1 migrations. |
| D9 | *(ask office)* Back-entry of history | **Proposed: no.** Start from the current fiscal year's next free number; enter history manually only if demanded later. |

## 3. What already exists and gets reused

| Need | Existing piece |
|---|---|
| Fiscal year (BS bounds, Open/Closed) | `tbl_fiscal_years` (`title` like `2082-83`) |
| Per-FY numbering precedent | `accountingNextVoucherNo()` (`functions/accounting.php:61`) — shown for the format convention only; its read-last-row approach is **not** reused (see §4.3) |
| Raw parameterised SQL | `$db->execute($sql, $params)` (`classes/Database.php`) for the counter upsert |
| File uploads | `validateUpload()`, `storeUpload()` + `tbl_expense_claim_files` table shape |
| Notifications | `notifyUser()`, `notifyPermissionHolders()` (`functions/hr.php`) |
| Audit trail | `auditLog()` (`functions/helpers.php:691`) |
| Permission gates | `Auth::can()` module/submodule; `Auth::isSuperAdmin()`; `show_page.php` / `operation.php` front controllers |
| Print/PDF shell | `?print=1` / `?pdf=1` page flags (`admin/modules/payroll/payslip.php` pattern) |
| BS/AD dates | `adToBs()`, `bsToAd()`, `useBsDates()` (`functions/helpers.php`) — used for **input from P1**, not just print |
| Search UI pattern | existing list pages (filter row + `LIKE` with `$db->escapeLike()`) |

## 4. Data model

Three new tables, one migration each, created in this order (no circular
FKs, so no fix-up migration):

1. `create-table-correspondence_counters.php`
2. `create-table-darta_entries.php`
3. `create-table-chalani_entries.php`

Each has `$query` **and** `$rollbackQuery`, is appended to
`database/migration/migrations.php`, and is documented in `docs/Schema.md`.
P2 adds `create-table-correspondence_files.php`. No changes to existing tables.

### 4.1 `tbl_darta_entries` (Inward register)

| Column | Type | Notes |
|---|---|---|
| `id` | INT AI PK | |
| `fiscal_year_id` | INT → `tbl_fiscal_years` | `ON DELETE RESTRICT` |
| `darta_no` | INT | sequence within FY; display per D3 |
| `sender_ref_no` | VARCHAR(100) NULL | the incoming letter's own पत्र संख्या |
| `sender_chalani_no` | VARCHAR(100) NULL | the sender's चलानी नं. printed on the letter |
| `letter_date` | DATE | date on the letter (entered in BS when `useBsDates()`, stored AD) |
| `received_on` | DATETIME | when the office received it (defaults now) |
| `sender_type` | ENUM('Office','Person','Other') | |
| `sender_name` | VARCHAR(255) | person or office name |
| `sender_address` | VARCHAR(255) NULL | |
| `received_via` | ENUM('Hand','Post','Email','Courier','Other') | |
| `subject` | VARCHAR(500) | required |
| `summary` | TEXT NULL | gist / body notes |
| `priority` | ENUM('Normal','Urgent') DEFAULT 'Normal' | |
| `assigned_to` | INT NULL → `tbl_users_login` | handler; `SET NULL` |
| `status` | ENUM('Pending','In Progress','Disposed','Filed','Cancelled') DEFAULT 'Pending' | `Filed` = तामेली (closed, no action/reply needed); `Cancelled` replaces delete |
| `disposal_note` | TEXT NULL | कारबाही: what was done, or why filed/cancelled |
| `disposed_on` | DATETIME NULL | set on Disposed / Filed / Cancelled |
| `disposed_by` | INT NULL → `tbl_users_login` | `SET NULL` |
| `added_by`, `updated_by` | INT NULL | standard audit cols |
| `added_on`, `updated_on` | DATETIME | standard |

Constraints: `UNIQUE (fiscal_year_id, darta_no)` (backstop for §4.3);
`KEY (letter_date)`, `KEY (status)`, `KEY (sender_ref_no)`.

No `reply_chalani_id` column: replies are found with
`SELECT … FROM tbl_chalani_entries WHERE ref_darta_id = ?` (one source of
truth, supports several replies per darta — D8).

### 4.2 `tbl_chalani_entries` (Outward register)

| Column | Type | Notes |
|---|---|---|
| `id` | INT AI PK | |
| `fiscal_year_id` | INT → `tbl_fiscal_years` | `ON DELETE RESTRICT` |
| `chalani_no` | INT | sequence within FY; display per D3 |
| `letter_date` | DATE | date on the dispatched letter (BS input, AD stored) |
| `status` | ENUM('Reserved','Dispatched','Cancelled') DEFAULT 'Reserved' | D7; `Cancelled` keeps the number visible in the register instead of a gap |
| `dispatched_on` | DATETIME NULL | set when marked Dispatched |
| `recipient_type` | ENUM('Office','Person','Other') | |
| `recipient_name` | VARCHAR(255) | |
| `recipient_address` | VARCHAR(255) NULL | |
| `cc_recipients` | TEXT NULL | बोधार्थ — one recipient per line, printed on the slip |
| `dispatch_mode` | ENUM('Hand','Post','Email','Courier','Other') | |
| `subject` | VARCHAR(500) | required |
| `description` | TEXT NULL | body / enclosures |
| `sent_by` | INT NULL → `tbl_users_login` | `SET NULL` |
| `ref_darta_id` | INT NULL → `tbl_darta_entries` | `SET NULL`; the darta this replies to (D8) |
| `added_by`, `updated_by` | INT NULL | standard |
| `added_on`, `updated_on` | DATETIME | standard |

Constraints: `UNIQUE (fiscal_year_id, chalani_no)`; `KEY (letter_date)`,
`KEY (status)`, `KEY (ref_darta_id)`.

`cc_recipients` is free text in v1; promote to a child table only if the
office needs to search by copy recipient.

### 4.3 Number allocation

**Why not `MAX()+1` with retry:** under InnoDB's default REPEATABLE READ, a
retried `SELECT MAX()` inside the same transaction reads the same snapshot and
returns the same number, so the retry collides again. `MAX() … FOR UPDATE`
fixes visibility but gap-locks an empty fiscal year and deadlocks two
first-of-year saves. And `Database::transaction()` rolls back the whole unit
on any exception, so a retry would have to be caught inside the callable.

**Instead — a counter table, atomic upsert:**

`tbl_correspondence_counters`

| Column | Type | Notes |
|---|---|---|
| `register` | ENUM('darta','chalani') | |
| `fiscal_year_id` | INT → `tbl_fiscal_years` | `ON DELETE RESTRICT` |
| `last_no` | INT NOT NULL | |

`PRIMARY KEY (register, fiscal_year_id)`. No AUTO_INCREMENT (so
`LAST_INSERT_ID()` is only ever set by the expression below).

```sql
INSERT INTO tbl_correspondence_counters (register, fiscal_year_id, last_no)
VALUES (?, ?, LAST_INSERT_ID(1))
ON DUPLICATE KEY UPDATE last_no = LAST_INSERT_ID(last_no + 1);
SELECT LAST_INSERT_ID() AS no;
```

The upsert row-locks the counter until the caller's transaction commits, so
concurrent saves serialise on that one row and get distinct numbers with no
retry. If the save rolls back, the number is released too (no gap).
`UNIQUE(fiscal_year_id, …_no)` on the register tables stays as a backstop.

`functions/correspondence.php` (new, **snake_case** per RULES §1.3):

- `correspondence_display_no(string $register, int $seq, string $fyTitle): string`
  — pure: `D-12/2082-83` / `C-7/2082-83`. Unit-tested.
- `correspondence_allocate_no(Database $db, string $register, int $fyId): int`
  — runs the upsert above via `$db->execute()` + `$db->selectOne()`. Must be
  called inside the caller's `$db->transaction()`; throws on unknown register.
  DB-dependent test skips without a DB (TEST-03).

## 5. Permissions & RBAC

- New module key `correspondence` in `$modules` / `$navBars` /
  `$subNavBars` (`darta` → 'Darta (Inward)', `chalani` → 'Chalani (Outward)'),
  `$navSidebarSections` → `MY OFFICE`, pages list updated in `$pages`
  (`admin/includes/varriables.php`).
- `Auth::can('correspondence', 'darta'|'chalani')` gates both read
  (`show_page.php`) and write (`operation.php`). Per D2 there are **no extra
  per-user checks inside pages** in v1. Staff-type Admin / Super Admin pass
  automatically.
- Sidebar shows the module only on grant (existing `mainSideBar.php` logic —
  nothing to build).

## 6. Pages & UX

Module folder `admin/modules/correspondence/` per RULES §4.1: `home.php`
(landing: counts of pending darta / reserved chalani, links to both
registers), page views, and `operation/<page>.php` write handlers (PRG + CSRF +
`e()` everywhere).

| Page | File | What it does |
|---|---|---|
| Home | `home.php` | Module landing per RULES §4.1. |
| Darta register | `darta.php` | Filterable table (FY, status, month, free text over sender, subject, `sender_ref_no`). Row: no., sender's ref no., letter date, sender, subject, status badge, assigned to, replies (linked chalani nos.), actions (View/Edit — read-only for Disposed/Filed/Cancelled —, Dispose/File, Cancel, Print *(P2)*). "Add Darta" opens the side drawer form. |
| Chalani register | `chalani.php` | Same shape. "Reserve number" creates a `Reserved` row; "Mark dispatched" sets date + mode. "Link to darta" select: Pending / In Progress darta of the current FY **plus any non-Cancelled darta already linked to the entry** — so dispose → "reserve reply चलानी number now" pre-fills the just-disposed darta and the link stays editable (a Cancelled darta can never be linked, and is never offered). |
| Darta detail | — | No inner page in v1: detail is the drawer + print view (avoids an `$innerPageGrants` entry). |
| Print register | `?print=1` on each register page **(P2 — built)** | Traditional landscape ledger: column headers in English + Nepali (दर्ता नं., पत्र संख्या, मिति, प्रेषक, विषय, कारबाही), BS/AD date pair, one FY per printout, footer with entry count. Cancelled rows print struck through so the number sequence has no gaps. `?pdf=1` exports the same page as a PDF (landscape), `?word=1` as a `.docx` (document-setup letterhead/meta, **2026-10-25**). |
| Print single entry | `?entry=ID&print=1` **(P2 — built)** | Slip for a single darta/chalani (office stamp area; बोधार्थ list on chalani). Same views export via `&pdf=1` (portrait PDF) / `&word=1` (`.docx`, **2026-10-25**); row + toolbar buttons on both registers. |

Forms (drawer): required = party name, subject, letter date; everything else
optional. Date inputs accept BS when `useBsDates()` is on and convert with
`bsToAd()` before save (from P1). Disposal is a separate small form: outcome
(Disposed / Filed) + note + optional "reserve reply chalani now" which opens
the chalani form pre-linked.

## 7. Notifications & audit (P1-light, P3-full)

- **P1:** `auditLog('correspondence', 'create'|'update'|'dispose'|'cancel', 'darta'|'chalani', id, old, new)`.
- **P3 ✅ (built 2026-10-16):** on `assigned_to` set/changed → `notifyUser()`
  (never to the actor); on dispose (manual or via the dispatch offer) →
  `notifyUser()` to the registrar (`added_by`) and handler (`assigned_to`),
  minus the actor (`correspondence_dispose_recipients()`); dashboard tile
  "Darta pending disposal" for grant holders (`Auth::can('correspondence',
  'darta')`, `dashboard/home.php`).

## 8. Phases & acceptance criteria

### P1 — registers (core)
- 3 migrations (counter + 2 registers) registered + Schema.md updated.
- `home.php`, `darta.php`, `chalani.php` + operation handlers: create, edit
  (while not Disposed/Filed/Cancelled), dispose/file, cancel, reserve →
  dispatch, auto-numbering. **No delete** — Cancel replaces it.
- BS date input on all date fields.
- Filters: fiscal year, status, month, free text (party / subject / sender's
  ref no.).
- `composer check` green; ≥ 4 unit tests: `correspondence_display_no` (happy +
  edge) and `correspondence_allocate_no` (first number of a FY = 1;
  sequential within FY; independent across FYs and registers — DB tests skip
  without a DB, TEST-03).

**Accept:** register 10 letters under two fiscal years; numbers increment per
FY and per register, never repeat; two simultaneous saves (two tabs) produce
two distinct numbers; a cancelled entry keeps its number and shows in the
register; a chalani number can be reserved before dispatch.

### P2 — print + attachments + linkage ✅ (built 2026-10-16)
- Landscape register printout (`?print=1`) matching §6; single-entry slip.
- PDF / Word export of those two views (`?pdf=1` inline PDF, `?word=1`
  editable `.docx` — added 2026-10-25; toolbar + row buttons on both
  registers, digit-toggle link hidden in exports).
- Attachments on both registers via `tbl_correspondence_files`
  (`register`, `entry_id`, `file_name`, `file_location`, audit cols) —
  expense-claim-files pattern.
- Dispatching a chalani linked to a darta offers to mark the darta Disposed
  (not automatic — an acknowledgement reply should not close the darta).

**Accept:** print a full FY register on A4 landscape and export the same
register as PDF/Word; attach a PDF to a darta
entry; dispatch a reply chalani, mark the darta Disposed, and see the link
on both rows (darta shows its reply chalani nos.).

### P3 — polish ✅ (built 2026-10-16)
- Devanagari digit toggle on print (`?nep=1`, screen-only switch link — Latin
  stays in the DB, plan §10 review note 3).
- Dashboard tile + notifications (§7).
- Optional `manage_correspondence` special — **deliberately not built** (§10
  review note 2; only if the office asks).

## 9. Out of scope (v1)

- Scanned-document OCR / digitising paper history (see D9).
- SMS dispatch to recipients.
- Multi-office / branch registers (single-office product today).
- Public portal for citizens/students to track a darta number.
- Auto-filing received emails from IMAP (P4 idea only).

## 10. Review log

### Self-review (2026-10-15, before any code)

1. **Fiscal year mid-year deploy** — `tbl_fiscal_years` may have no Open year
   (vouchers have the same dependency). *Fix:* register page falls back to
   the FY containing today; if none, save is blocked with a clear flash
   pointing at Setup → Fiscal Years. Same behaviour as Accounts.
2. **Special permission temptation** — adding `manage_correspondence` now
   would split "who can type a letter" from "who can see the register",
   which no Nepali office keeps separate on paper. *Fix:* submodule grant
   only in v1; special key reserved (§8 P3) — documented, not built.
3. **Nepali digits stored** would break sort/search. *Fix:* Latin only in DB,
   Devanagari is a render-time toggle (D4).
4. **Module sprawl** — if the client wants fewer sidebar items, D1 flips to
   `my_office/darta` + `my_office/chalani` with zero data-model change.

### Second review (2026-10-15) — corrections applied

1. **Number allocation was unsafe.** `MAX()+1` + one duplicate-key retry
   re-reads the same REPEATABLE READ snapshot and collides again; the
   transaction wrapper rolls back on the exception anyway. *Fix:* counter
   table with `LAST_INSERT_ID()` upsert (§4.3).
2. **camelCase function names** (`correspondenceDisplayNo`,
   `correspondenceAllocateNo`) violated RULES §1.3 while the self-review
   marked snake_case ✓. *Fix:* renamed to `correspondence_display_no` /
   `correspondence_allocate_no`.
3. **Circular FK** (`reply_chalani_id` ↔ `ref_darta_id`) duplicated one fact
   and needed a fix-up migration. *Fix:* dropped `reply_chalani_id`; replies
   are derived from `ref_darta_id` (also allows several replies per darta).
4. **Missing register columns.** Added `sender_ref_no` / `sender_chalani_no`
   (the incoming letter's own पत्र संख्या / चलानी नं. — the most-searched
   field in a paper darta book), `cc_recipients` (बोधार्थ; replaces
   `copies INT`), and `Filed` (तामेली) status.
5. **Chalani workflow order.** Paper practice takes the number before the
   letter is printed. *Fix:* `Reserved → Dispatched` status (D7).
6. **BS dates deferred to P3** although clerks type BS dates from day one.
   *Fix:* BS input in P1 (§6).
7. **Contradictions.** "No extra checks" (D2) vs "disposal fields editable only
   by disposer" — resolved in favour of D2. Display format unified on D3
   (`D-12/2082-83`).
8. **Delete vs paper register.** Super-admin delete left number gaps. *Fix:*
   `Cancelled` status in v1, no delete.
9. **`KEY (sender_name(64))` "for search"** — useless for `LIKE '%x%'`.
   *Fix:* dropped; indexed `sender_ref_no` (exact lookups) instead.
10. **`home.php` missing** from the module file list (RULES §4.1). *Fix:*
    added (§6, §8 P1).
11. **Auto-dispose on link** would close a darta on a mere acknowledgement.
    *Fix:* linking offers to dispose; it does not force it (§8 P2).
