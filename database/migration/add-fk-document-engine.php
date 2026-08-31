<?php
/**
 * Migration: Add foreign key constraints to document engine tables.
 * These were omitted in the original create-table-document-engine migration.
 */
$query = [
    "ALTER TABLE `tbl_document_items`
        ADD CONSTRAINT `fk_document_items_doc`
        FOREIGN KEY (`document_id`) REFERENCES `tbl_documents` (`id`) ON DELETE CASCADE",

    "ALTER TABLE `tbl_document_files`
        ADD CONSTRAINT `fk_document_files_doc`
        FOREIGN KEY (`document_id`) REFERENCES `tbl_documents` (`id`) ON DELETE CASCADE",
];

$rollbackQuery = [
    "ALTER TABLE `tbl_document_items` DROP FOREIGN KEY `fk_document_items_doc`",
    "ALTER TABLE `tbl_document_files` DROP FOREIGN KEY `fk_document_files_doc`",
];
