<?php

/**
 * SB-Tech — accounting service helpers (fiscal years, chart of accounts,
 * voucher posting, ledger, expense claims). Loaded by config/bootstrap.php
 * after functions/helpers.php.
 *
 * Business rules live here so module pages stay thin (X-10). All writes go
 * through Database::* prepared statements.
 */

/** Voucher-type registry: key → table, posting label, number prefix, icon. */
function accountingVoucherConfig(): array
{
    return [
        'journal'  => ['table' => 'tbl_journal_vouchers',   'type' => 'Journal',  'label' => 'Journal Voucher',  'prefix' => 'JV',  'fa' => 'fa-book'],
        'receipt'  => ['table' => 'tbl_receipt_vouchers',   'type' => 'Receipt',  'label' => 'Receipt Voucher',  'prefix' => 'RV',  'fa' => 'fa-hand-holding-usd'],
        'payment'  => ['table' => 'tbl_payment_vouchers',   'type' => 'Payment',  'label' => 'Payment Voucher',  'prefix' => 'PV',  'fa' => 'fa-money-check-alt'],
        'contra'   => ['table' => 'tbl_contra_vouchers',    'type' => 'Contra',   'label' => 'Contra Voucher',   'prefix' => 'CV',  'fa' => 'fa-exchange-alt'],
        'purchase' => ['table' => 'tbl_purchase_vouchers',  'type' => 'Purchase', 'label' => 'Purchase Voucher', 'prefix' => 'PUV', 'fa' => 'fa-shopping-cart'],
        'sales'    => ['table' => 'tbl_sales_vouchers',     'type' => 'Sales',    'label' => 'Sales Voucher',    'prefix' => 'SV',  'fa' => 'fa-file-invoice'],
    ];
}

/**
 * Resolve the Open fiscal year that contains $date (Y-m-d).
 * Returns the FY row, or null when the date is outside every open FY
 * (AC-FIN-01.2: closed FYs are read-only for new postings).
 */
function accountingFiscalForDate(string $date): ?array
{
    $fy = Database::instance()->selectOne(
        'SELECT * FROM `tbl_fiscal_years`
         WHERE `closing` = ? AND `starting_date` <= ? AND `ending_date` >= ?
         ORDER BY `id` DESC LIMIT 1',
        ['Open', $date, $date]
    );
    return $fy ?: null;
}

/** The currently-open FY covering today (fallback: any Open FY). */
function accountingCurrentOpenFy(): ?array
{
    $db = Database::instance();
    $fy = $db->selectOne(
        'SELECT * FROM `tbl_fiscal_years` WHERE `closing` = ?
         AND `starting_date` <= CURDATE() AND `ending_date` >= CURDATE()
         ORDER BY `id` DESC LIMIT 1',
        ['Open']
    );
    if (!$fy) {
        $fy = $db->selectOne(
            'SELECT * FROM `tbl_fiscal_years` WHERE `closing` = ? ORDER BY `id` DESC LIMIT 1',
            ['Open']
        );
    }
    return $fy ?: null;
}

/** Next per-FY voucher number, e.g. JV-0007 (AC-FIN-03.1, AC-FIN-03.4). */
function accountingNextVoucherNo(Database $db, string $typeKey, int $fyId): string
{
    $cfg = accountingVoucherConfig()[$typeKey] ?? null;
    if (!$cfg) {
        throw new InvalidArgumentException('Unknown voucher type: ' . $typeKey);
    }
    $row = $db->selectOne(
        'SELECT `voucher_no` FROM `' . $cfg['table'] . '` WHERE `fiscal_year_id` = ? ORDER BY `id` DESC LIMIT 1',
        [$fyId]
    );
    $seq = 1;
    if ($row && preg_match('/(\d+)\s*$/', (string) $row['voucher_no'], $m)) {
        $seq = (int) $m[1] + 1;
    }
    return $cfg['prefix'] . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
}

/** Fetch a voucher row for a type key + id, or null. */
function accountingVoucherById(Database $db, string $typeKey, int $id): ?array
{
    $cfg = accountingVoucherConfig()[$typeKey] ?? null;
    if (!$cfg || $id < 1) {
        return null;
    }
    $v = $db->selectOne(
        'SELECT v.*, u.fullname AS added_by_name FROM `' . $cfg['table'] . '` v
         LEFT JOIN `tbl_users_login` u ON u.id = v.added_by WHERE v.id = ?',
        [$id]
    );
    return $v ?: null;
}

/** Chart-of-account tree grouped for <select> optgroups (4-level COA). */
function accountingTerminalOptions(): array
{
    $rows = Database::instance()->select(
        'SELECT t.id, t.title, sg.title AS subgroup_title, g.title AS group_title
         FROM `tbl_account_terminals` t
         JOIN `tbl_account_sub_groups` sg ON sg.id = t.account_subgroup_id
         JOIN `tbl_account_groups` g ON g.id = sg.group_id
         ORDER BY g.position, g.id, sg.position, sg.id, t.position, t.title'
    );
    $tree = [];
    foreach ($rows as $r) {
        $tree[$r['group_title']][$r['subgroup_title']][] = $r;
    }
    return $tree;
}

/** Terminal row with its group/subgroup ids + titles (for posting denorm). */
function accountingTerminalMeta(Database $db, int $terminalId): ?array
{
    return $db->selectOne(
        'SELECT t.*, sg.id AS subgroup_id, sg.title AS subgroup_title,
                g.id AS group_id, g.title AS group_title
         FROM `tbl_account_terminals` t
         LEFT JOIN `tbl_account_sub_groups` sg ON sg.id = t.account_subgroup_id
         LEFT JOIN `tbl_account_groups` g ON g.id = sg.group_id
         WHERE t.id = ?',
        [$terminalId]
    ) ?: null;
}

/** First terminal matching a title (used for cash/bank defaults). */
function accountingTerminalByTitle(string $title): ?array
{
    $row = Database::instance()->selectOne(
        'SELECT * FROM `tbl_account_terminals` WHERE `title` = ? ORDER BY `id` LIMIT 1',
        [$title]
    );
    return $row ?: null;
}

/**
 * Parse + validate posted voucher lines.
 * Returns ['ok'=>bool, 'error'=>string, 'lines'=>array, 'debit'=>float, 'credit'=>float].
 * Blocked: empty rows, negative amounts, no lines, unbalanced totals
 * (AC-FIN-03.2: debits must equal credits before save).
 */
function accountingParseVoucherLines(array $post): array
{
    $terminals = (array) ($post['account_terminal_id'] ?? []);
    $debits    = (array) ($post['debit'] ?? []);
    $credits   = (array) ($post['credit'] ?? []);
    $remarks   = (array) ($post['line_remarks'] ?? []);

    $lines = [];
    $totalDebit = 0.0;
    $totalCredit = 0.0;
    $count = max(count($terminals), count($debits), count($credits));

    for ($i = 0; $i < $count; $i++) {
        $terminalId = (int) ($terminals[$i] ?? 0);
        $d = (float) ($debits[$i] ?? 0);
        $c = (float) ($credits[$i] ?? 0);
        if (!$terminalId) {
            continue;
        }
        if ($d < 0 || $c < 0) {
            return ['ok' => false, 'error' => 'Negative amounts are not allowed.'];
        }
        if ($d <= 0 && $c <= 0) {
            continue; // blank row
        }
        $totalDebit += $d;
        $totalCredit += $c;
        $lines[] = [
            'account_terminal_id' => $terminalId,
            'debit'               => round($d, 4),
            'credit'              => round($c, 4),
            'remarks'             => trim((string) ($remarks[$i] ?? '')) ?: null,
        ];
    }

    if (!$lines) {
        return ['ok' => false, 'error' => 'At least one voucher line is required.'];
    }
    if (abs($totalDebit - $totalCredit) > 0.01) {
        return [
            'ok'     => false,
            'error'  => 'Voucher does not balance — debits ' . number_format($totalDebit, 2)
                . ' vs credits ' . number_format($totalCredit, 2) . '.',
            'lines'  => $lines,
            'debit'  => $totalDebit,
            'credit' => $totalCredit,
        ];
    }
    return [
        'ok'     => true,
        'lines'  => $lines,
        'debit'  => round($totalDebit, 4),
        'credit' => round($totalCredit, 4),
    ];
}

/**
 * Replace a voucher's ledger particulars (delete old + insert new), each
 * row carrying denormalized account titles for report speed (AC-FIN-03.1).
 */
function accountingReplaceLines(
    Database $db,
    string $voucherType,
    int $voucherId,
    int $fyId,
    string $date,
    array $lines,
    int $me,
    string $status
): void {
    $db->delete('tbl_ledger_particulars', '`voucher_type` = ? AND `voucher_type_id` = ?', [$voucherType, $voucherId]);
    foreach ($lines as $line) {
        $meta = accountingTerminalMeta($db, (int) $line['account_terminal_id']);
        if (!$meta) {
            continue;
        }
        $db->insert('tbl_ledger_particulars', [
            'voucher_type_id'        => $voucherId,
            'voucher_type'           => $voucherType,
            'voucher_status'         => $status,
            'particulars_date'       => $date,
            'fiscal_year_id'         => $fyId,
            'account_group_id'       => (int) $meta['group_id'],
            'account_subgroup_id'    => (int) $meta['subgroup_id'],
            'account_terminal_id'    => (int) $meta['id'],
            'account_group_title'    => (string) $meta['group_title'],
            'account_subgroup_title' => (string) $meta['subgroup_title'],
            'account_terminal_title' => (string) $meta['title'],
            'debit'                  => (float) $line['debit'],
            'credit'                 => (float) $line['credit'],
            'remarks'                => $line['remarks'] ?? null,
            'added_by'               => $me,
            'updated_by'             => $me,
        ]);
    }
}

/** Flip a voucher's ledger lines between Pending and Approved. */
function accountingSetLineStatus(Database $db, string $voucherType, int $voucherId, string $status): void
{
    $db->execute(
        'UPDATE `tbl_ledger_particulars` SET `voucher_status` = ?
         WHERE `voucher_type` = ? AND `voucher_type_id` = ?',
        [$status, $voucherType, $voucherId]
    );
}

/** Ledger particulars for a voucher (drill-down / detail view). */
function accountingParticularsFor(Database $db, string $voucherType, int $voucherId): array
{
    return $db->select(
        'SELECT * FROM `tbl_ledger_particulars`
         WHERE `voucher_type` = ? AND `voucher_type_id` = ?
         ORDER BY `id`',
        [$voucherType, $voucherId]
    );
}

/** Resolve a voucher number from a ledger-particular row (6-way lookup). */
function accountingVoucherNoFor(Database $db, string $voucherType, int $voucherTypeId): ?string
{
    $map = [
        'Journal'  => 'tbl_journal_vouchers',
        'Receipt'  => 'tbl_receipt_vouchers',
        'Payment'  => 'tbl_payment_vouchers',
        'Contra'   => 'tbl_contra_vouchers',
        'Purchase' => 'tbl_purchase_vouchers',
        'Sales'    => 'tbl_sales_vouchers',
    ];
    if (!isset($map[$voucherType])) {
        return null;
    }
    $row = $db->selectOne(
        'SELECT `voucher_no` FROM `' . $map[$voucherType] . '` WHERE `id` = ?',
        [$voucherTypeId]
    );
    return $row ? $row['voucher_no'] : null;
}

/**
 * Ledger lines for one terminal (AC-FIN-04.1). $extraWhere is an array of
 * SQL fragments; $params must match ? placeholders in order.
 */
function accountingLedgerLines(
    Database $db,
    int $terminalId,
    string $from,
    string $to,
    array $extraWhere = [],
    array $params = []
): array {
    $where = array_merge(
        ['lp.account_terminal_id = ?', 'lp.particulars_date >= ?', 'lp.particulars_date <= ?'],
        $extraWhere
    );
    $allParams = array_merge([$terminalId, $from, $to], $params);
    return $db->select(
        'SELECT lp.*,
                COALESCE(jv.voucher_no, rv.voucher_no, pv.voucher_no, cv.voucher_no, puv.voucher_no, sv.voucher_no) AS voucher_no,
                COALESCE(jv.narration, rv.narration, pv.narration, cv.narration, puv.narration, sv.narration) AS narration
         FROM `tbl_ledger_particulars` lp
         LEFT JOIN `tbl_journal_vouchers`  jv  ON lp.voucher_type = \'Journal\'  AND jv.id  = lp.voucher_type_id
         LEFT JOIN `tbl_receipt_vouchers`  rv  ON lp.voucher_type = \'Receipt\'  AND rv.id  = lp.voucher_type_id
         LEFT JOIN `tbl_payment_vouchers`  pv  ON lp.voucher_type = \'Payment\'  AND pv.id  = lp.voucher_type_id
         LEFT JOIN `tbl_contra_vouchers`   cv  ON lp.voucher_type = \'Contra\'   AND cv.id  = lp.voucher_type_id
         LEFT JOIN `tbl_purchase_vouchers` puv ON lp.voucher_type = \'Purchase\' AND puv.id = lp.voucher_type_id
         LEFT JOIN `tbl_sales_vouchers`    sv  ON lp.voucher_type = \'Sales\'    AND sv.id  = lp.voucher_type_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY lp.particulars_date, lp.id',
        $allParams
    );
}

/** Opening balance (debit − credit) for a terminal before $from, Approved only. */
function accountingOpeningBalance(Database $db, int $terminalId, string $from): float
{
    $row = $db->selectOne(
        'SELECT COALESCE(SUM(lp.debit), 0) AS d, COALESCE(SUM(lp.credit), 0) AS c
         FROM `tbl_ledger_particulars` lp
         WHERE lp.account_terminal_id = ? AND lp.particulars_date < ?
           AND lp.voucher_status = ?',
        [$terminalId, $from, 'Approved']
    );
    return round((float) ($row['d'] ?? 0) - (float) ($row['c'] ?? 0), 4);
}

/** Audit trail entry for voucher actions (AC-FIN-09.1). */
function accountingLogVoucher(
    Database $db,
    string $typeKey,
    int $id,
    string $voucherNo,
    string $action,
    $old,
    $new,
    int $me
): void {
    $db->insert('tbl_voucher_logs', [
        'voucher_type'   => $typeKey,
        'voucher_type_id'=> $id,
        'voucher_no'     => $voucherNo,
        'action'         => $action,
        'old_data'       => $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE),
        'new_data'       => $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE),
        'added_by'       => $me,
    ]);
}

/**
 * Auto-create a Pending Payment voucher for an approved expense claim
 * (AC-FIN-07.1): debit Staff Payable, credit Cash in Hand. Returns the id.
 */
function accountingPaymentVoucherForClaim(Database $db, array $claim, int $me): int
{
    $fy = accountingFiscalForDate($claim['expense_date'] ?: date('Y-m-d'));
    if (!$fy) {
        throw new RuntimeException('No open fiscal year covers the claim expense date.');
    }
    $date = $claim['expense_date'] ?: date('Y-m-d');
    $staff = $db->selectOne('SELECT `fullname` FROM `tbl_users_login` WHERE `id` = ?', [(int) $claim['staff_id']]);
    $narration = 'Expense claim ' . $claim['claim_no']
        . ($claim['category'] ? ' — ' . $claim['category'] : '')
        . ' — ' . ($staff['fullname'] ?? 'staff');
    $amount = (float) $claim['amount'];

    $debitTerminal = accountingTerminalByTitle('Staff Payable');
    $creditTerminal = accountingTerminalByTitle('Cash in Hand');
    if (!$debitTerminal || !$creditTerminal) {
        throw new RuntimeException('Chart of accounts is missing "Staff Payable" or "Cash in Hand".');
    }

    $voucherNo = accountingNextVoucherNo($db, 'payment', (int) $fy['id']);
    $voucherId = $db->insert('tbl_payment_vouchers', [
        'fiscal_year_id'    => (int) $fy['id'],
        'voucher_no'        => $voucherNo,
        'voucher_date'      => $date,
        'narration'         => $narration,
        'description'       => $narration,
        'amount'            => $amount,
        'total_amount'      => $amount,
        'entry_type'        => 'Auto',
        'status'            => 'Pending',
        'added_by'          => $me,
        'updated_by'        => $me,
    ]);
    accountingReplaceLines($db, 'Payment', $voucherId, (int) $fy['id'], $date, [
        ['account_terminal_id' => (int) $debitTerminal['id'], 'debit' => $amount, 'credit' => 0.0, 'remarks' => 'Claim ' . $claim['claim_no']],
        ['account_terminal_id' => (int) $creditTerminal['id'], 'debit' => 0.0, 'credit' => $amount, 'remarks' => 'Claim ' . $claim['claim_no']],
    ], $me, 'Pending');
    accountingLogVoucher($db, 'payment', $voucherId, $voucherNo, 'Create', null, ['source' => 'expense_claim', 'claim_no' => $claim['claim_no']], $me);
    return $voucherId;
}
