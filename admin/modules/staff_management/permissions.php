<?php
/**
 * SB-Tech — Staff Management / Permissions (US-AUTH-02).
 * Super-admin-only checkbox matrix per staff member. Grants are stored as
 * JSON on tbl_users_login (permitted_modules / permitted_submodules /
 * special_permission); Auth.php reads them from the DB on every check, so a
 * change applies on the user's next request (AC-AUTH-02.2). The route gate
 * (Auth::can) plus this super-admin guard keep the editor out of reach of
 * everyone except full-access users (no privilege escalation).
 *
 * 'permissions' is an admin-managed submodule — it renders in the sidebar
 * for the super admin but is never offered as a grantable checkbox.
 */
if (!Auth::isSuperAdmin()) {
    echo '<div class="callout callout-warning"><h5>Access denied</h5>'
        . '<p>Only a Super Admin can manage permissions.</p></div>';
    return;
}

$db = Database::instance();

$editUser = null;
if (isset($_GET['id'])) {
    $editUser = $db->selectOne('SELECT * FROM `tbl_users_login` WHERE `id` = ?', [(int) $_GET['id']]);
}
$staff = $db->select('SELECT id, fullname, username, role, status FROM `tbl_users_login` ORDER BY fullname');

// Admin-managed submodules are never offered in the grant matrix.
$nonGrantable = ['permissions'];
?>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Staff</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped table-hover">
                    <thead><tr><th>Name</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($staff as $s): ?>
                        <tr class="<?= $editUser && (int) $editUser['id'] === (int) $s['id'] ? 'table-primary' : '' ?>">
                            <td><a href="<?= pageUrl('staff_management', 'permissions') ?>&id=<?= (int) $s['id'] ?>"><?= e($s['fullname']) ?></a>
                                <small class="d-block text-muted">@<?= e($s['username']) ?><?= $s['role'] ? ' · ' . e($s['role']) : '' ?></small></td>
                            <td><span class="badge badge-<?= $s['status'] === 'Active' ? 'success' : 'secondary' ?>"><?= e($s['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <?php if (!$editUser): ?>
            <div class="callout callout-info"><h5>Select a staff member</h5><p>Pick a staff member from the list to edit their module permissions.</p></div>
        <?php elseif (Auth::isSuperAdmin($editUser)): ?>
            <div class="callout callout-success"><h5>Super Admin</h5><p><b><?= e($editUser['fullname']) ?></b> has full access ('All' permissions) and bypasses all permission checks. No changes needed.</p></div>
        <?php else: ?>
            <?php
            $modulesGranted = json_decode(html_entity_decode((string) $editUser['permitted_modules'], ENT_QUOTES, 'UTF-8'), true);
            $subsGranted = json_decode(html_entity_decode((string) $editUser['permitted_submodules'], ENT_QUOTES, 'UTF-8'), true);
            $specialGranted = json_decode(html_entity_decode((string) $editUser['special_permission'], ENT_QUOTES, 'UTF-8'), true);
            $modulesGranted = is_array($modulesGranted) ? $modulesGranted : [];
            $subsGranted = is_array($subsGranted) ? $subsGranted : [];
            $specialGranted = is_array($specialGranted) ? $specialGranted : [];
            ?>
            <form action="<?= e('operation.php?module=staff_management&page=permissions') ?>" method="post">
                <?= csrfField() ?>
                <input type="hidden" name="user_id" value="<?= (int) $editUser['id'] ?>">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Permissions — <?= e($editUser['fullname']) ?></h3>
                        <button type="submit" class="btn btn-primary btn-sm float-right"><i class="fas fa-save mr-1"></i>Save Permissions</button>
                    </div>
                    <div class="card-body">
                        <?php foreach ($modules as $m): ?>
                            <div class="form-group border rounded p-2 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input module-check" type="checkbox" name="modules[]" value="<?= e($m) ?>" id="mod_<?= e($m) ?>"
                                        <?= in_array($m, $modulesGranted, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label font-weight-bold" for="mod_<?= e($m) ?>">
                                        <?= e($navBars[$m] ?? ucfirst($m)) ?>
                                    </label>
                                </div>
                                <?php if (!empty($subNavBars[$m])): ?>
                                    <div class="pl-4 mt-1">
                                        <?php foreach ($subNavBars[$m] as $subKey => $subLabel): ?>
                                            <?php if (in_array($subKey, $nonGrantable, true)): continue; endif; ?>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" name="submodules[<?= e($m) ?>][]" value="<?= e($subKey) ?>"
                                                    id="sub_<?= e($m) ?>_<?= e($subKey) ?>"
                                                    <?= in_array($subKey, ($subsGranted[$m] ?? []), true) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="sub_<?= e($m) ?>_<?= e($subKey) ?>"><?= e($subLabel) ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <h5 class="mt-4">Special permissions</h5>
                        <div class="pl-2">
                            <?php foreach ($specialPermissions as $key => $label): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="special[]" value="<?= e($key) ?>" id="sp_<?= e($key) ?>"
                                        <?= in_array($key, $specialGranted, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="sp_<?= e($key) ?>"><?= e($label) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </form>
            <script>
                // Checking a module checks all its submodules; unchecking the
                // module unchecks them too. Conversely the module checkbox
                // follows its submodules: it is checked only when at least one
                // submodule under it is checked (a submodule grant implies the
                // module grant, mirroring Auth::hasModule()).
                document.querySelectorAll('.module-check').forEach(function (moduleCheck) {
                    moduleCheck.addEventListener('change', function () {
                        moduleCheck.closest('.form-group')
                            .querySelectorAll('input[type="checkbox"][name^="submodules"]')
                            .forEach(function (sub) { sub.checked = moduleCheck.checked; });
                    });
                });
                document.querySelectorAll('input[type="checkbox"][name^="submodules"]').forEach(function (sub) {
                    sub.addEventListener('change', function () {
                        var group = sub.closest('.form-group');
                        var moduleCheck = group.querySelector('.module-check');
                        var submodules = group.querySelectorAll('input[type="checkbox"][name^="submodules"]');
                        moduleCheck.checked = Array.from(submodules).some(function (s) { return s.checked; });
                    });
                });
            </script>
        <?php endif; ?>
    </div>
</div>