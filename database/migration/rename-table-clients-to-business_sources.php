<?php

/**
 * SB-Tech — Rename tbl_clients → tbl_business_sources.
 *
 * The sales domain models "where a deal comes from": an individual or a
 * company. InnoDB auto-updates every existing foreign key that referenced
 * tbl_clients (tbl_leads.business_source_id[ex-client_id],
 * tbl_leads.won_business_source_id[ex-won_client_id],
 * tbl_client_projects.business_source_id[ex-client_id], plus quotations,
 * expense claims and document links) to point at tbl_business_sources, so no
 * dependent DDL is required — ids are preserved untouched.
 */

$query = [
    'RENAME TABLE `tbl_clients` TO `tbl_business_sources`;',
];

$rollbackQuery = [
    'RENAME TABLE `tbl_business_sources` TO `tbl_clients`;',
];
