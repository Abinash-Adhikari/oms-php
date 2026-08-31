<?php
/**
 * SB-Tech — My Office / HR Care (tabbed shell).
 * Tabs per SYSTEM_MODULES.md §1 OFFICE section:
 * Attendance · Leaves · Profile · Tasks · Meetings · Speak Up.
 * Attendance + Leaves ship in Phase 2; the rest arrive in later phases.
 */
$hrTabs = [
    'attendance' => ['label' => 'Attendance', 'fa' => 'fa-calendar-check'],
    'leaves'     => ['label' => 'Leaves', 'fa' => 'fa-umbrella-beach'],
    'profile'    => ['label' => 'Profile', 'fa' => 'fa-user'],
    'tasks'      => ['label' => 'Tasks', 'fa' => 'fa-tasks'],
    'meetings'   => ['label' => 'Meetings', 'fa' => 'fa-handshake'],
    'speak_up'   => ['label' => 'Speak Up', 'fa' => 'fa-bullhorn'],
];

$tab = (string) ($_GET['tab'] ?? 'attendance');
if (!isset($hrTabs[$tab])) {
    $tab = 'attendance';
}
$canSeeAll = Auth::isSuperAdmin() || Auth::hasSpecial('view_all_attendance');
?>
<div class="card card-primary card-outline">
    <div class="card-header p-0 pt-1 border-bottom-0">
        <ul class="nav nav-tabs" id="hrTabs" role="tablist">
            <?php foreach ($hrTabs as $tk => $meta): ?>
                <li class="nav-item">
                    <a class="nav-link<?= $tab === $tk ? ' active' : '' ?>" href="<?= pageUrl('staff_management', 'hr_care') ?>&tab=<?= urlencode($tk) ?>">
                        <i class="fas <?= e($meta['fa']) ?> mr-1"></i><?= e($meta['label']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="card-body">
        <?php
        $tabFile = __DIR__ . '/includes/' . preg_replace('/[^a-z0-9_]/', '', $tab) . '_tab.php';
        if (is_file($tabFile)) {
            include $tabFile;
        } else {
            include __DIR__ . '/includes/_placeholder_tab.php';
        }
        ?>
    </div>
</div>
