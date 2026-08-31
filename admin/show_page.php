<?php
/**
 * SB-Tech — admin front controller (read path).
 *
 * GET /admin/show_page.php?module=X&page=Y
 *   1. bootstrap (session, DB, helpers)
 *   2. auth gate → redirect to login.php
 *   3. resolve route (includes/route.php)
 *   4. render theme shell + permission-gated module page
 */

// Start output buffering so PDF generation can replace all output and the
// print preview can discard the admin chrome. When ?pdf=1, ?preview=1, ?print=1,
// or ?word=1 is set, the whole page is buffered; the module either replaces it
// with a PDF (and exits) or flushes only the clean document shell. Without
// this, PDF headers would fail because the admin chrome is already sent.
if (!empty($_GET['pdf']) || !empty($_GET['preview']) || !empty($_GET['print']) || !empty($_GET['word'])) {
    ob_start();
}

include __DIR__ . '/../config/setup.php';

if (!Auth::check()) {
    redirect('login.php');
}

include __DIR__ . '/includes/route.php';

$page = (string) $page;
$permissionModule = (string) $permissionModule;
$moduleFs = (string) $moduleFs;

// Load navigation (used by head + sidebar).
include __DIR__ . '/includes/varriables.php';

// Phase 2 — canonical redirects: old URLs for moved submodules 301 to their
// new home (must run before head.php emits any output).
if (isset($routeCanonical[$permissionModule][$page]) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $canonical = $routeCanonical[$permissionModule][$page];
    http_response_code(301);
    redirect(pageUrl($canonical['module'], $canonical['page']));
}

$pageTitle = ($navBars[$permissionModule] ?? ucfirst($permissionModule))
    . (isset($subNavBars[$permissionModule][$page]) ? ' — ' . $subNavBars[$permissionModule][$page] : '');

include __DIR__ . '/includes/head.php';
?>
<?php include __DIR__ . '/includes/topNavBar.php'; ?>
<?php include __DIR__ . '/includes/mainSideBar.php'; ?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0"><?= e($navBars[$permissionModule] ?? ucfirst($permissionModule)) ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?= pageUrl('dashboard') ?>">Home</a></li>
                        <li class="breadcrumb-item active"><?= e($subNavBars[$permissionModule][$page] ?? $navBars[$permissionModule] ?? ucfirst($page)) ?></li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?= renderFlash() ?>
            <?php
            if (!Auth::can($permissionModule, $page)) {
                echo '<div class="callout callout-warning"><h5>Access denied</h5>'
                    . '<p>You are not permitted to access <b>' . e($permissionModule . ($page !== '' && $page !== $permissionModule ? '/' . $page : '')) . '</b>. '
                    . 'Please contact the administrator.</p></div>';
            } elseif ($moduleFs === 'admin') {
                // Reserved for future admin-only pages.
                echo '<div class="callout callout-info"><p>Admin module — page not installed yet.</p></div>';
            } elseif (file_exists(__DIR__ . '/modules/' . $moduleFs . '/' . $page . '.php')) {
                include __DIR__ . '/modules/' . $moduleFs . '/' . $page . '.php';
            } elseif (file_exists(__DIR__ . '/modules/' . $moduleFs . '/home.php')) {
                include __DIR__ . '/modules/' . $moduleFs . '/home.php';
            } else {
                echo '<div class="callout callout-secondary"><h5>Page not installed</h5>'
                    . '<p>Module <b>' . e($permissionModule) . '</b> page <b>' . e($page) . '</b> has no implementation yet. '
                    . 'It is part of a later build phase.</p></div>';
            }
            ?>
        </div>
    </section>
</div>
<!-- /.content-wrapper -->

<?php include __DIR__ . '/includes/footer.php'; ?>
