<?php
/**
 * SB-Tech — My Office / Documents (US-DOC-01).
 * Google Drive-style document manager: navigation sidebar, smart filters,
 * search + sort toolbar, grid/list views with selection & bulk actions.
 * Category CRUD + multi-file upload, renew-date flags, Public/Private
 * access filtering (Private requires access_private_documents) and CSV export.
 */
$db = Database::instance();
$canSeePrivate = Auth::isSuperAdmin() || Auth::hasSpecial('access_private_documents');

$categories = $db->select(
    'SELECT c.*, (SELECT COUNT(*) FROM `tbl_office_documents` d WHERE d.category_id = c.id) AS doc_count
     FROM `tbl_office_document_category` c
     ORDER BY c.title'
);

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
$statusFilter = (string) ($_GET['status'] ?? '');
$q = trim((string) ($_GET['q'] ?? ''));
$sort = (string) ($_GET['sort'] ?? 'renew');
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
if ($q !== '') {
    $where[] = 'd.title LIKE ?';
    $params[] = '%' . $q . '%';
}
$today = date('Y-m-d');
$todayTs = time();
if ($statusFilter === 'expired') {
    $where[] = 'd.renew_date IS NOT NULL AND d.renew_date < ?';
    $params[] = $today;
} elseif ($statusFilter === 'renewing') {
    $where[] = 'd.renew_date IS NOT NULL AND d.renew_date BETWEEN ? AND ?';
    $params[] = $today;
    $params[] = date('Y-m-d', $todayTs + 30 * 86400);
}
if ($sort === 'name') {
    $orderBy = 'd.title';
} elseif ($sort === 'added') {
    $orderBy = 'd.added_on DESC, d.title';
} else {
    $orderBy = 'd.renew_date IS NOT NULL DESC, d.renew_date, d.title';
}
$docs = $db->select(
    'SELECT d.*, c.title AS category_title,
            (SELECT COUNT(*) FROM `tbl_office_document_files` f WHERE f.document_id = d.id) AS file_count
     FROM `tbl_office_documents` d
     LEFT JOIN `tbl_office_document_category` c ON c.id = d.category_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY ' . $orderBy,
    $params
);

// Load all files for the visible docs (grouped by document_id) for preview / cards
$filesByDoc = [];
if ($docs) {
    $docIds = array_map(static fn($d) => (int) $d['id'], $docs);
    $placeholders = implode(',', array_fill(0, count($docIds), '?'));
    $fileRows = $db->select(
        'SELECT * FROM `tbl_office_document_files` WHERE `document_id` IN (' . $placeholders . ') ORDER BY document_id, added_on',
        $docIds
    );
    foreach ($fileRows as $fr) {
        $filesByDoc[(int) $fr['document_id']][] = $fr;
    }
}

// Build a JSON-safe map (url + name + icon) for the preview modal
$iconMap = [
    'pdf'   => 'fas fa-file-pdf text-danger',
    'doc'   => 'fas fa-file-word text-primary',
    'docx'  => 'fas fa-file-word text-primary',
    'xls'   => 'fas fa-file-excel text-success',
    'xlsx'  => 'fas fa-file-excel text-success',
    'ppt'   => 'fas fa-file-powerpoint text-warning',
    'pptx'  => 'fas fa-file-powerpoint text-warning',
    'jpg'   => 'fas fa-file-image text-info',
    'jpeg'  => 'fas fa-file-image text-info',
    'png'   => 'fas fa-file-image text-info',
    'gif'   => 'fas fa-file-image text-info',
    'webp'  => 'fas fa-file-image text-info',
    'zip'   => 'fas fa-file-archive text-secondary',
    'rar'   => 'fas fa-file-archive text-secondary',
    'csv'   => 'fas fa-file-csv text-secondary',
    'txt'   => 'fas fa-file-alt text-secondary',
];
$filesMap = [];
foreach ($filesByDoc as $docId => $frows) {
    foreach ($frows as $fr) {
        $ext = strtolower((string) ($fr['file_extension'] ?? pathinfo((string) $fr['file_name'], PATHINFO_EXTENSION)));
        $filesMap[$docId][] = [
            'url' => assetUrl('user_uploads/' . $fr['file_location']),
            'name' => $fr['file_name'],
            'ext' => $ext,
            'icon' => $iconMap[$ext] ?? 'fas fa-file text-secondary',
            'image' => in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true),
        ];
    }
}

// Drive-style card tile per file
$tileMap = [
    'pdf'   => ['tile-pdf', 'fa-file-pdf'],
    'doc'   => ['tile-doc', 'fa-file-word'],
    'docx'  => ['tile-doc', 'fa-file-word'],
    'xls'   => ['tile-sheet', 'fa-file-excel'],
    'xlsx'  => ['tile-sheet', 'fa-file-excel'],
    'ppt'   => ['tile-slide', 'fa-file-powerpoint'],
    'pptx'  => ['tile-slide', 'fa-file-powerpoint'],
    'zip'   => ['tile-zip', 'fa-file-archive'],
    'rar'   => ['tile-zip', 'fa-file-archive'],
    'csv'   => ['tile-text', 'fa-file-csv'],
    'txt'   => ['tile-text', 'fa-file-alt'],
];

// View mode: grid | list (legacy cards/table mapped)
$viewMode = (string) ($_GET['view'] ?? '');
if (in_array($viewMode, ['cards', 'grid'], true)) {
    $viewMode = 'grid';
} elseif (in_array($viewMode, ['table', 'list'], true)) {
    $viewMode = 'list';
} else {
    $viewMode = 'grid';
}

// KPI stats (sidebar overview)
$stats = $db->selectOne(
    'SELECT COUNT(*) AS total_docs,
            (SELECT COUNT(*) FROM `tbl_office_document_files`) AS total_files,
            SUM(d.access_type = "Private") AS private_docs,
            SUM(d.renew_date IS NOT NULL AND d.renew_date < ?) AS expired_docs,
            SUM(d.renew_date IS NOT NULL AND d.renew_date BETWEEN ? AND ?) AS renewing_docs
     FROM `tbl_office_documents` d',
    [$today, $today, date('Y-m-d', $todayTs + 30 * 86400)]
);
$totalDocs = (int) ($stats['total_docs'] ?? 0);
$totalFiles = (int) ($stats['total_files'] ?? 0);
$expiredDocs = (int) ($stats['expired_docs'] ?? 0);
$renewingDocs = (int) ($stats['renewing_docs'] ?? 0);
$privateDocs = (int) ($stats['private_docs'] ?? 0);

$drawerOpen = ($edit !== null);
$pageUrl = pageUrl('my_office', 'documents');

// Preserve current filters on links
$qs = [];
if ($catFilter) {
    $qs['category_id'] = $catFilter;
}
if ($accessFilter !== '') {
    $qs['access_type'] = $accessFilter;
}
if ($statusFilter !== '') {
    $qs['status'] = $statusFilter;
}
if ($q !== '') {
    $qs['q'] = $q;
}
if ($sort !== 'renew') {
    $qs['sort'] = $sort;
}
$qsStr = $qs ? '&' . http_build_query($qs) : '';
?>

<!-- ═══════════════ DRIVE SHELL ═══════════════ -->
<div class="drive-shell">

    <!-- ── Sidebar ── -->
    <aside class="drive-sidebar">
        <button type="button" class="drive-new-btn" onclick="openDrawer()">
            <i class="fas fa-plus"></i> New Document
        </button>

        <nav class="drive-nav">
            <div class="drive-nav-group-title">My Drive</div>
            <a class="drive-nav-item <?= !$catFilter && !$statusFilter && $accessFilter === '' ? 'active' : '' ?>" href="<?= $pageUrl ?>">
                <i class="fas fa-cloud"></i> My Drive
                <span class="drive-nav-count"><?= $totalDocs ?></span>
            </a>
            <a class="drive-nav-item warning <?= $statusFilter === 'renewing' ? 'active' : '' ?>" href="<?= $pageUrl ?>&status=renewing<?= $catFilter ? '&category_id=' . $catFilter : '' ?>">
                <i class="fas fa-hourglass-half"></i> Renewing soon
                <span class="drive-nav-count"><?= $renewingDocs ?></span>
            </a>
            <a class="drive-nav-item danger <?= $statusFilter === 'expired' ? 'active' : '' ?>" href="<?= $pageUrl ?>&status=expired<?= $catFilter ? '&category_id=' . $catFilter : '' ?>">
                <i class="fas fa-exclamation-triangle"></i> Expired
                <span class="drive-nav-count"><?= $expiredDocs ?></span>
            </a>
            <?php if ($canSeePrivate): ?>
                <a class="drive-nav-item <?= $accessFilter === 'Private' ? 'active' : '' ?>" href="<?= $pageUrl ?>&access_type=Private<?= $catFilter ? '&category_id=' . $catFilter : '' ?>">
                    <i class="fas fa-lock"></i> Private
                    <span class="drive-nav-count"><?= $privateDocs ?></span>
                </a>
            <?php endif; ?>
        </nav>

        <nav class="drive-nav">
            <div class="drive-nav-group-title">Folders</div>
            <?php if ($categories): ?>
                <?php foreach ($categories as $c): ?>
                    <div class="d-flex align-items-center" style="gap:2px">
                        <a class="drive-nav-item flex-fill <?= $catFilter === (int) $c['id'] ? 'active' : '' ?>"
                           style="min-width:0" href="<?= $pageUrl ?>&category_id=<?= (int) $c['id'] ?>">
                            <i class="fas fa-folder"></i>
                            <span class="text-truncate" title="<?= e($c['title']) ?>"><?= e($c['title']) ?></span>
                            <span class="drive-nav-count"><?= (int) $c['doc_count'] ?></span>
                        </a>
                        <form action="operation.php?module=my_office&page=documents" method="post" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete_category">
                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                            <button type="submit" class="btn btn-link btn-sm p-0 text-muted confirm-submit"
                                    data-confirm="Delete folder '<?= e($c['title']) ?>'? Documents keep their title/link but lose the folder link."
                                    title="Delete folder"><i class="fas fa-times"></i></button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <span class="text-muted small px-2">No folders yet.</span>
            <?php endif; ?>
            <form action="operation.php?module=my_office&page=documents" method="post" class="d-flex mt-1" style="gap:.4rem;padding:0 .2rem">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save_category">
                <input type="text" name="title" class="form-control form-control-sm flex-fill" placeholder="New folder" required>
                <button type="submit" class="btn btn-sm btn-outline-primary" title="Add folder"><i class="fas fa-plus"></i></button>
            </form>
        </nav>

        <div class="drive-storage">
            <div class="drive-storage-title"><i class="fas fa-hdd mr-1"></i>Storage</div>
            <div class="drive-storage-row"><span>Documents</span><b><?= $totalDocs ?></b></div>
            <div class="drive-storage-row"><span>Files</span><b><?= $totalFiles ?></b></div>
            <?php if ($totalDocs > 0): ?>
                <?php $activePct = (int) round(($totalDocs - $expiredDocs) / $totalDocs * 100); ?>
                <div class="drive-storage-bar mb-1">
                    <span style="width:<?= $activePct ?>%;background:#10B981"></span>
                    <span style="width:<?= 100 - $activePct ?>%;background:#EF4444"></span>
                </div>
                <div class="drive-storage-row">
                    <span><i class="fas fa-check-circle mr-1" style="color:#10B981"></i><?= $totalDocs - $expiredDocs ?> active</span>
                    <span><i class="fas fa-times-circle mr-1" style="color:#EF4444"></i><?= $expiredDocs ?> expired</span>
                </div>
            <?php endif; ?>
        </div>
    </aside>

    <!-- ── Main ── -->
    <main class="drive-main">

        <!-- Bulk action bar -->
        <div class="drive-bulkbar" id="driveBulkbar">
            <button type="button" class="drive-clear" onclick="clearSelection()" title="Clear selection"><i class="fas fa-times"></i></button>
            <span class="drive-count" id="driveBulkCount">0 selected</span>
            <span class="ml-auto"></span>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="bulkPreview()"><i class="fas fa-eye mr-1"></i>Preview</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="bulkDownload()"><i class="fas fa-download mr-1"></i>Download</button>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="bulkDelete()"><i class="fas fa-trash mr-1"></i>Delete</button>
        </div>

        <!-- Toolbar -->
        <div class="drive-toolbar">
            <form method="get" class="d-flex flex-fill" style="gap:.6rem;min-width:0">
                <input type="hidden" name="module" value="my_office">
                <input type="hidden" name="page" value="documents">
                <?php if ($catFilter): ?><input type="hidden" name="category_id" value="<?= $catFilter ?>"><?php endif; ?>
                <?php if ($statusFilter !== ''): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
                <?php if ($accessFilter !== ''): ?><input type="hidden" name="access_type" value="<?= e($accessFilter) ?>"><?php endif; ?>
                <div class="drive-search">
                    <i class="fas fa-search"></i>
                    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search documents…" aria-label="Search documents">
                </div>
                <select name="sort" class="form-control form-control-sm" style="width:auto" onchange="this.form.submit()">
                    <option value="renew" <?= $sort === 'renew' ? 'selected' : '' ?>>Sort: Renew date</option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Sort: Name</option>
                    <option value="added" <?= $sort === 'added' ? 'selected' : '' ?>>Sort: Recently added</option>
                </select>
                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Apply search"><i class="fas fa-filter"></i></button>
            </form>

            <form action="operation.php?module=my_office&page=documents" method="post" class="ml-1">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="export_documents">
                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Export CSV"><i class="fas fa-file-csv"></i></button>
            </form>

            <div class="drive-view-toggle ml-1" role="group" aria-label="View mode">
                <a href="<?= $pageUrl ?>&view=grid<?= $qsStr ?>" class="btn btn-sm <?= $viewMode === 'grid' ? 'active' : '' ?>" title="Grid view"><i class="fas fa-th-large"></i></a>
                <a href="<?= $pageUrl ?>&view=list<?= $qsStr ?>" class="btn btn-sm <?= $viewMode === 'list' ? 'active' : '' ?>" title="List view"><i class="fas fa-list"></i></a>
            </div>
        </div>

        <?php if ($qs): ?>
            <div class="drive-restore">
                <i class="fas fa-filter"></i> Filtered view
                <a href="<?= $pageUrl ?>">Clear filters</a>
            </div>
        <?php endif; ?>

        <?php if (!$docs): ?>
            <div class="drive-empty">
                <i class="fas fa-folder-open"></i>
                <h5><?= $qs ? 'No documents match your filters.' : 'Your Drive is empty.' ?></h5>
                <p class="small mb-0"><?= $qs ? 'Try clearing filters or adjusting your search.' : 'Click "New Document" to upload the first file.' ?></p>
            </div>
        <?php elseif ($viewMode === 'grid'): ?>
            <!-- ═══════════ GRID VIEW (Google Drive cards) ═══════════ -->
            <div class="drive-grid" id="driveGrid">
                <?php foreach ($docs as $d): ?>
                    <?php
                    $id = (int) $d['id'];
                    $fileCount = (int) $d['file_count'];
                    $docFiles = $filesMap[$id] ?? [];
                    $renewBadge = '';
                    if ($d['renew_date']) {
                        $rd = strtotime($d['renew_date']);
                        if ($rd < $todayTs) {
                            $renewBadge = '<span class="drive-card-badge badge-danger"><i class="fas fa-exclamation-triangle mr-1"></i>Expired</span>';
                        } elseif ($rd <= $todayTs + 30 * 86400) {
                            $renewBadge = '<span class="drive-card-badge badge-warning"><i class="fas fa-hourglass-half mr-1"></i>Renew soon</span>';
                        }
                    }
                    $thumb = '';
                    if ($docFiles) {
                        $shown = array_slice($docFiles, 0, 3);
                        $count = count($shown);
                        $stack = '';
                        foreach ($shown as $j => $ff) {
                            $layerClass = $j === 1 ? ' mid' : ($j === 2 ? ' back' : '');
                            if ($ff['image']) {
                                $stack .= '<img src="' . e($ff['url']) . '" alt="' . e($ff['name']) . '" class="drive-tile drive-tile-img' . $layerClass . '" loading="lazy">';
                            } else {
                                $ext = $ff['ext'];
                                $tile = $tileMap[$ext] ?? ['tile-file', 'fa-file'];
                                $stack .= '<span class="drive-tile ' . $tile[0] . $layerClass . '"><i class="fas ' . $tile[1] . '"></i><small>' . e(strtoupper($ext)) . '</small></span>';
                            }
                        }
                        $thumb = $count > 1 ? '<div class="drive-tile-many">' . $stack . '</div>' : $stack;
                    }
                    ?>
                    <div class="drive-card<?= $renewBadge ? ' has-badge' : '' ?>" data-id="<?= $id ?>" onclick="toggleSelect(this, <?= $id ?>)">
                        <span class="drive-check"><i class="fas fa-check"></i></span>
                        <?= $renewBadge ?>
                        <div class="drive-card-thumb">
                            <?= $thumb ?: '<span class="drive-tile tile-file"><i class="fas fa-folder-open"></i><small>DOC</small></span>' ?>
                        </div>
                        <div class="drive-card-body">
                            <div class="drive-card-name" title="<?= e($d['title']) ?>"><?= e($d['title']) ?></div>
                            <div class="drive-card-meta">
                                <?= e($d['category_title'] ?? $d['category'] ?? 'General') ?> ·
                                <?= $fileCount ?> file<?= $fileCount === 1 ? '' : 's' ?> ·
                                <?php if ($d['access_type'] === 'Private'): ?>
                                    <i class="fas fa-lock mr-1"></i><?= e($d['access_type']) ?>
                                <?php else: ?>
                                    <i class="fas fa-globe mr-1"></i><?= e($d['access_type']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="drive-card-actions" onclick="event.stopPropagation()">
                            <?php if ($fileCount > 0): ?>
                                <a href="#" onclick="openDocFiles(<?= $id ?>, <?= $fileCount ?>);return false;" class="success" title="Download / preview files"><i class="fas fa-download"></i></a>
                            <?php endif; ?>
                            <button type="button" title="Preview files" onclick="openDocFiles(<?= $id ?>, <?= $fileCount ?>)"><i class="fas fa-eye"></i></button>
                            <button type="button" title="Edit" onclick="openDrawer(<?= $id ?>)"><i class="fas fa-edit"></i></button>
                            <button type="button" class="danger" title="Delete" onclick="deleteDoc(<?= $id ?>)"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- ═══════════ LIST VIEW (Google Drive rows) ═══════════ -->
            <div class="drive-list-card">
                <table class="drive-list-table">
                    <thead>
                        <tr>
                            <th style="width:34px">
                                <span class="drive-check-row" style="cursor:pointer" onclick="toggleSelectAll(this)" title="Select all"><i class="fas fa-check"></i></span>
                            </th>
                            <th>Name</th>
                            <th>Folder</th>
                            <th>Files</th>
                            <th>Access</th>
                            <th>Renew date</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($docs as $d): ?>
                        <?php
                        $id = (int) $d['id'];
                        $fileCount = (int) $d['file_count'];
                        $docFiles = $filesMap[$id] ?? [];
                        $firstFile = $docFiles[0] ?? null;
                        $renewBadge = '';
                        $renewText = '—';
                        if ($d['renew_date']) {
                            $rd = strtotime($d['renew_date']);
                            $renewText = e(formatDateView($d['renew_date']));
                            if ($rd < $todayTs) {
                                $renewBadge = ' <span class="badge badge-danger"><i class="fas fa-exclamation-triangle mr-1"></i>Expired</span>';
                            } elseif ($rd <= $todayTs + 30 * 86400) {
                                $renewBadge = ' <span class="badge badge-warning"><i class="fas fa-hourglass-half mr-1"></i>Renew soon</span>';
                            }
                        }
                        $ext = $firstFile['ext'] ?? '';
                        $tile = $tileMap[$ext] ?? ['tile-file', 'fa-file'];
                        ?>
                        <tr data-id="<?= $id ?>" onclick="toggleSelect(this, <?= $id ?>)">
                            <td><span class="drive-check-row"><i class="fas fa-check"></i></span></td>
                            <td>
                                <div class="drive-list-name">
                                    <span class="drive-tile <?= $tile[0] ?>"><i class="fas <?= $tile[1] ?>"></i></span>
                                    <b class="text-truncate" style="max-width:260px" title="<?= e($d['title']) ?>"><?= e($d['title']) ?></b>
                                </div>
                            </td>
                            <td><?= e($d['category_title'] ?? $d['category'] ?? '—') ?></td>
                            <td><span class="badge badge-light border"><?= $fileCount ?> file<?= $fileCount === 1 ? '' : 's' ?></span></td>
                            <td>
                                <span class="badge badge-pill badge-<?= $d['access_type'] === 'Public' ? 'info' : 'secondary' ?>">
                                    <i class="fas fa-<?= $d['access_type'] === 'Public' ? 'globe' : 'lock' ?> mr-1"></i><?= e($d['access_type']) ?>
                                </span>
                            </td>
                            <td><?= $renewText ?><?= $renewBadge ?></td>
                            <td>
                                <div class="drive-list-actions" onclick="event.stopPropagation()">
                                    <?php if ($fileCount > 0): ?>
                                        <a href="#" onclick="openDocFiles(<?= $id ?>, <?= $fileCount ?>);return false;" class="success" title="Download files"><i class="fas fa-download"></i></a>
                                    <?php endif; ?>
                                    <button type="button" title="Preview files" onclick="openDocFiles(<?= $id ?>, <?= $fileCount ?>)"><i class="fas fa-eye"></i></button>
                                    <button type="button" title="Edit" onclick="openDrawer(<?= $id ?>)"><i class="fas fa-edit"></i></button>
                                    <button type="button" class="danger" title="Delete" onclick="deleteDoc(<?= $id ?>)"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
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
        <form action="operation.php?module=my_office&page=documents" method="post" enctype="multipart/form-data" id="drawerForm">
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
                    <div class="mt-2 border rounded p-2 bg-light">
                        <?php foreach ($editFiles as $ef): ?>
                            <div class="d-flex align-items-center justify-content-between px-1" style="border-bottom:1px solid #f1f5f9">
                                <span class="text-truncate pr-2" style="max-width:180px" title="<?= e($ef['file_name']) ?>">
                                    <i class="fas fa-file mr-1 text-muted"></i><?= e($ef['file_name']) ?>
                                    <?php if ($ef['file_extension']): ?>
                                        <span class="badge badge-light border ml-1"><?= e(strtoupper($ef['file_extension'])) ?></span>
                                    <?php endif; ?>
                                </span>
                                <a href="<?= assetUrl('user_uploads/' . $ef['file_location']) ?>" target="_blank" rel="noopener"
                                   class="tms-file-preview text-muted" title="Preview"
                                   data-src="<?= assetUrl('user_uploads/' . $ef['file_location']) ?>"
                                   data-name="<?= e($ef['file_name']) ?>">
                                    <i class="fas fa-eye ml-1"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
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
var DOC_FILES = <?= json_encode($filesMap, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG) ?>;
var selectedIds = {};

function openDrawer(docId) {
    var drawer = document.getElementById('formDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.add('open');
    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';

    if (docId) {
        window.location.href = '<?= $pageUrl ?>&doc_id=' + docId;
    }
}

function closeDrawer() {
    var drawer = document.getElementById('formDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.remove('open');
    backdrop.classList.remove('active');
    document.body.style.overflow = '';
}

function deleteDoc(docId) {
    var files = (DOC_FILES[docId] || []);
    var names = files.length ? files.map(function(f){ return f.name; }).join(', ') : 'this document';
    if (!confirm('Delete this document (' + names + ') and its files?')) return;

    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'operation.php?module=my_office&page=documents';
    form.innerHTML = '<?= csrfField() ?>';
    var act = document.createElement('input');
    act.type = 'hidden'; act.name = 'action'; act.value = 'delete_document';
    var idEl = document.createElement('input');
    idEl.type = 'hidden'; idEl.name = 'id'; idEl.value = docId;
    form.appendChild(act);
    form.appendChild(idEl);
    document.body.appendChild(form);
    form.submit();
}

function openDocFiles(docId, fileCount) {
    var files = DOC_FILES[docId] || [];
    var title = (fileCount === undefined || fileCount === 0) ? 'Document files' : 'Document files (' + fileCount + ')';
    openFileGallery(title, files);
}

/* ── Selection & bulk actions ── */
function selectedList() {
    return Object.keys(selectedIds).map(Number);
}

function updateBulkBar() {
    var list = selectedList();
    var bar = document.getElementById('driveBulkbar');
    var countEl = document.getElementById('driveBulkCount');
    if (!bar || !countEl) return;
    if (list.length) {
        bar.classList.add('show');
        countEl.textContent = list.length + ' selected';
    } else {
        bar.classList.remove('show');
        countEl.textContent = '0 selected';
    }
    var checkRow = document.querySelector('.drive-list-table thead .drive-check-row');
    if (checkRow) checkRow.style.background = '';
}

function toggleSelect(node, docId) {
    var key = String(docId);
    if (selectedIds[key]) {
        delete selectedIds[key];
        node.classList.remove('selected');
    } else {
        selectedIds[key] = true;
        node.classList.add('selected');
    }
    updateBulkBar();
}

function toggleSelectAll(checkbox) {
    var cards = document.querySelectorAll('#driveGrid .drive-card');
    var rows = document.querySelectorAll('.drive-list-table tbody tr');
    if (cards.length) {
        var allSel = cards.length === cards.length && Object.keys(selectedIds).length === cards.length;
        if (allSel) {
            clearSelection();
        } else {
            cards.forEach(function (c) {
                selectedIds[String(c.getAttribute('data-id'))] = true;
                c.classList.add('selected');
            });
        }
    } else if (rows.length) {
        var allSelRows = Object.keys(selectedIds).length === rows.length;
        if (allSelRows) {
            clearSelection();
        } else {
            rows.forEach(function (r) {
                selectedIds[String(r.getAttribute('data-id'))] = true;
                r.classList.add('selected');
            });
        }
    }
    updateBulkBar();
    var checkRow = document.querySelector('.drive-list-table thead .drive-check-row');
    if (checkRow) checkRow.style.background = Object.keys(selectedIds).length ? '#2563EB' : '';
}

function clearSelection() {
    selectedIds = {};
    document.querySelectorAll('.drive-card.selected, .drive-list-table tr.selected').forEach(function (n) {
        n.classList.remove('selected');
    });
    updateBulkBar();
}

function bulkPreview() {
    var list = selectedList();
    if (list.length) openDocFiles(list[0], undefined);
}

function bulkDownload() {
    var list = selectedList();
    if (!list.length) return;
    var target = list[0];
    var files = DOC_FILES[target] || [];
    if (files.length === 1) {
        window.open(files[0].url, '_blank');
    } else {
        openDocFiles(target, undefined);
    }
}

function bulkDelete() {
    var list = selectedList();
    if (!list.length) return;
    if (!confirm('Delete ' + list.length + ' selected document(s) and all their files?')) return;

    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'operation.php?module=my_office&page=documents';
    form.innerHTML = '<?= csrfField() ?>';
    var act = document.createElement('input');
    act.type = 'hidden'; act.name = 'action'; act.value = 'delete_documents_bulk';
    form.appendChild(act);
    list.forEach(function (docId) {
        var input = document.createElement('input');
        input.type = 'hidden'; input.name = 'ids[]'; input.value = docId;
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
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