<?php
include __DIR__ . '/../config/setup.php';

$orgName  = office_display_name();
$orgShort = office_display_short_name();

// Already logged in → dashboard.
if (Auth::check()) {
    redirect(pageUrl('dashboard'));
}

$errorMsg = $_SESSION['login_err_msg'] ?? null;
unset($_SESSION['login_err_msg']);

$rateLimited = !empty($_SESSION['login_rate_limited']);
unset($_SESSION['login_rate_limited']);

// Brand logo (best-effort; falls back to an initial monogram).
$orgLogo = null;
try {
    $profile = Database::instance()->selectOne('SELECT `logo` FROM `tbl_office_profiles` WHERE `id` = 1');
    if (!empty($profile['logo'])) {
        $orgLogo = assetUrl('user_uploads/' . $profile['logo']);
    }
} catch (Throwable $e) {
    // logo lookup is best-effort
}
$monogram = mb_strtoupper(mb_substr($orgShort, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/theme-boot.php'; ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in | <?= e($orgName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap">

    <!-- AdminLTE 3 bundles Bootstrap 4 styles; no separate bootstrap.min.css needed. -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= assetUrl('assets/css/theme-variables.css') ?>">
    <link rel="stylesheet" href="<?= assetUrl('assets/css/adminlte-overrides.css') ?>">
    <link rel="stylesheet" href="<?= assetUrl('assets/css/admin.css') ?>">
</head>
<body class="hold-transition login-page">
<div class="premium-login">

    <!-- ── Brand panel (hidden below lg) ── -->
    <aside class="premium-login-brand">
        <div class="pl-brand-orb pl-brand-orb--a"></div>
        <div class="pl-brand-orb pl-brand-orb--b"></div>
        <div class="pl-brand-grid"></div>

        <div class="pl-brand-top">
            <div class="pl-brand-mark">
                <?php if ($orgLogo): ?>
                    <img src="<?= e($orgLogo) ?>" alt="logo">
                <?php else: ?>
                    <span class="pl-brand-monogram"><?= e($monogram) ?></span>
                <?php endif; ?>
            </div>
            <div class="pl-brand-id">
                <div class="pl-brand-name"><?= e($orgShort) ?></div>
                <div class="pl-brand-sub"><?= e($orgName) ?></div>
            </div>
        </div>

        <div class="pl-brand-body">
            <h1 class="pl-brand-heading">Welcome back.</h1>
            <p class="pl-brand-tagline">Sign in to manage your office — staff, leads, finance and everything in between, from one workspace.</p>

            <ul class="pl-features">
                <li class="pl-feature"><i class="fas fa-shield-halved"></i><span>Bank-grade security on every session</span></li>
                <li class="pl-feature"><i class="fas fa-bolt"></i><span>One dashboard for the whole organisation</span></li>
                <li class="pl-feature"><i class="fas fa-mobile-screen"></i><span>Works from anywhere, on any device</span></li>
            </ul>
        </div>

        <div class="pl-brand-foot">
            &copy; <?= date('Y') ?> <?= e($orgShort) ?>. All rights reserved.
        </div>
    </aside>

    <!-- ── Form panel ── -->
    <main class="premium-login-form">
        <div class="pl-card">
            <button type="button" class="pl-theme-toggle" id="plThemeToggle" title="Toggle theme" aria-label="Toggle theme">
                <i class="fas fa-moon"></i>
            </button>

            <div class="pl-card-head">
                <div class="pl-card-mark">
                    <?php if ($orgLogo): ?>
                        <img src="<?= e($orgLogo) ?>" alt="logo">
                    <?php else: ?>
                        <span class="pl-brand-monogram"><?= e($monogram) ?></span>
                    <?php endif; ?>
                </div>
                <h2 class="pl-card-title">Sign in</h2>
                <p class="pl-card-sub">Enter your credentials to access the dashboard.</p>
            </div>

            <?php if ($errorMsg): ?>
                <div class="pl-alert <?= $rateLimited ? 'pl-alert--warn' : 'pl-alert--danger' ?>" role="alert">
                    <i class="fas fa-<?= $rateLimited ? 'hourglass-half' : 'triangle-exclamation' ?>"></i>
                    <span><?= e($errorMsg) ?></span>
                </div>
            <?php endif; ?>

            <form action="loginOperation.php" method="post" id="loginForm" class="pl-form">
                <?= csrfField() ?>
                <div class="pl-field">
                    <i class="fas fa-user pl-field-icon"></i>
                    <input type="text" name="userId" class="pl-input" placeholder="Username or User ID" required autofocus autocomplete="username" <?= $rateLimited ? 'disabled' : '' ?>>
                </div>
                <div class="pl-field">
                    <i class="fas fa-lock pl-field-icon"></i>
                    <input type="password" name="password" class="pl-input" id="plPassword" placeholder="Password" required autocomplete="current-password" <?= $rateLimited ? 'disabled' : '' ?>>
                    <button type="button" class="pl-eye" id="plEye" tabindex="-1" aria-label="Show password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>

                <button type="submit" class="pl-submit" id="loginBtn" <?= $rateLimited ? 'disabled' : '' ?>>
                    <?php if ($rateLimited): ?>
                        <i class="fas fa-clock mr-1"></i>Temporarily Locked
                    <?php else: ?>
                        <span class="pl-submit-label">Sign In</span>
                        <i class="fas fa-arrow-right pl-submit-arrow"></i>
                        <span class="pl-spinner d-none"><i class="fas fa-spinner fa-spin"></i></span>
                    <?php endif; ?>
                </button>
            </form>

            <div class="pl-card-foot">
                <i class="fas fa-lock mr-1"></i>Protected by secure sign-in
            </div>
        </div>

        <a href="<?= e((string) config('server_path', '')) ?>" class="pl-back-link"><i class="fas fa-arrow-left mr-1"></i>Back to website</a>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    'use strict';
    var form = document.getElementById('loginForm');
    var btn = document.getElementById('loginBtn');

    // ── Submit loading state ──
    if (form && btn && !btn.disabled) {
        form.addEventListener('submit', function () {
            btn.disabled = true;
            var label = btn.querySelector('.pl-submit-label');
            var arrow = btn.querySelector('.pl-submit-arrow');
            var spin = btn.querySelector('.pl-spinner');
            if (label) { label.textContent = 'Signing in…'; }
            if (arrow) { arrow.classList.add('d-none'); }
            if (spin) { spin.classList.remove('d-none'); }
        });
    }

    // ── Show / hide password ──
    var eye = document.getElementById('plEye');
    var pw = document.getElementById('plPassword');
    if (eye && pw) {
        eye.addEventListener('click', function () {
            var show = pw.type === 'password';
            pw.type = show ? 'text' : 'password';
            eye.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
        });
    }

    // ── Theme toggle (mirrors theme-boot storage keys) ──
    var toggle = document.getElementById('plThemeToggle');
    function paintToggle() {
        var mode = document.documentElement.getAttribute('data-mode') === 'dark' ? 'dark' : 'light';
        toggle.querySelector('i').className = mode === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
    if (toggle) {
        toggle.addEventListener('click', function () {
            var mode = (document.documentElement.getAttribute('data-mode') === 'dark') ? 'light' : 'dark';
            document.documentElement.setAttribute('data-mode', mode);
            document.documentElement.setAttribute('data-theme', mode);
            try { localStorage.setItem('cmsThemeMode', mode); } catch (e) { /* quota */ }
            paintToggle();
        });
        paintToggle();
    }

    // ── Rate-limited auto-refresh fallback ──
    <?php if ($rateLimited): ?>
    setTimeout(function () { location.reload(); }, 60000);
    <?php endif; ?>
})();
</script>
</body>
</html>
