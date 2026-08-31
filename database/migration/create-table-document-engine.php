<?php

/**
 * Migration: Create unified document engine tables.
 *
 * Replaces per-type tables (tbl_quotations, tbl_quotation_items, tbl_quotation_files)
 * with unified tables that power ALL document types.
 */
$query = [
    // ═══════════════════════════════════════════════════════════════
    // tbl_documents — unified header for ALL document types
    // ═══════════════════════════════════════════════════════════════
    "CREATE TABLE IF NOT EXISTS `tbl_documents` (
        `id`              INT NOT NULL AUTO_INCREMENT,
        `document_type`   VARCHAR(30) NOT NULL COMMENT 'quotation|invoice|proforma|proposal|contract|price_list|brochure|credit_note',
        `document_number` VARCHAR(50) NOT NULL COMMENT 'Unique number: QTN-2026-0001, INV-2026-0001, etc.',
        `client_id`       INT DEFAULT NULL,
        `client_name`     VARCHAR(191) DEFAULT NULL,
        `client_email`    VARCHAR(191) DEFAULT NULL,
        `client_phone`    VARCHAR(50) DEFAULT NULL,
        `client_address`  TEXT DEFAULT NULL,
        `subject`         VARCHAR(255) DEFAULT NULL,
        `document_date`   DATE NOT NULL,
        `valid_until`     DATE DEFAULT NULL COMMENT 'For quotations/proposals/contracts',
        `due_date`        DATE DEFAULT NULL COMMENT 'For invoices/proforma',
        `subtotal`        DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
        `discount_type`   ENUM('percentage','fixed') DEFAULT NULL,
        `discount_value`  DECIMAL(18,4) DEFAULT NULL,
        `tax_type`        ENUM('percentage','fixed') DEFAULT NULL,
        `tax_value`       DECIMAL(18,4) DEFAULT NULL,
        `total`           DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
        `notes`           TEXT DEFAULT NULL,
        `terms`           TEXT DEFAULT NULL,
        `status`          VARCHAR(30) NOT NULL DEFAULT 'Draft',
        `lead_id`         INT DEFAULT NULL COMMENT 'Linked lead',
        `reference_id`    INT DEFAULT NULL COMMENT 'Linked parent doc (e.g. invoice→quotation)',
        `added_by`        INT DEFAULT NULL,
        `updated_by`      INT DEFAULT NULL,
        `added_on`        DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_on`      DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_doc_number` (`document_number`),
        KEY `idx_doc_type` (`document_type`),
        KEY `idx_doc_status` (`status`),
        KEY `idx_doc_client` (`client_id`),
        KEY `idx_doc_lead` (`lead_id`),
        KEY `idx_doc_added` (`added_on`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Unified document engine — all business documents in one table'",

    // ═══════════════════════════════════════════════════════════════
    // tbl_document_items — shared line items for all item-based docs
    // ═══════════════════════════════════════════════════════════════
    "CREATE TABLE IF NOT EXISTS `tbl_document_items` (
        `id`            INT NOT NULL AUTO_INCREMENT,
        `document_id`   INT NOT NULL,
        `item_name`     VARCHAR(255) NOT NULL,
        `description`   TEXT DEFAULT NULL,
        `quantity`      DECIMAL(10,2) NOT NULL DEFAULT 1.00,
        `unit`          VARCHAR(50) DEFAULT NULL,
        `unit_price`    DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
        `amount`        DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
        `sort_order`    INT NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_di_doc` (`document_id`),
        CONSTRAINT `fk_document_items_doc` FOREIGN KEY (`document_id`) REFERENCES `tbl_documents` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Shared line items for all document types'",

    // ═══════════════════════════════════════════════════════════════
    // tbl_document_files — shared attachments for all documents
    // ═══════════════════════════════════════════════════════════════
    "CREATE TABLE `tbl_document_files` (
        `id`              INT NOT NULL AUTO_INCREMENT,
        `document_id`     INT NOT NULL,
        `file_name`       VARCHAR(255) NOT NULL,
        `file_location`   VARCHAR(255) NOT NULL,
        `file_extension`  VARCHAR(10) DEFAULT NULL,
        `file_size`       INT DEFAULT NULL,
        `added_by`        INT DEFAULT NULL,
        `added_on`        DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_df_doc` (`document_id`),
        CONSTRAINT `fk_document_files_doc` FOREIGN KEY (`document_id`) REFERENCES `tbl_documents` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Shared file attachments for all document types'",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_document_files`;',
    'DROP TABLE IF EXISTS `tbl_document_items`;',
    'DROP TABLE IF EXISTS `tbl_documents`;',
];
