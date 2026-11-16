<?php
/**
 * SB-Tech — Website CMS page shell.
 * Expects $_pageSections (array of section keys) and $_pageTitle.
 * Renders section tabs, a full-width list for the active section, and a
 * slide-in drawer (same pattern as other admin modules) for add/edit.
 *
 * Sections with a 'clients' field get a website-client picker + a "Link
 * clients" row action; sections with 'link_projects' get a "Link portfolio
 * items" drawer (posted to that page's operation as action=link_projects).
 *
 * Edit opens the drawer client-side from the row data (no reload). The drawer
 * also opens server-side when ?edit_id= is given or after a failed save
 * (old input carries the submitted id), so validation errors keep context.
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

// Sort: the section's own 'order', else `position` only when it has that field.
$orderBy = $cfg['order'] ?? (isset($cfg['fields']['position']) ? '`position`, `id` DESC' : '`id` DESC');
$rows = $db->select('SELECT * FROM `' . $cfg['table'] . '` ORDER BY ' . $orderBy);

// Drawer opens on load for ?edit_id= or after a failed save of this section.
$failedSave = old('section', '') === $section;
$editId = isset($_GET['edit_id']) ? (int) $_GET['edit_id'] : ($failedSave ? (int) old('id', 0) : 0);
$edit = null;
if ($editId > 0) {
    $edit = $db->selectOne('SELECT * FROM `' . $cfg['table'] . '` WHERE `id` = ?', [$editId]);
}
$drawerOpen = $edit !== null || $failedSave;

// Departments / website clients are only queried when a section uses them.
$departments = [];
$pickClients = [];
$linked = []; // fname => [row id => list of clients]
foreach ($cfg['fields'] as $fname => $f) {
    if ($f['type'] === 'department' && $departments === []) {
        $departments = $db->select('SELECT `id`, `title` FROM `tbl_office_departments` ORDER BY `title`');
    }
    if ($f['type'] === 'clients') {
        if ($pickClients === []) {
            $pickClients = $db->select('SELECT `id`, `name`, `company_name`, `is_active` FROM `tbl_cms_clients` ORDER BY `name`');
        }
        $linked[$fname] = cmsLinkedClients($db, $f, array_map(static fn ($r) => (int) $r['id'], $rows));
    }
}

// "Link portfolio items" drawer data (website clients section).
$linkProjects = !empty($cfg['link_projects']);
$portfolioItems = [];
$projectsByClient = []; // client id => list of ['id' => project id, 'title' => ...]
if ($linkProjects) {
    $portfolioItems = $db->select('SELECT `id`, `title`, `is_active` FROM `tbl_cms_projects` ORDER BY `position`, `id` DESC');
    $titles = array_column($portfolioItems, 'title', 'id');
    foreach ($db->select('SELECT `cms_client_id`, `project_id` FROM `tbl_cms_project_clients` WHERE `cms_client_id` IS NOT NULL ORDER BY `project_id`') as $l) {
        $pid = (int) $l['project_id'];
        $projectsByClient[(int) $l['cms_client_id']][] = ['id' => $pid, 'title' => (string) ($titles[$pid] ?? '#' . $pid)];
    }
}

// Row data for client-side edit: only configured fields + upload preview URLs.
$jsRows = [];
foreach ($rows as $r) {
    $item = ['id' => (int) $r['id']];
    foreach ($cfg['fields'] as $fname => $f) {
        if ($f['type'] === 'clients') {
            $item[$fname] = array_map('cmsClientValue', $linked[$fname][(int) $r['id']] ?? []);
        } elseif ($f['type'] === 'image' || $f['type'] === 'file') {
            $loc = (string) ($r[$f['loc']] ?? '');
            $item[$fname] = $loc !== '' ? [
                'url'  => assetUrl('user_uploads/' . $loc),
                'name' => (string) ($r[$f['name']] ?? basename($loc)),
            ] : null;
        } else {
            $item[$fname] = $r[$fname] ?? '';
        }
    }
    $jsRows[(int) $r['id']] = $item;
}
$jsFields = [];
foreach ($cfg['fields'] as $fname => $f) {
    $jsFields[$fname] = $f['type'];
}
// First image field (if any) is shown as a thumbnail column in the list.
$thumbLoc = null;
foreach ($cfg['fields'] as $f) {
    if ($f['type'] === 'image') {
        $thumbLoc = $f['loc'];
        break;
    }
}
$labelLower = strtolower($cfg['label']);
$actionUrl = 'operation.php?module=webcms&page=' . $_pageKey;
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
</div>

<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas <?= e($cfg['fa']) ?> mr-1"></i><?= e(ucfirst($cfg['label'])) ?>s (<?= count($rows) ?>)</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary btn-sm" onclick="openCmsDrawer()">
                <i class="fas fa-plus mr-1"></i>Add <?= e($labelLower) ?>
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead><tr><th>#</th>
                    <?php if ($thumbLoc): ?><th style="width:90px"><?= $cfg['table'] === 'tbl_cms_clients' ? 'Logo' : 'Photo' ?></th><?php endif; ?>
                    <?php foreach ($cfg['list'] as $col): ?><th><?= e(ucwords(str_replace('_', ' ', $col))) ?></th><?php endforeach; ?>
                    <?php if ($linkProjects): ?><th>Portfolio items</th><?php endif; ?>
                    <th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <?php if ($thumbLoc): ?>
                            <td>
                                <?php if (!empty($r[$thumbLoc])): ?>
                                    <a href="<?= e(assetUrl('user_uploads/' . $r[$thumbLoc])) ?>" target="_blank" rel="noopener">
                                        <img src="<?= e(assetUrl('user_uploads/' . $r[$thumbLoc])) ?>" class="img-thumbnail" style="width:72px;height:48px;object-fit:cover;padding:2px" alt="" loading="lazy">
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small"><i class="far fa-image"></i> None</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                        <?php foreach ($cfg['list'] as $col): ?>
                            <td>
                                <?php if ($col === 'is_active'): ?>
                                    <span class="badge badge-<?= $r[$col] ? 'success' : 'secondary' ?>"><?= $r[$col] ? 'Yes' : 'No' ?></span>
                                <?php elseif (isset($linked[$col])): ?>
                                    <?php $cl = $linked[$col][(int) $r['id']] ?? []; ?>
                                    <?php if ($cl === []): ?><span class="text-muted">—</span><?php endif; ?>
                                    <?php foreach ($cl as $c): ?>
                                        <span class="badge badge-<?= $c['client_id'] !== null ? 'info' : 'light border' ?> mr-1" title="<?= $c['client_id'] !== null ? 'Website client' : 'Free-text name' ?>"><?= e($c['name']) ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?= e(mb_strimwidth((string) ($r[$col] ?? ''), 0, 60, '…')) ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <?php if ($linkProjects): ?>
                            <td>
                                <?php $lp = $projectsByClient[(int) $r['id']] ?? []; ?>
                                <?php if ($lp === []): ?><span class="text-muted">—</span><?php endif; ?>
                                <?php foreach ($lp as $p): ?>
                                    <span class="badge badge-info mr-1"><?= e(mb_strimwidth($p['title'], 0, 30, '…')) ?></span>
                                <?php endforeach; ?>
                            </td>
                        <?php endif; ?>
                        <td class="text-right text-nowrap">
                            <?php if ($linkProjects): ?>
                                <button type="button" class="btn btn-xs btn-outline-info" onclick="openLinkDrawer(<?= (int) $r['id'] ?>)" title="Link portfolio items"><i class="fas fa-link"></i></button>
                            <?php endif; ?>
                            <?php if ($linked !== []): ?>
                                <button type="button" class="btn btn-xs btn-outline-info" onclick="openCmsDrawer(<?= (int) $r['id'] ?>, 'clients')" title="Link clients"><i class="fas fa-link"></i></button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-xs btn-outline-primary" onclick="openCmsDrawer(<?= (int) $r['id'] ?>)" title="Edit"><i class="fas fa-edit"></i></button>
                            <form action="<?= e($actionUrl) ?>" method="post" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="section" value="<?= e($section) ?>">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete this <?= e($labelLower) ?>?"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="<?= count($cfg['list']) + ($thumbLoc ? 3 : 2) + ($linkProjects ? 1 : 0) ?>" class="text-center text-muted">Nothing here yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Slide-in Drawer Backdrop -->
<div class="cms-drawer-backdrop" id="drawerBackdrop" onclick="closeCmsDrawer()"></div>

<!-- Slide-in Drawer -->
<div class="cms-drawer" id="formDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas <?= e($cfg['fa']) ?>"></i><span id="drawerTitle"><?= $edit ? 'Edit ' : 'Add ' ?><?= e($labelLower) ?></span></h3>
        <button type="button" class="cms-drawer-close" onclick="closeCmsDrawer()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="cms-drawer-body">
        <form action="<?= e($actionUrl) ?>" method="post" enctype="multipart/form-data" id="cmsDrawerForm">
            <?= csrfField() ?>
            <input type="hidden" name="_scope" value="cms-save">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="section" value="<?= e($section) ?>">
            <input type="hidden" name="id" id="fld_id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
            <?php foreach ($cfg['fields'] as $fname => $f): ?>
                <?php
                $val = $edit[$fname] ?? '';
                $label = ucwords(str_replace('_', ' ', $fname));
                $fid = 'fld_' . $fname;
                ?>
                <div class="form-group">
                    <label for="<?= e($fid) ?>"><?= e($label) ?><?= isset($f['hint']) ? ' <small class="text-muted">(' . e($f['hint']) . ')</small>' : '' ?></label>
                    <?php if ($f['type'] === 'textarea' || $f['type'] === 'longtext'): ?>
                        <textarea name="<?= e($fname) ?>" id="<?= e($fid) ?>" class="form-control" rows="<?= $f['type'] === 'textarea' ? 3 : 6 ?>"><?= e(old($fname, $val)) ?></textarea>
                    <?php elseif ($f['type'] === 'date'): ?>
                        <input type="date" name="<?= e($fname) ?>" id="<?= e($fid) ?>" class="form-control" value="<?= e(old($fname, $val)) ?>">
                    <?php elseif ($f['type'] === 'number'): ?>
                        <input type="number" name="<?= e($fname) ?>" id="<?= e($fid) ?>" class="form-control" value="<?= e(old($fname, $val)) ?>">
                    <?php elseif ($f['type'] === 'select'): ?>
                        <select name="<?= e($fname) ?>" id="<?= e($fid) ?>" class="form-control">
                            <?php foreach ($f['options'] as $opt): ?>
                                <option value="<?= e($opt) ?>" <?= old($fname, $val) === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($f['type'] === 'checkbox'): ?>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="<?= e($fid) ?>" name="<?= e($fname) ?>" value="1" <?= old($fname, (!$edit && $fname === 'is_active') ? '1' : ($edit[$fname] ?? '')) ? 'checked' : '' ?>>
                            <label class="custom-control-label" for="<?= e($fid) ?>">Active</label>
                        </div>
                    <?php elseif ($f['type'] === 'clients'): ?>
                        <?php
                        $current = $edit ? array_map('cmsClientValue', $linked[$fname][(int) $edit['id']] ?? []) : [];
                        $selected = array_map('strval', (array) old($fname, $current));
                        $salesValues = [];
                        ?>
                        <select name="<?= e($fname) ?>[]" id="<?= e($fid) ?>" class="form-control cms-clients" multiple data-placeholder="Search website clients or type a name…">
                            <?php foreach ($pickClients as $sc): ?>
                                <?php $sv = 'client:' . (int) $sc['id']; $salesValues[] = $sv; ?>
                                <option value="<?= e($sv) ?>" <?= in_array($sv, $selected, true) ? 'selected' : '' ?>><?= e($sc['name']) ?><?= $sc['company_name'] ? ' — ' . e($sc['company_name']) : '' ?><?= $sc['is_active'] ? '' : ' (hidden)' ?></option>
                            <?php endforeach; ?>
                            <?php foreach ($selected as $sv): ?>
                                <?php if (!in_array($sv, $salesValues, true) && strpos($sv, 'client:') !== 0): ?>
                                    <option value="<?= e($sv) ?>" data-free="1" selected><?= e($sv) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Add new website clients (with logo) in <a href="<?= pageUrl('webcms', 'cms_clients') ?>">Website CMS → Clients</a>.</small>
                    <?php elseif ($f['type'] === 'department'): ?>
                        <select name="<?= e($fname) ?>" id="<?= e($fid) ?>" class="form-control">
                            <option value="">—</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= (int) $d['id'] ?>" <?= (int) old($fname, (int) $val) === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($f['type'] === 'image'): ?>
                        <?php $loc = (string) ($edit[$f['loc']] ?? ''); ?>
                        <?= image_field_html('f_' . $fname, [
                            'id'      => $fid,
                            'current' => $loc !== '' ? assetUrl('user_uploads/' . $loc) : '',
                            'aspect'  => $f['aspect'] ?? null,
                            'shape'   => $f['shape'] ?? 'rect',
                            'max'     => (int) ($f['max'] ?? 1600),
                        ]) ?>
                    <?php elseif ($f['type'] === 'file'): ?>
                        <?php
                        $loc = (string) ($edit[$f['loc']] ?? '');
                        $isImage = false;
                        $noun = 'file';
                        ?>
                        <div class="mb-1" id="<?= e($fid) ?>_current" <?= $loc === '' ? 'style="display:none"' : '' ?>>
                            <?php if ($isImage): ?>
                                <img src="<?= $loc !== '' ? e(assetUrl('user_uploads/' . $loc)) : '' ?>" class="img-thumbnail" style="max-height:90px" alt="">
                            <?php else: ?>
                                <a href="<?= $loc !== '' ? e(assetUrl('user_uploads/' . $loc)) : '#' ?>" target="_blank" rel="noopener"><i class="fas fa-file mr-1"></i><span><?= e((string) ($edit[$f['name']] ?? '')) ?></span></a>
                            <?php endif; ?>
                        </div>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="<?= e($fid) ?>" name="f_<?= e($fname) ?>" data-noun="<?= $noun ?>">
                            <label class="custom-file-label" for="<?= e($fid) ?>"><?= $loc !== '' ? 'Replace ' . $noun : 'Choose ' . $noun ?></label>
                        </div>
                    <?php else: ?>
                        <input type="text" name="<?= e($fname) ?>" id="<?= e($fid) ?>" class="form-control" value="<?= e(old($fname, $val)) ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="cmsDrawerForm" class="btn btn-primary btn-block">
            <i class="fas fa-save mr-1"></i><span id="drawerBtnText"><?= $edit ? 'Update' : 'Save' ?></span>
        </button>
    </div>
</div>

<?php if ($linkProjects): ?>
<!-- Link portfolio items drawer (website clients) -->
<div class="cms-drawer" id="linkDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas fa-link"></i><span id="linkDrawerTitle">Link portfolio items</span></h3>
        <button type="button" class="cms-drawer-close" onclick="closeCmsDrawer()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="cms-drawer-body">
        <form action="<?= e($actionUrl) ?>" method="post" id="linkDrawerForm">
            <?= csrfField() ?>
            <input type="hidden" name="_scope" value="cms-link-projects">
            <input type="hidden" name="action" value="link_projects">
            <input type="hidden" name="id" id="link_client_id" value="0">
            <p class="text-muted small mb-2">Tick the portfolio items this client was part of. Unticking removes the link.</p>
            <?php if ($portfolioItems === []): ?>
                <p class="text-muted">No portfolio items yet. Add them in <a href="<?= pageUrl('webcms', 'projects') ?>">Portfolio</a>.</p>
            <?php endif; ?>
            <?php foreach ($portfolioItems as $p): ?>
                <div class="custom-control custom-checkbox mb-2">
                    <input type="checkbox" class="custom-control-input link-project" id="lp_<?= (int) $p['id'] ?>" name="project_ids[]" value="<?= (int) $p['id'] ?>">
                    <label class="custom-control-label" for="lp_<?= (int) $p['id'] ?>"><?= e($p['title']) ?><?= $p['is_active'] ? '' : ' <span class="badge badge-secondary">hidden</span>' ?></label>
                </div>
            <?php endforeach; ?>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="linkDrawerForm" class="btn btn-primary btn-block">
            <i class="fas fa-link mr-1"></i>Save links
        </button>
    </div>
</div>
<?php endif; ?>

<script>
var cmsLinkedProjects = <?= json_encode(array_map(static fn ($l) => array_column($l, 'id'), $projectsByClient), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var cmsRows = <?= json_encode($jsRows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var cmsFields = <?= json_encode($jsFields, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var cmsLabel = <?= json_encode($labelLower, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

/** Fill the drawer form from a row (edit) or with defaults (add). */
function fillCmsForm(row) {
    document.getElementById('fld_id').value = row ? row.id : 0;
    Object.keys(cmsFields).forEach(function (name) {
        var type = cmsFields[name];
        var el = document.getElementById('fld_' + name);
        if (!el) { return; }
        var v = row ? row[name] : null;
        if (type === 'clients') {
            setCmsClients(el, row && Array.isArray(v) ? v : []);
        } else if (type === 'checkbox') {
            el.checked = row ? String(v) === '1' : name === 'is_active';
        } else if (type === 'image') {
            // Editor field: drop any pending pick and show this row's image.
            if (window.ImageField) { ImageField.reset(el, v && v.url ? v.url : ''); }
        } else if (type === 'file') {
            el.value = '';
            var noun = el.getAttribute('data-noun');
            var label = el.parentNode.querySelector('.custom-file-label');
            var current = document.getElementById('fld_' + name + '_current');
            if (v && v.url) {
                if (type === 'image') {
                    current.querySelector('img').src = v.url;
                } else {
                    current.querySelector('a').href = v.url;
                    current.querySelector('span').textContent = v.name;
                }
                current.style.display = '';
                label.textContent = 'Replace ' + noun;
            } else {
                current.style.display = 'none';
                label.textContent = 'Choose ' + noun;
            }
        } else if (el.tagName === 'SELECT') {
            el.value = v == null || v === '' ? (el.options.length ? el.options[0].value : '') : String(v);
        } else {
            el.value = v == null ? '' : v;
        }
    });
}

/** Select the given client values; free-text names get their own option. */
function setCmsClients(el, values) {
    Array.prototype.slice.call(el.querySelectorAll('option[data-free]')).forEach(function (o) { o.remove(); });
    values.forEach(function (val) {
        var exists = Array.prototype.some.call(el.options, function (o) { return o.value === val; });
        if (!exists) {
            var o = new Option(val, val, false, false);
            o.setAttribute('data-free', '1');
            el.appendChild(o);
        }
    });
    Array.prototype.forEach.call(el.options, function (o) { o.selected = values.indexOf(o.value) !== -1; });
    if (window.jQuery && jQuery(el).data('select2')) { jQuery(el).trigger('change'); }
}

/** Open the add/edit drawer; focusField (e.g. 'clients') scrolls to and opens that field. */
function openCmsDrawer(id, focusField) {
    var row = id ? cmsRows[id] : null;
    fillCmsForm(row || null);
    document.getElementById('drawerTitle').textContent = (row ? 'Edit ' : 'Add ') + cmsLabel;
    document.getElementById('drawerBtnText').textContent = row ? 'Update' : 'Save';
    showCmsDrawer();
    if (focusField) {
        var el = document.getElementById('fld_' + focusField);
        if (el) {
            setTimeout(function () {
                el.closest('.form-group').scrollIntoView({ behavior: 'smooth', block: 'center' });
                if (window.jQuery && jQuery(el).data('select2')) { jQuery(el).select2('open'); } else { el.focus(); }
            }, 300);
        }
    }
}

/** Open the "Link portfolio items" drawer for a website client. */
function openLinkDrawer(clientId) {
    var linked = (cmsLinkedProjects[clientId] || []).map(String);
    var row = cmsRows[clientId];
    document.getElementById('link_client_id').value = clientId;
    document.getElementById('linkDrawerTitle').textContent = 'Link portfolio items' + (row && row.name ? ' — ' + row.name : '');
    document.querySelectorAll('#linkDrawerForm .link-project').forEach(function (cb) {
        cb.checked = linked.indexOf(cb.value) !== -1;
    });
    document.getElementById('linkDrawer').classList.add('open');
    document.getElementById('drawerBackdrop').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function showCmsDrawer() {
    document.getElementById('formDrawer').classList.add('open');
    document.getElementById('drawerBackdrop').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeCmsDrawer() {
    document.getElementById('formDrawer').classList.remove('open');
    var linkDrawer = document.getElementById('linkDrawer');
    if (linkDrawer) { linkDrawer.classList.remove('open'); }
    document.getElementById('drawerBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeCmsDrawer(); }
});

// Show the chosen filename in the custom file label.
document.querySelectorAll('#cmsDrawerForm .custom-file-input').forEach(function (input) {
    input.addEventListener('change', function () {
        var label = input.parentNode.querySelector('.custom-file-label');
        if (input.files && input.files.length) { label.textContent = input.files[0].name; }
    });
});

// Multi-client picker: Sales clients + free-text names (select2 tags).
document.addEventListener('DOMContentLoaded', function () {
    if (!window.jQuery || !jQuery.fn.select2) { return; }
    jQuery('#cmsDrawerForm .cms-clients').each(function () {
        jQuery(this).select2({
            theme: 'bootstrap4',
            width: '100%',
            tags: true,
            tokenSeparators: [','],
            placeholder: this.getAttribute('data-placeholder'),
            dropdownParent: jQuery('#formDrawer'),
            createTag: function (params) {
                var term = jQuery.trim(params.term);
                // "client:<id>" is reserved for Sales clients.
                if (term === '' || /^client:/i.test(term)) { return null; }
                return { id: term, text: term + ' (new name)', newTag: true };
            }
        });
    });
});

<?php if ($drawerOpen): ?>
// Server already rendered the form for ?edit_id= or a failed save — just show it.
document.addEventListener('DOMContentLoaded', showCmsDrawer);
<?php endif; ?>
</script>
