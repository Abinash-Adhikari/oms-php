<?php

/**
 * Migration: money received from a client (Sales → Client → Record Payment).
 *
 * A payment belongs to a client and optionally to one of the client's
 * invoices (tbl_documents, document_type = 'invoice'); a payment with no
 * invoice is an advance / on-account receipt. When the payment is posted to
 * Accounts, receipt_voucher_id points at the Auto / Pending receipt voucher
 * (no FK: an accountant may delete that voucher in Accounts, which only
 * detaches it).
 *
 * client_id is ON DELETE RESTRICT so deleting a client can never silently
 * erase its payment history.
 */
$query = [
    "CREATE TABLE IF NOT EXISTS `tbl_client_payments` (
        `id`                  INT NOT NULL AUTO_INCREMENT,
        `client_id`           INT NOT NULL,
        `document_id`         INT DEFAULT NULL COMMENT 'Invoice paid (tbl_documents); NULL = advance / on-account',
        `payment_date`        DATE NOT NULL,
        `amount`              DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
        `method`              ENUM('Cash','Bank Transfer','Cheque','Wallet','Card','Other') NOT NULL DEFAULT 'Bank Transfer',
        `reference_no`        VARCHAR(128) DEFAULT NULL COMMENT 'Cheque no, bank / wallet transaction id',
        `notes`               VARCHAR(1024) DEFAULT NULL,
        `deposit_terminal_id` INT DEFAULT NULL COMMENT 'Cash/bank account debited (tbl_account_terminals)',
        `credit_terminal_id`  INT DEFAULT NULL COMMENT 'Income/receivable account credited (tbl_account_terminals)',
        `receipt_voucher_id`  INT DEFAULT NULL COMMENT 'Auto receipt voucher (tbl_receipt_vouchers)',
        `added_by`            INT DEFAULT NULL,
        `updated_by`          INT DEFAULT NULL,
        `added_on`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_on`          DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_cpay_client_date` (`client_id`, `payment_date`),
        KEY `idx_cpay_document` (`document_id`),
        CONSTRAINT `fk_cpay_client`
            FOREIGN KEY (`client_id`) REFERENCES `tbl_clients` (`id`)
            ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT `fk_cpay_document`
            FOREIGN KEY (`document_id`) REFERENCES `tbl_documents` (`id`)
            ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Payments received from clients, optionally against an invoice'",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_client_payments`;',
];
