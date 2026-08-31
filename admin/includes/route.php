<?php
/**
 * SB-Tech — shared route resolver for the admin front controllers
 * (show_page.php, operation.php, ajax.php).
 *
 * Resolves GET/POST `module` + `page` into:
 *   $routeModule       — raw module key from the request
 *   $page              — sanitized page key (defaults to module key)
 *   $permissionModule  — lowercased permission key ('home' → 'dashboard')
 *   $moduleFs          — module folder on disk (case-tolerant fallback)
 *
 * All values are restricted to [a-zA-Z0-9_-] so every include stays
 * inside admin/modules (no path traversal).
 */

// Sanitize both module and page to [a-zA-Z0-9_-] — no path traversal possible.
$routeModule = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($_GET['module'] ?? ($_POST['module'] ?? 'dashboard')));

$page = isset($_GET['page'])
    ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $_GET['page'])
    : (isset($_POST['page']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $_POST['page']) : '');
if ($page === '') {
    $page = preg_replace('/[^a-zA-Z0-9_-]/', '', $routeModule);
}

$permissionModule = strtolower((string) ($routeModule === 'home' ? 'dashboard' : $routeModule));
$moduleFs = $permissionModule;

// Case-tolerance: some module folders keep mixed case (e.g. myOffice).
// Fall back to the original-cased folder when the lowercased path is absent.
if (!is_dir(__DIR__ . '/../modules/' . $moduleFs)
    && preg_match('/^[a-zA-Z0-9_-]+$/', $routeModule)
    && is_dir(__DIR__ . '/../modules/' . $routeModule)) {
    $moduleFs = $routeModule;
}
