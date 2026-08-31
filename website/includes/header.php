<?php

/** @var string $sitePage set by site.php */
$setup = siteSetup();
$siteTitle = $setup['site_title'] ?? config('organization_name', 'Office');
$tagline = $setup['tagline'] ?? 'Your technology partner';
$nav = [
    'home'     => siteUrl(),
    'about'    => siteUrl('about'),
    'services' => siteUrl('services'),
    'projects' => siteUrl('projects'),
    'team'     => siteUrl('team'),
    'news'     => siteUrl('news'),
    'notices'  => siteUrl('notices'),
    'careers'  => siteUrl('careers'),
    'contact'  => siteUrl('contact'),
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Theme boot (inline, blocking): honors prefs saved via the theme switcher
         (same localStorage keys as the admin panel) and prevents a flash of light mode. -->
    <script>
        (function() {
            try {
                var mode = localStorage.getItem('app_color_mode') || 'light';
                var accent = localStorage.getItem('app_accent') || 'blue';
                if (mode !== 'light' && mode !== 'dark') {
                    mode = 'light';
                }
                document.documentElement.setAttribute('data-mode', mode);
                document.documentElement.setAttribute('data-accent', accent);
            } catch (e) {
                document.documentElement.setAttribute('data-mode', 'light');
                document.documentElement.setAttribute('data-accent', 'blue');
            }
        })();
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($setup['seo_meta_description'] ?? '') ?>">

    <!-- Preconnect for performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preconnect" href="https://code.jquery.com" crossorigin>

    <!-- DNS Prefetch -->
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="https://code.jquery.com">

    <title><?= e($siteTitle) ?> — <?= e($tagline) ?></title>
    <meta name="keywords" content="<?= e($setup['seo_meta_keywords'] ?? '') ?>">

    <!-- Premium Fonts: Poppins + Open Sans -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap">

    <!-- Bootstrap 4 + Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <!-- Theme Variables + Site Styles -->
    <link rel="stylesheet" href="<?= siteAsset('css/theme-variables.css') ?>">
    <link rel="stylesheet" href="<?= siteAsset('css/site.css') ?>">

    <!-- Favicon -->
    <?php if (!empty($setup['favicon'])): ?>
        <link rel="icon" type="image/x-icon" href="<?= e(siteUrl('user_uploads/' . $setup['favicon'])) ?>">
    <?php endif; ?>
</head>

<body>
    <nav class="navbar navbar-expand-lg site-navbar" id="siteNavbar">
        <div class="container">
            <a class="navbar-brand" href="<?= siteUrl() ?>" data-admin-url="<?= e(siteUrl('admin/login.php')) ?>">
                <?php if (!empty($setup['logo'])): ?>
                    <img src="<?= e(siteUrl('user_uploads/' . $setup['logo'])) ?>" alt="<?= e($siteTitle) ?>" height="36" style="margin-right: 0.5rem;">
                <?php endif; ?>
                <?= e($siteTitle) ?>
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#siteNav" aria-controls="siteNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="siteNav">
                <ul class="navbar-nav ml-auto">
                    <?php foreach ($nav as $key => $url): ?>
                        <li class="nav-item<?= $sitePage === $key ? ' active' : '' ?>">
                            <a class="nav-link" href="<?= e($url) ?>"><?= e(ucfirst($key)) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if (!empty($setup['contact_phone'])): ?>
                    <a href="tel:<?= e($setup['contact_phone']) ?>" class="site-phone-link ml-3 d-none d-lg-inline-flex align-items-center">
                        <span class="site-phone-icon"><i class="fas fa-phone-alt"></i></span>
                        <span class="site-phone-number"><?= e($setup['contact_phone']) ?></span>
                    </a>
                <?php endif; ?>
                <button type="button" id="siteModeToggle" class="site-mode-toggle ml-lg-3"
                    aria-label="Switch between light and dark mode">
                    <i class="fas fa-moon" data-mode-icon="dark" hidden></i>
                    <i class="fas fa-sun" data-mode-icon="light" hidden></i>
                </button>
            </div>
        </div>
    </nav>
    <main>
        <div class="container mt-3">
            <?= renderFlash() ?>
        </div>

        <!-- Premium Navbar Scroll Effect -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const navbar = document.getElementById('siteNavbar');
                let lastScroll = 0;

                window.addEventListener('scroll', function() {
                    const currentScroll = window.pageYOffset;

                    if (currentScroll > 50) {
                        navbar.classList.add('scrolled');
                    } else {
                        navbar.classList.remove('scrolled');
                    }

                    lastScroll = currentScroll;
                });
            });
        </script>