<?php

/**
 * SB-Tech — admin theme head (Smart-School shell ported wholesale).
 * Requires $loginPageMode to be false normally.
 * Uses local AdminLTE 3 + Bootstrap 4 + Font Awesome copies from the
 * merged theme folder (admin/assets + admin/theme2).
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
    <!-- User ID for the shell JS (badges, polls) -->
    <meta data-user-id="<?= (int) Auth::id() ?>">
    <title><?= e($pageTitle) ?> | <?= e($orgName) ?></title>

    <?php
    // Favicon from the office profile logo (best-effort).
    $tabIcon = '../favicon.ico';
    try {
        $officeProfile = Database::instance()->selectOne(
            'SELECT `id`, `logo` FROM `tbl_office_profiles` WHERE `id` = 1'
        );
        if (!empty($officeProfile['logo'])) {
            $logoRel = ltrim((string) $officeProfile['logo'], '/');
            $logoFs  = dirname(__DIR__, 2) . '/user_uploads/' . $logoRel;
            if ($logoRel !== '' && is_file($logoFs)) {
                $tabIcon = '../user_uploads/' . $logoRel . '?v=' . filemtime($logoFs);
            }
        }
    } catch (Throwable $e) {
        // Icon is decorative — never break the head on a DB hiccup.
    }
    ?>
    <link rel="icon" href="<?= e($tabIcon) ?>">
    <link rel="shortcut icon" href="<?= e($tabIcon) ?>">

    <?php
    $__bsMode  = function_exists('useBsDates') && useBsDates();
    $__calMode = $__bsMode ? 'BS' : 'AD';
    $__calAd   = !$__bsMode;
    $__todayPicker = date('Y-m-d');
    if ($__bsMode && function_exists('adToBs')) {
        $__todayPicker = adToBs($__todayPicker) ?? $__todayPicker;
    }
    ?>
    <script>
        window.APP_CALENDAR_MODE = <?= json_encode($__calMode, JSON_UNESCAPED_UNICODE) ?>;
        window.APP_USE_AD_DATES = <?= $__calAd ? 'true' : 'false' ?>;
        window.APP_TODAY_PICKER = <?= json_encode($__todayPicker, JSON_UNESCAPED_UNICODE) ?>;
        window.APP_DATE_INPUT_PLACEHOLDER = 'YYYY-MM-DD';
    </script>

    <!-- Font preconnect + premium fonts (moved out of CSS @import — non-blocking) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Sans+Pro:ital,wght@0,300;0,400;0,600;0,700;1,400&display=swap">

    <!-- SB-Tech base (module pages depend on these tokens) -->
    <link rel="stylesheet" href="<?= assetUrl('assets/css/admin.css') ?>">
    <link rel="stylesheet" href="<?= assetUrl('assets/css/theme-variables.css') ?>">
    <link rel="stylesheet" href="<?= assetUrl('assets/css/adminlte-overrides.css') ?>">

    <!-- Bootstrap CSS -->
    <link href="theme2/assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="./assets/plugins/fontawesome-free/css/all.min.css">
    <link href="theme2/assets/plugins/web-fonts/font-awesome/font-awesome.min.css" rel="stylesheet">

    <!-- Theme CSS -->
    <link rel="stylesheet" href="./assets/dist/css/adminlte.min.css">
    <link id="theme" rel="stylesheet" type="text/css" media="all" href="theme2/assets/css/colors/color.css">

    <!-- Date & Time Pickers -->
    <link href="theme2/assets/plugins/bootstrap-datepicker/bootstrap-datepicker.css" rel="stylesheet">
    <link href="theme2/assets/plugins/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet">
    <link rel="stylesheet" href="./assets/plugins/daterangepicker/daterangepicker.css">
    <link rel="stylesheet" href="./assets/plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css">
    <link href="theme2/assets/plugins/bootstrap-datetimepicker/bootstrap-datetimepicker.min.css" rel="stylesheet">

    <!-- Data Tables -->
    <link href="theme2/assets/plugins/datatable/dataTables.bootstrap4.min.css" rel="stylesheet">
    <link href="theme2/assets/plugins/datatable/responsivebootstrap4.min.css" rel="stylesheet">
    <link href="theme2/assets/plugins/datatable/fileexport/buttons.bootstrap4.min.css" rel="stylesheet">

    <!-- Select2 -->
    <link rel="stylesheet" href="./assets/plugins/select2/css/select2.min.css">
    <link rel="stylesheet" href="./assets/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">

    <!-- Other Plugins -->
    <link rel="stylesheet" href="./assets/plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
    <link rel="stylesheet" href="./assets/plugins/dropzone/min/dropzone.min.css">
    <link rel="stylesheet" href="./assets/plugins/fullcalendar/main.css">
    <link rel="stylesheet" href="./assets/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
    <link rel="stylesheet" href="./assets/plugins/toastr/toastr.min.css">
    <link href="theme2/assets/plugins/summernote/summernote-bs4.min.css" rel="stylesheet">

    <!-- Nepali Datepicker (B.S. UI; v3.7 NepaliFunctions for conversion) -->
    <link rel="stylesheet" href="theme2/assets/css/nepali.datepicker.v3.7.min.css">

    <?php if (useBsDates() && bsCalendarAvailable()): ?>
        <!-- SB-Tech native <input type="date"> BS upgrade -->
        <link rel="stylesheet" href="<?= assetUrl('assets/css/bs-datepicker.css') ?>">
    <?php endif; ?>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="./assets/css/cms-scroll-tables.css">
    <link rel="stylesheet" href="./assets/css/custom.css">
    <link rel="stylesheet" href="./assets/css/cms-theme.css?v=<?= @filemtime(__DIR__ . '/../assets/css/cms-theme.css') ?: '1' ?>">
    <link rel="stylesheet" href="./assets/css/style.css">
    <?php include_once __DIR__ . '/admin_list_ui.css.php'; ?>

    <!-- this should go after your </body> -->
    <style>
        /* Theme-aware Select2 text (do not hardcode black — breaks dark mode) */
        .select2-container--default .select2-selection--single .select2-selection__rendered,
        .select2-container--classic .select2-selection--single .select2-selection__rendered,
        .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
            color: var(--cms-text, #0f172a) !important;
        }

        .select2-results__option--highlighted,
        .select2-results__option--highlighted[aria-selected],
        .select2-results__option--highlighted[aria-selected="true"],
        .select2-results__option--highlighted[aria-selected="false"],
        .select2-container--default .select2-results__option--highlighted,
        .select2-container--default .select2-results__option--highlighted[aria-selected],
        .select2-container--default .select2-results__option--highlighted[aria-selected="true"],
        .select2-container--default .select2-results__option--highlighted[aria-selected="false"],
        .select2-container--classic .select2-results__option--highlighted,
        .select2-container--bootstrap4 .select2-results__option--highlighted {
            background-color: #047857 !important;
            color: #ffffff !important;
        }

        .select2-results__option--highlighted *,
        .select2-container--default .select2-results__option--highlighted *,
        .select2-results__option--highlighted .text-muted,
        .select2-results__option--highlighted .student-info,
        .select2-results__option--highlighted .lib-copy-meta,
        .select2-results__option--highlighted .lib-copy-option__title {
            color: #ffffff !important;
        }
    </style>

</head>

<body class="hold-transition sidebar-mini layout-fixed cms-admin cms-staff-app">
    <div class="wrapper">