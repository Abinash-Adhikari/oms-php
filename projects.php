<?php
require __DIR__ . '/website/includes/site.php';
$projects = siteRows('tbl_cms_projects', '`position`, `id` DESC', '`id`, `slug`, `title`, `client_name`, `category`, `short_description`, `description`, `technologies`, `project_url`, `image_location`');
$detail = null;
if (isset($_GET['slug']) && $_GET['slug'] !== '') {
    $slug = preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['slug']));
    foreach ($projects as $p) {
        if ($p['slug'] === $slug) {
            $detail = $p;
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
                <h2 class="mb-3">Project not found</h2>
                <p class="text-muted mb-4">The project you're looking for doesn't exist.</p>
                <a href="<?= siteUrl('projects') ?>" class="btn btn-primary">Back to Projects</a>
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
            <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= siteUrl('projects') ?>">Projects</a></li><li class="breadcrumb-item active"><?= e($detail['title']) ?></li></ol></nav>
            <h1><?= e($detail['title']) ?></h1>
            <?php if ($detail['client_name']): ?><p>Client: <?= e($detail['client_name']) ?></p><?php endif; ?>
        <?php else: ?>
            <h1>Our projects</h1>
            <p>A portfolio of work we're proud of</p>
        <?php endif; ?>
    </div>
</section>

<section>
    <div class="container">
        <?php if ($detail): ?>
            <div class="row" data-reveal-group>
                <div class="col-lg-8 reveal">
                    <?php if ($detail['category']): ?><span class="badge eyebrow-badge mb-3"><?= e($detail['category']) ?></span><?php endif; ?>
                    <p style="font-size: 1.0625rem; line-height: 1.8; color: var(--text-secondary);"><?= nl2br(e($detail['description'] ?: $detail['short_description'])) ?></p>
                    <?php if ($detail['technologies']): ?>
                        <div class="icon-chip-row mt-4">
                            <span class="icon-chip"><i class="fas fa-layer-group"></i></span>
                            <h5 class="mb-0">Technologies</h5>
                        </div>
                        <p style="color: var(--text-secondary);"><?= nl2br(e($detail['technologies'])) ?></p>
                    <?php endif; ?>
                    <?php if ($detail['project_url']): ?><a href="<?= e($detail['project_url']) ?>" class="btn btn-primary mt-2" target="_blank" rel="noopener">Visit project <i class="fas fa-external-link-alt ml-2"></i></a><?php endif; ?>
                </div>
                <?php if ($detail['image_location']): ?>
                    <div class="col-lg-4 detail-media reveal">
                        <img src="<?= e(siteUrl('user_uploads/' . $detail['image_location'])) ?>" class="img-fluid" loading="lazy" alt="<?= e($detail['title']) ?>">
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="row" data-reveal-group>
                <?php foreach ($projects as $p): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100 reveal">
                            <?php if ($p['image_location']): ?>
                                <div class="card-img-zoom" style="height: 170px;">
                                    <img src="<?= e(siteUrl('user_uploads/' . $p['image_location'])) ?>" class="card-img-top" loading="lazy" alt="<?= e($p['title']) ?>">
                                </div>
                            <?php endif; ?>
                            <div class="card-body p-4">
                                <?php if ($p['category']): ?><span class="badge eyebrow-badge mb-2"><?= e($p['category']) ?></span><?php endif; ?>
                                <h5 class="card-title-sm"><?= e($p['title']) ?></h5>
                                <p class="card-text-sm"><?= e($p['short_description']) ?></p>
                                <a href="<?= siteUrl('projects') ?>/<?= e($p['slug']) ?>" class="btn btn-sm btn-outline-primary">View project</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$projects): ?><p class="empty-note w-100">Projects coming soon.</p><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/website/includes/footer.php'; ?>
