<?php
/**
 * SB-Tech — Document Type Registry
 *
 * Central config for all document types in the document engine.
 * Each type defines: prefix, statuses, default fields, permissions, and labels.
 *
 * Usage: $types = documentTypes(); $type = $types['quotation'];
 */

return [
    // ──────────────────────────────────────────────────────────────
    // QUOTATION — Price proposal for a specific client/project
    // ──────────────────────────────────────────────────────────────
    'quotation' => [
        'label'         => 'Quotation',
        'plural'        => 'Quotations',
        'prefix'        => 'QTN',
        'icon'          => 'fas fa-file-invoice',
        'color'         => 'primary',
        'sidebar_section' => 'SALES',
        'sidebar_parent' => 'leads',
        'module_key'    => 'leads',
        'statuses'      => ['Draft', 'Sent', 'Accepted', 'Rejected', 'Expired'],
        'default_status'=> 'Draft',
        'status_badges' => [
            'Draft'     => 'secondary',
            'Sent'      => 'info',
            'Accepted'  => 'success',
            'Rejected'  => 'danger',
            'Expired'   => 'warning',
        ],
        'required_fields' => ['client_name', 'subject', 'document_number', 'document_date'],
        'optional_fields' => ['valid_until', 'lead_id'],
        'has_items'     => true,
        'has_discount'  => true,
        'has_tax'       => true,
        'has_notes'     => true,
        'has_terms'     => true,
        'has_files'     => true,
        'has_signature' => false,
        'has_payment'   => false,
        'has_validity'  => true,
        'has_delivery'  => false,
        'description'   => 'Formal price proposal sent to a client with itemized pricing and terms.',
    ],

    // ──────────────────────────────────────────────────────────────
    // INVOICE — Payment request after service delivery
    // ──────────────────────────────────────────────────────────────
    'invoice' => [
        'label'         => 'Invoice',
        'plural'        => 'Invoices',
        'prefix'        => 'INV',
        'icon'          => 'fas fa-receipt',
        'color'         => 'success',
        'sidebar_section' => 'FINANCE',
        'sidebar_parent' => 'accounts',
        'module_key'    => 'accounts',
        'statuses'      => ['Draft', 'Sent', 'Paid', 'Overdue', 'Cancelled'],
        'default_status'=> 'Draft',
        'status_badges' => [
            'Draft'     => 'secondary',
            'Sent'      => 'info',
            'Paid'      => 'success',
            'Overdue'   => 'danger',
            'Cancelled' => 'warning',
        ],
        'required_fields' => ['client_name', 'subject', 'document_number', 'document_date', 'due_date'],
        'optional_fields' => ['quotation_id', 'contract_id'],
        'has_items'     => true,
        'has_discount'  => true,
        'has_tax'       => true,
        'has_notes'     => true,
        'has_terms'     => true,
        'has_files'     => true,
        'has_signature' => false,
        'has_payment'   => true,
        'has_validity'  => false,
        'has_delivery'  => false,
        'description'   => 'Formal payment request issued after service delivery or product shipment.',
    ],

    // ──────────────────────────────────────────────────────────────
    // PROFORMA INVOICE — Pre-delivery billing / advance payment
    // ──────────────────────────────────────────────────────────────
    'proforma' => [
        'label'         => 'Proforma Invoice',
        'plural'        => 'Proforma Invoices',
        'prefix'        => 'PRO',
        'icon'          => 'fas fa-file-invoice-dollar',
        'color'         => 'info',
        'sidebar_section' => 'SALES',
        'sidebar_parent' => 'leads',
        'module_key'    => 'leads',
        'statuses'      => ['Draft', 'Sent', 'Paid', 'Cancelled'],
        'default_status'=> 'Draft',
        'status_badges' => [
            'Draft'     => 'secondary',
            'Sent'      => 'info',
            'Paid'      => 'success',
            'Cancelled' => 'warning',
        ],
        'required_fields' => ['client_name', 'subject', 'document_number', 'document_date', 'due_date'],
        'optional_fields' => ['quotation_id'],
        'has_items'     => true,
        'has_discount'  => true,
        'has_tax'       => true,
        'has_notes'     => true,
        'has_terms'     => true,
        'has_files'     => true,
        'has_signature' => false,
        'has_payment'   => true,
        'has_validity'  => true,
        'has_delivery'  => false,
        'description'   => 'Pre-delivery invoice for advance payment before work begins.',
    ],

    // ──────────────────────────────────────────────────────────────
    // PROPOSAL — Technical/creative project proposal
    // ──────────────────────────────────────────────────────────────
    'proposal' => [
        'label'         => 'Proposal',
        'plural'        => 'Proposals',
        'prefix'        => 'PRP',
        'icon'          => 'fas fa-lightbulb',
        'color'         => 'warning',
        'sidebar_section' => 'SALES',
        'sidebar_parent' => 'leads',
        'module_key'    => 'leads',
        'statuses'      => ['Draft', 'Sent', 'Accepted', 'Rejected', 'Expired'],
        'default_status'=> 'Draft',
        'status_badges' => [
            'Draft'     => 'secondary',
            'Sent'      => 'info',
            'Accepted'  => 'success',
            'Rejected'  => 'danger',
            'Expired'   => 'warning',
        ],
        'required_fields' => ['client_name', 'subject', 'document_number', 'document_date'],
        'optional_fields' => ['valid_until', 'lead_id'],
        'has_items'     => true,
        'has_discount'  => true,
        'has_tax'       => true,
        'has_notes'     => true,
        'has_terms'     => true,
        'has_files'     => true,
        'has_signature' => true,
        'has_payment'   => false,
        'has_validity'  => true,
        'has_delivery'  => false,
        'description'   => 'Technical or creative proposal with scope, timeline, team, and cost.',
    ],

    // ──────────────────────────────────────────────────────────────
    // CONTRACT — Legal agreement with client
    // ──────────────────────────────────────────────────────────────
    'contract' => [
        'label'         => 'Contract',
        'plural'        => 'Contracts',
        'prefix'        => 'CON',
        'icon'          => 'fas fa-file-contract',
        'color'         => 'secondary',
        'sidebar_section' => 'SALES',
        'sidebar_parent' => 'leads',
        'module_key'    => 'leads',
        'statuses'      => ['Draft', 'Sent', 'Signed', 'Active', 'Completed', 'Terminated'],
        'default_status'=> 'Draft',
        'status_badges' => [
            'Draft'      => 'secondary',
            'Sent'       => 'info',
            'Signed'     => 'primary',
            'Active'     => 'success',
            'Completed'  => 'dark',
            'Terminated' => 'danger',
        ],
        'required_fields' => ['client_name', 'subject', 'document_number', 'document_date', 'valid_until'],
        'optional_fields' => ['lead_id', 'quotation_id'],
        'has_items'     => true,
        'has_discount'  => true,
        'has_tax'       => true,
        'has_notes'     => true,
        'has_terms'     => true,
        'has_files'     => true,
        'has_signature' => true,
        'has_payment'   => false,
        'has_validity'  => true,
        'has_delivery'  => false,
        'description'   => 'Legal agreement with scope, terms, payment schedule, and signatures.',
    ],

    // ──────────────────────────────────────────────────────────────
    // PRICE LIST — Standard pricing catalog
    // ──────────────────────────────────────────────────────────────
    'price_list' => [
        'label'         => 'Price List',
        'plural'        => 'Price Lists',
        'prefix'        => 'PRL',
        'icon'          => 'fas fa-tags',
        'color'         => 'teal',
        'sidebar_section' => 'SALES',
        'sidebar_parent' => 'leads',
        'module_key'    => 'leads',
        'statuses'      => ['Draft', 'Active', 'Archived'],
        'default_status'=> 'Draft',
        'status_badges' => [
            'Draft'    => 'secondary',
            'Active'   => 'success',
            'Archived' => 'dark',
        ],
        'required_fields' => ['subject', 'document_number', 'document_date'],
        'optional_fields' => ['valid_until'],
        'has_items'     => true,
        'has_discount'  => true,
        'has_tax'       => true,
        'has_notes'     => true,
        'has_terms'     => true,
        'has_files'     => true,
        'has_signature' => false,
        'has_payment'   => false,
        'has_validity'  => true,
        'has_delivery'  => false,
        'description'   => 'Standard pricing catalog for services/products (not client-specific).',
    ],

    // ──────────────────────────────────────────────────────────────
    // BROCHURE — Marketing collateral
    // ──────────────────────────────────────────────────────────────
    'brochure' => [
        'label'         => 'Brochure',
        'plural'        => 'Brochures',
        'prefix'        => 'BRO',
        'icon'          => 'fas fa-book-open',
        'color'         => 'orange',
        'sidebar_section' => 'SALES',
        'sidebar_parent' => 'leads',
        'module_key'    => 'leads',
        'statuses'      => ['Draft', 'Published', 'Archived'],
        'default_status'=> 'Draft',
        'status_badges' => [
            'Draft'     => 'secondary',
            'Published' => 'success',
            'Archived'  => 'dark',
        ],
        'required_fields' => ['subject', 'document_number', 'document_date'],
        'optional_fields' => [],
        'has_items'     => true,
        'has_discount'  => true,
        'has_tax'       => true,
        'has_notes'     => true,
        'has_terms'     => false,
        'has_files'     => true,
        'has_signature' => false,
        'has_payment'   => false,
        'has_validity'  => false,
        'has_delivery'  => false,
        'description'   => 'Marketing collateral showcasing services, portfolio, and company info.',
    ],

    // ──────────────────────────────────────────────────────────────
    // CREDIT NOTE — Financial adjustment / return
    // ──────────────────────────────────────────────────────────────
    'credit_note' => [
        'label'         => 'Credit Note',
        'plural'        => 'Credit Notes',
        'prefix'        => 'CRN',
        'icon'          => 'fas fa-undo',
        'color'         => 'danger',
        'sidebar_section' => 'FINANCE',
        'sidebar_parent' => 'accounts',
        'module_key'    => 'accounts',
        'statuses'      => ['Draft', 'Sent', 'Applied'],
        'default_status'=> 'Draft',
        'status_badges' => [
            'Draft'   => 'secondary',
            'Sent'    => 'info',
            'Applied' => 'success',
        ],
        'required_fields' => ['client_name', 'subject', 'document_number', 'document_date', 'original_invoice_id'],
        'optional_fields' => [],
        'has_items'     => true,
        'has_discount'  => true,
        'has_tax'       => true,
        'has_notes'     => true,
        'has_terms'     => false,
        'has_files'     => true,
        'has_signature' => false,
        'has_payment'   => false,
        'has_validity'  => false,
        'has_delivery'  => false,
        'description'   => 'Credit adjustment against an existing invoice (returns, corrections).',
    ],
];
