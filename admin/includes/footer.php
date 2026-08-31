<?php
/**
 * SB-Tech — footer. Closes the wrapper opened in head.php.
 */
?>
</div>
<!-- ./wrapper -->

<!-- Footer -->
<footer class="main-footer">
    <strong>&copy; <?= date('Y') ?> <a href="#"><?= e(config('organization_name', 'Office')) ?></a>.</strong>
    All rights reserved.
</footer>

<?php include __DIR__ . '/javascript.php'; ?>
</body>
</html>
