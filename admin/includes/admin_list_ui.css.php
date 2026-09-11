<?php
/**
 * Global admin list UI — legacy page compat + shared cms-ap scope.
 * Loaded on every admin page via head.php so unmigrated screens still match Transport/Accounts chrome.
 */
include __DIR__ . '/../modules/accounts/includes/postings_list_ui.css.php';
include __DIR__ . '/../modules/accounts/includes/accounts_filter_bar.css.php';
?>
<style id="admin-list-ui-global">
/* cms-ap: auto-added to .content-wrapper when no module *-ap class is present */
.cms-ap .content-header h1,
.cms-ap .content-header h1.mb-0 {
    font-weight: 600;
    font-size: 1.5rem;
}

.cms-ap .card.custom-card,
.cms-ap .card.card-default {
    border: 1px solid var(--cms-card-border, rgba(15, 23, 42, 0.08));
    border-radius: var(--cms-radius, 10px);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    background: var(--cms-card-bg, #fff);
}

.cms-ap .card.custom-card > .card-body,
.cms-ap .card.card-default > .card-body {
    padding: 1rem 1.1rem;
}

.cms-ap .table-responsive > .table.table-bordered,
.cms-ap .table-responsive > .table.table-hover,
.cms-ap .postings-detail-panel .table,
.cms-ap .table.table-bordered.table-hover {
    margin-bottom: 0;
}

.cms-ap .table thead th,
.cms-ap .table.table-bordered thead th {
    background: var(--cms-surface-muted, #f8fafc);
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    color: var(--cms-text-muted, #64748b);
    white-space: nowrap;
    vertical-align: middle;
    border-color: var(--cms-card-border, rgba(15, 23, 42, 0.08));
}

.cms-ap .table td {
    vertical-align: middle;
}

.cms-ap .table td:last-child {
    white-space: nowrap;
}

.cms-ap .table .badge {
    font-weight: 600;
}

/* Legacy Bootstrap buttons → ledger-filter look */
.cms-ap .btn.btn-primary,
.cms-ap .btn.btn-success:not(.btn-outline-success):not(.btn-outline-primary),
.cms-ap a.btn.btn-success {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    min-height: 2.25rem;
    padding: 0.35rem 0.9rem;
    border-radius: var(--cms-radius-sm, 8px);
    font-size: 0.875rem;
    font-weight: 600;
    background: var(--cms-accent, #10b981);
    border-color: var(--cms-accent, #10b981);
    color: #fff;
}

.cms-ap .btn.btn-primary:hover,
.cms-ap .btn.btn-success:not(.btn-outline-success):hover,
.cms-ap a.btn.btn-success:hover {
    background: var(--cms-accent-hover, #059669);
    border-color: var(--cms-accent-hover, #059669);
    color: #fff;
}

.cms-ap .btn.btn-secondary,
.cms-ap .btn.btn-default,
.cms-ap .btn.btn-outline-secondary {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    min-height: 2.25rem;
    padding: 0.35rem 0.9rem;
    border-radius: var(--cms-radius-sm, 8px);
    font-size: 0.875rem;
    font-weight: 600;
    background: var(--cms-input-bg, #fff);
    color: var(--cms-text, #0f172a);
    border: 1px solid var(--cms-input-border, rgba(15, 23, 42, 0.12));
}

.cms-ap .btn.btn-danger:not(.btn-outline-danger) {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    min-height: 2.25rem;
    padding: 0.35rem 0.9rem;
    border-radius: var(--cms-radius-sm, 8px);
    font-size: 0.875rem;
    font-weight: 600;
    background: #fff;
    color: #b91c1c;
    border: 1px solid rgba(185, 28, 28, 0.35);
}

.cms-ap .btn.btn-danger:not(.btn-outline-danger):hover {
    background: #fef2f2;
    color: #991b1b;
}

.cms-ap .btn.btn-outline-primary {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    min-height: 2.25rem;
    padding: 0.35rem 0.9rem;
    border-radius: var(--cms-radius-sm, 8px);
    font-size: 0.875rem;
    font-weight: 600;
    background: var(--cms-accent-soft, rgba(16, 185, 129, 0.14));
    color: var(--cms-accent-hover, #059669);
    border: 1px solid var(--cms-input-border, rgba(15, 23, 42, 0.12));
}

.cms-ap .btn-group .btn.btn-outline-primary.active,
.cms-ap .btn-group .btn.btn-outline-primary:active {
    background: var(--cms-accent, #10b981);
    color: #fff;
    border-color: var(--cms-accent, #10b981);
}

.cms-ap .dropdown-toggle.btn-primary,
.cms-ap .dropdown-toggle.btn-success,
.cms-ap .btn.dropdown-toggle.btn-sm {
    min-height: 2.25rem;
    border-radius: var(--cms-radius-sm, 8px);
    font-weight: 600;
}

.cms-ap .form-control,
.cms-ap select.form-control,
.cms-ap .custom-select {
    height: 2.25rem;
    border-radius: var(--cms-radius-sm, 8px);
    border: 1px solid var(--cms-input-border, rgba(15, 23, 42, 0.12));
    font-size: 0.875rem;
}

.cms-ap textarea.form-control {
    height: auto;
    min-height: 4.5rem;
}

.cms-ap .form-control:focus,
.cms-ap select.form-control:focus {
    border-color: var(--cms-accent, #10b981);
    box-shadow: 0 0 0 3px var(--cms-accent-soft, rgba(16, 185, 129, 0.14));
}

.cms-ap label.col-form-label,
.cms-ap .form-group > label:not(.sr-only):not(.ledger-filter__label) {
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    color: var(--cms-text-muted, #64748b);
}

.cms-ap .card.border-primary > .card-header.bg-primary,
.cms-ap .card.border-success > .card-header.bg-success,
.cms-ap .postings-add-panel > .card-header.bg-primary {
    background: var(--cms-accent, #10b981) !important;
    border: 0;
}

.cms-ap .card-footer,
.cms-ap .modal-footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
    padding: 0.85rem 1.1rem;
    background: var(--cms-surface-muted, #f8fafc);
    border-top: 1px solid var(--cms-card-border, rgba(15, 23, 42, 0.08));
}

.cms-ap .modal .modal-content {
    border: 1px solid var(--cms-card-border, rgba(15, 23, 42, 0.08));
    border-radius: 0.65rem;
    overflow: hidden;
    box-shadow: 0 12px 40px rgba(15, 23, 42, 0.18);
}

.cms-ap .modal .modal-header {
    padding: 0.85rem 1.1rem;
    background: var(--cms-surface-muted, #f8fafc);
    border-bottom: 1px solid var(--cms-card-border, rgba(15, 23, 42, 0.08));
}

.cms-ap .modal .modal-title {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
}

.cms-ap .modal .modal-body {
    padding: 1rem 1.1rem;
}

.cms-ap .card.p-3.bg-light,
.cms-ap .card.bg-light.border {
    background: var(--cms-card-bg, #fff) !important;
    border: 1px solid var(--cms-card-border, rgba(15, 23, 42, 0.08)) !important;
    border-radius: var(--cms-radius, 10px);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    padding: 0.65rem 0.85rem !important;
}

.cms-ap .page-header {
    padding: 0 0 1rem;
}

.cms-ap .page-header .main-content-title {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 0.35rem;
}

/* Info / print / split actions → theme accent (not Bootstrap cyan) */
.cms-ap .btn.btn-info,
.cms-ap a.btn.btn-info,
.cms-ap .btn-group > .btn.btn-info,
.cms-ap .voucher-details-buttons .btn-info {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    min-height: 2.25rem;
    padding: 0.35rem 0.9rem;
    border-radius: var(--cms-radius-sm, 8px);
    font-size: 0.875rem;
    font-weight: 600;
    background: var(--cms-accent, #10b981);
    border-color: var(--cms-accent, #10b981);
    color: var(--cms-accent-contrast, #fff);
}
.cms-ap .btn.btn-info:hover,
.cms-ap a.btn.btn-info:hover,
.cms-ap .voucher-details-buttons .btn-info:hover {
    background: var(--cms-accent-hover, #059669);
    border-color: var(--cms-accent-hover, #059669);
    color: #fff;
}
.cms-ap .btn.btn-info.btn-sm,
.cms-ap a.btn.btn-info.btn-sm,
.cms-ap .table .btn.btn-sm {
    min-height: 1.85rem;
    padding: 0.2rem 0.65rem;
    font-size: 0.78rem;
}

/* View → soft accent; Remove stays red */
.cms-ap .btn.btn-warning,
.cms-ap a.btn.btn-warning,
.cms-ap .voucher-details-buttons .btn-warning {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    min-height: 2.25rem;
    padding: 0.35rem 0.9rem;
    border-radius: var(--cms-radius-sm, 8px);
    font-size: 0.875rem;
    font-weight: 600;
    background: var(--cms-accent-soft, rgba(16, 185, 129, 0.14));
    color: var(--cms-accent-hover, #059669);
    border: 1px solid var(--cms-input-border, rgba(15, 23, 42, 0.12));
}
.cms-ap .btn.btn-warning:hover,
.cms-ap a.btn.btn-warning:hover {
    background: var(--cms-accent, #10b981);
    color: #fff;
    border-color: var(--cms-accent, #10b981);
}
.cms-ap .btn.btn-warning[value="Remove"],
.cms-ap input.btn.btn-warning[value="Remove"] {
    background: #fff;
    color: #b91c1c;
    border: 1px solid rgba(185, 28, 28, 0.35);
}

.cms-ap .bg-primary,
.cms-ap .card-header.bg-primary,
.cms-ap .badge-primary,
.cms-ap .badge-info,
.cms-ap .voucher-details-buttons .badge-info {
    background-color: var(--cms-accent, #10b981) !important;
    border-color: var(--cms-accent, #10b981) !important;
    color: var(--cms-accent-contrast, #fff) !important;
}
.cms-ap .text-primary,
.cms-ap .voucher-details-expanded a,
.cms-ap table.dataTable tbody td a:not(.btn):not(.dropdown-item) {
    color: var(--cms-accent, #10b981);
}
.cms-ap .voucher-details-expanded a:hover,
.cms-ap table.dataTable tbody td a:not(.btn):hover {
    color: var(--cms-accent-hover, #059669);
}
.cms-ap .border-primary {
    border-color: var(--cms-accent, #10b981) !important;
}
.cms-ap .voucher-details-buttons {
    border-left-color: var(--cms-accent, #10b981);
}

.cms-ap .page-item.active .page-link,
.cms-ap .pagination > .active > a,
.cms-ap .pagination > .active > span {
    background-color: var(--cms-accent, #10b981);
    border-color: var(--cms-accent, #10b981);
    color: #fff;
}
.cms-ap .page-link {
    color: var(--cms-accent, #10b981);
}

.cms-ap .dataTables_wrapper .dataTables_filter input,
.cms-ap .dataTables_wrapper .dataTables_length select {
    height: 2.25rem;
    border-radius: var(--cms-radius-sm, 8px);
    border: 1px solid var(--cms-input-border, rgba(15, 23, 42, 0.12));
    font-size: 0.875rem;
    padding: 0.25rem 0.65rem;
    background: var(--cms-input-bg, #fff);
    color: var(--cms-text, #0f172a);
}
.cms-ap .dataTables_wrapper .dataTables_filter input:focus,
.cms-ap .dataTables_wrapper .dataTables_length select:focus {
    border-color: var(--cms-accent, #10b981);
    box-shadow: 0 0 0 3px var(--cms-accent-soft, rgba(16, 185, 129, 0.14));
    outline: none;
}
.cms-ap .dataTables_wrapper .dataTables_paginate .paginate_button {
    border-radius: var(--cms-radius-sm, 8px) !important;
    border: 1px solid var(--cms-input-border, rgba(15, 23, 42, 0.12)) !important;
}
.cms-ap .dataTables_wrapper .dataTables_paginate .paginate_button.current,
.cms-ap .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
    background: var(--cms-accent, #10b981) !important;
    border-color: var(--cms-accent, #10b981) !important;
    color: #fff !important;
}
.cms-ap .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: var(--cms-accent-soft, rgba(16, 185, 129, 0.14)) !important;
    color: var(--cms-accent-hover, #059669) !important;
    border-color: var(--cms-accent, #10b981) !important;
}

.cms-ap .acct-table thead th,
.cms-ap .finance-table thead th {
    background: var(--cms-accent-soft, rgba(16, 185, 129, 0.14));
    color: var(--cms-text-muted, #64748b);
    border-color: var(--cms-card-border, rgba(15, 23, 42, 0.08));
}

.cms-ap .card.toolbar-card {
    border: 1px solid var(--cms-card-border, rgba(15, 23, 42, 0.08));
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.cms-ap .nav-pills .nav-link.active,
.cms-ap .nav-tabs .nav-link.active {
    background-color: var(--cms-accent, #10b981);
    border-color: var(--cms-accent, #10b981);
    color: #fff;
}
</style>
