<?php

$files = [
    // --- Foundation & auth ---
    'create-table-migrations',
    'create-table-office_departments',
    'create-table-office_designation',
    'create-table-users_login',
    'seed-table-users_login',
    'create-table-login_attempts',
    'create-table-user_registered_devices',
    'create-table-user_profiles',
    'create-table-notifications',
    'create-table-calendars',
    'seed-table-calendar',

    // --- Office setup ---
    'create-table-office_profiles',
    'ensure_tbl_office_profiles_id_1',
    'create-table-office_bank_details',
    'create-table-office_holidays',
    'create-table-office_meeting_hall_setup',
    'create-table-office_spaces',

    // --- Staff ---
    'create-table-staff_documents',
    'create-table-staff_social_medias',
    'create-table-staff_history',
    'create-table-daily_tasks',
    'create-table-staff_attendances',

    // --- Leave ---
    'create-table-office_leave_configs',
    'create-table-office_staff_leave_allocation',
    'create-table-staff_leave_applications',

    // --- Office ops (tasks, meetings, grievances, documents) ---
    'create-table-office_tasks',
    'create-table-office_task_assignees',
    'create-table-office_task_files',
    'create-table-office_events',
    'create-table-office_event_schedules',
    'create-table-office_grievances',
    'create-table-office_grievance_files',
    'create-table-office_document_category',
    'create-table-office_documents',
    'create-table-office_document_files',

    // --- Finance: fiscal years & chart of accounts ---
    'create-table-fiscal_years',
    'seed-table-fiscal_years',
    'create-table-account_groups',
    'seed-table-account_groups',
    'create-table-account_sub_groups',
    'create-table-account_terminals',
    'create-table-account_sub_terminals',
    'seed-table-account_sub_groups_services',
    'seed-table-account_terminals_services',

    // --- Finance: vouchers, ledger, tax, claims ---
    'create-table-journal_vouchers',
    'create-table-receipt_vouchers',
    'create-table-payment_vouchers',
    'create-table-contra_vouchers',
    'create-table-purchase_vouchers',
    'create-table-sales_vouchers',
    'create-table-ledger_particulars',
    'create-table-sub_ledger_particulars',
    'create-table-bank_reconciliation',
    'create-table-ledger_closings',
    'create-table-voucher_logs',
    'create-table-account_tds_types',
    'create-table-account_tds_report_entries',
    'create-table-account_dr_cr_notes',
    'create-table-account_confirmation_letters',
    'create-table-expense_claims',
    'create-table-expense_claim_files',

    // --- Leads & clients ---
    'create-table-clients',
    'create-table-leads',
    'create-table-client_projects',
    'create-table-lead_activities',
    'create-table-lead_files',
    'create-table-cms_contacts_us',
    'alter-table-leads-add-client_id',

    // --- Communication ---
    'create-table-communication_templates',
    'create-table-communication_campaigns',
    'create-table-communication_logs',
    'create-table-communication_settings',
    'create-table-communication_signatures',

    // --- Website CMS ---
    'create-table-cms_setup',
    'create-table-cms_hero',
    'create-table-cms_services',
    'create-table-cms_abouts',
    'create-table-cms_projects',
    'create-table-cms_gallery_categories',
    'create-table-cms_galleries',
    'create-table-cms_staffs',
    'create-table-cms_news',
    'create-table-cms_notices',
    'create-table-cms_messages',
    'create-table-cms_testimonials',
    'create-table-cms_careers',
    'create-table-cms_career_applications',

    // --- Performance: add missing indexes ---
    'add-index-cms-active-columns',

    // --- Settings: PDF/Word document setup ---
    'create-table-document_settings',
    'alter-table-document_settings-add-letterhead_style',

    // --- Sales: quotations & proposals ---
    'create-table-quotations',

    // --- SEO: slug columns for detail pages ---
    'add-slug-columns-cms-services-projects',

    // --- Document Engine: unified tables ---
    'create-table-document-engine',
    'add-type-specific-columns-documents',
    'add-fk-document-engine',
    'ensure-fk-document-engine',
    'add-title-column-document-files',

    // --- Client projects: package/db_name + lead-to-project link ---
    'alter-table-client_projects-add-package-db_name',
    'alter-table-leads-add-project_id',
    'alter-table-client_projects-make-client-optional',
    'alter-table-clients-add-module-entitlements',

    // --- Sales pipeline re-model: catalog projects + business sources ---
    'create-table-projects',
    'rename-table-clients-to-business_sources',
    'alter-table-business_sources-add-type',
    'create-table-business_source_contacts',
    'alter-table-client_projects-provisioning',
    'alter-table-leads-repoint-business-source',
    'alter-table-leads-repoint-project-catalog',

    // --- Rename business_sources back to clients ---
    'rename-tables-back-to-clients',

    // --- Office calendar: profile-selected weekly off days ---
    'alter-table-tbl_office_profiles-add-weekly_off_days',

    // --- Office calendar: personal to-do items ---
    'add-table-tbl_office_todos',

    // --- Office notes: auto-expire ---
    'alter-table-tbl_office_events-add-expire_date',

    // --- Inventory: assets, stock, procurement ---
    'create-table-inv_categories',
    'create-table-inv_items',
    'create-table-inv_assets',
    'create-table-inv_asset_logs',
    'create-table-inv_suppliers',
    'create-table-inv_stock',
    'create-table-inv_stock_movements',
    'create-table-inv_purchase_requisitions',
    'create-table-inv_purchase_requisition_items',

    // --- Audit log ---
    'create-table_audit_log',

    // --- Notifications: title + url columns for SSE dropdown ---
    'alter-table-notifications-add-title-url',

    // --- Communication: seed default SMTP settings ---
    'seed-table-communication_settings',

    // Append new migration filenames below only (do not insert in the middle).
];
