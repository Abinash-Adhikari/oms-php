<?php
/**
 * SB-Tech — Staff Management / Leave Management (US-LV-01, US-LV-03, US-LV-04).
 * Tabs per reference: Applications · Report · Setup · Allocations.
 * Gated by the manage_staff_leaves special permission (Super Admin bypass).
 */
if (!Auth::isSuperAdmin() && !Auth::hasSpecial('manage_staff_leaves')) {
    echo '<div class="callout callout-warning"><h5>Access denied</h5>'
        . '<p>You do not have the <b>manage_staff_leaves</b> permission. Contact the administrator.</p></div>';
    return;
}

$leaveTabs = [
    'leave_application' => ['label' => 'Applications', 'fa' => 'fa-file-alt'],
    'leave_report'      => ['label' => 'Report', 'fa' => 'fa-chart-bar'],
    'setup_leave'       => ['label' => 'Setup', 'fa' => 'fa-cog'],
    'view_allocations'  => ['label' => 'Allocations', 'fa' => 'fa-layer-group'],
];

$tab = (string) ($_GET['tab'] ?? 'leave_application');
if (!isset($leaveTabs[$tab])) {
    $tab = 'leave_application';
}
?>
<div class="card card-primary card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-umbrella-beach mr-1"></i>Leave Management</h3>
        <div class="card-tools">
            <?php foreach ($leaveTabs as $tk => $meta): ?>
                <a href="<?= pageUrl('staff_management', 'leave_management') ?>&tab=<?= urlencode($tk) ?>"
                   class="btn btn-sm btn-<?= $tab === $tk ? 'primary' : 'default' ?>">
                    <i class="fas <?= e($meta['fa']) ?> mr-1"></i><?= e($meta['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card-body">
        <?php
        $tabFile = __DIR__ . '/includes/' . preg_replace('/[^a-z0-9_]/', '', $tab) . '_tab.php';
        if (is_file($tabFile)) {
            include $tabFile;
        } else {
            echo '<div class="callout callout-secondary"><p>Tab not found.</p></div>';
        }
        ?>
    </div>
</div>
