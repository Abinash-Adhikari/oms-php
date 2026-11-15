<?php
/**
 * SB-Tech — Correspondence / shared register data (view-only partial).
 * Included by darta.php and chalani.php; expects $db already set.
 *
 * Sets: $correspondenceFiscalYears, $correspondenceCurrentFy,
 * $correspondenceOpenFy, $correspondenceCanRegister, $correspondenceStaff,
 * $correspondenceStatusBadges.
 */

$correspondenceFiscalYears = $db->select(
    'SELECT * FROM `tbl_fiscal_years` ORDER BY `starting_date` DESC'
);
$correspondenceCurrentFy = correspondence_current_fy($db);
$correspondenceStaff = $db->select(
    "SELECT `id`, `fullname` FROM `tbl_users_login` WHERE `status` = 'Active' ORDER BY `fullname` ASC"
);

// New numbers may only be allocated into an Open fiscal year; an entry that
// already exists keeps its (possibly closed) year. Registering therefore
// needs both a fiscal year covering today and at least one Open year.
$correspondenceOpenFy = array_values(array_filter(
    $correspondenceFiscalYears,
    static fn (array $fy): bool => ($fy['closing'] ?? '') === 'Open'
));
$correspondenceCanRegister = $correspondenceCurrentFy !== null && $correspondenceOpenFy !== [];

$correspondenceStatusBadges = [
    'darta' => [
        'Pending'     => 'warning',
        'In Progress' => 'info',
        'Disposed'    => 'success',
        'Filed'       => 'secondary',
        'Cancelled'   => 'danger',
    ],
    'chalani' => [
        'Reserved'   => 'warning',
        'Dispatched' => 'success',
        'Cancelled'  => 'danger',
    ],
];
