<?php
require __DIR__ . '/website/includes/site.php';
require __DIR__ . '/website/includes/header.php';
?>
<section class="py-5 text-center" style="padding-top: 6rem; padding-bottom: 6rem;">
    <div class="container">
        <h1 class="error-code mb-3">404</h1>
        <h2 class="mb-3">Page not found</h2>
        <p class="text-muted mb-4">The page you're looking for doesn't exist or has been moved.</p>
        <a href="<?= siteUrl() ?>" class="btn btn-primary btn-lg">Back to Home <i class="fas fa-arrow-right ml-2"></i></a>
    </div>
</section>
<?php require __DIR__ . '/website/includes/footer.php'; ?>
