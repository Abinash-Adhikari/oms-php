<?php
require __DIR__ . '/website/includes/site.php';
$items = $db->select('SELECT `id`, `slug`, `title`, `news_date`, `image_location`, `description` FROM `tbl_cms_news` WHERE `is_active` = 1 ORDER BY `news_date` DESC, `id` DESC');
$detail = null;
if (isset($_GET['slug']) && $_GET['slug'] !== '') {
    $slug = preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['slug']));
    foreach ($items as $n) {
        if ($n['slug'] === $slug) {
            $detail = $n;
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
                <h2 class="mb-3">News article not found</h2>
                <p class="text-muted mb-4">The article you're looking for doesn't exist.</p>
                <a href="<?= siteUrl('news') ?>" class="btn btn-primary">Back to News</a>
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
            <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= siteUrl('news') ?>">News</a></li><li class="breadcrumb-item active"><?= e($detail['title']) ?></li></ol></nav>
            <h1><?= e($detail['title']) ?></h1>
            <p><?= $detail['news_date'] ? e(formatDateView($detail['news_date'])) : '' ?></p>
        <?php else: ?>
            <h1>News</h1>
            <p>Updates and announcements</p>
        <?php endif; ?>
    </div>
</section>

<section>
    <div class="container">
        <?php if ($detail): ?>
            <div class="row" data-reveal-group>
                <div class="col-lg-8 reveal">
                    <?php if ($detail['image_location']): ?>
                        <div class="detail-media mb-4">
                            <img src="<?= e(siteUrl('user_uploads/' . $detail['image_location'])) ?>" class="img-fluid" style="max-height: 360px; width: 100%;" loading="lazy" alt="<?= e($detail['title']) ?>">
                        </div>
                    <?php endif; ?>
                    <p style="font-size: 1.0625rem; line-height: 1.8; color: var(--text-secondary);"><?= nl2br(e($detail['description'])) ?></p>
                </div>
            </div>
        <?php else: ?>
            <div class="row" data-reveal-group>
                <?php foreach ($items as $n): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100 reveal">
                            <?php if ($n['image_location']): ?>
                                <div class="card-img-zoom" style="height: 150px;">
                                    <img src="<?= e(siteUrl('user_uploads/' . $n['image_location'])) ?>" class="card-img-top" loading="lazy" alt="<?= e($n['title']) ?>">
                                </div>
                            <?php endif; ?>
                            <div class="card-body p-4">
                                <small class="feed-meta"><?= $n['news_date'] ? e(formatDateView($n['news_date'])) : '' ?></small>
                                <h5 class="card-title-sm"><?= e($n['title']) ?></h5>
                                <a href="<?= siteUrl('news') ?>/<?= e($n['slug']) ?>" class="btn btn-sm btn-outline-primary">Read more</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$items): ?><p class="empty-note w-100">No news yet.</p><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/website/includes/footer.php'; ?>
