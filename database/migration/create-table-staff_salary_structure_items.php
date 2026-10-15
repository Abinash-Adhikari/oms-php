<?php
// Component values on a salary structure (amount, or % of basic per component.calc).
$query = [
    "CREATE TABLE `tbl_staff_salary_structure_items` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `structure_id` INT NOT NULL,
    `component_id` INT NOT NULL,
    `value` DECIMAL(18,4) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_structure_component` (`structure_id`, `component_id`),
    KEY `idx_structure_item_component` (`component_id`),
    CONSTRAINT `fk_structure_item_structure`
        FOREIGN KEY (`structure_id`) REFERENCES `tbl_staff_salary_structures` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_structure_item_component`
        FOREIGN KEY (`component_id`) REFERENCES `tbl_payroll_components` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];
$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_staff_salary_structure_items`;',
];
