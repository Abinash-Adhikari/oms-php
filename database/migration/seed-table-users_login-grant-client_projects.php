<?php

/**
 * Migration: Grant the new Leads → Client Projects submodule.
 *
 * Client Projects used to be an unlisted page (reachable only from a client's
 * detail page and not grantable), so it now becomes a sidebar submodule.
 * Every user who can open Leads → Leads gets Leads → Client Projects so no
 * one loses access. Super admins ('All') are skipped; they need no grant.
 */
$query = [
    "UPDATE `tbl_users_login`
        SET `permitted_submodules` = JSON_ARRAY_APPEND(`permitted_submodules`, '$.leads', 'client_projects')
      WHERE JSON_VALID(`permitted_submodules`)
        AND JSON_CONTAINS(`permitted_submodules`, '\"leads\"', '$.leads')
        AND NOT JSON_CONTAINS(`permitted_submodules`, '\"client_projects\"', '$.leads')",
];

$rollbackQuery = [
    "UPDATE `tbl_users_login`
        SET `permitted_submodules` = JSON_REMOVE(
                `permitted_submodules`,
                JSON_UNQUOTE(JSON_SEARCH(`permitted_submodules`, 'one', 'client_projects', NULL, '$.leads'))
            )
      WHERE JSON_VALID(`permitted_submodules`)
        AND JSON_CONTAINS(`permitted_submodules`, '\"client_projects\"', '$.leads')",
];
