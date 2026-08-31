<?php
require __DIR__ . '/website/includes/site.php';
$services = siteRows('tbl_cms_services', '`position`, `id`', '`id`, `slug`, `title`, `icon`, `short_description`, `description`, `image_location`');
$detail = null;
if (isset($_GET['slug']) && $_GET['slug'] !== '') {
    $slug = preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['slug']));
    foreach ($services as $s) {
        if ($s['slug'] === $slug) {
            $detail = $s;
            break;
        }
    }
    // ID was provided but not found → 404.
    if (!$detail) {
        http_response_code(404);
        require __DIR__ . '/website/includes/header.php';
        ?>
        <section class="py-5 text-center">
            <div class="container">
                <h1 class="error-code mb-3">404</h1>
                <h2 class="mb-3">Service not found</h2>
                <p class="text-muted mb-4">The service you're looking for doesn't exist.</p>
                <a href="<?= siteUrl('services') ?>" class="btn btn-primary">Back to Services</a>
            </div>
        </section>
        <?php
        require __DIR__ . '/website/includes/footer.php';
        exit;
    }
}
require __DIR__ . '/website/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <?php if ($detail): ?>
            <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= siteUrl('services') ?>">Services</a></li><li class="breadcrumb-item active"><?= e($detail['title']) ?></li></ol></nav>
            <h1><?= e($detail['title']) ?></h1>
        <?php else: ?>
            <h1>Our services</h1>
            <p>Comprehensive technology solutions tailored to elevate your business</p>
        <?php endif; ?>
    </div>
</section>

<section>
    <div class="container">
        <?php if ($detail): ?>
            <div class="row" data-reveal-group>
                <div class="col-lg-8 reveal">
                    <?php if ($detail['icon']): ?><span class="icon-chip mb-3"><i class="<?= e($detail['icon']) ?>"></i></span><?php endif; ?>
                    <p style="font-size: 1.0625rem; line-height: 1.8; color: var(--text-secondary);"><?= nl2br(e($detail['description'] ?: $detail['short_description'])) ?></p>
                    <a href="<?= siteUrl('contact') ?>" class="btn btn-primary btn-lg mt-2">Request this service <i class="fas fa-arrow-right ml-2"></i></a>
                </div>
                <?php if ($detail['image_location']): ?>
                    <div class="col-lg-4 detail-media reveal">
                        <img src="<?= e(siteUrl('user_uploads/' . $detail['image_location'])) ?>" class="img-fluid" loading="lazy" alt="<?= e($detail['title']) ?>">
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="row" data-reveal-group>
                <?php foreach ($services as $s): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100 reveal">
                            <div class="card-body text-center p-4">
                                <?php if ($s['icon']): ?><div class="icon-wrapper mx-auto mb-4"><i class="<?= e($s['icon']) ?>"></i></div><?php endif; ?>
                                <h5 class="card-title-sm"><?= e($s['title']) ?></h5>
                                <p class="card-text-sm"><?= e($s['short_description']) ?></p>
                                <a href="<?= siteUrl('services') ?>/<?= e($s['slug']) ?>" class="btn btn-sm btn-outline-primary">Learn more</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$services): ?><p class="empty-note w-100">Services coming soon.</p><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/website/includes/footer.php'; ?>
