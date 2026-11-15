<?php
/**
 * SB-Tech — Correspondence / Darta (Inward register).
 *
 * Every incoming letter registered with an auto-allocated दर्ता number,
 * tracked to disposal (कारबाही) or filing (तामेली). No delete — Cancelled
 * keeps the number visible. See docs/DARTA_CHALANI_PLAN.md §6.
 */
$db = Database::instance();

include __DIR__ . '/includes/registers.php';

$allStatuses = ['Pending', 'In Progress', 'Disposed', 'Filed', 'Cancelled'];

$filterFy = isset($_GET['fy'])
    ? (int) $_GET['fy']
    : ($correspondenceCurrentFy ? (int) $correspondenceCurrentFy['id'] : 0);
$filterStatus = (string) ($_GET['status'] ?? '');
$filterMonth = (string) ($_GET['month'] ?? '');
$search = trim((string) ($_GET['q'] ?? ''));
$currentPage = max(1, (int) ($_GET['p'] ?? 1));

$where = ['1=1'];
$params = [];

if ($filterFy > 0) {
    $where[] = 'd.`fiscal_year_id` = ?';
    $params[] = $filterFy;
}
if (in_array($filterStatus, $allStatuses, true)) {
    $where[] = 'd.`status` = ?';
    $params[] = $filterStatus;
}
if (preg_match('/^\d{4}-\d{2}$/', $filterMonth)) {
    $where[] = "DATE_FORMAT(d.`letter_date`, '%Y-%m') = ?";
    $params[] = $filterMonth;
}
if ($search !== '') {
    $where[] = '(d.`sender_name` LIKE ? OR d.`subject` LIKE ? OR d.`sender_ref_no` LIKE ?)';
    $like = '%' . $db->escapeLike($search) . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = implode(' AND ', $where);
$total = (int) ($db->selectOne(
    'SELECT COUNT(*) AS c FROM `tbl_darta_entries` d WHERE ' . $whereSql,
    $params
)['c'] ?? 0);
$pp = paginationParams($total, $currentPage);

$dartaRows = $db->select(
    'SELECT d.*, fy.`title` AS fy_title, a.`fullname` AS assigned_name
     FROM `tbl_darta_entries` d
     JOIN `tbl_fiscal_years` fy ON fy.`id` = d.`fiscal_year_id`
     LEFT JOIN `tbl_users_login` a ON a.`id` = d.`assigned_to`
     WHERE ' . $whereSql . '
     ORDER BY d.`letter_date` DESC, d.`id` DESC
     LIMIT ' . $pp['per_page'] . ' OFFSET ' . $pp['offset'],
    $params
);

// Reply chalanis (ref_darta_id) for the visible rows.
$replyMap = [];
if ($dartaRows) {
    $rowIds = array_map('intval', array_column($dartaRows, 'id'));
    $ph = implode(',', array_fill(0, count($rowIds), '?'));
    foreach ($db->select(
        'SELECT c.`ref_darta_id`, c.`chalani_no`, fy.`title` AS fy_title
         FROM `tbl_chalani_entries` c
         JOIN `tbl_fiscal_years` fy ON fy.`id` = c.`fiscal_year_id`
         WHERE c.`ref_darta_id` IN (' . $ph . ')
         ORDER BY c.`chalani_no` ASC',
        $rowIds
    ) as $reply) {
        $replyMap[(int) $reply['ref_darta_id']][] = correspondence_display_no(
            'chalani',
            (int) $reply['chalani_no'],
            (string) $reply['fy_title']
        );
    }
}

// Edit deep-link (also reopens after a validation error redirect).
$editDarta = null;
if (isset($_GET['edit_id'])) {
    $editDarta = $db->selectOne(
        'SELECT d.*, fy.`title` AS fy_title
         FROM `tbl_darta_entries` d
         JOIN `tbl_fiscal_years` fy ON fy.`id` = d.`fiscal_year_id`
         WHERE d.`id` = ?',
        [(int) $_GET['edit_id']]
    );
}
$drawerOpen = ($editDarta !== null) || isset($_GET['new']);

// Month filter options (letters of the selected FY).
$monthOptions = [];
$ymRows = $db->select(
    "SELECT DISTINCT DATE_FORMAT(d.`letter_date`, '%Y-%m') AS ym
     FROM `tbl_darta_entries` d" . ($filterFy > 0 ? ' WHERE d.`fiscal_year_id` = ?' : '') . ' ORDER BY ym DESC',
    $filterFy > 0 ? [$filterFy] : []
);
foreach ($ymRows as $ymRow) {
    $ym = (string) $ymRow['ym'];
    if ($ym === '') {
        continue;
    }
    $label = $ym;
    if (useBsDates()) {
        $bs = adToBs($ym . '-01');
        if ($bs !== null) {
            $label = substr($bs, 0, 7);
        }
    }
    $monthOptions[$ym] = $label;
}

$displayNo = static fn (array $row): string => correspondence_display_no(
    'darta',
    (int) $row['darta_no'],
    (string) $row['fy_title']
);

// Value a date field shows: B.S. when the office runs on B.S. dates (the
// write path converts back with normalizeDateInput()), A.D. otherwise.
$inputDate = static fn (string $adDate): string => $adDate === ''
    ? ''
    : (useBsDates() ? (adToBs($adDate) ?? $adDate) : $adDate);
$todayInput = $inputDate(date('Y-m-d'));
$calLabel = date_system_label();

// New entries may only pick an Open fiscal year (existing ones keep theirs).
$fyChoices = $editDarta ? $correspondenceFiscalYears : ($correspondenceOpenFy ?: $correspondenceFiscalYears);
$defaultNewFyId = ($correspondenceCurrentFy && $correspondenceCurrentFy['closing'] === 'Open')
    ? (int) $correspondenceCurrentFy['id']
    : (int) ($fyChoices[0]['id'] ?? 0);

$queryBase = 'module=correspondence&page=darta'
    . '&fy=' . $filterFy
    . '&status=' . urlencode($filterStatus)
    . '&month=' . urlencode($filterMonth)
    . '&q=' . urlencode($search);

// Attachments of the visible rows (P2) + of the server-rendered edit entry.
$corrFileShape = static fn (array $file): array => [
    'id'   => (int) $file['id'],
    'name' => (string) $file['file_name'],
    'url'  => assetUrl('user_uploads/' . (string) $file['file_location']),
];
$fileMap = [];
if ($dartaRows) {
    $rowIds = array_map('intval', array_column($dartaRows, 'id'));
    $ph = implode(',', array_fill(0, count($rowIds), '?'));
    foreach ($db->select(
        'SELECT `id`, `entry_id`, `file_name`, `file_location`
         FROM `tbl_correspondence_files` WHERE `register` = \'darta\' AND `entry_id` IN (' . $ph . ')
         ORDER BY `id` ASC',
        $rowIds
    ) as $file) {
        $fileMap[(int) $file['entry_id']][] = $corrFileShape($file);
    }
}
$editFiles = ($editDarta !== null)
    ? array_map($corrFileShape, $db->select(
        'SELECT `id`, `entry_id`, `file_name`, `file_location`
         FROM `tbl_correspondence_files` WHERE `register` = \'darta\' AND `entry_id` = ? ORDER BY `id` ASC',
        [(int) $editDarta['id']]
    ))
    : [];

// Printable/exportable output (P2 + PDF/Word follow-up): ?print=1 = landscape
// ledger of the filtered rows, ?entry=ID&print=1 = single-entry slip, and the
// same URLs with ?pdf=1 / ?word=1 stream a PDF or .docx. The include renders
// and exits.
$printRegister = 'darta';
$printRows = $dartaRows;
$printFyLabel = 'All fiscal years';
foreach ($correspondenceFiscalYears as $fyOption) {
    if ($filterFy === (int) $fyOption['id']) {
        $printFyLabel = (string) $fyOption['title'];
        break;
    }
}
if (!empty($_GET['print']) || !empty($_GET['pdf']) || !empty($_GET['word'])) {
    include __DIR__ . '/includes/register_print.php'; // exits
}

$jsRows = [];
foreach ($dartaRows as $r) {
    $jsRows[] = [
        'id'                => (int) $r['id'],
        'fiscal_year_id'    => (int) $r['fiscal_year_id'],
        'darta_no'          => (int) $r['darta_no'],
        'display_no'        => $displayNo($r),
        'sender_ref_no'     => (string) ($r['sender_ref_no'] ?? ''),
        'sender_chalani_no' => (string) ($r['sender_chalani_no'] ?? ''),
        'letter_date'       => $inputDate((string) $r['letter_date']),
        'sender_type'       => (string) $r['sender_type'],
        'sender_name'       => (string) $r['sender_name'],
        'sender_address'    => (string) ($r['sender_address'] ?? ''),
        'received_via'      => (string) $r['received_via'],
        'subject'           => (string) $r['subject'],
        'summary'           => (string) ($r['summary'] ?? ''),
        'priority'          => (string) $r['priority'],
        'assigned_to'       => $r['assigned_to'] !== null ? (int) $r['assigned_to'] : '',
        'status'            => (string) $r['status'],
        'files'             => $fileMap[(int) $r['id']] ?? [],
    ];
}
?>

<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-inbox mr-1"></i>Darta Register — Inward (<?= number_format($total) ?>)</h3>
        <div class="card-tools">
            <a href="<?= e(pageUrl('correspondence', 'darta') . '&fy=' . $filterFy . '&status=' . urlencode($filterStatus) . '&month=' . urlencode($filterMonth) . '&q=' . urlencode($search) . '&print=1') ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Print the filtered register (A4 landscape)"><i class="fas fa-print mr-1"></i>Print</a>
            <a href="<?= e(pageUrl('correspondence', 'darta') . '&fy=' . $filterFy . '&status=' . urlencode($filterStatus) . '&month=' . urlencode($filterMonth) . '&q=' . urlencode($search) . '&pdf=1') ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-danger" title="Download the filtered register as PDF"><i class="fas fa-file-pdf mr-1"></i>PDF</a>
            <a href="<?= e(pageUrl('correspondence', 'darta') . '&fy=' . $filterFy . '&status=' . urlencode($filterStatus) . '&month=' . urlencode($filterMonth) . '&q=' . urlencode($search) . '&word=1') ?>" class="btn btn-sm btn-outline-success" title="Download the filtered register as Word (.docx)"><i class="fas fa-file-word mr-1"></i>Word</a>
            <button type="button" class="btn btn-primary btn-sm" onclick="openDrawer()" <?= $correspondenceCanRegister ? '' : 'disabled' ?>>
                <i class="fas fa-plus mr-1"></i>Add Darta
            </button>
        </div>
    </div>
    <div class="card-body">
        <?php if (!$correspondenceCanRegister): ?>
        <div class="callout callout-warning mb-3">
            <h5><i class="fas fa-exclamation-triangle mr-1"></i><?= !$correspondenceCurrentFy ? 'No fiscal year covers today' : 'No open fiscal year' ?></h5>
            <p class="mb-0">New darta numbers cannot be allocated<?= !$correspondenceCurrentFy ? '' : ' into a closed fiscal year' ?>. Create/open the current fiscal year under
                <a href="<?= e(pageUrl('accounts', 'fiscal_years')) ?>">Setup → Fiscal Years</a>.</p>
        </div>
        <?php endif; ?>
        <form class="form-inline mb-0" method="get">
            <input type="hidden" name="module" value="correspondence">
            <input type="hidden" name="page" value="darta">
            <select name="fy" class="form-control form-control-sm mr-2 mb-2">
                <option value="">All fiscal years</option>
                <?php foreach ($correspondenceFiscalYears as $fyOption): ?>
                <option value="<?= (int) $fyOption['id'] ?>" <?= $filterFy === (int) $fyOption['id'] ? 'selected' : '' ?>>
                    <?= e($fyOption['title']) ?><?= $fyOption['closing'] === 'Closed' ? ' (Closed)' : '' ?>
                </option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="form-control form-control-sm mr-2 mb-2">
                <option value="">All statuses</option>
                <?php foreach ($allStatuses as $statusOption): ?>
                <option value="<?= e($statusOption) ?>" <?= $filterStatus === $statusOption ? 'selected' : '' ?>><?= e($statusOption) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="month" class="form-control form-control-sm mr-2 mb-2">
                <option value="">All months</option>
                <?php foreach ($monthOptions as $ym => $ymLabel): ?>
                <option value="<?= e($ym) ?>" <?= $filterMonth === $ym ? 'selected' : '' ?>><?= e($ymLabel) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="q" class="form-control form-control-sm mr-2 mb-2" placeholder="Sender / subject / पत्र संख्या…" value="<?= e($search) ?>">
            <button class="btn btn-sm btn-primary mb-2"><i class="fas fa-search"></i> Filter</button>
            <a href="<?= e(pageUrl('correspondence', 'darta')) ?>" class="btn btn-sm btn-outline-secondary mb-2 ml-1">Reset</a>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>दर्ता नं. / Darta No.</th>
                        <th>पत्र संख्या / Sender's Ref</th>
                        <th>मिति / Letter Date</th>
                        <th>प्रेषक / Sender</th>
                        <th>विषय / Subject</th>
                        <th>Status</th>
                        <th>Assigned To</th>
                        <th>Replies</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$dartaRows): ?>
                    <tr><td colspan="9" class="text-center text-muted">No darta entries found.</td></tr>
                <?php else: foreach ($dartaRows as $row): ?>
                    <tr<?= $row['status'] === 'Cancelled' ? ' class="text-muted"' : '' ?>>
                        <td>
                            <strong><?= e($displayNo($row)) ?></strong>
                            <?php if ($row['priority'] === 'Urgent'): ?><span class="badge badge-danger">Urgent</span><?php endif; ?>
                        </td>
                        <td class="small"><?= e($row['sender_ref_no'] ?? '—') ?><?= $row['sender_chalani_no'] ? '<br><span class="text-muted">चलानी: ' . e($row['sender_chalani_no']) . '</span>' : '' ?></td>
                        <td class="small"><?= e(formatDateView($row['letter_date'])) ?></td>
                        <td><?= e($row['sender_name']) ?><br><span class="small text-muted"><?= e($row['sender_type']) ?><?= $row['sender_address'] ? ' · ' . e($row['sender_address']) : '' ?></span></td>
                        <td style="max-width:260px"><?= e(mb_strimwidth((string) $row['subject'], 0, 80, '…')) ?></td>
                        <td><span class="badge badge-<?= $correspondenceStatusBadges['darta'][$row['status']] ?? 'secondary' ?>"><?= e($row['status']) ?></span></td>
                        <td class="small"><?= e($row['assigned_name'] ?? '—') ?></td>
                        <td class="small">
                            <?php if (!empty($replyMap[(int) $row['id']])): ?>
                                <?= e(implode(', ', $replyMap[(int) $row['id']])) ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="text-right text-nowrap">
                            <a href="<?= e(pageUrl('correspondence', 'darta') . '&entry=' . (int) $row['id'] . '&print=1') ?>" target="_blank" rel="noopener" class="btn btn-xs btn-outline-secondary" title="Print slip"><i class="fas fa-print"></i></a>
                            <a href="<?= e(pageUrl('correspondence', 'darta') . '&entry=' . (int) $row['id'] . '&pdf=1') ?>" target="_blank" rel="noopener" class="btn btn-xs btn-outline-danger" title="Download slip as PDF"><i class="fas fa-file-pdf"></i></a>
                            <a href="<?= e(pageUrl('correspondence', 'darta') . '&entry=' . (int) $row['id'] . '&word=1') ?>" class="btn btn-xs btn-outline-success" title="Download slip as Word (.docx)"><i class="fas fa-file-word"></i></a>
                            <button type="button" class="btn btn-xs btn-outline-primary" title="<?= in_array($row['status'], ['Pending', 'In Progress'], true) ? 'View / Edit' : 'View (closed entry)' ?>" onclick="openDrawer(<?= (int) $row['id'] ?>)"><i class="fas fa-edit"></i></button>
                            <?php if (in_array($row['status'], ['Pending', 'In Progress'], true)): ?>
                            <button type="button" class="btn btn-xs btn-outline-success" title="Dispose / File" onclick="openDispose(<?= (int) $row['id'] ?>, <?= e(json_encode($displayNo($row), JSON_HEX_TAG)) ?>)"><i class="fas fa-check-double"></i></button>
                            <form action="operation.php?module=correspondence&page=darta" method="post" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Cancel darta <?= e($displayNo($row)) ?>? The number stays visible in the register." title="Cancel"><i class="fas fa-ban"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($pp['pages'] > 1): ?>
    <div class="card-footer">
        <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($i = max(1, $pp['page'] - 3); $i <= min($pp['pages'], $pp['page'] + 3); $i++): ?>
                <li class="page-item<?= $i === $pp['page'] ? ' active' : '' ?>">
                    <a class="page-link" href="?<?= e($queryBase) ?>&p=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<!-- Slide-in Drawer Backdrop -->
<div class="cms-drawer-backdrop" id="drawerBackdrop" onclick="closeDrawer(); closeDispose();"></div>

<!-- Darta entry drawer (add / edit) -->
<div class="cms-drawer" id="formDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas fa-inbox"></i><span id="drawerTitle"><?= $editDarta ? 'Edit Darta' : 'Add Darta' ?></span></h3>
        <button type="button" class="cms-drawer-close" onclick="closeDrawer()" aria-label="Close"><i class="fas fa-times"></i></button>
    </div>
    <div class="cms-drawer-body">
        <div class="alert alert-secondary py-2 mb-3" id="readonlyBanner" style="display:none">
            <i class="fas fa-lock mr-1"></i>This darta is <strong id="readonlyStatus">closed</strong> — closed entries can be viewed but not edited.
        </div>
        <form action="operation.php?module=correspondence&page=darta" method="post" id="dartaForm" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="formId" value="<?= $editDarta ? (int) $editDarta['id'] : 0 ?>">

            <div class="form-group" id="dartaNoWrap" <?= $editDarta ? '' : 'style="display:none"' ?>>
                <label>Darta No.</label>
                <div class="form-control" id="formDartaNo"><?= $editDarta ? e($displayNo($editDarta)) : '' ?></div>
            </div>

            <div class="form-group">
                <label>Fiscal Year *<?= $editDarta ? '' : ' <span class="text-muted">(open years only)</span>' ?></label>
                <select name="fiscal_year_id" class="form-control" id="formFy" required <?= $editDarta ? 'disabled' : '' ?>>
                    <?php foreach ($fyChoices as $fyOption): ?>
                    <option value="<?= (int) $fyOption['id'] ?>"
                        <?= (int) ($editDarta ? $editDarta['fiscal_year_id'] : old('fiscal_year_id', $defaultNewFyId)) === (int) $fyOption['id'] ? 'selected' : '' ?>>
                        <?= e($fyOption['title']) ?><?= $fyOption['closing'] === 'Closed' ? ' (Closed)' : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($editDarta): ?><input type="hidden" id="formFyLocked" name="fiscal_year_id" value="<?= (int) $editDarta['fiscal_year_id'] ?>"><?php endif; ?>
            </div>
            <div class="form-row">
                <div class="form-group col-6">
                    <label>पत्र संख्या (Sender's Ref No.)</label>
                    <input type="text" name="sender_ref_no" class="form-control" id="formRefNo" maxlength="100" value="<?= e(old('sender_ref_no', $editDarta['sender_ref_no'] ?? '')) ?>">
                </div>
                <div class="form-group col-6">
                    <label>चलानी नं. (Sender's)</label>
                    <input type="text" name="sender_chalani_no" class="form-control" id="formSenderChalaniNo" maxlength="100" value="<?= e(old('sender_chalani_no', $editDarta['sender_chalani_no'] ?? '')) ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Letter Date * <span class="text-muted"><?= e($calLabel) ?></span></label>
                <input type="<?= useBsDates() ? 'text' : 'date' ?>" name="letter_date" class="form-control<?= useBsDates() ? ' datepicker' : '' ?>" id="formLetterDate" required
                    <?= useBsDates() ? 'data-datepicker-placeholder-off placeholder="YYYY-MM-DD (BS)"' : '' ?>
                    value="<?= e(old('letter_date', $editDarta ? $inputDate((string) $editDarta['letter_date']) : $todayInput)) ?>">
            </div>
            <div class="form-row">
                <div class="form-group col-6">
                    <label>Sender Type</label>
                    <select name="sender_type" class="form-control" id="formSenderType">
                        <?php foreach (['Office', 'Person', 'Other'] as $t): ?>
                        <option value="<?= e($t) ?>" <?= old('sender_type', $editDarta['sender_type'] ?? 'Person') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-6">
                    <label>Received Via</label>
                    <select name="received_via" class="form-control" id="formReceivedVia">
                        <?php foreach (['Hand', 'Post', 'Email', 'Courier', 'Other'] as $v): ?>
                        <option value="<?= e($v) ?>" <?= old('received_via', $editDarta['received_via'] ?? 'Hand') === $v ? 'selected' : '' ?>><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>प्रेषक / Sender Name *</label>
                <input type="text" name="sender_name" class="form-control" id="formSenderName" maxlength="255" required value="<?= e(old('sender_name', $editDarta['sender_name'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label>Sender Address</label>
                <input type="text" name="sender_address" class="form-control" id="formSenderAddress" maxlength="255" value="<?= e(old('sender_address', $editDarta['sender_address'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label>विषय / Subject *</label>
                <input type="text" name="subject" class="form-control" id="formSubject" maxlength="500" required value="<?= e(old('subject', $editDarta['subject'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label>Summary / Gist</label>
                <textarea name="summary" class="form-control" id="formSummary" rows="3"><?= e(old('summary', $editDarta['summary'] ?? '')) ?></textarea>
            </div>
            <div class="form-group">
                <label>Attachments <span class="text-muted">(PDF / JPG / PNG, max 10 MB each)</span></label>
                <input type="file" name="files[]" id="formFiles" class="form-control-file" multiple accept=".pdf,.jpg,.jpeg,.png">
                <div id="filesList" class="mt-2"></div>
            </div>
            <div class="form-row">
                <div class="form-group col-6">
                    <label>Priority</label>
                    <select name="priority" class="form-control" id="formPriority">
                        <option value="Normal" <?= old('priority', $editDarta['priority'] ?? 'Normal') === 'Normal' ? 'selected' : '' ?>>Normal</option>
                        <option value="Urgent" <?= old('priority', $editDarta['priority'] ?? '') === 'Urgent' ? 'selected' : '' ?>>Urgent</option>
                    </select>
                </div>
                <div class="form-group col-6">
                    <label>Assigned To</label>
                    <select name="assigned_to" class="form-control" id="formAssigned">
                        <option value="0">— Unassigned —</option>
                        <?php foreach ($correspondenceStaff as $staffMember): ?>
                        <option value="<?= (int) $staffMember['id'] ?>" <?= (string) old('assigned_to', $editDarta['assigned_to'] ?? '') === (string) $staffMember['id'] ? 'selected' : '' ?>>
                            <?= e($staffMember['fullname']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="dartaForm" class="btn btn-primary btn-block">
            <i class="fas fa-save mr-1"></i><?= $editDarta ? 'Update Darta' : 'Register Darta' ?>
        </button>
    </div>
</div>

<!-- Dispose / File drawer -->
<div class="cms-drawer" id="disposeDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas fa-check-double"></i>Dispose / File Darta</h3>
        <button type="button" class="cms-drawer-close" onclick="closeDispose()" aria-label="Close"><i class="fas fa-times"></i></button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=correspondence&page=darta" method="post" id="disposeForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="dispose">
            <input type="hidden" name="id" id="disposeId" value="0">
            <div class="form-group">
                <label>Darta</label>
                <div class="form-control" id="disposeNo">&nbsp;</div>
            </div>
            <div class="form-group">
                <label>कारबाही / Outcome *</label>
                <select name="outcome" class="form-control" required>
                    <option value="Disposed">Disposed — action / reply done</option>
                    <option value="Filed">Filed (तामेली) — closed, no action needed</option>
                </select>
            </div>
            <div class="form-group">
                <label>Disposal Note</label>
                <textarea name="disposal_note" class="form-control" rows="4" placeholder="What was done, or why filed…"></textarea>
            </div>
            <div class="custom-control custom-switch">
                <input type="checkbox" name="reserve_reply" value="1" class="custom-control-input" id="reserveReply">
                <label class="custom-control-label" for="reserveReply">Reserve reply चलानी number now (opens the chalani form pre-linked)</label>
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="disposeForm" class="btn btn-success btn-block">
            <i class="fas fa-check mr-1"></i>Save Disposal
        </button>
    </div>
</div>

<!-- Attachment delete (standalone PRG post from the drawer file list) -->
<form action="operation.php?module=correspondence&page=darta" method="post" id="deleteFileForm" class="d-none">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="delete_file">
    <input type="hidden" name="file_id" value="0">
</form>

<script>
var dartaRowsData = <?= json_encode($jsRows, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
// Status of the server-rendered entry (deep link / validation-error reopen),
// used when the row is not in the current table page.
var serverEditStatus = <?= json_encode((string) ($editDarta['status'] ?? '')) ?>;
var serverEditFiles = <?= json_encode($editFiles, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
var editableStatuses = ['Pending', 'In Progress'];

function fillDartaForm(row) {
    document.getElementById('formId').value = row.id;
    var fySelect = document.getElementById('formFy');
    if (fySelect) { fySelect.value = row.fiscal_year_id; }
    document.getElementById('formRefNo').value = row.sender_ref_no;
    document.getElementById('formSenderChalaniNo').value = row.sender_chalani_no;
    document.getElementById('formLetterDate').value = row.letter_date;
    document.getElementById('formSenderType').value = row.sender_type;
    document.getElementById('formReceivedVia').value = row.received_via;
    document.getElementById('formSenderName').value = row.sender_name;
    document.getElementById('formSenderAddress').value = row.sender_address;
    document.getElementById('formSubject').value = row.subject;
    document.getElementById('formSummary').value = row.summary;
    document.getElementById('formPriority').value = row.priority;
    document.getElementById('formAssigned').value = row.assigned_to || '0';
}

function resetDartaForm() {
    document.getElementById('formId').value = '0';
    var fySelect = document.getElementById('formFy');
    if (fySelect && fySelect.options.length && <?= json_encode($defaultNewFyId) ?>) {
        fySelect.value = <?= json_encode($defaultNewFyId) ?>;
    }
    document.getElementById('formRefNo').value = '';
    document.getElementById('formSenderChalaniNo').value = '';
    document.getElementById('formLetterDate').value = <?= json_encode($todayInput) ?>;
    document.getElementById('formSenderType').value = 'Person';
    document.getElementById('formReceivedVia').value = 'Hand';
    document.getElementById('formSenderName').value = '';
    document.getElementById('formSenderAddress').value = '';
    document.getElementById('formSubject').value = '';
    document.getElementById('formSummary').value = '';
    document.getElementById('formPriority').value = 'Normal';
    document.getElementById('formAssigned').value = '0';
    var fileInput = document.getElementById('formFiles');
    if (fileInput) { fileInput.value = ''; }
    renderFiles([], false);
}

/** P2 attachments: list entries with a download link (+ remove when editable). */
function renderFiles(files, readonly) {
    var list = document.getElementById('filesList');
    if (!list) { return; }
    list.innerHTML = '';
    if (!files || !files.length) { return; }
    for (var i = 0; i < files.length; i++) {
        var f = files[i];
        var row = document.createElement('div');
        row.className = 'd-flex align-items-center mb-1';
        var link = document.createElement('a');
        link.href = f.url;
        link.target = '_blank';
        link.rel = 'noopener';
        link.className = 'small text-truncate mr-2';
        link.style.maxWidth = '220px';
        link.title = f.name;
        link.textContent = f.name;
        row.appendChild(link);
        if (!readonly) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-xs btn-outline-danger ml-auto';
            btn.title = 'Remove attachment';
            btn.innerHTML = '<i class="fas fa-times"></i>';
            btn.setAttribute('onclick', 'deleteFile(' + f.id + ')');
            row.appendChild(btn);
        }
        list.appendChild(row);
    }
}

function deleteFile(fileId) {
    if (!confirm('Remove this attachment?')) { return; }
    var form = document.getElementById('deleteFileForm');
    form.querySelector('[name=file_id]').value = fileId;
    form.submit();
}

function setDartaNoDisplay(text) {
    var wrap = document.getElementById('dartaNoWrap');
    if (text) {
        document.getElementById('formDartaNo').textContent = text;
        wrap.style.display = '';
    } else {
        wrap.style.display = 'none';
    }
}

/** Closed entries are view-only: disable every field and hide the save bar. */
function applyFormMode(status) {
    var form = document.getElementById('dartaForm');
    var readonly = !!status && editableStatuses.indexOf(status) === -1;
    var fields = form.querySelectorAll('input, select, textarea');
    for (var i = 0; i < fields.length; i++) { fields[i].disabled = readonly; }
    document.getElementById('readonlyBanner').style.display = readonly ? '' : 'none';
    document.querySelector('#formDrawer .cms-drawer-footer').style.display = readonly ? 'none' : '';
    if (readonly) { document.getElementById('readonlyStatus').textContent = status; }
    return readonly;
}

/** Edit mode posts the fiscal year from the locked hidden field instead of
 *  the disabled select; add mode posts the (enabled) select. */
function lockFiscalYear(locked) {
    var sel = document.getElementById('formFy');
    var hidden = document.getElementById('formFyLocked');
    if (sel) { sel.disabled = !!locked; }
    if (hidden) { hidden.disabled = !locked; }
}

function openDrawer(editId, keepServerValues) {
    editId = editId || 0;
    var title = document.querySelector('#drawerTitle');
    var row = null;
    for (var i = 0; i < dartaRowsData.length; i++) {
        if (dartaRowsData[i].id === editId) { row = dartaRowsData[i]; break; }
    }
    var status = row ? row.status : serverEditStatus;
    if (editId) {
        title.textContent = 'Edit Darta';
        // keepServerValues: the drawer was rendered server-side for this row
        // (deep link or validation-error reopen), so its old() values are the
        // current ones — refilling from the table snapshot would discard the
        // input the user just corrected.
        if (row && !keepServerValues) {
            fillDartaForm(row);
        }
        setDartaNoDisplay(row ? row.display_no : document.getElementById('formDartaNo').textContent);
    } else {
        title.textContent = 'Add Darta';
        if (!keepServerValues) {
            resetDartaForm();
            setDartaNoDisplay('');
        }
        status = '';
    }
    var readonly = applyFormMode(status);
    if (!readonly) { lockFiscalYear(!!editId); }
    var files = [];
    if (editId) {
        files = (row && !keepServerValues) ? row.files : serverEditFiles;
    }
    renderFiles(files, readonly);
    document.getElementById('formDrawer').classList.add('open');
    document.getElementById('drawerBackdrop').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDrawer() {
    document.getElementById('formDrawer').classList.remove('open');
    if (!document.getElementById('disposeDrawer').classList.contains('open')) {
        document.getElementById('drawerBackdrop').classList.remove('active');
        document.body.style.overflow = '';
    }
}

function openDispose(id, displayNo) {
    document.getElementById('disposeId').value = id;
    document.getElementById('disposeNo').textContent = displayNo;
    document.getElementById('disposeDrawer').classList.add('open');
    document.getElementById('drawerBackdrop').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDispose() {
    document.getElementById('disposeDrawer').classList.remove('open');
    if (!document.getElementById('formDrawer').classList.contains('open')) {
        document.getElementById('drawerBackdrop').classList.remove('active');
        document.body.style.overflow = '';
    }
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeDrawer(); closeDispose(); }
});

<?php if ($drawerOpen): ?>
document.addEventListener('DOMContentLoaded', function () {
    openDrawer(<?= (int) ($editDarta['id'] ?? 0) ?>, true);
});
<?php endif; ?>
</script>
