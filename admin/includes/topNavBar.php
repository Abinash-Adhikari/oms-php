<?php
/**
 * SB-Tech — top navbar: sidebar toggle, page breadcrumb, user dropdown,
 * logout. Uses $_SESSION keys set at login (userId, username, fullname).
 */
$orgShort = defined('ORGANIZATION_SHORT_NAME') && ORGANIZATION_SHORT_NAME !== ''
    ? (string) ORGANIZATION_SHORT_NAME : config('organization_short_name', 'Office');

/** Initials for the premium avatar chip (falls back to a user icon). */
$userFullname = trim((string) ($_SESSION['fullname'] ?? ''));
$userInitials = '';
if ($userFullname !== '') {
    $words = preg_split('/\s+/', $userFullname, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $first = $words[0] ?? '';
    $last  = count($words) > 1 ? $words[count($words) - 1] : '';
    $userInitials = mb_strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
}
?>
<!-- Navbar -->
<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
            <a href="<?= pageUrl('dashboard') ?>" class="nav-link"><?= e($orgShort) ?></a>
        </li>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
        <!-- SSE Status Indicator -->
        <li class="nav-item d-none d-sm-inline-flex align-items-center">
            <span id="sse-status" class="sse-status"></span>
        </li>

        <!-- Notification Bell -->
        <li class="nav-item dropdown">
            <a class="nav-link position-relative" data-toggle="dropdown" href="#" id="notif-bell">
                <i class="far fa-bell"></i>
                <span id="notif-badge" class="notification-badge" style="display:none;"></span>
            </a>
            <div class="dropdown-menu dropdown-menu-right notification-dropdown">
                <div class="notif-header">
                    <h6>Notifications</h6>
                    <a href="#" id="notif-mark-all" class="text-muted" style="font-size:.75rem;">Mark all read</a>
                </div>
                <div class="notif-body" id="notif-body">
                    <div class="notif-empty">
                        <i class="fas fa-bell-slash fa-2x mb-2 d-block" style="opacity:.3"></i>
                        No notifications yet
                    </div>
                </div>
                <div class="notif-footer">
                    <a href="<?= pageUrl('dashboard') ?>">View All Notifications</a>
                </div>
            </div>
        </li>

        <!-- Theme Switcher -->
        <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#" title="Change theme">
                <i class="fas fa-palette"></i>
            </a>
            <div class="dropdown-menu dropdown-menu-right theme-switcher-dropdown" id="theme-switcher-container">
                <!-- Populated by theme-switcher.js -->
            </div>
        </li>

        <!-- User Dropdown -->
        <li class="nav-item dropdown">
            <a class="nav-link d-flex align-items-center" data-toggle="dropdown" href="#">
                <?php if ($userInitials !== ''): ?>
                    <span class="user-avatar-chip"><?= e($userInitials) ?></span>
                <?php else: ?>
                    <span class="user-avatar-chip"><i class="fas fa-user"></i></span>
                <?php endif; ?>
                <span class="d-none d-sm-inline ml-1"><?= e($userFullname) ?></span>
            </a>
            <div class="dropdown-menu dropdown-menu-right">
                <span class="dropdown-header">
                    <?= e($_SESSION['fullname'] ?? '') ?><br>
                    <small class="text-muted">@<?= e($_SESSION['username'] ?? '') ?></small>
                </span>
                <div class="dropdown-divider"></div>
                <a href="logout.php" class="dropdown-item"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
            </div>
        </li>
    </ul>
</nav>
<!-- /.navbar -->
