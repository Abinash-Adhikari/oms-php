<?php
/**
 * SB-Tech — Website CMS / Setup: site-wide settings (tbl_cms_setup).
 */
$db = Database::instance();
$setup = $db->selectOne('SELECT * FROM `tbl_cms_setup` WHERE `id` = 1');
$fields = [
    'site_title' => 'Site title', 'tagline' => 'Tagline', 'template' => 'Template',
    'primary_color' => 'Primary color', 'secondary_color' => 'Secondary color',
    'maps_embed' => 'Maps embed', 'contact_email' => 'Contact email', 'contact_phone' => 'Contact phone',
    'facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'twitter' => 'Twitter',
    'seo_meta_keywords' => 'SEO meta keywords',
];
?>
<div class="card card-primary card-outline">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-cog mr-1"></i>Website settings</h3></div>
    <div class="card-body">
        <form action="operation.php?module=webcms&page=webcms_setup" method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_setup">
            <div class="row">
                <div class="col-md-6">
                    <?php foreach (array_slice($fields, 0, 7, true) as $fk => $fl): ?>
                        <div class="form-group">
                            <label><?= e($fl) ?></label>
                            <?php if ($fk === 'maps_embed'): ?>
                                <textarea name="<?= e($fk) ?>" class="form-control" rows="3"><?= $setup ? e($setup[$fk]) : '' ?></textarea>
                            <?php else: ?>
                                <input type="text" name="<?= e($fk) ?>" class="form-control" value="<?= $setup ? e($setup[$fk]) : '' ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="col-md-6">
                    <?php foreach (array_slice($fields, 7, null, true) as $fk => $fl): ?>
                        <div class="form-group">
                            <label><?= e($fl) ?></label>
                            <?php if ($fk === 'seo_meta_keywords'): ?>
                                <textarea name="<?= e($fk) ?>" class="form-control" rows="3"><?= $setup ? e($setup[$fk]) : '' ?></textarea>
                            <?php else: ?>
                                <input type="text" name="<?= e($fk) ?>" class="form-control" value="<?= $setup ? e($setup[$fk]) : '' ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="form-group">
                        <label>Favicon / brand note</label>
                        <p class="text-muted small">Site title and tagline appear in the public header/footer. Social links appear in the footer.</p>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save settings</button>
        </form>
    </div>
</div>
