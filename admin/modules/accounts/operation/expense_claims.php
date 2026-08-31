<?php
/**
 * SB-Tech — Accounts / Expense Claims operations (US-FIN-06/07/08).
 * save_claim / submit_claim / delete_claim / approve_claim (auto-creates a
 * Pending Payment voucher) / reject_claim / export_claims.
 */
$db = Database::instance();
$me = (int) Auth::id();
$action = (string) ($_POST['action'] ?? '');
$back = pageUrl('accounts', 'expense_claims');
$canApprove = Auth::isSuperAdmin() || Auth::hasSpecial('approve_expense_claims');

try {
    if ($action === 'save_claim') {
        $id = (int) ($_POST['id'] ?? 0);
        $category = trim((string) ($_POST['category'] ?? ''));
        $expenseDate = (string) ($_POST['expense_date'] ?? '');
        $description = trim((string) ($_POST['description'] ?? ''));
        $amount = round((float) ($_POST['amount'] ?? 0), 4);
        if ($category === '' || !strtotime($expenseDate) || $description === '' || $amount <= 0) {
            setFlash('error', 'Category, expense date, description and a positive amount are required.');
            redirect($back);
        }
        $submitNow = !empty($_POST['submit_now']);

        $existing = null;
        if ($id) {
            $existing = $db->selectOne('SELECT * FROM `tbl_expense_claims` WHERE `id` = ? AND `staff_id` = ?', [$id, $me]);
            if (!$existing) {
                setFlash('error', 'Claim not found.');
                redirect($back);
            }
            if (!in_array($existing['status'], ['Draft', 'Rejected'], true)) {
                setFlash('error', 'Only Draft or Rejected claims can be edited (AC-FIN-06.3).');
                redirect($back);
            }
        }

        // Receipt files (required at submit — AC-FIN-06.1).
        $fileCount = 0;
        if ($submitNow) {
            if (empty($_FILES['receipt_files']['name'][0])) {
                setFlash('error', 'At least one receipt file is required to submit a claim.');
                redirect($back . ($id ? '&edit_id=' . $id : ''));
            }
            foreach ($_FILES['receipt_files']['name'] as $i => $name) {
                if ($name === '') {
                    continue;
                }
                $file = [
                    'name' => $_FILES['receipt_files']['name'][$i],
                    'type' => $_FILES['receipt_files']['type'][$i],
                    'tmp_name' => $_FILES['receipt_files']['tmp_name'][$i],
                    'error' => $_FILES['receipt_files']['error'][$i],
                    'size' => $_FILES['receipt_files']['size'][$i],
                ];
                $up = validateUpload($file, ['jpg', 'jpeg', 'png', 'pdf']);
                if (!$up['ok']) {
                    setFlash('error', $up['message']);
                    redirect($back . ($id ? '&edit_id=' . $id : ''));
                }
                $fileCount++;
            }
            if ($fileCount === 0 && !$existing) {
                setFlash('error', 'At least one receipt file is required to submit a claim.');
                redirect($back);
            }
        }

        $data = [
            'category'     => $category,
            'expense_date' => $expenseDate,
            'description'  => $description,
            'amount'       => $amount,
            'project_id'   => (int) ($_POST['project_id'] ?? 0) ?: null,
            'client_id'    => (int) ($_POST['client_id'] ?? 0) ?: null,
            'status'       => $submitNow ? 'Submitted' : 'Draft',
            'updated_by'   => $me,
        ];

        if ($id) {
            $db->update('tbl_expense_claims', $data, '`id` = ?', [$id]);
            $claimId = $id;
        } else {
            $claimNo = 'CLM-' . date('Ymd') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $attempts = 0;
            while ($db->selectOne('SELECT id FROM `tbl_expense_claims` WHERE `claim_no` = ?', [$claimNo])) {
                $attempts++;
                if ($attempts >= 50) {
                    setFlash('error', 'Could not generate a unique claim number. Please try again.');
                    redirect($back);
                }
                $claimNo = 'CLM-' . date('Ymd') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            }
            $claimId = $db->insert('tbl_expense_claims', array_merge($data, [
                'staff_id' => $me, 'claim_no' => $claimNo, 'added_by' => $me,
            ]));
        }

        if ($submitNow) {
            foreach ($_FILES['receipt_files']['name'] as $i => $name) {
                if ($name === '') {
                    continue;
                }
                $file = [
                    'name' => $_FILES['receipt_files']['name'][$i],
                    'type' => $_FILES['receipt_files']['type'][$i],
                    'tmp_name' => $_FILES['receipt_files']['tmp_name'][$i],
                    'error' => $_FILES['receipt_files']['error'][$i],
                    'size' => $_FILES['receipt_files']['size'][$i],
                ];
                $up = validateUpload($file, ['jpg', 'jpeg', 'png', 'pdf']);
                $loc = storeUpload($file, 'expense_claims', $up['extension']);
                if ($loc) {
                    $db->insert('tbl_expense_claim_files', [
                        'claim_id' => $claimId, 'file_location' => $loc,
                        'file_name' => basename($name), 'file_extension' => $up['extension'],
                        'file_size' => (int) $file['size'], 'added_by' => $me,
                    ]);
                }
            }
            notifyPermissionHolders('approve_expense_claims', 'Expense claim ' . ($existing['claim_no'] ?? '') . ' submitted for approval.', 'Expense', (string) $claimId, $me);
        }
        setFlash('success', 'Claim ' . ($submitNow ? 'submitted' : 'saved as draft') . '.');
        redirect($back);
    }

    if ($action === 'submit_claim') {
        $id = (int) ($_POST['id'] ?? 0);
        $claim = $db->selectOne('SELECT * FROM `tbl_expense_claims` WHERE `id` = ? AND `staff_id` = ?', [$id, $me]);
        if (!$claim) {
            setFlash('error', 'Claim not found.');
            redirect($back);
        }
        if ($claim['status'] !== 'Draft') {
            setFlash('error', 'Only Draft claims can be submitted.');
            redirect($back);
        }
        $files = $db->count('tbl_expense_claim_files', '`claim_id` = ?', [$id]);
        if ($files < 1) {
            setFlash('error', 'At least one receipt file is required to submit a claim.');
            redirect($back);
        }
        $db->update('tbl_expense_claims', ['status' => 'Submitted', 'updated_by' => $me], '`id` = ?', [$id]);
        notifyPermissionHolders('approve_expense_claims', 'Expense claim ' . $claim['claim_no'] . ' submitted for approval.', 'Expense', (string) $id, $me);
        setFlash('success', 'Claim submitted for approval.');
        redirect($back);
    }

    if ($action === 'delete_claim') {
        $id = (int) ($_POST['id'] ?? 0);
        $claim = $db->selectOne('SELECT * FROM `tbl_expense_claims` WHERE `id` = ? AND `staff_id` = ?', [$id, $me]);
        if (!$claim) {
            setFlash('error', 'Claim not found.');
            redirect($back);
        }
        if (!in_array($claim['status'], ['Draft', 'Rejected'], true)) {
            setFlash('error', 'Only Draft or Rejected claims can be deleted (AC-FIN-06.3).');
            redirect($back);
        }
        foreach ($db->select('SELECT `file_location` FROM `tbl_expense_claim_files` WHERE `claim_id` = ?', [$id]) as $f) {
            if (!empty($f['file_location'])) {
                $path = dirname(__DIR__, 3) . '/user_uploads/' . $f['file_location'];
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
        $db->delete('tbl_expense_claim_files', '`claim_id` = ?', [$id]);
        $db->delete('tbl_expense_claims', '`id` = ?', [$id]);
        setFlash('success', 'Claim deleted.');
        redirect($back);
    }

    if ($action === 'approve_claim') {
        if (!$canApprove) {
            http_response_code(403);
            die('Access denied: you need the approve_expense_claims permission.');
        }
        $id = (int) ($_POST['id'] ?? 0);
        $claim = $db->selectOne('SELECT * FROM `tbl_expense_claims` WHERE `id` = ?', [$id]);
        if (!$claim) {
            setFlash('error', 'Claim not found.');
            redirect($back);
        }
        if ($claim['status'] !== 'Submitted') {
            setFlash('error', 'Only Submitted claims can be approved.');
            redirect($back);
        }
        // AC-FIN-07.1: final approval auto-creates a linked Pending Payment voucher.
        $voucherId = accountingPaymentVoucherForClaim($db, $claim, $me);
        $db->update('tbl_expense_claims', [
            'status' => 'Approved', 'approved_by' => $me, 'approved_on' => date('Y-m-d H:i:s'),
            'payment_voucher_id' => $voucherId, 'updated_by' => $me,
        ], '`id` = ?', [$id]);
        notifyUser((int) $claim['staff_id'], 'Your expense claim ' . $claim['claim_no'] . ' was approved — payment voucher created.', 'Expense', (string) $id, $me);
        setFlash('success', 'Claim approved — Payment voucher created (Pending, in Posting).');
        redirect($back);
    }

    if ($action === 'reject_claim') {
        if (!$canApprove) {
            http_response_code(403);
            die('Access denied: you need the approve_expense_claims permission.');
        }
        $id = (int) ($_POST['id'] ?? 0);
        $reason = trim((string) ($_POST['reject_reason'] ?? ''));
        $claim = $db->selectOne('SELECT * FROM `tbl_expense_claims` WHERE `id` = ?', [$id]);
        if (!$claim) {
            setFlash('error', 'Claim not found.');
            redirect($back);
        }
        if ($claim['status'] !== 'Submitted') {
            setFlash('error', 'Only Submitted claims can be rejected.');
            redirect($back);
        }
        if ($reason === '') {
            setFlash('error', 'A rejection reason is required.');
            redirect($back);
        }
        $db->update('tbl_expense_claims', [
            'status' => 'Rejected', 'reject_reason' => $reason,
            'approved_by' => $me, 'approved_on' => date('Y-m-d H:i:s'), 'updated_by' => $me,
        ], '`id` = ?', [$id]);
        notifyUser((int) $claim['staff_id'], 'Your expense claim ' . $claim['claim_no'] . ' was rejected: ' . $reason, 'Expense', (string) $id, $me);
        setFlash('success', 'Claim rejected (reason recorded).');
        redirect($back);
    }

    if ($action === 'export_claims') {
        if (!$canApprove) {
            http_response_code(403);
            die('Access denied: you need the approve_expense_claims permission.');
        }
        $claims = $db->select(
            'SELECT c.*, u.fullname AS staff_name, pv.voucher_no AS payment_voucher_no
             FROM `tbl_expense_claims` c
             LEFT JOIN `tbl_users_login` u ON u.id = c.staff_id
             LEFT JOIN `tbl_payment_vouchers` pv ON pv.id = c.payment_voucher_id
             ORDER BY c.id DESC'
        );
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="expense_claims_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Claim No', 'Staff', 'Date', 'Category', 'Amount', 'Status', 'Payment Voucher', 'Reject Reason']);
        foreach ($claims as $c) {
            fputcsv($out, [
                $c['claim_no'], $c['staff_name'], $c['expense_date'], $c['category'],
                (float) $c['amount'], $c['status'], $c['payment_voucher_no'], $c['reject_reason'],
            ]);
        }
        fclose($out);
        exit;
    }

    setFlash('error', 'Unknown expense claim action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Expense claim operation failed: ' . $e->getMessage());
    redirect($back);
}
