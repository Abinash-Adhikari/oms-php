<?php
// staff_type 'Admin' is a super-admin bypass in Auth::isSuperAdmin(), so it
// must never be the accidental default. The column shipped DEFAULT 'Admin',
// which silently made every pre-existing account a super admin once the
// bypass was introduced (docs/ARCHITECTURE.md, RBAC).
//
// $query[0] flips the column default for future inserts.
// $query[1] backfills existing rows: 'Admin' survives only where the account
//           is already a super admin by permitted_modules / role.
// $rollbackQuery restores the old column default only — it deliberately does
//           NOT hand 'Admin' (full access) back to rows it cannot identify.
$query = [
    "ALTER TABLE `tbl_users_login`
        MODIFY COLUMN `staff_type` ENUM('Admin','Service') NOT NULL DEFAULT 'Service';",
    "UPDATE `tbl_users_login`
        SET `staff_type` = 'Service'
        WHERE `staff_type` = 'Admin'
          AND IFNULL(`permitted_modules`, '') <> 'All'
          AND LOWER(REPLACE(IFNULL(`role`, ''), ' ', '')) NOT IN ('superadmin');",
];

$rollbackQuery = [
    "ALTER TABLE `tbl_users_login`
        MODIFY COLUMN `staff_type` ENUM('Admin','Service') NOT NULL DEFAULT 'Admin';",
];
