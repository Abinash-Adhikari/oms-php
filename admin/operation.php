<?php
/**
 * SB-Tech — admin write path (PRG pattern, mirrors reference operation.php).
 *
 * POST /admin/operation.php?module=X&page=Y
 *   1. bootstrap
 *   2. auth gate
 *   3. CSRF check (X-01) — all POSTs must carry a token
 *   4. resolve route + permission gate
 *   5. include modules/<module>/operation/<page>.php (the operation handler,
 *      which performs the write and redirects back with a flash message)
 */
include __DIR__ . '/../config/setup.php';

if (!Auth::check()) {
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('show_page.php?module=dashboard');
}

verifyCsrf();

include __DIR__ . '/includes/route.php';
include __DIR__ . '/includes/varriables.php';

$page = (string) $page;
$permissionModule = (string) $permissionModule;
$moduleFs = (string) $moduleFs;

// Phase 2 — normalize POSTs that still target a moved submodule to its
// canonical module/page so old forms keep dispatching (GETs are redirected
// with a 301 by show_page.php). Only entries flagged 'post' are rewritten;
// e.g. inventory/Reports keeps its own operation handler for CSV exports.
if (isset($routeCanonical[$permissionModule][$page]) && !empty($routeCanonical[$permissionModule][$page]['post'])) {
    $canonical = $routeCanonical[$permissionModule][$page];
    $permissionModule = (string) $canonical['module'];
    $moduleFs = (string) $canonical['module'];
    $page = (string) $canonical['page'];
}

if (!Auth::can($permissionModule, $page)) {
    http_response_code(403);
    die('Access denied: you do not have permission to perform this action.');
}

// Operations live in modules/<module>/operation/<page>.php.
$handler = __DIR__ . '/modules/' . $moduleFs . '/operation/' . $page . '.php';
if (!is_file($handler)) {
    $handler = null;
}

if ($handler === null) {
    http_response_code(404);
    die('Operation not found for module "' . e($permissionModule) . '" page "' . e($page) . '".');
}

// Wrap the handler in a database transaction so multi-step writes
// (e.g. create staff + insert history + insert profile) are atomic.
// The handler will typically call redirect() which calls exit, so we
// register a shutdown function to commit the transaction before the
// script ends — this is more reliable than relying on the mysqli
// destructor which may or may not auto-commit.
$db = Database::instance();
$db->mysqli()->begin_transaction();
$transactionActive = true;
register_shutdown_function(function () use ($db, &$transactionActive) {
    if ($transactionActive && $db->mysqli()->errno === 0) {
        $db->mysqli()->commit();
    }
});
try {
    include $handler;
    // If handler did NOT exit (no redirect), commit now.
    if ($transactionActive) {
        $db->mysqli()->commit();
    }
} catch (Throwable $e) {
    $transactionActive = false;
    $db->mysqli()->rollback();
    setFlash('error', 'Operation failed: ' . $e->getMessage());
    redirect(pageUrl($permissionModule, $page));
}
