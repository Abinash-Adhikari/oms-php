<?php
require __DIR__ . '/website/includes/site.php';
$items = $db->select('SELECT `id`, `title`, `notice_date`, `description`, `file_location`, `file_name` FROM `tbl_cms_notices` WHERE `is_active` = 1 ORDER BY `notice_date` DESC, `id` DESC');
require __DIR__ . '/website/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <h1>Notices</h1>
        <p>Official announcements and downloadable documents</p>
    </div>
</section>

<section>
    <div class="container" data-reveal-group>
        <div class="list-group notice-list reveal">
            <?php foreach ($items as $n): ?>
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1 card-title-sm"><?= e($n['title']) ?></h5>
                            <small class="feed-meta"><?= $n['notice_date'] ? e(formatDateView($n['notice_date'])) : '' ?></small>
                            <?php if ($n['description']): ?><p class="mb-0 mt-1" style="color: var(--text-secondary);"><?= nl2br(e($n['description'])) ?></p><?php endif; ?>
                        </div>
                        <?php if ($n['file_location']): ?>
                            <a href="<?= e(siteUrl('user_uploads/' . $n['file_location'])) ?>" class="btn btn-sm notice-file-btn flex-shrink-0 ml-3" target="_blank" rel="noopener"><i class="fas fa-download mr-1"></i><?= e($n['file_name'] ?? 'Download') ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$items): ?><div class="empty-note">No notices yet.</div><?php endif; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/website/includes/footer.php'; ?>
