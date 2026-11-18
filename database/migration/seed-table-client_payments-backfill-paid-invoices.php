<?php

/**
 * Migration: invoices marked "Paid" by hand before payment tracking existed
 * have no tbl_client_payments rows, so the client balance would show them as
 * still owed. Backfill one payment per such invoice (full amount, method
 * 'Other', dated on the due date — or the invoice date — capped at today).
 * Not posted to Accounts (receipt_voucher_id NULL): the money was already
 * handled in the books, if at all, outside this feature.
 *
 * Invoices with no client_id are skipped (they belong to no client balance).
 * Rollback removes exactly the backfilled rows (matched by the notes marker).
 */
$query = [
    "INSERT INTO `tbl_client_payments`
        (`client_id`, `document_id`, `payment_date`, `amount`, `method`, `reference_no`, `notes`)
     SELECT d.`client_id`, d.`id`,
            LEAST(COALESCE(d.`due_date`, d.`document_date`), CURDATE()),
            d.`total`, 'Other', d.`document_number`,
            'Backfilled: invoice was marked Paid before payment tracking'
       FROM `tbl_documents` d
       JOIN `tbl_clients` c ON c.`id` = d.`client_id`
      WHERE d.`document_type` = 'invoice'
        AND d.`status` = 'Paid'
        AND d.`total` > 0
        AND NOT EXISTS (SELECT 1 FROM `tbl_client_payments` p WHERE p.`document_id` = d.`id`);",
];

$rollbackQuery = [
    "DELETE FROM `tbl_client_payments`
      WHERE `receipt_voucher_id` IS NULL
        AND `notes` = 'Backfilled: invoice was marked Paid before payment tracking';",
];
