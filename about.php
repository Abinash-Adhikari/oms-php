<?php
require __DIR__ . '/website/includes/site.php';
$abouts = siteRows('tbl_cms_abouts', '`id`', '`id`, `title`, `description`, `mission`, `vision`, `image_location`');
$team = siteRows('tbl_cms_staffs', '`id`', '`id`, `name`, `designation`, `image_location`');
require __DIR__ . '/website/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <h1>About us</h1>
    </div>
</section>

<section>
    <div class="container">
        <?php foreach (array_slice($abouts, 0, 1) as $a): ?>
            <div class="row align-items-start" data-reveal-group>
                <div class="col-lg-8 reveal">
                    <?php if ($a['description']): ?><p><?= nl2br(e($a['description'])) ?></p><?php endif; ?>
                    <div class="row mt-4">
                        <?php if ($a['mission']): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100 reveal">
                                    <div class="card-body">
                                        <div class="icon-chip-row">
                                            <span class="icon-chip"><i class="fas fa-bullseye"></i></span>
                                            <h5>Mission</h5>
                                        </div>
                                        <p class="mb-0" style="color: var(--text-secondary);"><?= nl2br(e($a['mission'])) ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($a['vision']): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100 reveal">
                                    <div class="card-body">
                                        <div class="icon-chip-row">
                                            <span class="icon-chip"><i class="fas fa-eye"></i></span>
                                            <h5>Vision</h5>
                                        </div>
                                        <p class="mb-0" style="color: var(--text-secondary);"><?= nl2br(e($a['vision'])) ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($a['image_location']): ?>
                    <div class="col-lg-4 detail-media reveal">
                        <img src="<?= e(siteUrl('user_uploads/' . $a['image_location'])) ?>" class="img-fluid" loading="lazy" alt="<?= e($a['title']) ?>">
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if (!$abouts): ?><p class="empty-note">About content coming soon.</p><?php endif; ?>
    </div>
</section>

<?php if ($team): ?>
    <section style="background: var(--border-light);">
        <div class="container">
            <div class="text-center mb-5 section-intro">
                <span class="badge eyebrow-badge mb-3">Team</span>
                <h2>Our team</h2>
            </div>
            <div class="row" data-reveal-group>
                <?php foreach ($team as $m): ?>
                    <div class="col-md-3 col-6 mb-4 text-center reveal">
                        <?php if ($m['image_location']): ?><img src="<?= e(siteUrl('user_uploads/' . $m['image_location'])) ?>" class="team-photo mb-2" loading="lazy" alt="<?= e($m['name']) ?>"><?php endif; ?>
                        <h6 class="mb-0 team-name"><?= e($m['name']) ?></h6>
                        <small class="text-muted"><?= e($m['designation']) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/website/includes/footer.php'; ?>
