<?php
/**
 * SB-Tech — Accounts / Posting operations (US-FIN-03, US-FIN-07, US-FIN-09).
 * save_voucher / approve_voucher / unapprove_voucher / delete_voucher /
 * export_vouchers. Balancing enforced server-side (AC-FIN-03.2); approvals
 * gated by approve_vouchers (AC-FIN-03.3); every action is audit-logged.
 */
$db = Database::instance();
$me = (int) Auth::id();
$action = (string) ($_POST['action'] ?? '');

$typeKey = (string) ($_POST['type'] ?? '');
$cfg = accountingVoucherConfig()[$typeKey] ?? null;
if (!$cfg) {
    setFlash('error', 'Unknown voucher type.');
    redirect(pageUrl('accounts', 'postings'));
}
$back = pageUrl('accounts', 'postings') . '&tab=' . urlencode($typeKey);
$canApprove = Auth::isSuperAdmin() || Auth::hasSpecial('approve_vouchers');

try {
    if ($action === 'save_voucher') {
        $id = (int) ($_POST['id'] ?? 0);
        $dateRaw = trim((string) ($_POST['date'] ?? ''));
        if ($dateRaw === '' || !strtotime($dateRaw)) {
            setFlash('error', 'A valid voucher date is required.');
            redirect($back);
        }
        if ($dateRaw > date('Y-m-d')) {
            setFlash('error', 'You cannot create a voucher for a future date.');
            redirect($back);
        }
        $fy = accountingFiscalForDate($dateRaw);
        if (!$fy) {
            setFlash('error', 'No open fiscal year covers ' . $dateRaw . ' — closed fiscal years are read-only.');
            redirect($back);
        }
        if ($fy['closing'] !== 'Open') {
            setFlash('error', 'The fiscal year for this date is closed.');
            redirect($back);
        }

        $parsed = accountingParseVoucherLines($_POST);
        if (!$parsed['ok']) {
            setFlash('error', $parsed['error']);
            redirect($back . ($id ? '&edit_id=' . $id : ''));
        }

        // Edit guard: only Pending vouchers can be edited (AC-FIN-03.4).
        $old = null;
        if ($id) {
            $old = accountingVoucherById($db, $typeKey, $id);
            if (!$old) {
                setFlash('error', 'Voucher not found.');
                redirect($back);
            }
            if ($old['status'] !== 'Pending') {
                setFlash('error', 'Approved vouchers cannot be edited — un-approve first or post a correcting entry.');
                redirect($back);
            }
            // Keep the voucher no when date + FY unchanged; else renumber.
            $sameFy = (int) $old['fiscal_year_id'] === (int) $fy['id'];
            $sameDate = (string) $old['voucher_date'] === $dateRaw;
            $voucherNo = ($sameFy && $sameDate) ? $old['voucher_no'] : accountingNextVoucherNo($db, $typeKey, (int) $fy['id']);
        } else {
            $voucherNo = accountingNextVoucherNo($db, $typeKey, (int) $fy['id']);
        }

        $narration = trim((string) ($_POST['narration'] ?? '')) ?: null;
        $reference = trim((string) ($_POST['reference_no'] ?? '')) ?: null;
        $amount = $parsed['debit'];

        $data = [
            'fiscal_year_id' => (int) $fy['id'],
            'voucher_no'     => $voucherNo,
            'voucher_date'   => $dateRaw,
            'reference_no'   => $reference,
            'narration'      => $narration,
            'description'    => $narration,
            'amount'         => $amount,
            'discount_amount'=> 0.0000,
            'tax_amount'     => 0.0000,
            'total_amount'   => $amount,
            'currency_code'  => 'NPR',
            'fx_rate'        => 1.00000000,
            'base_currency_code' => 'NPR',
            'entry_type'     => 'Manual',
            'status'         => 'Pending',
            'updated_by'     => $me,
        ];

        // Optional attachment (jpg/jpeg/png/pdf).
        $fileName = $old['file_name'] ?? null;
        if (!empty($_FILES['file']['name'])) {
            $up = validateUpload($_FILES['file'], ['jpg', 'jpeg', 'png', 'pdf']);
            if (!$up['ok']) {
                setFlash('error', $up['message']);
                redirect($back . ($id ? '&edit_id=' . $id : ''));
            }
            $loc = storeUpload($_FILES['file'], 'vouchers', $up['extension']);
            if ($loc) {
                $fileName = $loc;
            }
        }
        $data['file_name'] = $fileName;

        $voucherId = $id;
        if ($id) {
            $db->update($cfg['table'], $data, '`id` = ?', [$id]);
        } else {
            $voucherId = $db->insert($cfg['table'], array_merge($data, ['added_by' => $me]));
        }

        accountingReplaceLines($db, $cfg['type'], $voucherId, (int) $fy['id'], $dateRaw, $parsed['lines'], $me, 'Pending');
        accountingLogVoucher($db, $typeKey, $voucherId, $voucherNo, $id ? 'Update' : 'Create', $old, $data, $me);

        setFlash('success', ($id ? 'Voucher updated' : 'Voucher created') . ' — ' . $voucherNo . ' (Pending).');
        redirect($back);
    }

    if ($action === 'approve_voucher') {
        if (!$canApprove) {
            http_response_code(403);
            die('Access denied: you need the approve_vouchers permission.');
        }
        $id = (int) ($_POST['id'] ?? 0);
        $v = accountingVoucherById($db, $typeKey, $id);
        if (!$v) {
            setFlash('error', 'Voucher not found.');
            redirect($back);
        }
        if ($v['status'] !== 'Pending') {
            setFlash('error', 'Only Pending vouchers can be approved.');
            redirect($back);
        }
        $db->update($cfg['table'], ['status' => 'Approved', 'approved_by' => $me, 'updated_by' => $me], '`id` = ?', [$id]);
        accountingSetLineStatus($db, $cfg['type'], $id, 'Approved');
        accountingLogVoucher($db, $typeKey, $id, $v['voucher_no'], 'Approve', ['status' => 'Pending'], ['status' => 'Approved', 'approved_by' => $me], $me);

        // AC-FIN-07.2: approving the payment voucher of an expense claim
        // flips the claim to Paid.
        if ($typeKey === 'payment') {
            $db->execute(
                'UPDATE `tbl_expense_claims` SET `status` = ? WHERE `payment_voucher_id` = ? AND `status` IN (?, ?)',
                ['Paid', $id, 'Approved', 'Submitted']
            );
        }
        setFlash('success', $v['voucher_no'] . ' approved.');
        redirect($back);
    }

    if ($action === 'unapprove_voucher') {
        if (!$canApprove) {
            http_response_code(403);
            die('Access denied: you need the approve_vouchers permission.');
        }
        $id = (int) ($_POST['id'] ?? 0);
        $v = accountingVoucherById($db, $typeKey, $id);
        if (!$v) {
            setFlash('error', 'Voucher not found.');
            redirect($back);
        }
        if ($v['status'] !== 'Approved') {
            setFlash('error', 'Only Approved vouchers can be un-approved.');
            redirect($back);
        }
        $db->update($cfg['table'], ['status' => 'Pending', 'approved_by' => null, 'updated_by' => $me], '`id` = ?', [$id]);
        accountingSetLineStatus($db, $cfg['type'], $id, 'Pending');
        accountingLogVoucher($db, $typeKey, $id, $v['voucher_no'], 'Unapprove', ['status' => 'Approved'], ['status' => 'Pending'], $me);

        if ($typeKey === 'payment') {
            $db->execute(
                'UPDATE `tbl_expense_claims` SET `status` = ? WHERE `payment_voucher_id` = ? AND `status` = ?',
                ['Approved', $id, 'Paid']
            );
        }
        setFlash('success', $v['voucher_no'] . ' un-approved (audited).');
        redirect($back);
    }

    if ($action === 'delete_voucher') {
        $id = (int) ($_POST['id'] ?? 0);
        $v = accountingVoucherById($db, $typeKey, $id);
        if (!$v) {
            setFlash('error', 'Voucher not found.');
            redirect($back);
        }
        if ($v['status'] !== 'Pending') {
            setFlash('error', 'Only Pending vouchers can be deleted.');
            redirect($back);
        }
        $db->delete('tbl_ledger_particulars', '`voucher_type` = ? AND `voucher_type_id` = ?', [$cfg['type'], $id]);
        $db->delete($cfg['table'], '`id` = ?', [$id]);
        accountingLogVoucher($db, $typeKey, $id, $v['voucher_no'], 'Delete', $v, null, $me);
        setFlash('success', $v['voucher_no'] . ' deleted.');
        redirect($back);
    }

    if ($action === 'export_vouchers') {
        $vouchers = $db->select(
            'SELECT v.*, u.fullname AS added_by_name FROM `' . $cfg['table'] . '` v
             LEFT JOIN `tbl_users_login` u ON u.id = v.added_by
             ORDER BY v.voucher_date, v.id'
        );
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $typeKey . '_vouchers_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Voucher No', 'Date', 'Reference', 'Narration', 'Amount', 'Status', 'Added By', 'Added On']);
        foreach ($vouchers as $v) {
            fputcsv($out, [
                $v['voucher_no'], $v['voucher_date'], $v['reference_no'], $v['narration'],
                (float) ($v['total_amount'] ?? $v['amount']), $v['status'],
                $v['added_by_name'], $v['added_on'],
            ]);
        }
        fclose($out);
        exit;
    }

    setFlash('error', 'Unknown postings action.');
    redirect(pageUrl('accounts', 'postings'));
} catch (Throwable $e) {
    setFlash('error', 'Voucher operation failed: ' . $e->getMessage());
    redirect($back);
}
