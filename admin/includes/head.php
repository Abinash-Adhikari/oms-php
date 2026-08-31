<?php

/**
 * SB-Tech — admin theme head. Requires $loginPageMode to be false normally.
 * Uses AdminLTE 3 + Bootstrap 4 + Font Awesome from CDN (per reference).
 */
$pageTitle = $pageTitle ?? $navBars[$permissionModule] ?? config('organization_name', 'Office');
$orgShort  = defined('ORGANIZATION_SHORT_NAME') && ORGANIZATION_SHORT_NAME !== ''
    ? (string) ORGANIZATION_SHORT_NAME : config('organization_short_name', 'Office');
$orgName   = defined('ORGANIZATION_NAME') && ORGANIZATION_NAME !== ''
    ? (string) ORGANIZATION_NAME : config('organization_name', 'Office');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Theme boot (MUST be first — prevents flash of wrong theme) -->
    <?php include __DIR__ . '/theme-boot.php'; ?>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="csrf-token" content="<?= e(csrfToken()) ?>">
    <!-- User ID for notification client -->
    <meta data-user-id="<?= (int) Auth::id() ?>">
    <title><?= e($pageTitle) ?> | <?= e($orgName) ?></title>
    <!-- Font preconnect + premium fonts (moved out of CSS @import — non-blocking) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap">

    <!-- NOTE: AdminLTE 3 bundles Bootstrap 4 styles — loading stock bootstrap.min.css
         after it lets generic rules override AdminLTE's customized ones. Do not re-add. -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap4-theme@1.0.0/dist/select2-bootstrap4.min.css">
    <link rel="stylesheet" href="<?= assetUrl('assets/css/admin.css') ?>">
    <link rel="stylesheet" href="<?= assetUrl('assets/css/theme-variables.css') ?>">
    <link rel="stylesheet" href="<?= assetUrl('assets/css/adminlte-overrides.css') ?>">
    <link rel="stylesheet" href="<?= assetUrl('assets/css/custom.css') ?>">
    <?php if (useBsDates() && bsCalendarAvailable()): ?>
        <link rel="stylesheet" href="<?= assetUrl('assets/css/bs-datepicker.css') ?>">
    <?php endif; ?>
    <?php if ((($_GET['module'] ?? '') === 'sales') && (($page ?? '') === 'documents') && empty($_GET['print']) && empty($_GET['pdf']) && empty($_GET['preview'])): ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs4.min.css">
    <?php endif; ?>
</head>

<body class="hold-transition sidebar-mini layout-fixed sidebar-collapse">
    <div class="wrapper">