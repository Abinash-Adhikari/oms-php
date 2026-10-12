<?php

/**
 * Migration: Move legacy quotations (tbl_quotations + items + files) into the
 * unified document engine (tbl_documents, type 'quotation').
 *
 * - Columns map 1:1 (quotation_date → document_date); statuses are identical.
 * - A legacy number already used in tbl_documents gets the next free
 *   QTN-<year>-NNNN number; every other quotation keeps its number.
 * - Attachments are copied as rows only; both systems store paths relative to
 *   user_uploads/, so no files move.
 * - tbl_quotations is kept (read-only) and records the new id in
 *   migrated_document_id, so old ?module=leads&page=quotations&id=N links can
 *   be redirected. The legacy tables can be dropped in a later migration.
 *
 * Rollback deletes the migrated documents (including any edits made to them
 * after migrating) and drops the mapping columns.
 */
$query = [
    "ALTER TABLE `tbl_quotations`
        ADD COLUMN IF NOT EXISTS `migrated_document_number` VARCHAR(50) DEFAULT NULL COMMENT 'Number used in tbl_documents',
        ADD COLUMN IF NOT EXISTS `migrated_document_id` INT DEFAULT NULL COMMENT 'tbl_documents.id this quotation was moved to'",

    // Keep the original number when it is free.
    "UPDATE `tbl_quotations`
        SET `migrated_document_number` = `quotation_number`
      WHERE `migrated_document_id` IS NULL
        AND `quotation_number` NOT IN (SELECT `document_number` FROM `tbl_documents`)",

    // Collisions: next free QTN-<year>-NNNN after every existing number of that year.
    "UPDATE `tbl_quotations` q
       JOIN (
            SELECT c.`id`,
                   CONCAT('QTN-', YEAR(c.`quotation_date`), '-', LPAD(
                       COALESCE((SELECT MAX(CAST(SUBSTRING_INDEX(n.`num`, '-', -1) AS UNSIGNED))
                                   FROM (SELECT `document_number` AS `num` FROM `tbl_documents`
                                         UNION ALL SELECT `quotation_number` FROM `tbl_quotations`) n
                                  WHERE n.`num` REGEXP CONCAT('^QTN-', YEAR(c.`quotation_date`), '-[0-9]+$')), 0)
                       + ROW_NUMBER() OVER (PARTITION BY YEAR(c.`quotation_date`) ORDER BY c.`id`), 4, '0')) AS `num`
              FROM `tbl_quotations` c
             WHERE c.`migrated_document_id` IS NULL
               AND c.`migrated_document_number` IS NULL
       ) x ON x.`id` = q.`id`
        SET q.`migrated_document_number` = x.`num`",

    "INSERT INTO `tbl_documents`
        (`document_type`, `document_number`, `client_id`, `client_name`, `client_email`, `client_phone`,
         `client_address`, `subject`, `document_date`, `valid_until`, `subtotal`, `discount_type`,
         `discount_value`, `tax_type`, `tax_value`, `total`, `notes`, `terms`, `status`, `lead_id`,
         `show_items`, `added_by`, `updated_by`, `added_on`, `updated_on`)
     SELECT 'quotation', `migrated_document_number`, `client_id`, `client_name`, `client_email`, `client_phone`,
            `client_address`, `subject`, `quotation_date`, `valid_until`, `subtotal`, `discount_type`,
            `discount_value`, `tax_type`, `tax_value`, `total`, `notes`, `terms`, `status`, `lead_id`,
            1, `added_by`, `updated_by`, COALESCE(`added_on`, NOW()), `updated_on`
       FROM `tbl_quotations`
      WHERE `migrated_document_id` IS NULL
      ORDER BY `id`",

    "UPDATE `tbl_quotations` q
       JOIN `tbl_documents` d ON d.`document_number` = q.`migrated_document_number` AND d.`document_type` = 'quotation'
        SET q.`migrated_document_id` = d.`id`
      WHERE q.`migrated_document_id` IS NULL",

    "INSERT INTO `tbl_document_items`
        (`document_id`, `item_name`, `description`, `quantity`, `unit`, `unit_price`, `amount`, `sort_order`, `item_type`)
     SELECT q.`migrated_document_id`, i.`item_name`, i.`description`, i.`quantity`, i.`unit`, i.`unit_price`,
            i.`amount`, i.`sort_order`, 'item'
       FROM `tbl_quotation_items` i
       JOIN `tbl_quotations` q ON q.`id` = i.`quotation_id`
      WHERE q.`migrated_document_id` IS NOT NULL
      ORDER BY i.`quotation_id`, i.`sort_order`, i.`id`",

    "INSERT INTO `tbl_document_files`
        (`document_id`, `file_name`, `title`, `file_location`, `file_extension`, `file_size`, `added_by`, `added_on`)
     SELECT q.`migrated_document_id`, f.`file_name`, f.`file_name`, f.`file_location`, f.`file_extension`,
            f.`file_size`, f.`added_by`, COALESCE(f.`added_on`, NOW())
       FROM `tbl_quotation_files` f
       JOIN `tbl_quotations` q ON q.`id` = f.`quotation_id`
      WHERE q.`migrated_document_id` IS NOT NULL
      ORDER BY f.`id`",
];

// The runner executes $rollbackQuery in reverse, so list it in $query order:
// items/files/documents are deleted first, the mapping columns dropped last.
$rollbackQuery = [
    "ALTER TABLE `tbl_quotations`
        DROP COLUMN IF EXISTS `migrated_document_id`,
        DROP COLUMN IF EXISTS `migrated_document_number`",
    "DELETE d FROM `tbl_documents` d JOIN `tbl_quotations` q ON q.`migrated_document_id` = d.`id`",
    "DELETE f FROM `tbl_document_files` f JOIN `tbl_quotations` q ON q.`migrated_document_id` = f.`document_id`",
    "DELETE i FROM `tbl_document_items` i JOIN `tbl_quotations` q ON q.`migrated_document_id` = i.`document_id`",
];
