<?php
// Progressive slabs per tax year. Bands run lower_limit → upper_limit
// (NULL = no upper limit). `is_social_security_tax` marks the 1% band that
// SSF / pension-fund contributors do not pay.
$query = [
    "CREATE TABLE `tbl_payroll_tax_slabs` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `tax_year_id` INT NOT NULL,
    `filing_status` ENUM('All','Single','Couple') NOT NULL DEFAULT 'All',
    `lower_limit` DECIMAL(18,4) NOT NULL DEFAULT 0,
    `upper_limit` DECIMAL(18,4) DEFAULT NULL,
    `rate` DECIMAL(7,4) NOT NULL DEFAULT 0,
    `is_social_security_tax` TINYINT(1) NOT NULL DEFAULT 0,
    `position` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_tax_slab_year` (`tax_year_id`, `filing_status`, `lower_limit`),
    CONSTRAINT `fk_tax_slab_year`
        FOREIGN KEY (`tax_year_id`) REFERENCES `tbl_payroll_tax_years` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];
$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_payroll_tax_slabs`;',
];
