<?php
// An explicit "Unpaid Leave" type; idempotent (skips if the title exists).
$query = [
    "INSERT INTO `tbl_office_leave_configs`
        (`title`, `max_allowed`, `description`, `is_active`, `is_paid`, `carry_forward`, `max_carry_forward`, `requires_approval`, `gender_specific`, `documentation_required`)
     SELECT 'Unpaid Leave', 365, 'Leave without pay — deducted in payroll.', 1, 0, 0, 0, 1, 'Both', 0
     FROM DUAL
     WHERE NOT EXISTS (SELECT 1 FROM `tbl_office_leave_configs` WHERE `title` = 'Unpaid Leave');",
];
$rollbackQuery = [
    "DELETE FROM `tbl_office_leave_configs` WHERE `title` = 'Unpaid Leave' AND `is_paid` = 0
       AND NOT EXISTS (SELECT 1 FROM `tbl_staff_leave_applications` a WHERE a.`leave_type_id` = `tbl_office_leave_configs`.`id`);",
];
