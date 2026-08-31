<?php
/**
 * SB-Tech — Office Setup / Profile (US-SET-01).
 * Single active profile (row id=1); used as header on vouchers, reports,
 * certificates and the website footer.
 */
$db = Database::instance();
$profile = $db->selectOne('SELECT * FROM `tbl_office_profiles` WHERE `id` = 1 LIMIT 1');
if (!$profile) {
    $db->insert('tbl_office_profiles', ['name' => config('organization_name', 'Office'), 'accronym' => config('organization_short_name', 'Office')]);
    $profile = $db->selectOne('SELECT * FROM `tbl_office_profiles` WHERE `id` = 1 LIMIT 1');
}

$logoUrl = '';
$logoPath = (string) ($profile['logo'] ?? '');
if ($logoPath !== '') {
    $logoAbs = __DIR__ . '/../../../user_uploads/' . ltrim($logoPath, '/');
    if (is_file($logoAbs)) {
        $logoUrl = assetUrl('user_uploads/' . ltrim($logoPath, '/'));
    }
}
if ($logoUrl === '' && !empty($profile['logo_extension'])) {
    $legacy = 'office_setup/' . (int) $profile['id'] . $profile['logo_extension'];
    if (is_file(__DIR__ . '/../../../user_uploads/' . $legacy)) {
        $logoUrl = assetUrl('user_uploads/' . $legacy);
    }
}
?>
<form action="operation.php?module=office_setup&page=office_profile" method="post" enctype="multipart/form-data">
    <?= csrfField() ?>
    <input type="hidden" name="id" value="<?= (int) $profile['id'] ?>">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Office Profile</h3></div>
                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>Name *</label>
                            <input type="text" name="name" class="form-control" required value="<?= e($profile['name']) ?>">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Acronym</label>
                            <input type="text" name="accronym" class="form-control" value="<?= e($profile['accronym']) ?>">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Address 1</label>
                            <input type="text" name="address1" class="form-control" value="<?= e($profile['address1']) ?>">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Address 2</label>
                            <input type="text" name="address2" class="form-control" value="<?= e($profile['address2']) ?>">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" value="<?= e($profile['email']) ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label>Phone 1</label>
                            <input type="text" name="phone1" class="form-control" value="<?= e($profile['phone1']) ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label>Phone 2</label>
                            <input type="text" name="phone2" class="form-control" value="<?= e($profile['phone2']) ?>">
                        </div>
                        <div class="form-group col-md-6">
                            <label>VAT / PAN No</label>
                            <input type="text" name="vat_no" class="form-control" value="<?= e($profile['vat_no']) ?>">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Website</label>
                            <input type="text" name="website" class="form-control" value="<?= e($profile['website']) ?>">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Slogan</label>
                            <input type="text" name="slogan" class="form-control" value="<?= e($profile['slogan']) ?>">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Established (Estd)</label>
                            <input type="text" name="estd" class="form-control" value="<?= e($profile['estd']) ?>">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Calendar mode</label>
                            <select name="use_date" class="form-control">
                                <option value="AD" <?= $profile['use_date'] === 'AD' ? 'selected' : '' ?>>AD</option>
                                <option value="BS" <?= $profile['use_date'] === 'BS' ? 'selected' : '' ?> <?= !bsCalendarAvailable() ? 'disabled' : '' ?>>BS</option>
                            </select>
                            <?php if (!bsCalendarAvailable()): ?>
                                <small class="form-text text-muted">BS data not seeded — run <code>php scripts/seed_data.php</code> first.</small>
                            <?php else: ?>
                                <small class="form-text text-muted">Dates are stored in AD; BS is used for display/picking when BS is active (1975&ndash;2100 BS).</small>
                            <?php endif; ?>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Leave year mode</label>
                            <select name="leave_year_mode" class="form-control">
                                <option value="AD" <?= $profile['leave_year_mode'] === 'AD' ? 'selected' : '' ?>>AD</option>
                                <option value="BS" <?= $profile['leave_year_mode'] === 'BS' ? 'selected' : '' ?>>BS</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Backup email</label>
                            <input type="email" name="backup_email" class="form-control" value="<?= e($profile['backup_email']) ?>">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Allow IPs</label>
                            <input type="text" name="allow_ips" class="form-control" value="<?= e($profile['allow_ips']) ?>">
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save Profile</button>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Logo</h3></div>
                <div class="card-body text-center">
                    <?php if ($logoUrl): ?>
                        <img src="<?= e($logoUrl) ?>" alt="Office logo" class="img-fluid mb-3" style="max-height:120px">
                    <?php else: ?>
                        <p class="text-muted">No logo uploaded.</p>
                    <?php endif; ?>
                    <div class="file-upload-widget text-left" data-preview="true" data-title="false">
                        <div class="file-upload-preview"></div>
                        <div class="form-group mb-0 mt-2">
                            <label class="btn btn-outline-primary btn-block mb-0" style="cursor:pointer">
                                <i class="fas fa-cloud-upload-alt mr-1"></i>Choose logo (jpg/png)
                                <input type="file" class="file-upload-input d-none" name="logo" accept=".jpg,.jpeg,.png">
                            </label>
                            <small class="text-muted d-block mt-1" style="font-size:.72rem">Max 10 MB · JPG, JPEG, PNG</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
