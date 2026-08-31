<?php

/**
 * Migration: Add type-specific columns to document engine tables.
 * Each document type gets its own unique fields.
 */
$query = [
    // ═══════════════════════════════════════════════════════════════
    // INVOICE-specific: payment terms, bank details, late fees
    // ═══════════════════════════════════════════════════════════════
    "ALTER TABLE `tbl_documents`
        ADD COLUMN IF NOT EXISTS `payment_terms` TEXT DEFAULT NULL COMMENT 'Invoice: payment terms text' AFTER `terms`,
        ADD COLUMN IF NOT EXISTS `bank_name` VARCHAR(191) DEFAULT NULL COMMENT 'Invoice: bank name' AFTER `payment_terms`,
        ADD COLUMN IF NOT EXISTS `bank_account` VARCHAR(50) DEFAULT NULL COMMENT 'Invoice: account number' AFTER `bank_name`,
        ADD COLUMN IF NOT EXISTS `bank_routing` VARCHAR(50) DEFAULT NULL COMMENT 'Invoice: routing/swift code' AFTER `bank_account`,
        ADD COLUMN IF NOT EXISTS `late_fee_pct` DECIMAL(5,2) DEFAULT NULL COMMENT 'Invoice: late fee percentage per month' AFTER `bank_routing`",

    // ═══════════════════════════════════════════════════════════════
    // PROPOSAL-specific: rich sections
    // ═══════════════════════════════════════════════════════════════
    "ALTER TABLE `tbl_documents`
        ADD COLUMN IF NOT EXISTS `exec_summary` LONGTEXT DEFAULT NULL COMMENT 'Proposal: executive summary' AFTER `late_fee_pct`,
        ADD COLUMN IF NOT EXISTS `problem_statement` LONGTEXT DEFAULT NULL COMMENT 'Proposal: problem we solve' AFTER `exec_summary`,
        ADD COLUMN IF NOT EXISTS `proposed_solution` LONGTEXT DEFAULT NULL COMMENT 'Proposal: our solution' AFTER `problem_statement`,
        ADD COLUMN IF NOT EXISTS `timeline_text` LONGTEXT DEFAULT NULL COMMENT 'Proposal: timeline/milestones' AFTER `proposed_solution`,
        ADD COLUMN IF NOT EXISTS `team_text` LONGTEXT DEFAULT NULL COMMENT 'Proposal: team members bio' AFTER `timeline_text`,
        ADD COLUMN IF NOT EXISTS `case_studies` LONGTEXT DEFAULT NULL COMMENT 'Proposal: past work examples' AFTER `team_text`,
        ADD COLUMN IF NOT EXISTS `why_us` LONGTEXT DEFAULT NULL COMMENT 'Proposal: why choose us' AFTER `case_studies`",

    // ═══════════════════════════════════════════════════════════════
    // CONTRACT-specific: legal clauses, signatures, schedules
    // ═══════════════════════════════════════════════════════════════
    "ALTER TABLE `tbl_documents`
        ADD COLUMN IF NOT EXISTS `contract_clauses` LONGTEXT DEFAULT NULL COMMENT 'Contract: JSON array of clauses' AFTER `why_us`,
        ADD COLUMN IF NOT EXISTS `payment_schedule` LONGTEXT DEFAULT NULL COMMENT 'Contract: JSON payment milestones' AFTER `contract_clauses`,
        ADD COLUMN IF NOT EXISTS `signature_left_name` VARCHAR(191) DEFAULT NULL COMMENT 'Contract: party 1 name' AFTER `payment_schedule`,
        ADD COLUMN IF NOT EXISTS `signature_left_title` VARCHAR(191) DEFAULT NULL COMMENT 'Contract: party 1 title' AFTER `signature_left_name`,
        ADD COLUMN IF NOT EXISTS `signature_left_date` DATE DEFAULT NULL COMMENT 'Contract: party 1 signed date' AFTER `signature_left_title`,
        ADD COLUMN IF NOT EXISTS `signature_right_name` VARCHAR(191) DEFAULT NULL COMMENT 'Contract: party 2 name' AFTER `signature_left_date`,
        ADD COLUMN IF NOT EXISTS `signature_right_title` VARCHAR(191) DEFAULT NULL COMMENT 'Contract: party 2 title' AFTER `signature_right_name`,
        ADD COLUMN IF NOT EXISTS `signature_right_date` DATE DEFAULT NULL COMMENT 'Contract: party 2 signed date' AFTER `signature_right_title`",

    // ═══════════════════════════════════════════════════════════════
    // PRICE LIST-specific: category
    // ═══════════════════════════════════════════════════════════════
    "ALTER TABLE `tbl_documents`
        ADD COLUMN IF NOT EXISTS `pl_category` VARCHAR(100) DEFAULT NULL COMMENT 'Price List: category filter' AFTER `signature_right_date`",

    // ═══════════════════════════════════════════════════════════════
    // BROCHURE-specific: sections (JSON), hero image
    // ═══════════════════════════════════════════════════════════════
    "ALTER TABLE `tbl_documents`
        ADD COLUMN IF NOT EXISTS `brochure_sections` LONGTEXT DEFAULT NULL COMMENT 'Brochure: JSON array of sections' AFTER `pl_category`,
        ADD COLUMN IF NOT EXISTS `hero_image` VARCHAR(255) DEFAULT NULL COMMENT 'Brochure: hero banner image' AFTER `brochure_sections`",

    // ═══════════════════════════════════════════════════════════════
    // CREDIT NOTE-specific: reference to original invoice
    // ═══════════════════════════════════════════════════════════════
    "ALTER TABLE `tbl_documents`
        ADD COLUMN IF NOT EXISTS `original_invoice_id` INT DEFAULT NULL COMMENT 'Credit Note: linked invoice ID' AFTER `hero_image`,
        ADD COLUMN IF NOT EXISTS `credit_reason` TEXT DEFAULT NULL COMMENT 'Credit Note: reason for credit' AFTER `original_invoice_id`",

    // ═══════════════════════════════════════════════════════════════
    // Items: add section/type field for proposals
    // ═══════════════════════════════════════════════════════════════
    "ALTER TABLE `tbl_document_items`
        ADD COLUMN IF NOT EXISTS `item_type` VARCHAR(30) DEFAULT 'item' COMMENT 'item|milestone|clause|section' AFTER `sort_order`,
        ADD COLUMN IF NOT EXISTS `meta_json` TEXT DEFAULT NULL COMMENT 'Extra metadata per item type' AFTER `item_type`",
];

$rollbackQuery = [
    "ALTER TABLE `tbl_documents`
        DROP COLUMN IF EXISTS `payment_terms`,
        DROP COLUMN IF EXISTS `bank_name`,
        DROP COLUMN IF EXISTS `bank_account`,
        DROP COLUMN IF EXISTS `bank_routing`,
        DROP COLUMN IF EXISTS `late_fee_pct`,
        DROP COLUMN IF EXISTS `exec_summary`,
        DROP COLUMN IF EXISTS `problem_statement`,
        DROP COLUMN IF EXISTS `proposed_solution`,
        DROP COLUMN IF EXISTS `timeline_text`,
        DROP COLUMN IF EXISTS `team_text`,
        DROP COLUMN IF EXISTS `case_studies`,
        DROP COLUMN IF EXISTS `why_us`,
        DROP COLUMN IF EXISTS `contract_clauses`,
        DROP COLUMN IF EXISTS `payment_schedule`,
        DROP COLUMN IF EXISTS `signature_left_name`,
        DROP COLUMN IF EXISTS `signature_left_title`,
        DROP COLUMN IF EXISTS `signature_left_date`,
        DROP COLUMN IF EXISTS `signature_right_name`,
        DROP COLUMN IF EXISTS `signature_right_title`,
        DROP COLUMN IF EXISTS `signature_right_date`,
        DROP COLUMN IF EXISTS `pl_category`,
        DROP COLUMN IF EXISTS `brochure_sections`,
        DROP COLUMN IF EXISTS `hero_image`,
        DROP COLUMN IF EXISTS `original_invoice_id`,
        DROP COLUMN IF EXISTS `credit_reason`",
    "ALTER TABLE `tbl_document_items`
        DROP COLUMN IF EXISTS `item_type`,
        DROP COLUMN IF EXISTS `meta_json`",
];
