<?php
include __DIR__ . '/../config/setup.php';

$orgName  = defined('ORGANIZATION_NAME') && ORGANIZATION_NAME !== '' ? (string) ORGANIZATION_NAME : config('organization_name', 'Office');
$orgShort = defined('ORGANIZATION_SHORT_NAME') && ORGANIZATION_SHORT_NAME !== '' ? (string) ORGANIZATION_SHORT_NAME : config('organization_short_name', 'Office');

// Already logged in → dashboard.
if (Auth::check()) {
    redirect(pageUrl('dashboard'));
}

$errorMsg = $_SESSION['login_err_msg'] ?? null;
unset($_SESSION['login_err_msg']);

$rateLimited = !empty($_SESSION['login_rate_limited']);
unset($_SESSION['login_rate_limited']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/theme-boot.php'; ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | <?= e($orgName) ?></title>
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
<div class="login-box">
    <div class="login-logo">
        <a href="#"><b><?= e($orgShort) ?></b></a>
    </div>
    <!-- /.login-logo -->
    <div class="card">
        <div class="card-body login-card-body">
            <p class="login-box-msg">Sign in to <?= e($orgName) ?></p>

            <?php if ($errorMsg): ?>
                <div class="alert <?= $rateLimited ? 'alert-warning' : 'alert-danger' ?> alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <i class="fas fa-<?= $rateLimited ? 'hourglass-half' : 'exclamation-triangle' ?>"></i>
                    <?= e($errorMsg) ?>
                </div>
            <?php endif; ?>

            <form action="loginOperation.php" method="post" id="loginForm">
                <?= csrfField() ?>
                <div class="input-group mb-3">
                    <input type="text" name="userId" class="form-control" placeholder="Username" required autofocus autocomplete="username" <?= $rateLimited ? 'disabled' : '' ?>>
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-user"></span></div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="password" name="password" class="form-control" placeholder="Password" required autocomplete="current-password" <?= $rateLimited ? 'disabled' : '' ?>>
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-lock"></span></div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-block" id="loginBtn" <?= $rateLimited ? 'disabled' : '' ?>>
                            <?= $rateLimited ? '<i class="fas fa-clock"></i> Temporarily Locked' : 'Sign In' ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <!-- /.login-card-body -->
    </div>
</div>
<!-- /.login-box -->

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($rateLimited): ?>
<script>
(function() {
    var btn = document.getElementById('loginBtn');
    var inputs = document.querySelectorAll('#loginForm input[disabled]');
    // Auto-refresh after 60 seconds as a simple fallback.
    setTimeout(function() { location.reload(); }, 60000);
})();
</script>
<?php endif; ?>
</body>
</html>
