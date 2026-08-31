<?php
/**
 * SB-Tech — main sidebar. Renders sections (MAIN/OFFICE/...) with
 * modules + submodules, filtered by the user's RBAC grants
 * (AC-AUTH-01.3: sidebar renders only permitted modules/submodules).
 * Active item is derived from the routed $permissionModule / $page.
 */

/** @var string $permissionModule routed module key */
/** @var string $page routed page key */

$allowedModules = [];
foreach ($modules as $m) {
    if (Auth::hasModule($m)) {
        $allowedModules[] = $m;
    }
}

// Filter submodules by grant when the user is not a super admin.
$isSuper = Auth::isSuperAdmin();
$filteredSubs = [];
foreach ($subNavBars as $mod => $subs) {
    foreach ($subs as $subKey => $subLabel) {
        if ($isSuper || Auth::hasSubmodule($mod, (string) $subKey)) {
            $filteredSubs[$mod][$subKey] = $subLabel;
        }
    }
}

$activeModule = strtolower((string) ($permissionModule ?? ''));
$activePage   = strtolower((string) ($page ?? ''));

/** Initials for the pinned sidebar user card (Smart-School pattern). */
$sbFullname = trim((string) ($_SESSION['fullname'] ?? ''));
$sbUsername = (string) ($_SESSION['username'] ?? '');
$sbInitials = '';
if ($sbFullname !== '') {
    $sbWords   = preg_split('/\s+/', $sbFullname, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $sbFirst   = $sbWords[0] ?? '';
    $sbLast    = count($sbWords) > 1 ? $sbWords[count($sbWords) - 1] : '';
    $sbInitials = mb_strtoupper(mb_substr($sbFirst, 0, 1) . mb_substr($sbLast, 0, 1));
}

// Live count badges (premium sidebar). Computed only for permitted entries;
// any query failure is silently ignored so the sidebar never breaks.
$navBadges = [];
foreach (($navBadgeQueries ?? []) as $key => $b) {
    $badgeModule = $b['module'] ?? $key;
    if (!Auth::can($badgeModule, $b['page'] ?? '')) {
        continue;
    }
    try {
        $count = (int) Database::instance()->selectOne($b['sql'])['COUNT(*)'];
        if ($count > 0) {
            $navBadges[$key] = ['count' => $count, 'title' => $b['title'] ?? ''];
        }
    } catch (Throwable $e) {
        // Badge is decorative — never block the menu.
    }
}
?>
<aside class="main-sidebar sidebar-dark-primary elevation-0">
    <div class="cms-brand-wrap">
        <a href="<?= pageUrl('dashboard') ?>" class="brand-link">
            <?php
            $orgLogo = null;
            try {
                $profile = Database::instance()->selectOne('SELECT `logo` FROM `tbl_office_profiles` WHERE `id` = 1');
                if (!empty($profile['logo'])) {
                    $orgLogo = assetUrl('user_uploads/' . $profile['logo']);
                }
            } catch (Throwable $e) {
                // logo lookup is best-effort
            }
            ?>
            <?php if ($orgLogo): ?>
                <img src="<?= e($orgLogo) ?>" alt="<?= e(config('organization_short_name', 'Office')) ?>" class="cms-brand-logo">
            <?php endif; ?>
            <span class="brand-text"><?= e(config('organization_short_name', 'Office')) ?></span>
        </a>
    </div>

    <!-- Sidebar (Smart-School shell: search fixed, menu scrolls, user pinned) -->
    <div class="sidebar cms-sidebar">
        <div class="cms-sidebar-search-wrap" title="Search menu">
            <span class="cms-sidebar-search-icon" aria-hidden="true"><i class="fas fa-search"></i></span>
            <input type="search" id="cmsSidebarSearch" class="cms-sidebar-search" placeholder="Search menu…" autocomplete="off" aria-label="Search menu">
        </div>

        <div class="cms-sidebar-scroll">
        <nav class="mt-1">
            <ul class="nav nav-sidebar flex-column cms-sidebar-menu nav-collapse-hide-child" data-widget="treeview" role="menu" data-accordion="false">
                <?php
                $lastSection = null;
                foreach ($modules as $m) {
                    if (!in_array($m, $allowedModules, true)) {
                        continue;
                    }
                    $section = $navSidebarSections[$m] ?? 'MAIN';
                    if ($section !== $lastSection) {
                        $lastSection = $section;
                        echo '<li class="nav-header">' . e($section) . '</li>';
                    }

                    $icon = $icons[$m] ?? 'nav-icon fas fa-circle';
                    $label = $navBars[$m] ?? ucfirst($m);
                    $moduleActive = (strcasecmp($activeModule, $m) === 0);
                    $subs = $filteredSubs[$m] ?? [];

                    if (in_array($m, $singlePageModules, true) || count($subs) <= 1) {
                        $subKey = count($subs) === 1 ? array_key_first($subs) : 'home';
                        // $activePage defaults to the raw module key (route.php) when the URL
                        // carries no explicit page param — e.g. ?module=dashboard resolves to
                        // page 'dashboard', not 'home'. show_page.php already falls back from
                        // modules/$m/$page.php to modules/$m/home.php for this exact case; the
                        // highlight check needs the same fallback or the active class never lands.
                        $subActive = $moduleActive && (
                            strcasecmp($activePage, (string) $subKey) === 0
                            || strcasecmp($activePage, 'home') === 0
                            || strcasecmp($activePage, $m) === 0
                        );
                        echo '<li class="nav-item' . ($moduleActive ? ' menu-open' : '') . '" data-search="' . e(strtolower($label)) . '">';
                        echo '<a href="' . pageUrl($m, (string) $subKey) . '" class="nav-link' . ($moduleActive && $subActive ? ' active' : '') . '">';
                        echo '<i class="' . e($icon) . '"></i><p>' . e($label) . '</p></a></li>';
                        continue;
                    }

                    // Treeview module with submodule children.
                    $searchHaystack = strtolower($label . ' ' . implode(' ', $subs));
                    echo '<li class="nav-item' . ($moduleActive ? ' menu-open' : '') . '" data-search="' . e($searchHaystack) . '">';
                    echo '<a href="#" class="nav-link' . ($moduleActive ? ' active' : '') . '">';
                    echo '<i class="' . e($icon) . '"></i><p>' . e($label) . '<i class="right fas fa-angle-left"></i></p></a>';
                    echo '<ul class="nav nav-treeview">';
                    foreach ($subs as $subKey => $subLabel) {
                        $subActive = $moduleActive && (strcasecmp($activePage, (string) $subKey) === 0);
                        $subIcon = $subIcons[(string) $subKey] ?? 'nav-icon far fa-circle';
                        $badge = $navBadges[(string) $subKey] ?? null;
                        echo '<li class="nav-item">';
                        echo '<a href="' . pageUrl($m, (string) $subKey) . '" class="nav-link' . ($subActive ? ' active' : '') . '">';
                        echo '<i class="' . e($subIcon) . '"></i><p>' . e($subLabel);
                        if ($badge !== null) {
                            echo '<span class="nav-badge-count" title="' . e($badge['title']) . '">' . ($badge['count'] > 99 ? '99+' : (int) $badge['count']) . '</span>';
                        }
                        echo '</p></a></li>';
                    }
                    echo '</ul></li>';
                }
                ?>
            </ul>
        </nav>
        </div><!-- /.cms-sidebar-scroll -->

        <?php if ($sbFullname !== '' || $sbUsername !== ''): ?>
            <div class="cms-sidebar-user">
                <span class="cms-sidebar-user-avatar">
                    <?= $sbInitials !== '' ? e($sbInitials) : '<i class="fas fa-user"></i>' ?>
                </span>
                <div class="cms-sidebar-user-text overflow-hidden">
                    <div class="cms-sidebar-user-name text-truncate"><?= e($sbFullname !== '' ? $sbFullname : $sbUsername) ?></div>
                    <?php if ($sbUsername !== ''): ?>
                        <div class="cms-sidebar-user-meta text-truncate">@<?= e($sbUsername) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</aside>
