<?php

/**
 * SB-Tech — client payments (Sales → Client → Record Payment).
 *
 *   record()  insert a payment, optionally post an Auto / Pending receipt
 *             voucher (Dr cash/bank, Cr the chosen income or receivable
 *             account), then re-derive the invoice's Paid / Partially Paid status.
 *   delete()  remove a payment and its receipt voucher (only while that
 *             voucher is still Pending), then re-derive the invoice status.
 *
 * Callers run inside a transaction (admin/operation.php opens one per
 * request; Database::transaction() is not nestable). Validation rules are
 * pure functions in functions/sales.php so they are unit-tested.
 */
class ClientPaymentService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /**
     * Record a payment.
     *
     * @param array<string, mixed> $input Posted fields: client_id, document_id, payment_date, amount,
     *                                    method, reference_no, notes, post_to_accounts,
     *                                    deposit_terminal_id, credit_terminal_id
     * @return int New tbl_client_payments id
     * @throws RuntimeException on any validation or accounting failure
     */
    public function record(array $input, int $me): int
    {
        $clientId = (int) ($input['client_id'] ?? 0);
        $client = $this->db->selectOne('SELECT `id`, `name` FROM `tbl_clients` WHERE `id` = ?', [$clientId]);
        if (!$client) {
            throw new RuntimeException('Client not found.');
        }

        $invoice = null;
        $alreadyPaid = 0.0;
        $documentId = (int) ($input['document_id'] ?? 0);
        if ($documentId > 0) {
            // Lock the invoice so two payments submitted together cannot both pass the "left to pay" check.
            $invoice = $this->db->selectOne(
                "SELECT `id`, `document_number`, `total`, `status`, `due_date`, `client_id`
                 FROM `tbl_documents` WHERE `id` = ? AND `document_type` = 'invoice' FOR UPDATE",
                [$documentId]
            );
            if (!$invoice || (int) $invoice['client_id'] !== $clientId) {
                throw new RuntimeException('That invoice does not belong to this client.');
            }
            $alreadyPaid = $this->paidOnInvoice($documentId);
        }

        $v = validate_client_payment($input, $invoice, $alreadyPaid, date('Y-m-d'));
        if (!$v['ok']) {
            throw new RuntimeException($v['error']);
        }
        $data = $v['data'];

        $row = $data + [
            'client_id'   => $clientId,
            'document_id' => $invoice ? (int) $invoice['id'] : null,
            'added_by'    => $me,
            'updated_by'  => $me,
        ];

        $voucherId = null;
        if (!empty($input['post_to_accounts'])) {
            $deposit = (int) ($input['deposit_terminal_id'] ?? 0);
            $credit = (int) ($input['credit_terminal_id'] ?? 0);
            if (!$deposit || !accountingTerminalMeta($this->db, $deposit)) {
                throw new RuntimeException('Choose the cash or bank account the money went into.');
            }
            if (!$credit || !accountingTerminalMeta($this->db, $credit)) {
                throw new RuntimeException('Choose the income or receivable account to credit.');
            }
            if ($deposit === $credit) {
                throw new RuntimeException('The deposit account and the credit account must be different.');
            }
            $row['deposit_terminal_id'] = $deposit;
            $row['credit_terminal_id'] = $credit;
            $voucherId = $this->postReceipt($client, $invoice, $data, $deposit, $credit, $me);
            $row['receipt_voucher_id'] = $voucherId;
        }

        $paymentId = (int) $this->db->insert('tbl_client_payments', $row);
        if ($invoice) {
            $this->syncInvoiceStatus((int) $invoice['id']);
        }
        auditLog('sales', 'create', 'client_payment', $paymentId, null, $row);

        return $paymentId;
    }

    /**
     * Delete a payment. Its receipt voucher is deleted too while Pending or
     * Rejected; an Approved voucher blocks the delete (needs a reversal in Accounts).
     *
     * @return int The payment's client id (for the redirect)
     */
    public function delete(int $paymentId, int $me): int
    {
        $p = $this->db->selectOne('SELECT * FROM `tbl_client_payments` WHERE `id` = ? FOR UPDATE', [$paymentId]);
        if (!$p) {
            throw new RuntimeException('Payment not found.');
        }
        if ($p['receipt_voucher_id']) {
            $this->removeReceipt((int) $p['receipt_voucher_id'], $me);
        }
        $this->db->delete('tbl_client_payments', '`id` = ?', [$paymentId]);
        if ($p['document_id']) {
            $this->syncInvoiceStatus((int) $p['document_id']);
        }
        auditLog('sales', 'delete', 'client_payment', $paymentId, $p, null);

        return (int) $p['client_id'];
    }

    /** Re-derive an invoice's status from the payments recorded against it. */
    public function syncInvoiceStatus(int $documentId): void
    {
        $inv = $this->db->selectOne(
            "SELECT `status`, `total`, `due_date` FROM `tbl_documents` WHERE `id` = ? AND `document_type` = 'invoice'",
            [$documentId]
        );
        if (!$inv) {
            return;
        }
        $next = invoice_status_for_payments(
            (string) $inv['status'],
            (float) $inv['total'],
            $this->paidOnInvoice($documentId),
            $inv['due_date'] ?: null,
            date('Y-m-d')
        );
        if ($next !== $inv['status']) {
            $this->db->update('tbl_documents', ['status' => $next], '`id` = ?', [$documentId]);
        }
    }

    public function paidOnInvoice(int $documentId): float
    {
        $row = $this->db->selectOne(
            'SELECT COALESCE(SUM(`amount`), 0) AS paid FROM `tbl_client_payments` WHERE `document_id` = ?',
            [$documentId]
        );

        return round((float) ($row['paid'] ?? 0), 2);
    }

    /**
     * A client's invoices (newest first), each with the amount paid against it.
     *
     * @return list<array<string, mixed>>
     */
    public function invoicesFor(int $clientId): array
    {
        return $this->db->select(
            "SELECT d.`id`, d.`document_number`, d.`subject`, d.`document_date`, d.`due_date`, d.`total`, d.`status`,
                    COALESCE((SELECT SUM(p.`amount`) FROM `tbl_client_payments` p WHERE p.`document_id` = d.`id`), 0) AS paid
             FROM `tbl_documents` d
             WHERE d.`client_id` = ? AND d.`document_type` = 'invoice'
             ORDER BY d.`document_date` DESC, d.`id` DESC",
            [$clientId]
        );
    }

    /**
     * A client's payments (newest first) with the invoice number and voucher status.
     *
     * @return list<array<string, mixed>>
     */
    public function paymentsFor(int $clientId): array
    {
        return $this->db->select(
            'SELECT p.*, d.`document_number`, rv.`voucher_no`, rv.`status` AS voucher_status, u.`fullname` AS added_by_name
             FROM `tbl_client_payments` p
             LEFT JOIN `tbl_documents` d ON d.`id` = p.`document_id`
             LEFT JOIN `tbl_receipt_vouchers` rv ON rv.`id` = p.`receipt_voucher_id`
             LEFT JOIN `tbl_users_login` u ON u.`id` = p.`added_by`
             WHERE p.`client_id` = ?
             ORDER BY p.`payment_date` DESC, p.`id` DESC',
            [$clientId]
        );
    }

    /**
     * Auto / Pending receipt voucher: Dr deposit (cash/bank), Cr income or receivable.
     *
     * @param array<string, mixed>      $client
     * @param array<string, mixed>|null $invoice
     * @param array<string, mixed>      $data    Validated payment fields
     */
    private function postReceipt(array $client, ?array $invoice, array $data, int $deposit, int $credit, int $me): int
    {
        $date = (string) $data['payment_date'];
        $fy = accountingFiscalForDate($date);
        if (!$fy) {
            throw new RuntimeException('No open fiscal year covers ' . $date
                . '. Open one in Accounts → Fiscal Years, or untick "Post to Accounts".');
        }
        $amount = (float) $data['amount'];
        $label = 'Payment from ' . $client['name'] . ($invoice ? ' for ' . $invoice['document_number'] : ' (advance)');
        $voucherNo = accountingNextVoucherNo($this->db, 'receipt', (int) $fy['id']);
        $voucherId = (int) $this->db->insert('tbl_receipt_vouchers', [
            'fiscal_year_id' => (int) $fy['id'],
            'voucher_no'     => $voucherNo,
            'voucher_date'   => $date,
            'reference_no'   => $data['reference_no'] ?? ($invoice['document_number'] ?? null),
            'narration'      => $label,
            'description'    => $label . ' — ' . $data['method'] . '. Created by Sales → Clients; approve in Accounts → Posting.',
            'amount'         => $amount,
            'total_amount'   => $amount,
            'entry_type'     => 'Auto',
            'status'         => 'Pending',
            'added_by'       => $me,
            'updated_by'     => $me,
        ]);
        accountingReplaceLines($this->db, 'Receipt', $voucherId, (int) $fy['id'], $date, [
            ['account_terminal_id' => $deposit, 'debit' => $amount, 'credit' => 0.0, 'remarks' => $label],
            ['account_terminal_id' => $credit, 'debit' => 0.0, 'credit' => $amount, 'remarks' => $label],
        ], $me, 'Pending');
        accountingLogVoucher($this->db, 'receipt', $voucherId, $voucherNo, 'Create', null, ['source' => 'client_payment', 'client_id' => (int) $client['id']], $me);

        return $voucherId;
    }

    private function removeReceipt(int $voucherId, int $me): void
    {
        $v = accountingVoucherById($this->db, 'receipt', $voucherId);
        if (!$v) {
            return; // already deleted in Accounts
        }
        // A Rejected voucher never reached the ledger totals, so it can go with the payment.
        if ($v['status'] === 'Approved') {
            throw new RuntimeException($v['voucher_no'] . ' is already ' . strtolower((string) $v['status'])
                . ' in Accounts. Ask an accountant to un-approve it (or post a reversing entry) first.');
        }
        $this->db->delete('tbl_ledger_particulars', '`voucher_type` = ? AND `voucher_type_id` = ?', ['Receipt', $voucherId]);
        $this->db->delete('tbl_receipt_vouchers', '`id` = ?', [$voucherId]);
        accountingLogVoucher($this->db, 'receipt', $voucherId, (string) $v['voucher_no'], 'Delete', $v, ['reason' => 'Client payment deleted'], $me);
    }
}
