<?php
// Which staff contribute to which fund. NULL overrides fall back to the
// fund's default rates; employee_fixed (e.g. a CIT amount) beats the rate.
$query = [
    "CREATE TABLE `tbl_staff_fund_enrollments` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `staff_id` INT NOT NULL,
    `fund_id` INT NOT NULL,
    `member_no` VARCHAR(100) DEFAULT NULL,
    `employee_rate` DECIMAL(7,4) DEFAULT NULL,
    `employee_fixed` DECIMAL(18,4) DEFAULT NULL,
    `employer_rate` DECIMAL(7,4) DEFAULT NULL,
    `start_date` DATE DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_fund_enrollment` (`staff_id`, `fund_id`),
    KEY `idx_fund_enrollment_fund` (`fund_id`),
    CONSTRAINT `fk_fund_enrollment_staff`
        FOREIGN KEY (`staff_id`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_fund_enrollment_fund`
        FOREIGN KEY (`fund_id`) REFERENCES `tbl_payroll_funds` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];
$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_staff_fund_enrollments`;',
];
