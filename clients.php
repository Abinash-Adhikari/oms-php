<?php
/**
 * Public /clients page: every active website client (tbl_cms_clients) with
 * logo, company, description, website and the portfolio items they're
 * linked to. Each card has id="client-<id>" so the home carousel and project
 * pages can deep-link to it.
 */
require __DIR__ . '/website/includes/site.php';
$clients = siteRows('tbl_cms_clients', '`position`, `id`', '`id`, `name`, `company_name`, `logo_location`, `description`, `website_url`');
$clientProjects = siteClientProjects();
require __DIR__ . '/website/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <h1>Our clients</h1>
        <p>Organisations we've had the privilege to work with</p>
    </div>
</section>

<section>
    <div class="container">
        <div class="row" data-reveal-group>
            <?php foreach ($clients as $c): ?>
                <?php
                $url = (string) ($c['website_url'] ?? '');
                $safeUrl = preg_match('~^https?://~i', $url) ? $url : '';
                ?>
                <div class="col-lg-4 col-md-6 mb-4" id="client-<?= (int) $c['id'] ?>">
                    <div class="card h-100 client-card reveal">
                        <div class="card-body p-4">
                            <div class="client-card-logo mb-3">
                                <?php if ($c['logo_location']): ?>
                                    <img src="<?= e(siteUrl('user_uploads/' . $c['logo_location'])) ?>" alt="<?= e($c['name']) ?>" loading="lazy">
                                <?php else: ?>
                                    <span class="client-logo-text"><?= e($c['name']) ?></span>
                                <?php endif; ?>
                            </div>
                            <h5 class="mb-1"><?= e($c['name']) ?></h5>
                            <?php if ($c['company_name']): ?><div class="small mb-2" style="color: var(--text-secondary);"><?= e($c['company_name']) ?></div><?php endif; ?>
                            <?php if ($c['description']): ?><p class="small mb-3" style="color: var(--text-muted);"><?= nl2br(e($c['description'])) ?></p><?php endif; ?>
                            <?php if (!empty($clientProjects[(int) $c['id']])): ?>
                                <div class="small mb-2" style="color: var(--text-secondary);"><i class="fas fa-layer-group mr-1"></i>Projects</div>
                                <div class="mb-3">
                                    <?php foreach ($clientProjects[(int) $c['id']] as $p): ?>
                                        <a href="<?= siteUrl('projects') ?>/<?= e($p['slug']) ?>" class="badge eyebrow-badge mr-1 mb-1"><?= e($p['title']) ?></a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($safeUrl !== ''): ?>
                                <a href="<?= e($safeUrl) ?>" target="_blank" rel="noopener nofollow" class="small" style="color: var(--accent);">Visit website <i class="fas fa-external-link-alt ml-1"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$clients): ?><p class="empty-note w-100">Clients coming soon.</p><?php endif; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/website/includes/footer.php'; ?>
