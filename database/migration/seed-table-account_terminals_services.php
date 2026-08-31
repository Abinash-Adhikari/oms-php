<?php

$query = [
    "INSERT INTO `tbl_account_terminals` (`account_subgroup_id`, `title`, `position`) VALUES
    -- Current Assets (subgroup 1)
    (1, 'Cash in Hand', 1),
    (1, 'Bank Accounts', 2),
    (1, 'Accounts Receivable', 3),
    (1, 'Prepaid Expenses', 4),
    -- Fixed Assets (subgroup 2)
    (2, 'Office Equipment', 1),
    (2, 'Furniture & Fixtures', 2),
    (2, 'Vehicles', 3),
    -- Current Liabilities (subgroup 3)
    (3, 'Accounts Payable', 1),
    (3, 'TDS Payable', 2),
    (3, 'VAT Payable', 3),
    (3, 'Staff Payable', 4),
    -- Long Term Liabilities (subgroup 4)
    (4, 'Bank Loans', 1),
    -- Service Income (subgroup 5)
    (5, 'Software Development Income', 1),
    (5, 'Consulting Income', 2),
    (5, 'Maintenance & Support Income', 3),
    (5, 'Training Income', 4),
    -- Other Income (subgroup 6)
    (6, 'Interest Income', 1),
    (6, 'Miscellaneous Income', 2),
    -- Operating Expenses (subgroup 7)
    (7, 'Staff Salaries', 1),
    (7, 'Contractor Payments', 2),
    (7, 'Software & Licenses', 3),
    (7, 'Internet & Communication', 4),
    (7, 'Travel & Transportation', 5),
    -- Administrative Expenses (subgroup 8)
    (8, 'Office Rent', 1),
    (8, 'Utilities', 2),
    (8, 'Office Supplies', 3),
    (8, 'Professional Fees', 4),
    (8, 'Marketing & Advertising', 5),
    (8, 'Bank Charges', 6),
    -- Owner Capital (subgroup 9)
    (9, 'Owner Capital', 1)
    ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);",
];

$rollbackQuery = [
    "DELETE FROM `tbl_account_terminals`;",
];
