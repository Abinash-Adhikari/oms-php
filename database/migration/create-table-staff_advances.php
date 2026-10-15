<?php
// Salary advances / staff loans, recovered through payroll in monthly
// instalments (PAYROLL_PLAN P4). Recovered = sum of ADV line items in
// Approved/Posted/Paid runs — never stored, so a cancelled run can't drift it.
$query = [
    "CREATE TABLE `tbl_staff_advances` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `staff_id` INT NOT NULL,
    `title` VARCHAR(191) NOT NULL,
    `amount` DECIMAL(18,4) NOT NULL,
    `monthly_installment` DECIMAL(18,4) NOT NULL,
    `issued_on` DATE NOT NULL,
    `start_period_key` VARCHAR(7) DEFAULT NULL,
    `status` ENUM('Active','Closed','Cancelled') NOT NULL DEFAULT 'Active',
    `notes` VARCHAR(500) DEFAULT NULL,
    `payment_voucher_id` INT DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_staff_advance_staff` (`staff_id`, `status`),
    CONSTRAINT `fk_staff_advance_staff`
        FOREIGN KEY (`staff_id`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];
$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_staff_advances`;',
];
