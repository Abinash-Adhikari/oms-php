<?php
/**
 * SB-Tech — Website CMS page shell.
 * Expects $_pageSections (array of section keys) and $_pageTitle.
 * Renders section tabs, then the CRUD for the active section.
 */
require_once __DIR__ . '/cms_config.php';
$db = Database::instance();
$_pageKey = $_pageKey ?? basename($_SERVER['PHP_SELF'], '.php');

if (!isset($_pageSections) || !is_array($_pageSections) || $_pageSections === []) {
    echo '<div class="callout callout-danger"><p>CMS page misconfigured.</p></div>';
    return;
}
$_pageTitle = $_pageTitle ?? 'Website CMS';

$section = (string) ($_GET['section'] ?? $_pageSections[0]);
if (!in_array($section, cmsSectionKeys(), true) || !in_array($section, $_pageSections, true)) {
    $section = $_pageSections[0];
}
$cfg = cmsSections()[$section];
?>
<div class="card card-primary card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-globe mr-1"></i><?= e($_pageTitle) ?></h3>
        <div class="card-tools">
            <?php foreach ($_pageSections as $sk): ?>
                <?php $sc = cmsSections()[$sk]; ?>
                <a href="<?= pageUrl('webcms', $_pageKey) ?>&section=<?= urlencode($sk) ?>"
                   class="btn btn-sm <?= $section === $sk ? 'btn-primary' : 'btn-default' ?>">
                    <i class="fas <?= e($sc['fa']) ?> mr-1"></i><?= e($sc['label']) ?>s
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card-body">
        <?php
        $edit = null;
        if (isset($_GET['edit_id'])) {
            $edit = $db->selectOne('SELECT * FROM `' . $cfg['table'] . '` WHERE `id` = ?', [(int) $_GET['edit_id']]);
        }
        $rows = $db->select('SELECT * FROM `' . $cfg['table'] . '` ORDER BY `position`, `id` DESC');
        ?>
        <div class="row">
            <div class="col-md-4">
                <div class="card card-outline">
                    <div class="card-header">
                        <h3 class="card-title"><?= $edit ? 'Edit ' . strtolower($cfg['label']) : 'Add ' . strtolower($cfg['label']) ?></h3>
                        <?php if ($edit): ?><a href="<?= pageUrl('webcms', $_pageKey) ?>&section=<?= urlencode($section) ?>" class="btn btn-xs btn-default float-right">Cancel</a><?php endif; ?>
                    </div>
                    <div class="card-body">
                        <form action="operation.php?module=webcms&page=<?= e($_pageKey) ?>" method="post" enctype="multipart/form-data">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="save">
                            <input type="hidden" name="section" value="<?= e($section) ?>">
                            <input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
                            <?php foreach ($cfg['fields'] as $fname => $f): ?>
                                <?php
                                $val = $edit[$fname] ?? '';
                                $label = ucwords(str_replace('_', ' ', $fname));
                                ?>
                                <div class="form-group">
                                    <label><?= e($label) ?><?= isset($f['hint']) ? ' <small class="text-muted">(' . e($f['hint']) . ')</small>' : '' ?></label>
                                    <?php if ($f['type'] === 'textarea' || $f['type'] === 'longtext'): ?>
                                        <textarea name="<?= e($fname) ?>" class="form-control" rows="<?= $f['type'] === 'textarea' ? 3 : 5 ?>"><?= e($val) ?></textarea>
                                    <?php elseif ($f['type'] === 'date'): ?>
                                        <input type="date" name="<?= e($fname) ?>" class="form-control" value="<?= e($val) ?>">
                                    <?php elseif ($f['type'] === 'number'): ?>
                                        <input type="number" name="<?= e($fname) ?>" class="form-control" value="<?= e($val) ?>">
                                    <?php elseif ($f['type'] === 'select'): ?>
                                        <select name="<?= e($fname) ?>" class="form-control">
                                            <?php foreach ($f['options'] as $opt): ?>
                                                <option value="<?= e($opt) ?>" <?= $val === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($f['type'] === 'checkbox'): ?>
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="fld_<?= e($fname) ?>" name="<?= e($fname) ?>" value="1" <?= (!$edit && $fname === 'is_active') || $edit[$fname] ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="fld_<?= e($fname) ?>">Active</label>
                                        </div>
                                    <?php elseif ($f['type'] === 'department'): ?>
                                        <select name="<?= e($fname) ?>" class="form-control">
                                            <option value="">—</option>
                                            <?php foreach ($db->select('SELECT * FROM `tbl_office_departments` ORDER BY title') as $d): ?>
                                                <option value="<?= (int) $d['id'] ?>" <?= (int) $val === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['title']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($f['type'] === 'image'): ?>
                                        <?php $loc = $edit[$f['loc']] ?? ''; ?>
                                        <?php if ($loc): ?>
                                            <div class="mb-1"><img src="<?= assetUrl('user_uploads/' . $loc) ?>" class="img-thumbnail" style="max-height:70px" alt=""></div>
                                        <?php endif; ?>
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input" id="fld_<?= e($fname) ?>" name="f_<?= e($fname) ?>">
                                            <label class="custom-file-label" for="fld_<?= e($fname) ?>"><?= $loc ? 'Replace image' : 'Choose image' ?></label>
                                        </div>
                                    <?php elseif ($f['type'] === 'file'): ?>
                                        <?php $loc = $edit[$f['loc']] ?? ''; ?>
                                        <?php if ($loc): ?><div class="mb-1"><a href="<?= assetUrl('user_uploads/' . $loc) ?>" target="_blank"><i class="fas fa-file mr-1"></i><?= e($edit[$f['name']] ?? '') ?></a></div><?php endif; ?>
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input" id="fld_<?= e($fname) ?>" name="f_<?= e($fname) ?>">
                                            <label class="custom-file-label" for="fld_<?= e($fname) ?>"><?= $loc ? 'Replace file' : 'Choose file' ?></label>
                                        </div>
                                    <?php else: ?>
                                        <input type="text" name="<?= e($fname) ?>" class="form-control" value="<?= e($val) ?>">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <button type="submit" class="btn btn-primary btn-block"><?= $edit ? 'Update' : 'Save' ?></button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card card-outline">
                    <div class="card-header"><h3 class="card-title"><?= e(ucfirst($cfg['label'])) ?>s (<?= count($rows) ?>)</h3></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped table-hover mb-0">
                                <thead><tr><th>#</th>
                                    <?php foreach ($cfg['list'] as $col): ?><th><?= e(ucwords(str_replace('_', ' ', $col))) ?></th><?php endforeach; ?>
                                    <th class="text-right">Actions</th></tr></thead>
                                <tbody>
                                <?php foreach ($rows as $i => $r): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <?php foreach ($cfg['list'] as $col): ?>
                                            <td>
                                                <?php if ($col === 'is_active'): ?>
                                                    <span class="badge badge-<?= $r[$col] ? 'success' : 'secondary' ?>"><?= $r[$col] ? 'Yes' : 'No' ?></span>
                                                <?php else: ?>
                                                    <?= e(mb_strimwidth((string) ($r[$col] ?? ''), 0, 40, '…')) ?>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td class="text-right">
                                            <a href="<?= pageUrl('webcms', $_pageKey) ?>&section=<?= urlencode($section) ?>&edit_id=<?= (int) $r['id'] ?>" class="btn btn-xs btn-outline-primary"><i class="fas fa-edit"></i></a>
                                            <form action="operation.php?module=webcms&page=<?= e($_pageKey) ?>" method="post" class="d-inline">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="section" value="<?= e($section) ?>">
                                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                                <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete this <?= e(strtolower($cfg['label'])) ?>?"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$rows): ?><tr><td colspan="<?= count($cfg['list']) + 2 ?>" class="text-center text-muted">Nothing here yet.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
