<?php

/**
 * SB-Tech — navigation map (mirrors reference admin/includes/varriables.php).
 *
 * Module keys are lowercase snake_case. Sidebar sections group modules by
 * working rhythm and job flow:
 * MAIN / MY OFFICE / PEOPLE & HR / GROWTH & SALES / FINANCE / INVENTORY
 * / REPORTS / OFFICE SETUP / SETTINGS.
 *
 * Phase 1 = presentation reorg (labels/ordering/grouping, keys unchanged).
 * Phase 2 = structural consolidation. hr_care moved my_office → staff_management
 * (files + route canonical map below); inventory/Reports retired from the
 * sidebar (canonical home = reports/Inventory); dead settings submodules
 * (office_profile, permissions) removed (canonical homes in office_setup and
 * staff_management). Quotations consolidation is deferred (data migration).
 */

$icons = [
    'dashboard'        => 'nav-icon fas fa-tachometer-alt',
    'my_office'        => 'nav-icon fas fa-briefcase',
    'staff_management' => 'nav-icon fas fa-users',
    'leads'            => 'nav-icon fas fa-filter',
    'accounts'         => 'nav-icon fas fa-money-bill',
    'inventory'        => 'nav-icon fas fa-boxes',
    'reports'          => 'nav-icon fas fa-chart-bar',
    'office_setup'     => 'nav-icon fas fa-building',
    'communication'    => 'nav-icon fas fa-envelope',
    'webcms'           => 'nav-icon fas fa-globe',
    'settings'         => 'nav-icon fas fa-cogs',
];

$modules = [
    'dashboard',
    'my_office',
    'staff_management',
    'webcms',
    'leads',
    'sales',
    'communication',
    'accounts',
    'inventory',
    'reports',
    'office_setup',
    'settings',
];

$navBars = [
    'dashboard'        => 'Dashboard',
    'my_office'        => 'My Office',
    'staff_management' => 'Staff Management',
    'webcms'           => 'Website CMS',
    'leads'            => 'Leads',
    'sales'            => 'Sales Documents',
    'accounts'         => 'Accounts',
    'inventory'        => 'Inventory',
    'reports'          => 'Reports',
    'office_setup'     => 'Office Setup',
    'communication'    => 'Communication',
    'webcms'           => 'Website CMS',
    'settings'         => 'Settings',
];

$subNavBars = [
    'my_office' => [
        'office_calendar'  => 'Office Calendar',
        'office_spaces'    => 'Office Spaces',
    ],
    'reports' => [
        'overview'     => 'Overview',
        'attendance'   => 'Attendance',
        'leave'        => 'Leave',
        'tasks'        => 'Tasks',
        'leads'        => 'Leads & Pipeline',
        'finance'      => 'Finance',
        'inventory'    => 'Inventory',
        'staff'        => 'Staff',
        'audit'        => 'Audit Log',
    ],
    'staff_management' => [
        'add_staff'          => 'Employees',
        'staff_daily_tasks'  => 'Daily Tasks',
        'leave_management'   => 'Leave Management',
        'staff_history'      => 'Staff History',
        'terminated_staffs'  => 'Terminated Staffs',
        'hr_care'            => 'HR Care',
        'permissions'        => 'Module Permission',
    ],
    'leads' => [
        'leads'       => 'Leads',
        'clients'     => 'Clients',
        'quotations'  => 'Quotations',
    ],
    'sales' => [
        'documents'     => 'Documents',
    ],
    'accounts' => [
        'postings'             => 'Posting',
        'ledger'               => 'Ledger',
        'expense_claims'       => 'Expense Claims',
        'bank_reconciliation'  => 'Bank Reconciliation',
        'chart_of_account'     => 'Chart of Accounts',
        'fiscal_years'         => 'Fiscal Years',
        'account_reports'      => 'Account Reports',
    ],
    'inventory' => [
        'items'                 => 'Items',
        'stock'                 => 'Stock',
        'movements'             => 'Stock Movements',
        'categories'            => 'Categories',
        'suppliers'             => 'Suppliers',
        'purchase_requisitions' => 'Purchase Requisitions',
        'assets'                => 'Assets',
    ],
    'office_setup' => [
        'office_profile'  => 'Profile',
        'departments'     => 'Departments',
        'designations'    => 'Designations',
        'holidays'        => 'Holidays',
        'meeting_halls'   => 'Meeting Halls',
        'bank_details'    => 'Bank Details',
        'documents'       => 'Document Registry',
    ],
    'communication' => [
        'email_sms'   => 'Email/SMS',
        'templates'   => 'Templates',
        'logs'        => 'Logs',
    ],
    'webcms' => [
        'cms_home'     => 'Home',
        'services'     => 'Services',
        'projects'     => 'Projects',
        'news'         => 'News',
        'notices'      => 'Notices',
        'careers'      => 'Careers',
        'team'         => 'Team',
        'contact'      => 'Contact',
        'webcms_setup' => 'Setup',
    ],
    'settings' => [
        'users'          => 'Users',
        'document_setup' => 'PDF/Word Setup',
    ],
];

/**
 * Per-submodule icons (premium sidebar: distinct icon per child instead of
 * the generic far fa-circle). Missing keys fall back to the circle icon.
 */
$subIcons = [
    // my_office
    'hr_care'               => 'nav-icon fas fa-user-shield',
    'office_calendar'       => 'nav-icon far fa-calendar-alt',
    'office_spaces'         => 'nav-icon fas fa-door-open',
    // staff_management
    'add_staff'             => 'nav-icon fas fa-users',
    'staff_daily_tasks'     => 'nav-icon fas fa-clipboard-list',
    'leave_management'      => 'nav-icon fas fa-plane-departure',
    'permissions'           => 'nav-icon fas fa-user-lock',
    'staff_history'         => 'nav-icon fas fa-history',
    'terminated_staffs'     => 'nav-icon fas fa-user-slash',
    // leads
    'leads'                 => 'nav-icon fas fa-filter',
    'clients'               => 'nav-icon fas fa-handshake',
    // accounts
    'postings'              => 'nav-icon fas fa-file-invoice-dollar',
    'ledger'                => 'nav-icon fas fa-book',
    'account_reports'       => 'nav-icon far fa-chart-bar',
    'expense_claims'        => 'nav-icon fas fa-receipt',
    'fiscal_years'          => 'nav-icon far fa-calendar-check',
    'chart_of_account'      => 'nav-icon fas fa-sitemap',
    'bank_reconciliation'   => 'nav-icon fas fa-university',
    // inventory
    'items'                 => 'nav-icon fas fa-box-open',
    'categories'            => 'nav-icon fas fa-tags',
    'suppliers'             => 'nav-icon fas fa-truck',
    'stock'                 => 'nav-icon fas fa-warehouse',
    'movements'             => 'nav-icon fas fa-exchange-alt',
    'purchase_requisitions' => 'nav-icon fas fa-shopping-cart',
    'assets'                => 'nav-icon fas fa-laptop',
    'reports'               => 'nav-icon far fa-chart-bar',
    // reports
    'overview'              => 'nav-icon fas fa-tachometer-alt',
    'attendance'            => 'nav-icon far fa-clock',
    'leave'                 => 'nav-icon far fa-calendar-times',
    'tasks'                 => 'nav-icon fas fa-tasks',
    'finance'               => 'nav-icon fas fa-coins',
    'staff'                 => 'nav-icon fas fa-id-badge',
    'audit'                 => 'nav-icon fas fa-clipboard-check',
    // office_setup
    'office_profile'        => 'nav-icon fas fa-building',
    'departments'           => 'nav-icon fas fa-layer-group',
    'designations'          => 'nav-icon fas fa-id-card',
    'holidays'              => 'nav-icon fas fa-umbrella-beach',
    'bank_details'          => 'nav-icon fas fa-piggy-bank',
    'meeting_halls'         => 'nav-icon fas fa-chalkboard',
    'documents'             => 'nav-icon far fa-folder-open',
    'quotations'            => 'nav-icon fas fa-file-invoice',
    // sales
    'sales'                 => 'nav-icon fas fa-handshake',
    'documents'             => 'nav-icon fas fa-file-alt',
    // communication
    'email_sms'             => 'nav-icon far fa-envelope',
    'templates'             => 'nav-icon fas fa-clone',
    'logs'                  => 'nav-icon far fa-file-code',
    // webcms
    'cms_home'              => 'nav-icon fas fa-home',
    'services'              => 'nav-icon fas fa-concierge-bell',
    'projects'              => 'nav-icon fas fa-project-diagram',
    'team'                  => 'nav-icon fas fa-user-friends',
    'news'                  => 'nav-icon far fa-newspaper',
    'notices'               => 'nav-icon fas fa-bullhorn',
    'careers'               => 'nav-icon fas fa-briefcase',
    'contact'               => 'nav-icon far fa-address-book',
    'webcms_setup'          => 'nav-icon fas fa-sliders-h',
    // settings
    'users'                 => 'nav-icon fas fa-user-cog',
    'document_setup'        => 'nav-icon far fa-file-pdf',
];

/**
 * Live count badges per module/page key (premium sidebar). Each entry is
 * ['label' => permission module, 'page' => permission page, 'sql' => COUNT query].
 * Rendered only when the user holds the permission; failures are silent.
 */
$navBadgeQueries = [
    'leads' => [
        'page' => 'leads',
        'sql'  => "SELECT COUNT(*) FROM `tbl_leads` WHERE `stage` = 'New' AND (`is_active` = 1 OR `is_active` IS NULL)",
        'title' => 'New leads',
    ],
    'leave_management' => [
        'module' => 'staff_management',
        'page'   => 'leave_management',
        'sql'    => "SELECT COUNT(*) FROM `tbl_staff_leave_applications` WHERE `status` = 'Pending'",
        'title'  => 'Pending leave applications',
    ],
    'expense_claims' => [
        'module' => 'accounts',
        'page'   => 'expense_claims',
        'sql'    => "SELECT COUNT(*) FROM `tbl_expense_claims` WHERE `status` IN ('Submitted','Approved')",
        'title'  => 'Expense claims awaiting payment',
    ],
];

/** Valid pages per module (used for sidebar highlighting + page routing). */
$pages = [
    'dashboard'        => ['home'],
    'my_office'        => ['office_calendar', 'office_spaces'],
    'staff_management' => ['add_staff', 'staff_daily_tasks', 'leave_management', 'staff_history', 'terminated_staffs', 'hr_care', 'permissions'],
    'webcms'           => ['cms_home', 'services', 'projects', 'news', 'notices', 'careers', 'team', 'contact', 'webcms_setup'],
    'leads'            => ['leads', 'clients', 'quotations'],
    'sales'            => ['documents'],
    'communication'    => ['email_sms', 'templates', 'logs'],
    'accounts'         => ['postings', 'ledger', 'expense_claims', 'bank_reconciliation', 'chart_of_account', 'fiscal_years', 'account_reports'],
    'inventory'        => ['items', 'stock', 'movements', 'categories', 'suppliers', 'purchase_requisitions', 'assets', 'reports'],
    'reports'          => ['overview', 'attendance', 'leave', 'tasks', 'leads', 'finance', 'inventory', 'staff', 'audit'],
    'office_setup'     => ['office_profile', 'departments', 'designations', 'holidays', 'meeting_halls', 'bank_details', 'documents'],
    'settings'         => ['users', 'document_setup'],
];

/**
 * Phase 2 — canonical route map for moved submodules.
 *
 * Relative URLs like ?module=inventory&page=reports are 301-redirected (GET)
 * to the canonical home, and POSTs to 'post' => true entries are normalized
 * so moved forms keep dispatching to the canonical operation handler.
 *
 * Keys MUST stay in $pages (left there on purpose) so old bookmarks and
 * permission grants keep resolving through the redirect. 'post' => false
 * means the moved page keeps its original operation handler (e.g. Inventory
 * Reports CSV exports live at inventory/operation/reports_operation.php).
 *
 * NOTE (deferred): leads → quotations is NOT mapped here — it is a legacy
 * quotation system on tbl_quotations, separate from sales/documents type
 * 'quotation' (tbl_documents). Consolidation needs a data migration first.
 */
$routeCanonical = [
    'my_office'  => ['hr_care' => ['module' => 'staff_management', 'page' => 'hr_care', 'post' => true]],
    'inventory'  => ['reports' => ['module' => 'reports', 'page' => 'inventory', 'post' => false]],
    'settings'   => [
        'office_profile' => ['module' => 'office_setup', 'page' => 'office_profile', 'post' => false],
        'permissions'    => ['module' => 'staff_management', 'page' => 'permissions', 'post' => false],
    ],
];

/** Modules rendered directly as a page (no submodule shell). */
$singlePageModules = ['dashboard'];

/** Sidebar section labels (ALL CAPS — Phase 1 grouping; webcms/leads/sales/communication share GROWTH & SALES). */
$navSidebarSections = [
    'dashboard'        => 'MAIN',
    'my_office'        => 'MY OFFICE',
    'staff_management' => 'PEOPLE & HR',
    'webcms'           => 'GROWTH & SALES',
    'leads'            => 'GROWTH & SALES',
    'sales'            => 'GROWTH & SALES',
    'communication'    => 'GROWTH & SALES',
    'accounts'         => 'FINANCE',
    'inventory'        => 'INVENTORY',
    'reports'          => 'REPORTS',
    'office_setup'     => 'OFFICE SETUP',
    'settings'         => 'SETTINGS',
];
