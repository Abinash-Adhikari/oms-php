<?php

/**
 * The lead form stored the referral person in the same columns as the lead's
 * own contact person (`contact_name`/`email`/`phone`), so a referrer — an
 * external person unrelated to the client organization — leaked into
 * `tbl_clients.contact_person` on conversion. Give the referrer its own
 * columns; the legacy values on Referral rows ARE referrer values, so they
 * move over and the contact columns are cleared for those rows only.
 */

$query = [
    "ALTER TABLE `tbl_leads`
     ADD COLUMN `referrer_name` VARCHAR(191) DEFAULT NULL AFTER `phone`,
     ADD COLUMN `referrer_email` VARCHAR(191) DEFAULT NULL AFTER `referrer_name`,
     ADD COLUMN `referrer_phone` VARCHAR(50) DEFAULT NULL AFTER `referrer_email`;",
    "UPDATE `tbl_leads`
     SET `referrer_name`  = `contact_name`,
         `referrer_email` = `email`,
         `referrer_phone` = `phone`,
         `contact_name`   = NULL,
         `email`          = NULL,
         `phone`          = NULL
     WHERE `source` = 'Referral';",
];

// NOTE: artisan executes $rollbackQuery with array_reverse(), so entries are
// written positionally mirroring $query (rollback[i] undoes query[i]) — that
// way the actual execution order is a true reverse of $query.
$rollbackQuery = [
    "ALTER TABLE `tbl_leads`
     DROP COLUMN `referrer_name`,
     DROP COLUMN `referrer_email`,
     DROP COLUMN `referrer_phone`;",
    "UPDATE `tbl_leads`
     SET `contact_name` = `referrer_name`,
         `email`        = `referrer_email`,
         `phone`        = `referrer_phone`
     WHERE `source` = 'Referral'
       AND (`referrer_name` IS NOT NULL OR `referrer_email` IS NOT NULL OR `referrer_phone` IS NOT NULL);",
];
