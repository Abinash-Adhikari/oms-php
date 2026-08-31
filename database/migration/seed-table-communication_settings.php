<?php

$orgName = config('organization_name', 'SB-Tech');
$query = [
    // Insert Brevo SMTP defaults if no settings row exists yet.
    "INSERT IGNORE INTO `tbl_communication_settings`
     (`smtp_host`, `smtp_port`, `smtp_username`, `smtp_password_enc`, `smtp_from_name`, `smtp_from_email`, `is_active`, `added_by`)
     SELECT 'smtp-relay.brevo.com', 587, '', '', '" . $orgName . "', '', 1, 1
     FROM DUAL
     WHERE NOT EXISTS (SELECT 1 FROM `tbl_communication_settings` LIMIT 1);",
];

$rollbackQuery = [
    'DELETE FROM `tbl_communication_settings` WHERE `smtp_host` = \'smtp-relay.brevo.com\';',
];
