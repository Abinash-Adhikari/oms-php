<?php

/**
 * SB-Tech — Profile-level weekly off days for the calendar.
 *
 * Stores the selected weekly off day names (e.g. ["Saturday"]) as a JSON
 * array.  These days render red on the office calendar.  NULL means none
 * selected; `[]` means "all deselected".
 */

$query = [
    "ALTER TABLE `tbl_office_profiles`
     ADD COLUMN `weekly_off_days` VARCHAR(200) DEFAULT NULL AFTER `use_date`;",
];

$rollbackQuery = [
    "ALTER TABLE `tbl_office_profiles` DROP COLUMN `weekly_off_days`;",
];