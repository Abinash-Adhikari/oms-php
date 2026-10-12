<?php

/**
 * Migration: Copy Leads/Clients grants onto the consolidated Sales module.
 *
 * The leads and clients modules moved into 'sales':
 *   leads.leads           → sales.leads
 *   leads.client_projects → sales.client_projects
 *   leads.projects        → sales.products
 *   clients.clients       → sales.clients
 * Users holding the old module also get the 'sales' module grant.
 *
 * Old grants are left in place (they no longer render anywhere and are
 * dropped the next time the permissions editor saves that user), so the
 * rollback only has to remove the copied keys. Super admins ('All') are
 * skipped; they need no grant.
 */
$col = '`permitted_submodules`';
$hasOld = "(JSON_CONTAINS($col, '\"leads\"', '$.leads')
         OR JSON_CONTAINS($col, '\"client_projects\"', '$.leads')
         OR JSON_CONTAINS($col, '\"projects\"', '$.leads')
         OR JSON_CONTAINS($col, '\"clients\"', '$.clients'))";

$copy = static function (string $fromPath, string $fromKey, string $toKey) use ($col): string {
    return "UPDATE `tbl_users_login`
        SET $col = JSON_ARRAY_APPEND($col, '$.sales', '$toKey')
      WHERE JSON_VALID($col)
        AND JSON_CONTAINS($col, '\"$fromKey\"', '$fromPath')
        AND NOT JSON_CONTAINS($col, '\"$toKey\"', '$.sales')";
};

$remove = static function (string $key) use ($col): string {
    return "UPDATE `tbl_users_login`
        SET $col = JSON_REMOVE($col, JSON_UNQUOTE(JSON_SEARCH($col, 'one', '$key', NULL, '$.sales')))
      WHERE JSON_VALID($col)
        AND JSON_CONTAINS($col, '\"$key\"', '$.sales')";
};

$query = [
    // Make sure a $.sales list exists before appending to it.
    "UPDATE `tbl_users_login`
        SET $col = JSON_SET($col, '$.sales', JSON_ARRAY())
      WHERE JSON_VALID($col)
        AND JSON_EXTRACT($col, '$.sales') IS NULL
        AND $hasOld",
    $copy('$.leads', 'leads', 'leads'),
    $copy('$.leads', 'client_projects', 'client_projects'),
    $copy('$.leads', 'projects', 'products'),
    $copy('$.clients', 'clients', 'clients'),
    "UPDATE `tbl_users_login`
        SET `permitted_modules` = JSON_ARRAY_APPEND(`permitted_modules`, '$', 'sales')
      WHERE JSON_VALID(`permitted_modules`)
        AND NOT JSON_CONTAINS(`permitted_modules`, '\"sales\"')
        AND (JSON_CONTAINS(`permitted_modules`, '\"leads\"') OR JSON_CONTAINS(`permitted_modules`, '\"clients\"'))",
];

$rollbackQuery = [
    $remove('leads'),
    $remove('client_projects'),
    $remove('products'),
    $remove('clients'),
];
