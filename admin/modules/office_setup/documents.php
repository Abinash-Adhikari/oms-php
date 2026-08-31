<?php
/**
 * SB-Tech — Office Setup / Documents (US-DOC-01).
 * Category CRUD + document register with multi-file upload, renew-date
 * flags, Public/Private access filtering (Private requires the
 * access_private_documents permission) and CSV export.
 */
$db = Database::instance();
$canSeePrivate = Auth::isSuperAdmin() || Auth::hasSpecial('access_private_documents');

$categories = $db->select('SELECT * FROM `tbl_office_document_category` ORDER BY title');

$edit = null;
if (isset($_GET['doc_id'])) {
    $edit = $db->selectOne('SELECT * FROM `tbl_office_documents` WHERE `id` = ?', [(int) $_GET['doc_id']]);
}
$editFiles = [];
if ($edit) {
    $editFiles = $db->select('SELECT * FROM `tbl_office_document_files` WHERE `document_id` = ? ORDER BY added_on', [(int) $edit['id']]);
}

// Filters
$catFilter = (int) ($_GET['category_id'] ?? 0);
$accessFilter = (string) ($_GET['access_type'] ?? '');
$where = ['1=1'];
$params = [];
if (!$canSeePrivate) {
    $where[] = 'd.access_type = ?';
    $params[] = 'Public';
} elseif ($accessFilter === 'Public' || $accessFilter === 'Private') {
    $where[] = 'd.access_type = ?';
    $params[] = $accessFilter;
}
if ($catFilter) {
    $where[] = 'd.category_id = ?';
    $params[] = $catFilter;
}
$docs = $db->select(
    'SELECT d.*, c.title AS category_title,
            (SELECT COUNT(*) FROM `tbl_office_document_files` f WHERE f.document_id = d.id) AS file_count
     FROM `tbl_office_documents` d
     LEFT JOIN `tbl_office_document_category` c ON c.id = d.category_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY d.renew_date IS NOT NULL DESC, d.renew_date, d.title',
    $params
);
$today = date('Y-m-d');
$todayTs = time();
$drawerOpen = ($edit !== null);
?>

<!-- Categories (inline, compact) -->
<div class="card card-outline mb-3">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-folder mr-1"></i>Categories</h3>
    </div>
    <div class="card-body py-2">
        <form action="operation.php?module=office_setup&page=documents" method="post" class="form-inline">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_category">
            <input type="text" name="title" class="form-control form-control-sm mr-2" placeholder="New category" required style="max-width: 200px;">
            <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-plus mr-1"></i>Add</button>
        </form>
        <?php if ($categories): ?>
            <div class="mt-2">
                <?php foreach ($categories as $c): ?>
                    <span class="badge badge-light border mr-1 mb-1 d-inline-flex align-items-center">
                        <?= e($c['title']) ?>
                        <form action="operation.php?module=office_setup&page=documents" method="post" class="d-inline ml-1">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete_category">
                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                            <button type="submit" class="btn btn-link btn-sm p-0 text-danger confirm-submit" data-confirm="Delete category '<?= e($c['title']) ?>'? Documents keep their category text but lose the link." style="font-size: 0.7rem; line-height: 1;"><i class="fas fa-times"></i></button>
                        </form>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Document Register (full width) -->
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-folder-open mr-1"></i>Document Register</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary btn-sm" onclick="openDrawer()">
                <i class="fas fa-plus mr-1"></i>Add Document
            </button>
            <form method="get" class="form-inline d-inline ml-2">
                <input type="hidden" name="module" value="office_setup">
                <input type="hidden" name="page" value="documents">
                <select name="category_id" class="form-control form-control-sm mr-1" onchange="this.form.submit()">
                    <option value="0">All categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= $catFilter === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($canSeePrivate): ?>
                    <select name="access_type" class="form-control form-control-sm mr-1" onchange="this.form.submit()">
                        <option value="">Public + Private</option>
                        <option value="Public" <?= $accessFilter === 'Public' ? 'selected' : '' ?>>Public</option>
                        <option value="Private" <?= $accessFilter === 'Private' ? 'selected' : '' ?>>Private</option>
                    </select>
                <?php endif; ?>
            </form>
            <form action="operation.php?module=office_setup&page=documents" method="post" class="d-inline ml-1">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="export_documents">
                <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-file-csv mr-1"></i>CSV</button>
            </form>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead>
                    <tr><th>#</th><th>Title</th><th>Category</th><th>Files</th><th>Access</th><th>Renew date</th><th class="text-right">Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($docs as $i => $d): ?>
                    <?php
                    $renewBadge = '';
                    if ($d['renew_date']) {
                        $rd = strtotime($d['renew_date']);
                        if ($rd < $todayTs) {
                            $renewBadge = '<span class="badge badge-danger" title="Expired">Expired</span>';
                        } elseif ($rd <= $todayTs + 30 * 86400) {
                            $renewBadge = '<span class="badge badge-warning" title="Nearing renew date">Renew soon</span>';
                        }
                    }
                    ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($d['title']) ?></td>
                        <td><?= e($d['category_title'] ?? $d['category'] ?? '—') ?></td>
                        <td><span class="badge badge-light border"><?= (int) $d['file_count'] ?> file(s)</span></td>
                        <td><span class="badge badge-<?= $d['access_type'] === 'Public' ? 'info' : 'secondary' ?>"><?= e($d['access_type']) ?></span></td>
                        <td><?= $d['renew_date'] ? e(formatDateView($d['renew_date'])) . ' ' . $renewBadge : '—' ?></td>
                        <td class="text-right">
                            <button type="button" class="btn btn-xs btn-outline-primary" onclick="openDrawer(<?= (int) $d['id'] ?>)"><i class="fas fa-edit"></i></button>
                            <form action="operation.php?module=office_setup&page=documents" method="post" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete_document">
                                <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete document '<?= e($d['title']) ?>' and its files?"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$docs): ?><tr><td colspan="7" class="text-center text-muted">No documents found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Slide-in Drawer Backdrop -->
<div class="cms-drawer-backdrop" id="drawerBackdrop" onclick="closeDrawer()"></div>

<!-- Slide-in Drawer -->
<div class="cms-drawer" id="formDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas fa-file-upload"></i><span id="drawerTitle"><?= $edit ? 'Edit Document' : 'Add Document' ?></span></h3>
        <button type="button" class="cms-drawer-close" onclick="closeDrawer()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=office_setup&page=documents" method="post" enctype="multipart/form-data" id="drawerForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_document">
            <input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" class="form-control" required value="<?= $edit ? e($edit['title']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Category</label>
                <select name="category_id" class="form-control">
                    <option value="">—</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= $edit && (int) $edit['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Renew date</label>
                <input type="date" name="renew_date" class="form-control" value="<?= $edit ? e($edit['renew_date']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Access</label>
                <select name="access_type" class="form-control">
                    <option value="Public" <?= !$edit || $edit['access_type'] === 'Public' ? 'selected' : '' ?>>Public (all staff)</option>
                    <option value="Private" <?= $edit && $edit['access_type'] === 'Private' ? 'selected' : '' ?>>Private (permission only)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Files <?= $edit ? '' : '*' ?></label>
                <div class="custom-file">
                    <input type="file" class="custom-file-input" id="doc_files" name="doc_files[]" multiple <?= $edit ? '' : 'required' ?>>
                    <label class="custom-file-label" for="doc_files">Choose one or more files</label>
                </div>
                <?php if ($editFiles): ?>
                    <ul class="list-unstyled mt-2 mb-0">
                        <?php foreach ($editFiles as $ef): ?>
                            <li><a href="<?= assetUrl('user_uploads/' . $ef['file_location']) ?>" target="_blank"><i class="fas fa-file mr-1"></i><?= e($ef['file_name']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="drawerForm" class="btn btn-primary btn-block">
            <i class="fas fa-save mr-1"></i><span id="drawerBtnText"><?= $edit ? 'Update Document' : 'Save Document' ?></span>
        </button>
    </div>
</div>

<script>
function openDrawer(docId) {
    var drawer = document.getElementById('formDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.add('open');
    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';

    if (docId) {
        window.location.href = '<?= pageUrl('office_setup', 'documents') ?>&doc_id=' + docId;
    }
}

function closeDrawer() {
    var drawer = document.getElementById('formDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.remove('open');
    backdrop.classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDrawer();
});

<?php if ($drawerOpen): ?>
document.addEventListener('DOMContentLoaded', function() {
    openDrawer();
});
<?php endif; ?>
</script>
