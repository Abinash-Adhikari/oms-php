<?php
// Nepal FY 2083/84 (Shrawan 1 2083 = 2026-07-17 → Asar end 2084 = 2027-07-16),
// Finance Act 2083: one schedule for individuals and couples.
//   ≤10L 1% (social security tax) · 10–15L 10% · 15–25L 20% · 25–40L 27% · >40L 29%
// Retirement cap / fraction and the female rebate carry forward the prior
// years' rules — confirm with your auditor; everything is editable in
// Payroll → Tax Slabs.
$query = [
    "INSERT INTO `tbl_payroll_tax_years` (`title`, `start_date`, `end_date`, `retirement_cap`, `retirement_fraction`, `female_rebate_percent`, `notes`)
     SELECT '2083/84', '2026-07-17', '2027-07-16', 500000, 0.33333333, 10,
            'Finance Act 2083 slabs (single schedule; couple table removed). Seeded values — verify retirement cap, fraction, female rebate and the top-band rate (some sources describe it as 27% + 2% surcharge) with your auditor.'
     FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `tbl_payroll_tax_years` WHERE `title` = '2083/84');",
    "INSERT INTO `tbl_payroll_tax_slabs` (`tax_year_id`, `filing_status`, `lower_limit`, `upper_limit`, `rate`, `is_social_security_tax`, `position`)
     SELECT y.`id`, 'All', s.lo, s.hi, s.rate, s.sst, s.pos
     FROM `tbl_payroll_tax_years` y
     JOIN (SELECT 0 AS lo, 1000000 AS hi, 1 AS rate, 1 AS sst, 1 AS pos
           UNION ALL SELECT 1000000, 1500000, 10, 0, 2
           UNION ALL SELECT 1500000, 2500000, 20, 0, 3
           UNION ALL SELECT 2500000, 4000000, 27, 0, 4
           UNION ALL SELECT 4000000, NULL, 29, 0, 5) s
     WHERE y.`title` = '2083/84'
       AND NOT EXISTS (SELECT 1 FROM `tbl_payroll_tax_slabs` t WHERE t.`tax_year_id` = y.`id`);",
];
$rollbackQuery = [
    "DELETE FROM `tbl_payroll_tax_years` WHERE `title` = '2083/84';",
];
