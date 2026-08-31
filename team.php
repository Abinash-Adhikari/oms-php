<?php
require __DIR__ . '/website/includes/site.php';
$team = siteRows('tbl_cms_staffs', '`id`', '`id`, `name`, `designation`, `short_bio`, `image_location`');
require __DIR__ . '/website/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <h1>Our team</h1>
        <p>The people behind the work</p>
    </div>
</section>

<section>
    <div class="container">
        <div class="row" data-reveal-group>
            <?php foreach ($team as $m): ?>
                <div class="col-md-3 col-6 mb-4 text-center reveal">
                    <?php if ($m['image_location']): ?><img src="<?= e(siteUrl('user_uploads/' . $m['image_location'])) ?>" class="team-photo mb-2" loading="lazy" alt="<?= e($m['name']) ?>"><?php endif; ?>
                    <h5 class="mb-0 team-name"><?= e($m['name']) ?></h5>
                    <small style="color: var(--text-secondary);"><?= e($m['designation']) ?></small>
                    <?php if ($m['short_bio']): ?><p class="small mt-2 mb-0" style="color: var(--text-muted);"><?= e($m['short_bio']) ?></p><?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if (!$team): ?><p class="empty-note w-100">Team coming soon.</p><?php endif; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/website/includes/footer.php'; ?>
