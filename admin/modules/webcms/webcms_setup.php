<?php
/**
 * SB-Tech — Website CMS / Setup: site-wide settings (tbl_cms_setup), including
 * the logo and favicon shown in the public header/footer and browser tab.
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
// Brand images: column => [label, accepted extensions, help text].
$brandImages = [
    'logo'    => ['Logo', '.png,.jpg,.jpeg,.webp,.gif', 'Shown next to the site title in the public header and footer. PNG with a transparent background works best (about 36px tall on the site).'],
    'favicon' => ['Favicon', '.png,.ico,.jpg,.jpeg,.webp,.gif', 'Browser-tab icon. Square PNG or .ico, 32×32 or 64×64.'],
];
?>
<div class="card card-primary card-outline">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-cog mr-1"></i>Website settings</h3></div>
    <div class="card-body">
        <form action="operation.php?module=webcms&page=webcms_setup" method="post" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_setup">
            <div class="row">
                <div class="col-md-6">
                    <?php foreach (array_slice($fields, 0, 7, true) as $fk => $fl): ?>
                        <div class="form-group">
                            <label><?= e($fl) ?></label>
                            <?php if ($fk === 'maps_embed'): ?>
                                <textarea name="<?= e($fk) ?>" class="form-control" rows="3"><?= e(old($fk, $setup[$fk] ?? '')) ?></textarea>
                            <?php else: ?>
                                <input type="text" name="<?= e($fk) ?>" class="form-control" value="<?= e(old($fk, $setup[$fk] ?? '')) ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="col-md-6">
                    <?php foreach (array_slice($fields, 7, null, true) as $fk => $fl): ?>
                        <div class="form-group">
                            <label><?= e($fl) ?></label>
                            <?php if ($fk === 'seo_meta_keywords'): ?>
                                <textarea name="<?= e($fk) ?>" class="form-control" rows="3"><?= e(old($fk, $setup[$fk] ?? '')) ?></textarea>
                            <?php else: ?>
                                <input type="text" name="<?= e($fk) ?>" class="form-control" value="<?= e(old($fk, $setup[$fk] ?? '')) ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <hr>
            <div class="row">
                <?php foreach ($brandImages as $bk => [$bl, $accept, $help]): ?>
                    <?php $loc = (string) ($setup[$bk] ?? ''); ?>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="fld_<?= e($bk) ?>"><?= e($bl) ?></label>
                            <?php if ($loc !== ''): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <img src="<?= e(assetUrl('user_uploads/' . $loc)) ?>" alt="<?= e($bl) ?>" class="img-thumbnail mr-3" style="max-height:64px;max-width:200px;background:#f4f6f9">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="remove_<?= e($bk) ?>" name="remove_<?= e($bk) ?>" value="1">
                                        <label class="custom-control-label text-danger" for="remove_<?= e($bk) ?>">Remove <?= e(strtolower($bl)) ?></label>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="fld_<?= e($bk) ?>" name="<?= e($bk) ?>" accept="<?= e($accept) ?>">
                                <label class="custom-file-label" for="fld_<?= e($bk) ?>"><?= $loc !== '' ? 'Replace ' : 'Upload ' ?><?= e(strtolower($bl)) ?></label>
                            </div>
                            <small class="form-text text-muted"><?= e($help) ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save settings</button>
        </form>
    </div>
</div>
<script>
// Show the chosen filename in the custom file label.
document.querySelectorAll('.custom-file-input').forEach(function (input) {
    input.addEventListener('change', function () {
        var label = input.parentNode.querySelector('.custom-file-label');
        if (input.files && input.files.length) { label.textContent = input.files[0].name; }
    });
});
</script>
