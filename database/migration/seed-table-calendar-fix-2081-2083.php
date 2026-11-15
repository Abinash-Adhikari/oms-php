<?php

/**
 * Migration: fix BS 2081, 2082, 2083 month lengths in tbl_calendar.
 *
 * The original seed (seed-table-calendar) shipped three years with wrong month
 * lengths, so adToBs()/bsToAd() were off by one day inside the affected
 * windows. Most visibly Ashoj 2083 was stored as 30 days (2026-09-17 ..
 * 2026-10-16) instead of 31 (2026-09-17 .. 2026-10-17), which mapped
 * 2026-10-17 to 2083-07-01 instead of 2083-06-31 and shifted every
 * conversion until Falgun 2083.
 *
 * Correct lengths (Nepal Panchanga Nirnayak Samiti / Hamro Patro):
 *   2081: 31,32,31,32,31,30,30,30,29,30,29,31  (366 days)
 *   2082: 31,31,32,31,31,31,30,29,30,29,30,30  (365 days)
 *   2083: 31,31,32,31,31,31,30,29,30,29,30,30  (365 days)
 *
 * Year start dates are unchanged (2081-01-01 = 2024-04-13,
 * 2082-01-01 = 2025-04-14, 2083-01-01 = 2026-04-14).
 */
$query = [
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2024-05-14', `eng_end_date` = '2024-06-14', `no_days` = 32 WHERE `nepali_year` = 2081 AND `month_code` = 2;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2024-06-15', `eng_end_date` = '2024-07-15', `no_days` = 31 WHERE `nepali_year` = 2081 AND `month_code` = 3;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2025-02-13', `eng_end_date` = '2025-03-13', `no_days` = 29 WHERE `nepali_year` = 2081 AND `month_code` = 11;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2025-03-14', `eng_end_date` = '2025-04-13', `no_days` = 31 WHERE `nepali_year` = 2081 AND `month_code` = 12;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2025-04-14', `eng_end_date` = '2025-05-14', `no_days` = 31 WHERE `nepali_year` = 2082 AND `month_code` = 1;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2025-05-15', `eng_end_date` = '2025-06-14', `no_days` = 31 WHERE `nepali_year` = 2082 AND `month_code` = 2;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2025-06-15', `eng_end_date` = '2025-07-16', `no_days` = 32 WHERE `nepali_year` = 2082 AND `month_code` = 3;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2025-07-17', `eng_end_date` = '2025-08-16', `no_days` = 31 WHERE `nepali_year` = 2082 AND `month_code` = 4;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2025-09-17', `eng_end_date` = '2025-10-17', `no_days` = 31 WHERE `nepali_year` = 2082 AND `month_code` = 6;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2025-10-18', `eng_end_date` = '2025-11-16', `no_days` = 30 WHERE `nepali_year` = 2082 AND `month_code` = 7;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2025-11-17', `eng_end_date` = '2025-12-15', `no_days` = 29 WHERE `nepali_year` = 2082 AND `month_code` = 8;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2025-12-16', `eng_end_date` = '2026-01-14', `no_days` = 30 WHERE `nepali_year` = 2082 AND `month_code` = 9;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2026-01-15', `eng_end_date` = '2026-02-12', `no_days` = 29 WHERE `nepali_year` = 2082 AND `month_code` = 10;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2026-09-17', `eng_end_date` = '2026-10-17', `no_days` = 31 WHERE `nepali_year` = 2083 AND `month_code` = 6;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2026-10-18', `eng_end_date` = '2026-11-16', `no_days` = 30 WHERE `nepali_year` = 2083 AND `month_code` = 7;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2026-11-17', `eng_end_date` = '2026-12-15', `no_days` = 29 WHERE `nepali_year` = 2083 AND `month_code` = 8;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2026-12-16', `eng_end_date` = '2027-01-14', `no_days` = 30 WHERE `nepali_year` = 2083 AND `month_code` = 9;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2027-01-15', `eng_end_date` = '2027-02-12', `no_days` = 29 WHERE `nepali_year` = 2083 AND `month_code` = 10;",
    // A Draft run created before the fix keeps the old bounds and would
    // silently skip the last day of its month. Approve/recalculate rebuilds
    // the lines from the (now corrected) run bounds; Approved/Posted/Paid
    // runs are left alone so historical payroll is never rewritten.
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2024-05-14', `period_end` = '2024-06-14', `period_days` = 32 WHERE `period_key` = '2081-02' AND `status` = 'Draft' AND `period_start` = '2024-05-14' AND `period_end` = '2024-06-13';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2024-06-15', `period_end` = '2024-07-15', `period_days` = 31 WHERE `period_key` = '2081-03' AND `status` = 'Draft' AND `period_start` = '2024-06-14' AND `period_end` = '2024-07-15';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2025-02-13', `period_end` = '2025-03-13', `period_days` = 29 WHERE `period_key` = '2081-11' AND `status` = 'Draft' AND `period_start` = '2025-02-13' AND `period_end` = '2025-03-14';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2025-03-14', `period_end` = '2025-04-13', `period_days` = 31 WHERE `period_key` = '2081-12' AND `status` = 'Draft' AND `period_start` = '2025-03-15' AND `period_end` = '2025-04-13';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2025-04-14', `period_end` = '2025-05-14', `period_days` = 31 WHERE `period_key` = '2082-01' AND `status` = 'Draft' AND `period_start` = '2025-04-14' AND `period_end` = '2025-05-13';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2025-05-15', `period_end` = '2025-06-14', `period_days` = 31 WHERE `period_key` = '2082-02' AND `status` = 'Draft' AND `period_start` = '2025-05-14' AND `period_end` = '2025-06-14';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2025-06-15', `period_end` = '2025-07-16', `period_days` = 32 WHERE `period_key` = '2082-03' AND `status` = 'Draft' AND `period_start` = '2025-06-15' AND `period_end` = '2025-07-15';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2025-07-17', `period_end` = '2025-08-16', `period_days` = 31 WHERE `period_key` = '2082-04' AND `status` = 'Draft' AND `period_start` = '2025-07-16' AND `period_end` = '2025-08-16';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2025-09-17', `period_end` = '2025-10-17', `period_days` = 31 WHERE `period_key` = '2082-06' AND `status` = 'Draft' AND `period_start` = '2025-09-17' AND `period_end` = '2025-10-16';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2025-10-18', `period_end` = '2025-11-16', `period_days` = 30 WHERE `period_key` = '2082-07' AND `status` = 'Draft' AND `period_start` = '2025-10-17' AND `period_end` = '2025-11-15';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2025-11-17', `period_end` = '2025-12-15', `period_days` = 29 WHERE `period_key` = '2082-08' AND `status` = 'Draft' AND `period_start` = '2025-11-16' AND `period_end` = '2025-12-15';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2025-12-16', `period_end` = '2026-01-14', `period_days` = 30 WHERE `period_key` = '2082-09' AND `status` = 'Draft' AND `period_start` = '2025-12-16' AND `period_end` = '2026-01-13';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2026-01-15', `period_end` = '2026-02-12', `period_days` = 29 WHERE `period_key` = '2082-10' AND `status` = 'Draft' AND `period_start` = '2026-01-14' AND `period_end` = '2026-02-12';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2026-09-17', `period_end` = '2026-10-17', `period_days` = 31 WHERE `period_key` = '2083-06' AND `status` = 'Draft' AND `period_start` = '2026-09-17' AND `period_end` = '2026-10-16';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2026-10-18', `period_end` = '2026-11-16', `period_days` = 30 WHERE `period_key` = '2083-07' AND `status` = 'Draft' AND `period_start` = '2026-10-17' AND `period_end` = '2026-11-15';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2026-11-17', `period_end` = '2026-12-15', `period_days` = 29 WHERE `period_key` = '2083-08' AND `status` = 'Draft' AND `period_start` = '2026-11-16' AND `period_end` = '2026-12-15';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2026-12-16', `period_end` = '2027-01-14', `period_days` = 30 WHERE `period_key` = '2083-09' AND `status` = 'Draft' AND `period_start` = '2026-12-16' AND `period_end` = '2027-01-13';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2027-01-15', `period_end` = '2027-02-12', `period_days` = 29 WHERE `period_key` = '2083-10' AND `status` = 'Draft' AND `period_start` = '2027-01-14' AND `period_end` = '2027-02-12';",
];

$rollbackQuery = [
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2024-05-14', `eng_end_date` = '2024-06-13', `no_days` = 31 WHERE `nepali_year` = 2081 AND `month_code` = 2;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2024-06-14', `eng_end_date` = '2024-07-15', `no_days` = 32 WHERE `nepali_year` = 2081 AND `month_code` = 3;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2025-02-13', `eng_end_date` = '2025-03-14', `no_days` = 30 WHERE `nepali_year` = 2081 AND `month_code` = 11;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2025-03-15', `eng_end_date` = '2025-04-13', `no_days` = 30 WHERE `nepali_year` = 2081 AND `month_code` = 12;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2025-04-14', `eng_end_date` = '2025-05-13', `no_days` = 30 WHERE `nepali_year` = 2082 AND `month_code` = 1;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2025-05-14', `eng_end_date` = '2025-06-14', `no_days` = 32 WHERE `nepali_year` = 2082 AND `month_code` = 2;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2025-06-15', `eng_end_date` = '2025-07-15', `no_days` = 31 WHERE `nepali_year` = 2082 AND `month_code` = 3;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2025-07-16', `eng_end_date` = '2025-08-16', `no_days` = 32 WHERE `nepali_year` = 2082 AND `month_code` = 4;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2025-09-17', `eng_end_date` = '2025-10-16', `no_days` = 30 WHERE `nepali_year` = 2082 AND `month_code` = 6;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2025-10-17', `eng_end_date` = '2025-11-15', `no_days` = 30 WHERE `nepali_year` = 2082 AND `month_code` = 7;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2025-11-16', `eng_end_date` = '2025-12-15', `no_days` = 30 WHERE `nepali_year` = 2082 AND `month_code` = 8;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2025-12-16', `eng_end_date` = '2026-01-13', `no_days` = 29 WHERE `nepali_year` = 2082 AND `month_code` = 9;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2026-01-14', `eng_end_date` = '2026-02-12', `no_days` = 30 WHERE `nepali_year` = 2082 AND `month_code` = 10;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2026-09-17', `eng_end_date` = '2026-10-16', `no_days` = 30 WHERE `nepali_year` = 2083 AND `month_code` = 6;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2026-10-17', `eng_end_date` = '2026-11-15', `no_days` = 30 WHERE `nepali_year` = 2083 AND `month_code` = 7;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2026-11-16', `eng_end_date` = '2026-12-15', `no_days` = 30 WHERE `nepali_year` = 2083 AND `month_code` = 8;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2026-12-16', `eng_end_date` = '2027-01-13', `no_days` = 29 WHERE `nepali_year` = 2083 AND `month_code` = 9;",
    "UPDATE `tbl_calendar` SET `eng_start_date` = '2027-01-14', `eng_end_date` = '2027-02-12', `no_days` = 30 WHERE `nepali_year` = 2083 AND `month_code` = 10;",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2024-05-14', `period_end` = '2024-06-13', `period_days` = 31 WHERE `period_key` = '2081-02' AND `status` = 'Draft' AND `period_start` = '2024-05-14' AND `period_end` = '2024-06-14';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2024-06-14', `period_end` = '2024-07-15', `period_days` = 32 WHERE `period_key` = '2081-03' AND `status` = 'Draft' AND `period_start` = '2024-06-15' AND `period_end` = '2024-07-15';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2025-02-13', `period_end` = '2025-03-14', `period_days` = 30 WHERE `period_key` = '2081-11' AND `status` = 'Draft' AND `period_start` = '2025-02-13' AND `period_end` = '2025-03-13';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2025-03-15', `period_end` = '2025-04-13', `period_days` = 30 WHERE `period_key` = '2081-12' AND `status` = 'Draft' AND `period_start` = '2025-03-14' AND `period_end` = '2025-04-13';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2025-04-14', `period_end` = '2025-05-13', `period_days` = 30 WHERE `period_key` = '2082-01' AND `status` = 'Draft' AND `period_start` = '2025-04-14' AND `period_end` = '2025-05-14';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2025-05-14', `period_end` = '2025-06-14', `period_days` = 32 WHERE `period_key` = '2082-02' AND `status` = 'Draft' AND `period_start` = '2025-05-15' AND `period_end` = '2025-06-14';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2025-06-15', `period_end` = '2025-07-15', `period_days` = 31 WHERE `period_key` = '2082-03' AND `status` = 'Draft' AND `period_start` = '2025-06-15' AND `period_end` = '2025-07-16';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2025-07-16', `period_end` = '2025-08-16', `period_days` = 32 WHERE `period_key` = '2082-04' AND `status` = 'Draft' AND `period_start` = '2025-07-17' AND `period_end` = '2025-08-16';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2025-09-17', `period_end` = '2025-10-16', `period_days` = 30 WHERE `period_key` = '2082-06' AND `status` = 'Draft' AND `period_start` = '2025-09-17' AND `period_end` = '2025-10-17';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2025-10-17', `period_end` = '2025-11-15', `period_days` = 30 WHERE `period_key` = '2082-07' AND `status` = 'Draft' AND `period_start` = '2025-10-18' AND `period_end` = '2025-11-16';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2025-11-16', `period_end` = '2025-12-15', `period_days` = 30 WHERE `period_key` = '2082-08' AND `status` = 'Draft' AND `period_start` = '2025-11-17' AND `period_end` = '2025-12-15';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2025-12-16', `period_end` = '2026-01-13', `period_days` = 29 WHERE `period_key` = '2082-09' AND `status` = 'Draft' AND `period_start` = '2025-12-16' AND `period_end` = '2026-01-14';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2026-01-14', `period_end` = '2026-02-12', `period_days` = 30 WHERE `period_key` = '2082-10' AND `status` = 'Draft' AND `period_start` = '2026-01-15' AND `period_end` = '2026-02-12';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2026-09-17', `period_end` = '2026-10-16', `period_days` = 30 WHERE `period_key` = '2083-06' AND `status` = 'Draft' AND `period_start` = '2026-09-17' AND `period_end` = '2026-10-17';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2026-10-17', `period_end` = '2026-11-15', `period_days` = 30 WHERE `period_key` = '2083-07' AND `status` = 'Draft' AND `period_start` = '2026-10-18' AND `period_end` = '2026-11-16';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2026-11-16', `period_end` = '2026-12-15', `period_days` = 30 WHERE `period_key` = '2083-08' AND `status` = 'Draft' AND `period_start` = '2026-11-17' AND `period_end` = '2026-12-15';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2026-12-16', `period_end` = '2027-01-13', `period_days` = 29 WHERE `period_key` = '2083-09' AND `status` = 'Draft' AND `period_start` = '2026-12-16' AND `period_end` = '2027-01-14';",
    "UPDATE `tbl_payroll_runs` SET `period_start` = '2027-01-14', `period_end` = '2027-02-12', `period_days` = 30 WHERE `period_key` = '2083-10' AND `status` = 'Draft' AND `period_start` = '2027-01-15' AND `period_end` = '2027-02-12';",
];
