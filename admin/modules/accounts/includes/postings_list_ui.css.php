<?php
/**
 * Shared list UI (filter toolbar + detail panel + table) for Account → Posting tabs.
 * Scoped under .postings-ap, .library-ap, .office-ap, .attendance-ap, .academics-ap, .exam-ap, .webcms-ap, .fees-ap, and .cms-ap (auto on unmigrated pages).
 */
?>
<style>.postings-ap .postings-tab-panel > .card.custom-card,
    .library-ap .postings-tab-panel > .card.custom-card,
    .office-ap .postings-tab-panel > .card.custom-card,
    .postings-ap .postings-tab-panel > .card.border-primary,
    .library-ap .postings-tab-panel > .card.border-primary,
    .office-ap .postings-tab-panel > .card.border-primary,
    .attendance-ap .postings-tab-panel > .card.custom-card,
    .attendance-ap .postings-tab-panel > .card.border-primary,
    .academics-ap .postings-tab-panel > .card.custom-card,
    .exam-ap .postings-tab-panel > .card.custom-card,
    .webcms-ap .postings-tab-panel > .card.custom-card,
    .fees-ap .postings-tab-panel > .card.custom-card,
    .academics-ap .postings-tab-panel > .card.border-primary,
    .exam-ap .postings-tab-panel > .card.border-primary,
    .webcms-ap .postings-tab-panel > .card.border-primary,
    .fees-ap .postings-tab-panel > .card.border-primary, .cms-ap .postings-tab-panel > .card.border-primary{
        border: 0 !important;
        box-shadow: none !important;
        margin: 0 !important;
        background: transparent !important;
    }.postings-ap .postings-tab-panel > .card > .card-body,
    .library-ap .postings-tab-panel > .card > .card-body,
    .office-ap .postings-tab-panel > .card > .card-body,
    .postings-ap .postings-tab-panel > .card > .inner-body,
    .library-ap .postings-tab-panel > .card > .inner-body,
    .office-ap .postings-tab-panel > .card > .inner-body,
    .attendance-ap .postings-tab-panel > .card > .card-body,
    .attendance-ap .postings-tab-panel > .card > .inner-body,
    .academics-ap .postings-tab-panel > .card > .card-body,
    .exam-ap .postings-tab-panel > .card > .card-body,
    .webcms-ap .postings-tab-panel > .card > .card-body,
    .fees-ap .postings-tab-panel > .card > .card-body,
    .academics-ap .postings-tab-panel > .card > .inner-body,
    .exam-ap .postings-tab-panel > .card > .inner-body,
    .webcms-ap .postings-tab-panel > .card > .inner-body,
    .fees-ap .postings-tab-panel > .card > .inner-body, .cms-ap .postings-tab-panel > .card > .inner-body{
        padding-left: 0;
        padding-right: 0;
    }.postings-ap .ledger-filter,
    .library-ap .ledger-filter,
    .office-ap .ledger-filter,
    .attendance-ap .ledger-filter,
    .academics-ap .ledger-filter,
    .exam-ap .ledger-filter,
    .webcms-ap .ledger-filter,
    .fees-ap .ledger-filter, .cms-ap .ledger-filter{
        background: var(--cms-card-bg, #fff);
        border: 1px solid var(--cms-card-border, rgba(15, 23, 42, 0.08));
        border-radius: var(--cms-radius, 10px);
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        margin-bottom: 1rem;
        overflow: hidden;
    }.postings-ap .ledger-filter__body,
    .library-ap .ledger-filter__body,
    .office-ap .ledger-filter__body,
    .attendance-ap .ledger-filter__body,
    .academics-ap .ledger-filter__body,
    .exam-ap .ledger-filter__body,
    .webcms-ap .ledger-filter__body,
    .fees-ap .ledger-filter__body, .cms-ap .ledger-filter__body{
        padding: 0;
    }.postings-ap .ledger-filter__grid,
    .library-ap .ledger-filter__grid,
    .office-ap .ledger-filter__grid,
    .attendance-ap .ledger-filter__grid,
    .academics-ap .ledger-filter__grid,
    .exam-ap .ledger-filter__grid,
    .webcms-ap .ledger-filter__grid,
    .fees-ap .ledger-filter__grid, .cms-ap .ledger-filter__grid{
        display: flex;
        flex-wrap: nowrap;
        align-items: end;
        gap: 0.45rem 0.55rem;
    }.postings-ap .ledger-filter__field,
    .library-ap .ledger-filter__field,
    .office-ap .ledger-filter__field,
    .attendance-ap .ledger-filter__field,
    .academics-ap .ledger-filter__field,
    .exam-ap .ledger-filter__field,
    .webcms-ap .ledger-filter__field,
    .fees-ap .ledger-filter__field, .cms-ap .ledger-filter__field{
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
        min-width: 0;
    }.postings-ap .ledger-filter__field--sep,
    .library-ap .ledger-filter__field--sep,
    .office-ap .ledger-filter__field--sep,
    .attendance-ap .ledger-filter__field--sep,
    .academics-ap .ledger-filter__field--sep,
    .exam-ap .ledger-filter__field--sep,
    .webcms-ap .ledger-filter__field--sep,
    .fees-ap .ledger-filter__field--sep, .cms-ap .ledger-filter__field--sep{
        align-self: end;
        padding-bottom: 0.55rem;
        color: var(--cms-text-muted, #64748b);
        font-size: 0.8rem;
        font-weight: 600;
        text-align: center;
        user-select: none;
    }.postings-ap .ledger-filter__label,
    .library-ap .ledger-filter__label,
    .office-ap .ledger-filter__label,
    .attendance-ap .ledger-filter__label,
    .academics-ap .ledger-filter__label,
    .exam-ap .ledger-filter__label,
    .webcms-ap .ledger-filter__label,
    .fees-ap .ledger-filter__label, .cms-ap .ledger-filter__label{
        margin: 0;
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        color: var(--cms-text-muted, #64748b);
        line-height: 1.2;
    }.postings-ap .ledger-filter__control,
    .library-ap .ledger-filter__control,
    .office-ap .ledger-filter__control,
    .attendance-ap .ledger-filter__control,
    .academics-ap .ledger-filter__control,
    .exam-ap .ledger-filter__control,
    .webcms-ap .ledger-filter__control,
    .fees-ap .ledger-filter__control, .cms-ap .ledger-filter__control{
        width: 100%;
        height: 2.25rem;
        border-radius: var(--cms-radius-sm, 8px);
        border: 1px solid var(--cms-input-border, rgba(15, 23, 42, 0.12));
        background: var(--cms-input-bg, #fff);
        color: var(--cms-text, #0f172a);
        font-size: 0.875rem;
        padding: 0.35rem 0.65rem;
        transition: border-color 0.18s ease, box-shadow 0.18s ease;
    }.postings-ap .ledger-filter__control:focus,
    .library-ap .ledger-filter__control:focus,
    .office-ap .ledger-filter__control:focus,
    .attendance-ap .ledger-filter__control:focus,
    .academics-ap .ledger-filter__control:focus,
    .exam-ap .ledger-filter__control:focus,
    .webcms-ap .ledger-filter__control:focus,
    .fees-ap .ledger-filter__control:focus, .cms-ap .ledger-filter__control:focus{
        outline: none;
        border-color: var(--cms-accent, #10b981);
        box-shadow: 0 0 0 3px var(--cms-accent-soft, rgba(16, 185, 129, 0.14));
    }.postings-ap .ledger-filter__actions,
    .library-ap .ledger-filter__actions,
    .office-ap .ledger-filter__actions,
    .attendance-ap .ledger-filter__actions,
    .academics-ap .ledger-filter__actions,
    .exam-ap .ledger-filter__actions,
    .webcms-ap .ledger-filter__actions,
    .fees-ap .ledger-filter__actions, .cms-ap .ledger-filter__actions{
        display: flex;
        flex-wrap: nowrap;
        align-items: center;
        gap: 0.4rem;
        padding: 0;
        border-top: none;
        background: transparent;
    }.postings-ap .ledger-filter__actions-primary,
    .library-ap .ledger-filter__actions-primary,
    .office-ap .ledger-filter__actions-primary,
    .postings-ap .ledger-filter__actions-cta,
    .library-ap .ledger-filter__actions-cta,
    .office-ap .ledger-filter__actions-cta,
    .attendance-ap .ledger-filter__actions-primary,
    .attendance-ap .ledger-filter__actions-cta,
    .academics-ap .ledger-filter__actions-primary,
    .exam-ap .ledger-filter__actions-primary,
    .webcms-ap .ledger-filter__actions-primary,
    .fees-ap .ledger-filter__actions-primary,
    .academics-ap .ledger-filter__actions-cta,
    .exam-ap .ledger-filter__actions-cta,
    .webcms-ap .ledger-filter__actions-cta,
    .fees-ap .ledger-filter__actions-cta, .cms-ap .ledger-filter__actions-cta{
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
    }.postings-ap .ledger-filter__actions-cta,
    .library-ap .ledger-filter__actions-cta,
    .office-ap .ledger-filter__actions-cta,
    .attendance-ap .ledger-filter__actions-cta,
    .academics-ap .ledger-filter__actions-cta,
    .exam-ap .ledger-filter__actions-cta,
    .webcms-ap .ledger-filter__actions-cta,
    .fees-ap .ledger-filter__actions-cta, .cms-ap .ledger-filter__actions-cta{
        margin-left: auto;
    }.postings-ap .ledger-filter__btn,
    .library-ap .ledger-filter__btn,
    .office-ap .ledger-filter__btn,
    .attendance-ap .ledger-filter__btn,
    .academics-ap .ledger-filter__btn,
    .exam-ap .ledger-filter__btn,
    .webcms-ap .ledger-filter__btn,
    .fees-ap .ledger-filter__btn, .cms-ap .ledger-filter__btn{
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        min-height: 2.25rem;
        padding: 0.35rem 0.9rem;
        border-radius: var(--cms-radius-sm, 8px);
        border: 1px solid transparent;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        transition: background 0.15s ease, transform 0.15s ease, border-color 0.15s ease;
    }.postings-ap .ledger-filter__btn:hover,
    .library-ap .ledger-filter__btn:hover,
    .office-ap .ledger-filter__btn:hover,
    .attendance-ap .ledger-filter__btn:hover,
    .academics-ap .ledger-filter__btn:hover,
    .exam-ap .ledger-filter__btn:hover,
    .webcms-ap .ledger-filter__btn:hover,
    .fees-ap .ledger-filter__btn:hover, .cms-ap .ledger-filter__btn:hover{
        transform: translateY(-1px);
        text-decoration: none;
    }.postings-ap .ledger-filter__btn:focus,
    .library-ap .ledger-filter__btn:focus,
    .office-ap .ledger-filter__btn:focus,
    .attendance-ap .ledger-filter__btn:focus,
    .academics-ap .ledger-filter__btn:focus,
    .exam-ap .ledger-filter__btn:focus,
    .webcms-ap .ledger-filter__btn:focus,
    .fees-ap .ledger-filter__btn:focus, .cms-ap .ledger-filter__btn:focus{
        outline: none;
        box-shadow: 0 0 0 3px var(--cms-accent-soft, rgba(16, 185, 129, 0.14));
    }.postings-ap .ledger-filter__btn--search,
    .library-ap .ledger-filter__btn--search,
    .office-ap .ledger-filter__btn--search,
    .postings-ap .ledger-filter__btn--cta,
    .library-ap .ledger-filter__btn--cta,
    .office-ap .ledger-filter__btn--cta,
    .attendance-ap .ledger-filter__btn--search,
    .attendance-ap .ledger-filter__btn--cta,
    .academics-ap .ledger-filter__btn--search,
    .exam-ap .ledger-filter__btn--search,
    .webcms-ap .ledger-filter__btn--search,
    .fees-ap .ledger-filter__btn--search,
    .academics-ap .ledger-filter__btn--cta,
    .exam-ap .ledger-filter__btn--cta,
    .webcms-ap .ledger-filter__btn--cta,
    .fees-ap .ledger-filter__btn--cta, .cms-ap .ledger-filter__btn--cta{
        background: var(--cms-accent, #10b981);
        color: #fff;
    }.postings-ap .ledger-filter__btn--search:hover,
    .library-ap .ledger-filter__btn--search:hover,
    .office-ap .ledger-filter__btn--search:hover,
    .postings-ap .ledger-filter__btn--cta:hover,
    .library-ap .ledger-filter__btn--cta:hover,
    .office-ap .ledger-filter__btn--cta:hover,
    .attendance-ap .ledger-filter__btn--search:hover,
    .attendance-ap .ledger-filter__btn--cta:hover,
    .academics-ap .ledger-filter__btn--search:hover,
    .exam-ap .ledger-filter__btn--search:hover,
    .webcms-ap .ledger-filter__btn--search:hover,
    .fees-ap .ledger-filter__btn--search:hover,
    .academics-ap .ledger-filter__btn--cta:hover,
    .exam-ap .ledger-filter__btn--cta:hover,
    .webcms-ap .ledger-filter__btn--cta:hover,
    .fees-ap .ledger-filter__btn--cta:hover, .cms-ap .ledger-filter__btn--cta:hover{
        background: var(--cms-accent-hover, #059669);
        color: #fff;
    }.postings-ap .ledger-filter__btn--ghost,
    .library-ap .ledger-filter__btn--ghost,
    .office-ap .ledger-filter__btn--ghost,
    .attendance-ap .ledger-filter__btn--ghost,
    .academics-ap .ledger-filter__btn--ghost,
    .exam-ap .ledger-filter__btn--ghost,
    .webcms-ap .ledger-filter__btn--ghost,
    .fees-ap .ledger-filter__btn--ghost, .cms-ap .ledger-filter__btn--ghost{
        background: var(--cms-input-bg, #fff);
        color: var(--cms-text, #0f172a);
        border-color: var(--cms-input-border, rgba(15, 23, 42, 0.12));
    }.postings-ap .ledger-filter__btn--ghost:hover,
    .library-ap .ledger-filter__btn--ghost:hover,
    .office-ap .ledger-filter__btn--ghost:hover,
    .attendance-ap .ledger-filter__btn--ghost:hover,
    .academics-ap .ledger-filter__btn--ghost:hover,
    .exam-ap .ledger-filter__btn--ghost:hover,
    .webcms-ap .ledger-filter__btn--ghost:hover,
    .fees-ap .ledger-filter__btn--ghost:hover, .cms-ap .ledger-filter__btn--ghost:hover{
        background: var(--cms-surface-hover, #f1f5f9);
        color: var(--cms-text, #0f172a);
    }.postings-ap .ledger-filter__btn--soft,
    .library-ap .ledger-filter__btn--soft,
    .office-ap .ledger-filter__btn--soft,
    .attendance-ap .ledger-filter__btn--soft,
    .academics-ap .ledger-filter__btn--soft,
    .exam-ap .ledger-filter__btn--soft,
    .webcms-ap .ledger-filter__btn--soft,
    .fees-ap .ledger-filter__btn--soft, .cms-ap .ledger-filter__btn--soft{
        background: var(--cms-accent-soft, #eff6ff);
        color: var(--cms-accent-hover, #1d4ed8);
        border-color: var(--cms-input-border, #bfdbfe);
    }.postings-ap .ledger-filter__btn--soft:hover,
    .library-ap .ledger-filter__btn--soft:hover,
    .office-ap .ledger-filter__btn--soft:hover,
    .attendance-ap .ledger-filter__btn--soft:hover,
    .academics-ap .ledger-filter__btn--soft:hover,
    .exam-ap .ledger-filter__btn--soft:hover,
    .webcms-ap .ledger-filter__btn--soft:hover,
    .fees-ap .ledger-filter__btn--soft:hover, .cms-ap .ledger-filter__btn--soft:hover{
        background: var(--cms-surface-hover, #dbeafe);
        color: var(--cms-accent-hover, #1e40af);
    }.postings-ap .postings-detail-panel,
    .library-ap .postings-detail-panel,
    .office-ap .postings-detail-panel,
    .attendance-ap .postings-detail-panel,
    .academics-ap .postings-detail-panel,
    .exam-ap .postings-detail-panel,
    .webcms-ap .postings-detail-panel,
    .fees-ap .postings-detail-panel, .cms-ap .postings-detail-panel{
        background: var(--cms-card-bg, #fff);
        border: 1px solid var(--cms-card-border, rgba(15, 23, 42, 0.08));
        border-radius: 12px;
        box-shadow: var(--cms-shadow, 0 1px 3px rgba(15, 23, 42, 0.04));
        overflow: hidden;
        margin-bottom: 1rem;
    }.postings-ap .postings-detail-panel__head,
    .library-ap .postings-detail-panel__head,
    .office-ap .postings-detail-panel__head,
    .attendance-ap .postings-detail-panel__head,
    .academics-ap .postings-detail-panel__head,
    .exam-ap .postings-detail-panel__head,
    .webcms-ap .postings-detail-panel__head,
    .fees-ap .postings-detail-panel__head, .cms-ap .postings-detail-panel__head{
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.85rem 1.1rem;
        border-bottom: 1px solid var(--cms-card-border, rgba(15, 23, 42, 0.08));
        background: var(--cms-surface-muted, #f8fafc);
    }.postings-ap .postings-detail-panel__title,
    .library-ap .postings-detail-panel__title,
    .office-ap .postings-detail-panel__title,
    .attendance-ap .postings-detail-panel__title,
    .academics-ap .postings-detail-panel__title,
    .exam-ap .postings-detail-panel__title,
    .webcms-ap .postings-detail-panel__title,
    .fees-ap .postings-detail-panel__title, .cms-ap .postings-detail-panel__title{
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: var(--cms-text, #0f172a);
    }.postings-ap .postings-detail-panel__body,
    .library-ap .postings-detail-panel__body,
    .office-ap .postings-detail-panel__body,
    .attendance-ap .postings-detail-panel__body,
    .academics-ap .postings-detail-panel__body,
    .exam-ap .postings-detail-panel__body,
    .webcms-ap .postings-detail-panel__body,
    .fees-ap .postings-detail-panel__body, .cms-ap .postings-detail-panel__body{
        padding: 0;
    }.postings-ap .postings-detail-panel .table,
    .library-ap .postings-detail-panel .table,
    .office-ap .postings-detail-panel .table,
    .attendance-ap .postings-detail-panel .table,
    .academics-ap .postings-detail-panel .table,
    .exam-ap .postings-detail-panel .table,
    .webcms-ap .postings-detail-panel .table,
    .fees-ap .postings-detail-panel .table, .cms-ap .postings-detail-panel .table{
        margin-bottom: 0;
    }.postings-ap .postings-detail-panel .table thead th,
    .library-ap .postings-detail-panel .table thead th,
    .office-ap .postings-detail-panel .table thead th,
    .attendance-ap .postings-detail-panel .table thead th,
    .academics-ap .postings-detail-panel .table thead th,
    .exam-ap .postings-detail-panel .table thead th,
    .webcms-ap .postings-detail-panel .table thead th,
    .fees-ap .postings-detail-panel .table thead th, .cms-ap .postings-detail-panel .table thead th{
        background: var(--cms-surface-muted, #f8fafc);
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        color: var(--cms-text-muted, #64748b);
        white-space: nowrap;
        border-bottom-width: 1px;
        vertical-align: middle;
        border-color: var(--cms-card-border);
    }.postings-ap .postings-detail-panel .table td,
    .library-ap .postings-detail-panel .table td,
    .office-ap .postings-detail-panel .table td,
    .attendance-ap .postings-detail-panel .table td,
    .academics-ap .postings-detail-panel .table td,
    .exam-ap .postings-detail-panel .table td,
    .webcms-ap .postings-detail-panel .table td,
    .fees-ap .postings-detail-panel .table td, .cms-ap .postings-detail-panel .table td{
        vertical-align: middle;
    }/* DataTables wraps list tables for row.child expand — keep chrome out of the panel */
    .postings-ap .postings-detail-panel .dataTables_wrapper, .cms-ap .postings-detail-panel .dataTables_wrapper{
        margin: 0;
        padding: 0;
        max-height: none !important;
        overflow: visible !important;
    }.postings-ap .postings-detail-panel .dataTables_wrapper .dataTables_filter,
    .library-ap .postings-detail-panel .dataTables_wrapper .dataTables_filter,
    .office-ap .postings-detail-panel .dataTables_wrapper .dataTables_filter,
    .postings-ap .postings-detail-panel .dataTables_wrapper .dataTables_info,
    .library-ap .postings-detail-panel .dataTables_wrapper .dataTables_info,
    .office-ap .postings-detail-panel .dataTables_wrapper .dataTables_info,
    .postings-ap .postings-detail-panel .dataTables_wrapper .dataTables_length,
    .library-ap .postings-detail-panel .dataTables_wrapper .dataTables_length,
    .office-ap .postings-detail-panel .dataTables_wrapper .dataTables_length,
    .postings-ap .postings-detail-panel .dataTables_wrapper .dataTables_paginate,
    .library-ap .postings-detail-panel .dataTables_wrapper .dataTables_paginate,
    .office-ap .postings-detail-panel .dataTables_wrapper .dataTables_paginate,
    .attendance-ap .postings-detail-panel .dataTables_wrapper .dataTables_filter,
    .attendance-ap .postings-detail-panel .dataTables_wrapper .dataTables_info,
    .attendance-ap .postings-detail-panel .dataTables_wrapper .dataTables_length,
    .attendance-ap .postings-detail-panel .dataTables_wrapper .dataTables_paginate,
    .academics-ap .postings-detail-panel .dataTables_wrapper .dataTables_filter,
    .exam-ap .postings-detail-panel .dataTables_wrapper .dataTables_filter,
    .webcms-ap .postings-detail-panel .dataTables_wrapper .dataTables_filter,
    .fees-ap .postings-detail-panel .dataTables_wrapper .dataTables_filter,
    .academics-ap .postings-detail-panel .dataTables_wrapper .dataTables_info,
    .exam-ap .postings-detail-panel .dataTables_wrapper .dataTables_info,
    .webcms-ap .postings-detail-panel .dataTables_wrapper .dataTables_info,
    .fees-ap .postings-detail-panel .dataTables_wrapper .dataTables_info,
    .academics-ap .postings-detail-panel .dataTables_wrapper .dataTables_length,
    .exam-ap .postings-detail-panel .dataTables_wrapper .dataTables_length,
    .webcms-ap .postings-detail-panel .dataTables_wrapper .dataTables_length,
    .fees-ap .postings-detail-panel .dataTables_wrapper .dataTables_length,
    .academics-ap .postings-detail-panel .dataTables_wrapper .dataTables_paginate,
    .exam-ap .postings-detail-panel .dataTables_wrapper .dataTables_paginate,
    .webcms-ap .postings-detail-panel .dataTables_wrapper .dataTables_paginate,
    .fees-ap .postings-detail-panel .dataTables_wrapper .dataTables_paginate, .cms-ap .postings-detail-panel .dataTables_wrapper .dataTables_paginate{
        display: none !important;
    }.postings-ap .postings-detail-panel .dataTables_wrapper .table,
    .library-ap .postings-detail-panel .dataTables_wrapper .table,
    .office-ap .postings-detail-panel .dataTables_wrapper .table,
    .attendance-ap .postings-detail-panel .dataTables_wrapper .table,
    .academics-ap .postings-detail-panel .dataTables_wrapper .table,
    .exam-ap .postings-detail-panel .dataTables_wrapper .table,
    .webcms-ap .postings-detail-panel .dataTables_wrapper .table,
    .fees-ap .postings-detail-panel .dataTables_wrapper .table, .cms-ap .postings-detail-panel .dataTables_wrapper .table{
        width: 100% !important;
        margin-bottom: 0 !important;
    }.postings-ap .postings-add-panel,
    .library-ap .postings-add-panel,
    .office-ap .postings-add-panel,
    .attendance-ap .postings-add-panel,
    .academics-ap .postings-add-panel,
    .exam-ap .postings-add-panel,
    .webcms-ap .postings-add-panel,
    .fees-ap .postings-add-panel, .cms-ap .postings-add-panel{
        border: 1px solid rgba(16, 185, 129, 0.28);
        border-radius: var(--cms-radius, 10px);
        overflow: hidden;
        margin-bottom: 1rem;
    }.postings-ap .postings-add-panel > .card-header,
    .library-ap .postings-add-panel > .card-header,
    .office-ap .postings-add-panel > .card-header,
    .postings-ap .postings-add-panel .bg-primary.card-header,
    .library-ap .postings-add-panel .bg-primary.card-header,
    .office-ap .postings-add-panel .bg-primary.card-header,
    .attendance-ap .postings-add-panel > .card-header,
    .attendance-ap .postings-add-panel .bg-primary.card-header,
    .academics-ap .postings-add-panel > .card-header,
    .exam-ap .postings-add-panel > .card-header,
    .webcms-ap .postings-add-panel > .card-header,
    .fees-ap .postings-add-panel > .card-header,
    .academics-ap .postings-add-panel .bg-primary.card-header,
    .exam-ap .postings-add-panel .bg-primary.card-header,
    .webcms-ap .postings-add-panel .bg-primary.card-header,
    .fees-ap .postings-add-panel .bg-primary.card-header, .cms-ap .postings-add-panel .bg-primary.card-header{
        background: var(--cms-accent, #10b981) !important;
        border: 0;
    }

    @media (prefers-reduced-motion: reduce) {.postings-ap .ledger-filter__btn,
    .library-ap .ledger-filter__btn,
    .office-ap .ledger-filter__btn,
    .postings-ap .ledger-filter__control,
    .library-ap .ledger-filter__control,
    .office-ap .ledger-filter__control,
    .attendance-ap .ledger-filter__btn,
    .attendance-ap .ledger-filter__control,
    .academics-ap .ledger-filter__btn,
    .exam-ap .ledger-filter__btn,
    .webcms-ap .ledger-filter__btn,
    .fees-ap .ledger-filter__btn,
    .academics-ap .ledger-filter__control,
    .exam-ap .ledger-filter__control,
    .webcms-ap .ledger-filter__control,
    .fees-ap .ledger-filter__control, .cms-ap .ledger-filter__control{
            transition: none;
        }.postings-ap .ledger-filter__btn:hover,
    .library-ap .ledger-filter__btn:hover,
    .office-ap .ledger-filter__btn:hover,
    .attendance-ap .ledger-filter__btn:hover,
    .academics-ap .ledger-filter__btn:hover,
    .exam-ap .ledger-filter__btn:hover,
    .webcms-ap .ledger-filter__btn:hover,
    .fees-ap .ledger-filter__btn:hover, .cms-ap .ledger-filter__btn:hover{
            transform: none;
        }
    }
</style>
