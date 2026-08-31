<?php
require __DIR__ . '/website/includes/site.php';

$heroes = siteRows('tbl_cms_hero', '`position`, `id`', '`id`, `title`, `subtitle`, `description`, `button_text`, `button_link`, `photo_location`');
$services = siteRows('tbl_cms_services', '`position`, `id`', '`id`, `slug`, `title`, `short_description`, `icon`');
$projects = siteRows('tbl_cms_projects', '`position`, `id` DESC', '`id`, `slug`, `title`, `short_description`, `image_location`');
$abouts = siteRows('tbl_cms_abouts', '`id`', '`id`, `title`, `description`, `image_location`');
$testimonials = siteRows('tbl_cms_testimonials', '`position`, `id`', '`id`, `rating`, `testimonial`, `client_name`, `client_company`');
$news = $db->select('SELECT `id`, `slug`, `title`, `news_date` FROM `tbl_cms_news` WHERE `is_active` = 1 ORDER BY `news_date` DESC LIMIT 3');
$notices = $db->select('SELECT `id`, `title`, `notice_date`, `file_location` FROM `tbl_cms_notices` WHERE `is_active` = 1 ORDER BY `notice_date` DESC LIMIT 4');

require __DIR__ . '/website/includes/header.php';
?>

<?php if ($heroes): ?>
    <section class="site-hero">
        <div class="container">
            <?php foreach (array_slice($heroes, 0, 1) as $h): ?>
                <div class="row align-items-center" data-reveal-group>
                    <div class="col-lg-6">
                        <div class="reveal">
                            <h1 class="display-4"><?= e($h['title']) ?></h1>
                        </div>
                        <?php if ($h['subtitle']): ?>
                            <div class="reveal">
                                <p class="lead mb-4"><?= e($h['subtitle']) ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if ($h['description']): ?>
                            <div class="reveal">
                                <p style="color: rgba(255,255,255,0.8); max-width: 500px;"><?= e($h['description']) ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if ($h['button_text']): ?>
                            <div class="reveal">
                                <a href="<?= e($h['button_link'] ?: siteUrl('contact')) ?>" class="btn btn-light btn-lg mt-3">
                                    <?= e($h['button_text']) ?>
                                    <i class="fas fa-arrow-right ml-2"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($h['photo_location']): ?>
                        <div class="col-lg-6">
                            <div class="hero-media reveal">
                                <img src="<?= e(siteUrl('user_uploads/' . $h['photo_location'])) ?>" class="img-fluid" alt="<?= e($h['title']) ?>">
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($services): ?>
    <section style="background: var(--bg-body);">
        <div class="container">
            <div class="text-center mb-5 section-intro">
                <span class="badge eyebrow-badge mb-3">What We Do</span>
                <h2>Our Services</h2>
                <p class="mx-auto">Comprehensive technology solutions tailored to elevate your business</p>
            </div>
            <div class="row" data-reveal-group>
                <?php foreach ($services as $s): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card h-100 reveal">
                            <div class="card-body text-center p-4">
                                <?php if ($s['icon']): ?>
                                    <div class="icon-wrapper mx-auto mb-4">
                                        <i class="<?= e($s['icon']) ?>"></i>
                                    </div>
                                <?php endif; ?>
                                <h5 class="card-title-sm"><?= e($s['title']) ?></h5>
                                <p class="card-text-sm"><?= e($s['short_description']) ?></p>
                                <a href="<?= siteUrl('services') ?>/<?= e($s['slug']) ?>" class="btn btn-outline-primary btn-sm">
                                    Learn more <i class="fas fa-arrow-right ml-1 card-link-btn"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($abouts): ?>
    <section style="background: var(--border-light);">
        <div class="container">
            <?php foreach (array_slice($abouts, 0, 1) as $a): ?>
                <div class="row align-items-center" data-reveal-group>
                    <div class="col-lg-6 mb-4 mb-lg-0 reveal">
                        <span class="badge eyebrow-badge mb-3">About Us</span>
                        <h2 class="mb-4" style="display: inline-block;"><?= e($a['title']) ?></h2>
                        <p style="color: var(--text-secondary); font-size: 1.0625rem; line-height: 1.8;" class="mb-4"><?= nl2br(e($a['description'])) ?></p>
                        <div class="d-flex flex-wrap">
                            <a href="<?= siteUrl('about') ?>" class="btn btn-primary btn-lg mr-3">
                                About Us <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                            <a href="<?= siteUrl('contact') ?>" class="btn btn-outline-primary btn-lg">
                                Contact Us
                            </a>
                        </div>
                    </div>
                    <?php if ($a['image_location']): ?>
                        <div class="col-lg-6 about-media reveal">
                            <img src="<?= e(siteUrl('user_uploads/' . $a['image_location'])) ?>" class="img-fluid" loading="lazy" alt="<?= e($a['title']) ?>">
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($projects): ?>
    <section style="background: var(--bg-body);">
        <div class="container">
            <div class="text-center mb-5 section-intro">
                <span class="badge eyebrow-badge mb-3">Portfolio</span>
                <h2>Featured Projects</h2>
                <p class="mx-auto">Explore our latest work and successful client collaborations</p>
            </div>
            <div class="row" data-reveal-group>
                <?php foreach (array_slice($projects, 0, 3) as $p): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card h-100 reveal">
                            <?php if ($p['image_location']): ?>
                                <div class="card-img-zoom">
                                    <img src="<?= e(siteUrl('user_uploads/' . $p['image_location'])) ?>" class="card-img-top" loading="lazy" alt="<?= e($p['title']) ?>">
                                </div>
                            <?php endif; ?>
                            <div class="card-body p-4">
                                <h5 class="card-title-sm"><?= e($p['title']) ?></h5>
                                <p class="card-text-sm"><?= e($p['short_description']) ?></p>
                                <a href="<?= siteUrl('projects') ?>/<?= e($p['slug']) ?>" class="btn btn-outline-primary btn-sm">
                                    View Project <i class="fas fa-external-link-alt ml-1 card-link-btn"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($testimonials): ?>
    <section style="background: var(--border-light);">
        <div class="container">
            <div class="text-center mb-5 section-intro">
                <span class="badge eyebrow-badge mb-3">Testimonials</span>
                <h2>What Our Clients Say</h2>
                <p class="mx-auto">Trusted by leading companies worldwide</p>
            </div>
            <div class="row" data-reveal-group>
                <?php foreach (array_slice($testimonials, 0, 3) as $t): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card h-100 reveal">
                            <div class="card-body p-4">
                                <div class="mb-3" role="img" aria-label="<?= (int) $t['rating'] ?> out of 5 stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star rating-star<?= $i <= (int) $t['rating'] ? '' : ' is-off' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <p class="font-italic testimonial-quote">
                                    "<em><?= e($t['testimonial']) ?></em>"
                                </p>
                                <div class="d-flex align-items-center">
                                    <div class="testimonial-avatar mr-3" aria-hidden="true"><?= strtoupper(substr((string) $t['client_name'], 0, 1)) ?></div>
                                    <div>
                                        <strong class="testimonial-name"><?= e($t['client_name']) ?></strong>
                                        <?php if ($t['client_company']): ?>
                                            <div class="testimonial-company"><?= e($t['client_company']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<section style="background: var(--bg-body);">
    <div class="container">
        <div class="row">
            <?php if ($news): ?>
                <div class="col-lg-7 mb-4 mb-lg-0">
                    <div class="d-flex align-items-center mb-4">
                        <span class="badge eyebrow-badge mr-3">Latest News</span>
                        <a href="<?= siteUrl('news') ?>" class="btn btn-sm btn-link" style="color: var(--accent); text-decoration: none;">View All <i class="fas fa-arrow-right ml-1"></i></a>
                    </div>
                    <ul class="feed-list">
                        <?php foreach ($news as $n): ?>
                            <li>
                                <a href="<?= siteUrl('news') ?>/<?= e($n['slug']) ?>" class="feed-title">
                                    <strong><?= e($n['title']) ?></strong>
                                </a>
                                <small class="feed-meta"><?= $n['news_date'] ? e(formatDateView($n['news_date'])) : '' ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if ($notices): ?>
                <div class="col-lg-5">
                    <div class="d-flex align-items-center mb-4">
                        <span class="badge eyebrow-badge mr-3">Notices</span>
                    </div>
                    <ul class="feed-list">
                        <?php foreach ($notices as $n): ?>
                            <li>
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong style="color: var(--text-primary); display: block; margin-bottom: 0.25rem;"><?= e($n['title']) ?></strong>
                                        <small class="feed-meta"><?= $n['notice_date'] ? e(formatDateView($n['notice_date'])) : '' ?></small>
                                    </div>
                                    <?php if ($n['file_location']): ?>
                                        <a href="<?= e(siteUrl('user_uploads/' . $n['file_location'])) ?>" target="_blank" rel="noopener" class="btn btn-sm notice-file-btn">
                                            <i class="fas fa-download mr-1"></i>PDF
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="site-cta text-center">
    <div class="container position-relative">
        <h3 class="mb-3">Have a project in mind?</h3>
        <p class="mb-4" style="color: rgba(255,255,255,0.85); max-width: 500px; margin-left: auto; margin-right: auto;">Let's discuss how we can help bring your vision to life</p>
        <a href="<?= siteUrl('contact') ?>" class="btn btn-light btn-lg">
            Get in Touch <i class="fas fa-arrow-right ml-2"></i>
        </a>
    </div>
</section>

<?php require __DIR__ . '/website/includes/footer.php'; ?>
