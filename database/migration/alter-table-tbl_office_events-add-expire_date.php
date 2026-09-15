<?php

/**
 * SB-Tech — Notes auto-expire. Notes (and any future event type) can carry
 * an optional expire_date; once the date passes the item stops appearing in
 * the calendar and lists. Notes raised from the Office Calendar auto-set
 * expire_date to the calendar date the note was added on.
 */

$query = [
    "ALTER TABLE `tbl_office_events`
     ADD COLUMN `expire_date` DATE DEFAULT NULL AFTER `remarks`;",
];

$rollbackQuery = [
    "ALTER TABLE `tbl_office_events` DROP COLUMN `expire_date`;",
];