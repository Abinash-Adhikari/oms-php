<?php
/**
 * SB-Tech — footer (Smart-School style). Closes the wrapper opened in
 * head.php, renders the footer, then loads shared JS and closes the document.
 * NOTE: in ?pdf=1|preview=1|print=1|word=1 output the module replaces/flushes
 * this buffer before it is reached — keep this file output-light.
 */
?>
</div>
<!-- ./wrapper -->

<!-- Footer -->
<footer class="main-footer text-center">
    <div class="container">
        <div class="row row-sm">
            <div class="col-md-12">
                <span><strong><?= e(config('organization_name', 'Office')) ?></strong> © <?php echo date('Y'); ?>.
                    All rights reserved.</span>
            </div>
        </div>
    </div>
</footer>

<?php include __DIR__ . '/javascript.php'; ?>
</body>
</html>