<?php
/**
 * Migration: Idempotently ensure document-engine foreign keys exist.
 *
 * The original create-table-document-engine shipped these FKs inline, and
 * they were later split out into add-fk-document-engine. On databases that
 * already have the tables (and FKs) from the earlier inline version, re-adding
 * them raises errno 121 "Duplicate key". That is benign but noisy, and the
 * runner records the migration as applied either way.
 *
 * This migration guarantees the FKs exist while being a clean no-op if they
 * already do, so it is safe to run on both fresh and migrated databases.
 *
 * FK names must be unique database-wide, so existence is checked against
 * information_schema.REFERENTIAL_CONSTRAINTS before adding.
 */
$query = [
    "DROP PROCEDURE IF EXISTS `__ensure_fk_document_items_doc`",
    "CREATE PROCEDURE `__ensure_fk_document_items_doc`()
     BEGIN
         IF NOT EXISTS (
             SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND CONSTRAINT_NAME = 'fk_document_items_doc'
         ) THEN
             ALTER TABLE `tbl_document_items`
                 ADD CONSTRAINT `fk_document_items_doc`
                 FOREIGN KEY (`document_id`) REFERENCES `tbl_documents` (`id`) ON DELETE CASCADE;
         END IF;
     END",
    "CALL `__ensure_fk_document_items_doc`()",
    "DROP PROCEDURE IF EXISTS `__ensure_fk_document_items_doc`",

    "DROP PROCEDURE IF EXISTS `__ensure_fk_document_files_doc`",
    "CREATE PROCEDURE `__ensure_fk_document_files_doc`()
     BEGIN
         IF NOT EXISTS (
             SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND CONSTRAINT_NAME = 'fk_document_files_doc'
         ) THEN
             ALTER TABLE `tbl_document_files`
                 ADD CONSTRAINT `fk_document_files_doc`
                 FOREIGN KEY (`document_id`) REFERENCES `tbl_documents` (`id`) ON DELETE CASCADE;
         END IF;
     END",
    "CALL `__ensure_fk_document_files_doc`()",
    "DROP PROCEDURE IF EXISTS `__ensure_fk_document_files_doc`",
];

$rollbackQuery = [
    "ALTER TABLE `tbl_document_files` DROP FOREIGN KEY `fk_document_files_doc`",
    "ALTER TABLE `tbl_document_items` DROP FOREIGN KEY `fk_document_items_doc`",
];
