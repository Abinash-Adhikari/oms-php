<?php
/**
 * Grant My Office → My Payslips to everyone who already has any My Office
 * page, so every staff member can read their own payslips. Super admins
 * ('All') need no grant.
 */
$query = [
    "UPDATE `tbl_users_login`
        SET `permitted_submodules` = JSON_ARRAY_APPEND(`permitted_submodules`, '$.my_office', 'payslips')
      WHERE JSON_VALID(`permitted_submodules`)
        AND JSON_TYPE(JSON_EXTRACT(`permitted_submodules`, '$.my_office')) = 'ARRAY'
        AND NOT JSON_CONTAINS(`permitted_submodules`, '\"payslips\"', '$.my_office')",
];
$rollbackQuery = [
    "UPDATE `tbl_users_login`
        SET `permitted_submodules` = JSON_REMOVE(
                `permitted_submodules`,
                JSON_UNQUOTE(JSON_SEARCH(`permitted_submodules`, 'one', 'payslips', NULL, '$.my_office'))
            )
      WHERE JSON_VALID(`permitted_submodules`)
        AND JSON_CONTAINS(`permitted_submodules`, '\"payslips\"', '$.my_office')",
];
