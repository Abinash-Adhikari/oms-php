<?php
/**
 * SB-Tech — shared JS includes (jQuery, Bootstrap, AdminLTE, select2).
 */
?>
<!-- Organization name for JS -->
<script>var APP_ORG_NAME = <?= json_encode(config('organization_name', 'Office')) ?>;</script>
<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="<?= assetUrl('assets/js/admin.js') ?>"></script>
<script src="<?= assetUrl('assets/js/file-upload-preview.js') ?>"></script>
<script src="<?= assetUrl('assets/js/theme-switcher.js') ?>"></script>
<?php if ((($_GET['module'] ?? '') === 'sales') && (($page ?? '') === 'documents') && empty($_GET['print']) && empty($_GET['pdf']) && empty($_GET['preview'])): ?>
<!-- Summernote — sales documents only (NOT on print/PDF/preview output) -->
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs4.min.js"></script>
<?php endif; ?>
<?php if (($_GET['module'] ?? '') === 'dashboard'): ?>
<!-- Chart.js — dashboard only -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= assetUrl('assets/js/dashboard-charts.js') ?>"></script>
<?php endif; ?>
<script src="<?= assetUrl('assets/js/notifications-client.js') ?>"></script>
<?php if (useBsDates() && bsCalendarAvailable()): ?>
<!-- Nepali (BS) datepicker — active when Calendar mode = BS -->
<script src="<?= assetUrl('assets/js/bs-calendar-data.js') ?>"></script>
<script src="<?= assetUrl('assets/js/bs-datepicker.js') ?>"></script>
<?php endif; ?>
