<?php
/**
 * SB-Tech — My Office / Expense Claims (US-FIN-06, US-FIN-07, US-FIN-08).
 * Staff submit claims (Draft → Submitted); finance approves (→ Payment
 * voucher auto-created) or rejects with a reason; the claim becomes Paid
 * when its payment voucher is approved. Edit/delete only while Draft or
 * Rejected (AC-FIN-06.3).
 */
$db = Database::instance();
$me = (int) Auth::id();
$canApprove = Auth::isSuperAdmin() || Auth::hasSpecial('approve_expense_claims');

$projects = $db->select('SELECT id, title, client_id FROM `tbl_client_projects` ORDER BY title');
$clients = $db->select('SELECT id, name FROM `tbl_clients` ORDER BY name');

$edit = null;
$editId = (int) ($_GET['edit_id'] ?? 0);
if ($editId) {
    $edit = $db->selectOne('SELECT * FROM `tbl_expense_claims` WHERE `id` = ? AND `staff_id` = ?', [$editId, $me]);
    if ($edit && !in_array($edit['status'], ['Draft', 'Rejected'], true)) {
        $edit = null; // locked once submitted/approved/paid
    }
}
$editFiles = [];
if ($edit) {
    $editFiles = $db->select('SELECT * FROM `tbl_expense_claim_files` WHERE `claim_id` = ?', [(int) $edit['id']]);
}

// My claims.
$myStatusFilter = (string) ($_GET['my_status'] ?? '');
$anyMyStatus = in_array($myStatusFilter, ['Draft', 'Submitted', 'Approved', 'Rejected', 'Paid'], true);
$myWhere = 'c.staff_id = ?';
$myParams = [$me];
if ($anyMyStatus) {
    $myWhere .= ' AND c.status = ?';
    $myParams[] = $myStatusFilter;
}
$myClaims = $db->select(
    'SELECT c.*, pv.voucher_no AS payment_voucher_no
     FROM `tbl_expense_claims` c
     LEFT JOIN `tbl_payment_vouchers` pv ON pv.id = c.payment_voucher_id
     WHERE ' . $myWhere . '
     ORDER BY c.id DESC LIMIT 100',
    $myParams
);

// Status counts for the sidebar (scoped to my claims).
$myCounts = ['Draft' => 0, 'Submitted' => 0, 'Approved' => 0, 'Rejected' => 0, 'Paid' => 0];
foreach ($db->select(
    'SELECT status, COUNT(*) AS n FROM `tbl_expense_claims` WHERE staff_id = ? GROUP BY status',
    [$me]
) as $row) {
    $myCounts[$row['status']] = (int) $row['n'];
}

// Finance view: all claims + filters.
$allClaims = [];
if ($canApprove) {
    $where = ['1=1'];
    $params = [];
    $fStaff = (int) ($_GET['staff_id'] ?? 0);
    $fStatus = (string) ($_GET['status'] ?? '');
    $fCategory = trim((string) ($_GET['category'] ?? ''));
    $fQ = trim((string) ($_GET['q'] ?? ''));
    if ($fStaff) {
        $where[] = 'c.staff_id = ?';
        $params[] = $fStaff;
    }
    if (in_array($fStatus, ['Draft', 'Submitted', 'Approved', 'Rejected', 'Paid'], true)) {
        $where[] = 'c.status = ?';
        $params[] = $fStatus;
    }
    if ($fCategory !== '') {
        $where[] = 'c.category LIKE ?';
        $params[] = '%' . $db->escapeLike($fCategory) . '%';
    }
    if ($fQ !== '') {
        $where[] = '(c.claim_no LIKE ? OR c.category LIKE ? OR u.fullname LIKE ?)';
        $like = '%' . $db->escapeLike($fQ) . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    $allClaims = $db->select(
        'SELECT c.*, u.fullname AS staff_name, pv.voucher_no AS payment_voucher_no
         FROM `tbl_expense_claims` c
         LEFT JOIN `tbl_users_login` u ON u.id = c.staff_id
         LEFT JOIN `tbl_payment_vouchers` pv ON pv.id = c.payment_voucher_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY c.id DESC LIMIT 200',
        $params
    );
    $staffList = $db->select('SELECT id, fullname FROM `tbl_users_login` WHERE `status` = ? ORDER BY fullname', ['Active']);
}

// Summary
$summaryWhere = $canApprove ? '1=1' : 'staff_id = ?';
$summaryParams = $canApprove ? [] : [$me];
$summary = $db->selectOne(
    'SELECT COUNT(*) AS total,
            COALESCE(SUM(CASE WHEN status IN (\'Submitted\',\'Approved\') THEN amount ELSE 0 END), 0) AS outstanding,
            COALESCE(SUM(CASE WHEN status = \'Paid\' THEN amount ELSE 0 END), 0) AS paid
     FROM `tbl_expense_claims` WHERE ' . $summaryWhere,
    $summaryParams
);
$categories = $db->select(
    'SELECT category, COALESCE(SUM(amount),0) AS total, COUNT(*) AS c
     FROM `tbl_expense_claims` WHERE ' . $summaryWhere . ' GROUP BY category ORDER BY total DESC LIMIT 10',
    $summaryParams
);

// Receipt files for all visible claims (My + All), grouped per claim.
$visibleClaims = array_merge($myClaims, $allClaims);
$receiptsByClaim = [];
if ($visibleClaims) {
    $claimIds = array_values(array_unique(array_map(static fn($rc) => (int) $rc['id'], $visibleClaims)));
    $claimPlaces = implode(',', array_fill(0, count($claimIds), '?'));
    $receiptRows = $db->select(
        'SELECT * FROM `tbl_expense_claim_files` WHERE `claim_id` IN (' . $claimPlaces . ') ORDER BY claim_id, added_on',
        $claimIds
    );
    foreach ($receiptRows as $rr) {
        $receiptsByClaim[(int) $rr['claim_id']][] = $rr;
    }
}
$claimIconMap = [
    'pdf'  => 'fas fa-file-pdf text-danger',
    'jpg'  => 'fas fa-file-image text-info',
    'jpeg' => 'fas fa-file-image text-info',
    'png'  => 'fas fa-file-image text-info',
    'gif'  => 'fas fa-file-image text-info',
    'webp' => 'fas fa-file-image text-info',
    'doc'  => 'fas fa-file-word text-primary',
    'docx' => 'fas fa-file-word text-primary',
    'xls'  => 'fas fa-file-excel text-success',
    'xlsx' => 'fas fa-file-excel text-success',
    'csv'  => 'fas fa-file-csv text-secondary',
    'txt'  => 'fas fa-file-alt text-secondary',
];
$receiptsMap = [];
foreach ($receiptsByClaim as $claimId => $rows) {
    foreach ($rows as $rr) {
        $ext = strtolower((string) ($rr['file_extension'] ?? pathinfo((string) $rr['file_name'], PATHINFO_EXTENSION)));
        $receiptsMap[$claimId][] = [
            'url'   => assetUrl('user_uploads/' . $rr['file_location']),
            'name'  => $rr['file_name'],
            'ext'   => $ext,
            'icon'  => $claimIconMap[$ext] ?? 'fas fa-file text-secondary',
            'image' => in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true),
        ];
    }
}

$drawerOpen = ($edit !== null);
$pageUrl = pageUrl('my_office', 'expense_claims');
?>

<!-- ═══════════════ DRIVE SHELL (Expense Claims) ═══════════════ -->
<div class="drive-shell">

    <!-- ── Sidebar ── -->
    <aside class="drive-sidebar">
        <button type="button" class="drive-new-btn" onclick="openDrawer()">
            <i class="fas fa-plus"></i> New Claim
        </button>

        <nav class="drive-nav">
            <div class="drive-nav-group-title">My Claims</div>
            <a class="drive-nav-item <?= !$anyMyStatus ? 'active' : '' ?>" href="<?= $pageUrl ?>">
                <i class="fas fa-file-invoice-dollar"></i> All Claims
                <span class="drive-nav-count"><?= (int) $summary['total'] ?></span>
            </a>
            <?php foreach (['Draft' => 'file-draft', 'Submitted' => 'paper-plane', 'Approved' => 'check-circle', 'Rejected' => 'times-circle', 'Paid' => 'coins'] as $st => $icn): ?>
                <a class="drive-nav-item <?= $myStatusFilter === $st ? 'active' : '' ?>" href="<?= $pageUrl ?>&my_status=<?= urlencode($st) ?>">
                    <i class="fas fa-<?= $icn ?>"></i> <?= $st ?>
                    <span class="drive-nav-count"><?= (int) $myCounts[$st] ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <nav class="drive-nav">
            <div class="drive-nav-group-title">Summary</div>
            <div class="px-2 pb-1">
                <div class="drive-storage-row"><span><i class="fas fa-hourglass-half mr-1" style="color:#d97706"></i>Outstanding</span><b><?= e(formatMoney($summary['outstanding'])) ?></b></div>
                <div class="drive-storage-row"><span><i class="fas fa-coins mr-1" style="color:#16a34a"></i>Paid</span><b><?= e(formatMoney($summary['paid'])) ?></b></div>
            </div>
        </nav>

        <?php if ($categories): ?>
            <nav class="drive-nav">
                <div class="drive-nav-group-title">Top Categories</div>
                <?php foreach ($categories as $cat): ?>
                    <div class="drive-nav-item" style="cursor:default">
                        <i class="fas fa-tag"></i>
                        <span class="text-truncate" title="<?= e($cat['category'] ?: '—') ?>"><?= e($cat['category'] ?: '—') ?></span>
                        <span class="drive-nav-count"><?= (int) $cat['c'] ?></span>
                    </div>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
    </aside>

    <!-- ── Main ── -->
    <main class="drive-main">

        <!-- Toolbar -->
        <div class="drive-toolbar">
            <form method="get" class="d-flex flex-fill" style="gap:.6rem;min-width:0">
                <input type="hidden" name="module" value="my_office">
                <input type="hidden" name="page" value="expense_claims">
                <?php if ($anyMyStatus): ?><input type="hidden" name="my_status" value="<?= e($myStatusFilter) ?>"><?php endif; ?>
                <input type="text" name="q" class="form-control form-control-sm" style="max-width:220px" placeholder="Search claims… (finance view)" value="<?= e(trim((string) ($_GET['q'] ?? ''))) ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Apply search"><i class="fas fa-search"></i></button>
            </form>
            <?php if ($canApprove): ?>
                <form method="get" class="d-flex" style="gap:.4rem;margin-left:auto">
                    <input type="hidden" name="module" value="my_office">
                    <input type="hidden" name="page" value="expense_claims">
                    <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">All statuses</option>
                        <?php foreach (['Draft', 'Submitted', 'Approved', 'Rejected', 'Paid'] as $st): ?>
                            <option value="<?= $st ?>" <?= $fStatus === $st ? 'selected' : '' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="staff_id" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="0">All staff</option>
                        <?php foreach ($staffList as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" <?= $fStaff === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['fullname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Apply filter"><i class="fas fa-filter"></i></button>
                </form>
                <form action="operation.php?module=my_office&page=expense_claims" method="post" class="ml-1">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="export_claims">
                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Export CSV"><i class="fas fa-file-csv"></i></button>
                </form>
            <?php endif; ?>
        </div>

        <!-- ═══════════ MY CLAIMS — GRID ═══════════ -->
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h3 class="mb-0" style="font-size:1.05rem;font-weight:700">
                <i class="fas fa-file-invoice-dollar mr-1" style="color:var(--accent,#2563EB)"></i>My Claims
                <?php if ($anyMyStatus): ?><span class="badge badge-pill badge-primary ml-1"><?= e($myStatusFilter) ?></span><?php endif; ?>
            </h3>
            <button type="button" class="btn btn-primary btn-sm" onclick="openDrawer()">
                <i class="fas fa-plus mr-1"></i>New Claim
            </button>
        </div>

        <?php if (!$myClaims): ?>
            <div class="drive-empty">
                <i class="fas fa-file-invoice-dollar"></i>
                <h5><?= $anyMyStatus ? 'No ' . e($myStatusFilter) . ' claims.' : 'No claims yet.' ?></h5>
                <p class="small mb-0">Click "New Claim" to submit your first expense.</p>
            </div>
        <?php else: ?>
            <div class="drive-grid">
                <?php foreach ($myClaims as $c): ?>
                    <?php
                    $rid = (int) $c['id'];
                    $receipts = $receiptsMap[$rid] ?? [];
                    $receiptCount = count($receipts);
                    $statusClass = $c['status'] === 'Paid' ? 'success' : ($c['status'] === 'Approved' ? 'info' : ($c['status'] === 'Rejected' ? 'danger' : ($c['status'] === 'Submitted' ? 'primary' : 'secondary')));
                    $thumb = '';
                    if ($receipts) {
                        $first = $receipts[0];
                        if ($first['image']) {
                            $thumb = '<img src="' . e($first['url']) . '" alt="' . e($first['name']) . '" class="drive-tile drive-tile-img" loading="lazy">';
                        } else {
                            $tileSets = [
                                'tile-pdf'   => ['pdf'],
                                'tile-doc'   => ['doc', 'docx'],
                                'tile-sheet' => ['xls', 'xlsx', 'csv'],
                                'tile-text'  => ['txt'],
                                'tile-image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
                            ];
                            $tileClass = 'tile-file';
                            foreach ($tileSets as $tc => $exts) {
                                if (in_array($first['ext'], $exts, true)) {
                                    $tileClass = $tc;
                                    break;
                                }
                            }
                            $thumb = '<span class="drive-tile ' . $tileClass . '"><i class="fas fa-file"></i><small>' . e(strtoupper($first['ext'])) . '</small></span>';
                        }
                    }
                    ?>
                    <div class="drive-card" data-id="<?= $rid ?>">
                        <span class="drive-card-badge badge-<?= $statusClass ?>"><?= e($c['status']) ?></span>
                        <div class="drive-card-thumb">
                            <?= $thumb ?: '<span class="drive-tile tile-file"><i class="fas fa-receipt"></i><small>NO FILES</small></span>' ?>
                        </div>
                        <div class="drive-card-body">
                            <div class="drive-card-name" title="<?= e($c['claim_no']) ?>"><?= e($c['category'] ?? 'Expense') ?></div>
                            <div class="drive-card-meta">
                                <?= e(formatDateView($c['expense_date'])) ?> ·
                                <?= $receiptCount ?> receipt<?= $receiptCount === 1 ? '' : 's' ?>
                            </div>
                            <div class="drive-card-meta" style="font-size:.8rem;font-weight:600;color:var(--accent,#2563EB)">
                                NPR <?= e(formatMoney($c['amount'])) ?>
                            </div>
                            <div class="drive-card-meta">
                                <?php if ($c['status'] === 'Rejected' && $c['reject_reason']): ?>
                                    <small class="text-danger" title="<?= e($c['reject_reason']) ?>">rejected: <?= e(mb_strimwidth($c['reject_reason'], 0, 30, '…')) ?></small>
                                <?php elseif ($c['payment_voucher_no']): ?>
                                    Voucher <?= e($c['payment_voucher_no']) ?>
                                <?php else: ?>—<?php endif; ?>
                            </div>
                        </div>
                        <div class="drive-card-actions" onclick="event.stopPropagation()">
                            <?php if ($receiptCount > 0): ?>
                                <a href="#" onclick="openClaimReceipts(<?= $rid ?>, <?= $receiptCount ?>);return false;" class="success" title="View receipts"><i class="fas fa-paperclip"></i></a>
                            <?php endif; ?>
                            <?php if (in_array($c['status'], ['Draft', 'Rejected'], true)): ?>
                                <button type="button" title="Edit" onclick="openDrawer(<?= $rid ?>)"><i class="fas fa-edit"></i></button>
                            <?php endif; ?>
                            <?php if (in_array($c['status'], ['Draft', 'Rejected'], true)): ?>
                                <button type="button" class="danger" title="Delete" onclick="deleteClaim(<?= $rid ?>)"><i class="fas fa-trash"></i></button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($canApprove): ?>
            <!-- ═══════════ ALL CLAIMS REVIEW ═══════════ -->
            <hr class="my-4" style="border-color:var(--border-color)">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h3 class="mb-0" style="font-size:1.05rem;font-weight:700">
                    <i class="fas fa-clipboard-check mr-1" style="color:var(--accent,#2563EB)"></i>All Claims — Review
                </h3>
                <span class="text-muted small"><?= count($allClaims) ?> match<?= count($allClaims) === 1 ? '' : 'es' ?> the filter</span>
            </div>
            <div class="drive-list-card">
                <table class="drive-list-table">
                    <thead>
                        <tr>
                            <th>Claim no</th>
                            <th>Staff</th>
                            <th>Date</th>
                            <th>Category</th>
                            <th class="text-right">Amount</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($allClaims as $c): ?>
                        <?php
                        $rid = (int) $c['id'];
                        $receiptCount = count($receiptsMap[$rid] ?? []);
                        $statusClass = $c['status'] === 'Paid' ? 'success' : ($c['status'] === 'Approved' ? 'info' : ($c['status'] === 'Rejected' ? 'danger' : ($c['status'] === 'Submitted' ? 'primary' : 'secondary')));
                        ?>
                        <tr>
                            <td><b><?= e($c['claim_no']) ?></b></td>
                            <td><?= e($c['staff_name'] ?? '#' . $c['staff_id']) ?></td>
                            <td><?= e(formatDateView($c['expense_date'])) ?></td>
                            <td><?= e($c['category'] ?? '—') ?></td>
                            <td class="text-right"><?= e(formatMoney($c['amount'])) ?></td>
                            <td>
                                <span class="badge badge-<?= $statusClass ?>"><?= e($c['status']) ?></span>
                                <?php if ($c['status'] === 'Rejected' && $c['reject_reason']): ?>
                                    <small class="d-block text-danger" title="<?= e($c['reject_reason']) ?>"><?= e(mb_strimwidth($c['reject_reason'], 0, 30, '…')) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="drive-list-actions" style="opacity:1" onclick="event.stopPropagation()">
                                    <?php if ($receiptCount > 0): ?>
                                        <button type="button" title="View receipts" onclick="openClaimReceipts(<?= $rid ?>, <?= $receiptCount ?>)"><i class="fas fa-paperclip"></i></button>
                                    <?php endif; ?>
                                    <?php if ($c['status'] === 'Submitted'): ?>
                                        <form action="operation.php?module=my_office&page=expense_claims" method="post" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="approve_claim">
                                            <input type="hidden" name="id" value="<?= $rid ?>">
                                            <button class="btn btn-xs btn-outline-success confirm-submit" title="Approve" data-confirm="Approve claim <?= e($c['claim_no']) ?>? A Pending Payment voucher will be auto-created."><i class="fas fa-check"></i></button>
                                        </form>
                                        <form action="operation.php?module=my_office&page=expense_claims" method="post" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="reject_claim">
                                            <input type="hidden" name="id" value="<?= $rid ?>">
                                            <input type="text" name="reject_reason" class="form-control form-control-sm d-inline" style="max-width:120px" placeholder="Reason" required title="Reject reason">
                                            <button type="submit" class="btn btn-xs btn-outline-danger" title="Reject"><i class="fas fa-times"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$allClaims): ?><tr><td colspan="7" class="text-center text-muted py-4">No claims match the filter.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Slide-in Drawer Backdrop -->
<div class="cms-drawer-backdrop" id="drawerBackdrop" onclick="closeDrawer()"></div>

<!-- Slide-in Drawer -->
<div class="cms-drawer" id="claimDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas fa-file-invoice-dollar"></i><?= $edit ? 'Edit Claim' : 'New Expense Claim' ?></h3>
        <button type="button" class="cms-drawer-close" onclick="closeDrawer()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=my_office&page=expense_claims" method="post" enctype="multipart/form-data" id="claimForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_claim">
            <input type="hidden" name="id" id="claimId" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
            <div class="form-group">
                <label>Category *</label>
                <input type="text" name="category" class="form-control" list="claimCategories" id="claimCategory" required value="<?= $edit ? e($edit['category'] ?? '') : '' ?>" placeholder="e.g. Travel, Stationery">
                <datalist id="claimCategories">
                    <?php foreach ($categories as $cat): ?><option value="<?= e($cat['category']) ?>"><?php endforeach; ?>
                </datalist>
            </div>
            <div class="form-row">
                <div class="form-group col-6">
                    <label>Expense date *</label>
                    <input type="date" name="expense_date" class="form-control" id="claimDate" required value="<?= $edit ? e($edit['expense_date']) : date('Y-m-d') ?>">
                </div>
                <div class="form-group col-6">
                    <label>Amount (NPR) *</label>
                    <input type="number" name="amount" step="0.01" min="0.01" class="form-control" id="claimAmount" required value="<?= $edit ? e(number_format((float) $edit['amount'], 2, '.', '')) : '' ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Description *</label>
                <textarea name="description" class="form-control" id="claimDescription" rows="3" required><?= $edit ? e($edit['description'] ?? '') : '' ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group col-6">
                    <label>Project (optional)</label>
                    <select name="project_id" class="form-control" id="claimProject">
                        <option value="">—</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" <?= $edit && (int) $edit['project_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-6">
                    <label>Client (optional)</label>
                    <select name="client_id" class="form-control" id="claimClient">
                        <option value="">—</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= $edit && (int) $edit['client_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Receipt files <small class="text-muted">(jpg/png/pdf — drag & drop or choose)</small></label>
                <div class="file-upload-widget">
                    <div class="file-upload-preview"></div>
                    <label class="btn btn-outline-primary btn-block mb-0 mt-2" style="cursor:pointer">
                        <i class="fas fa-cloud-upload-alt mr-1"></i>Choose receipt files
                        <input type="file" name="receipt_files[]" class="file-upload-input d-none" multiple <?= $edit ? '' : 'required' ?> accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                    </label>
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
            <small class="d-block text-muted mt-1">Submitting locks the claim for review (AC-FIN-06.2).</small>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="claimForm" class="btn btn-secondary btn-block mb-2">
            <i class="fas fa-save mr-1"></i>Save as Draft
        </button>
        <button type="submit" name="submit_now" value="1" form="claimForm" class="btn btn-primary btn-block">
            <i class="fas fa-paper-plane mr-1"></i>Save & Submit
        </button>
    </div>
</div>

<!-- Receipt Files Modal -->
<div class="modal fade" id="claimReceiptsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-paperclip mr-1"></i><span id="claimReceiptsTitle"></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body" id="claimReceiptsBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
var claimsData = <?= json_encode(array_values($myClaims)) ?>;
var CLAIM_FILES = <?= json_encode($receiptsMap, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG) ?>;

function openDrawer(editId) {
    var drawer = document.getElementById('claimDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.add('open');
    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';

    var title = drawer.querySelector('.cms-drawer-header h3');
    if (editId) {
        var claim = claimsData.find(function(c) { return c.id == editId; });
        if (claim) {
            title.innerHTML = '<i class="fas fa-file-invoice-dollar"></i>Edit Claim';
            document.getElementById('claimId').value = claim.id;
            document.getElementById('claimCategory').value = claim.category || '';
            document.getElementById('claimDate').value = claim.expense_date || '';
            document.getElementById('claimAmount').value = claim.amount || '';
            document.getElementById('claimDescription').value = claim.description || '';
            document.getElementById('claimProject').value = claim.project_id || '';
            document.getElementById('claimClient').value = claim.client_id || '';
        }
    } else {
        title.innerHTML = '<i class="fas fa-file-invoice-dollar"></i>New Expense Claim';
        document.getElementById('claimId').value = '0';
        document.getElementById('claimCategory').value = '';
        document.getElementById('claimDate').value = '<?= date("Y-m-d") ?>';
        document.getElementById('claimAmount').value = '';
        document.getElementById('claimDescription').value = '';
        document.getElementById('claimProject').value = '';
        document.getElementById('claimClient').value = '';
    }
}

function closeDrawer() {
    document.getElementById('claimDrawer').classList.remove('open');
    document.getElementById('drawerBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

function deleteClaim(claimId) {
    if (!confirm('Delete this claim and its receipt files?')) return;
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'operation.php?module=my_office&page=expense_claims';
    form.innerHTML = '<?= csrfField() ?>';
    var act = document.createElement('input');
    act.type = 'hidden'; act.name = 'action'; act.value = 'delete_claim';
    var idEl = document.createElement('input');
    idEl.type = 'hidden'; idEl.name = 'id'; idEl.value = claimId;
    form.appendChild(act);
    form.appendChild(idEl);
    document.body.appendChild(form);
    form.submit();
}

function openClaimReceipts(claimId, fileCount) {
    var files = CLAIM_FILES[claimId] || [];
    document.getElementById('claimReceiptsTitle').textContent = (fileCount === undefined || fileCount === 0)
        ? 'Receipt files'
        : 'Receipt files (' + fileCount + ')';

    var body = document.getElementById('claimReceiptsBody');
    body.innerHTML = '';

    if (!files.length) {
        body.innerHTML = '<div class="text-center text-muted py-4">No receipts attached to this claim.</div>';
        jQuery('#claimReceiptsModal').modal('show');
        return;
    }

    var html = '';
    files.forEach(function (f) {
        html += '<div class="d-flex align-items-center border-bottom py-2 px-1" style="gap:10px">';
        html += '<span class="d-flex align-items-center justify-content-center" style="width:40px;height:40px;border-radius:8px;background:var(--bg-body);border:1px solid var(--border-color);flex-shrink:0">'
             +  '<i class="' + f.icon + '" style="font-size:1.1rem"></i></span>';
        html += '<span class="text-truncate font-weight-medium" style="min-width:0;flex:1" title="' + f.name.replace(/"/g, '&quot;') + '">' + f.name + '</span>';
        html += '<a href="' + f.url + '" target="_blank" rel="noopener" class="btn btn-xs btn-outline-secondary" title="Open in new tab"><i class="fas fa-external-link-alt"></i></a>';
        html += '<a href="' + f.url + '" download="' + f.name.replace(/"/g, '&quot;') + '" class="btn btn-xs btn-outline-success" title="Download"><i class="fas fa-download"></i></a>';
        html += '<button type="button" class="btn btn-xs btn-outline-primary" title="Preview" onclick=\'openFilePreview(' + JSON.stringify(f.url) + ',' + JSON.stringify(f.name) + ')\'><i class="fas fa-eye"></i></button>';
        html += '</div>';
    });
    body.innerHTML = html;
    jQuery('#claimReceiptsModal').modal('show');
}

document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeDrawer(); });

<?php if ($drawerOpen): ?>
document.addEventListener('DOMContentLoaded', function() { openDrawer(<?= (int) $edit['id'] ?>); });
<?php endif; ?>
</script>
