<?php
/**
 * SB-Tech — Correspondence / Chalani (Outward register).
 *
 * Number-first workflow (D7): save as Reserved → number is on the letter →
 * print/send → Mark dispatched. No delete — Cancelled keeps the number
 * visible. See docs/DARTA_CHALANI_PLAN.md §6.
 */
$db = Database::instance();

include __DIR__ . '/includes/registers.php';

$allStatuses = ['Reserved', 'Dispatched', 'Cancelled'];

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
    $where[] = 'c.`fiscal_year_id` = ?';
    $params[] = $filterFy;
}
if (in_array($filterStatus, $allStatuses, true)) {
    $where[] = 'c.`status` = ?';
    $params[] = $filterStatus;
}
if (preg_match('/^\d{4}-\d{2}$/', $filterMonth)) {
    $where[] = "DATE_FORMAT(c.`letter_date`, '%Y-%m') = ?";
    $params[] = $filterMonth;
}
if ($search !== '') {
    $where[] = '(c.`recipient_name` LIKE ? OR c.`subject` LIKE ?)';
    $like = '%' . $db->escapeLike($search) . '%';
    $params[] = $like;
    $params[] = $like;
}

$whereSql = implode(' AND ', $where);
$total = (int) ($db->selectOne(
    'SELECT COUNT(*) AS c FROM `tbl_chalani_entries` c WHERE ' . $whereSql,
    $params
)['c'] ?? 0);
$pp = paginationParams($total, $currentPage);

$chalaniRows = $db->select(
    'SELECT c.*, fy.`title` AS fy_title, d.`darta_no` AS ref_darta_no, d.`sender_name` AS ref_darta_sender,
            d.`subject` AS ref_darta_subject, d.`status` AS ref_darta_status,
            dfy.`title` AS ref_darta_fy_title, s.`fullname` AS sent_by_name
     FROM `tbl_chalani_entries` c
     JOIN `tbl_fiscal_years` fy ON fy.`id` = c.`fiscal_year_id`
     LEFT JOIN `tbl_darta_entries` d ON d.`id` = c.`ref_darta_id`
     LEFT JOIN `tbl_fiscal_years` dfy ON dfy.`id` = d.`fiscal_year_id`
     LEFT JOIN `tbl_users_login` s ON s.`id` = c.`sent_by`
     WHERE ' . $whereSql . '
     ORDER BY c.`letter_date` DESC, c.`id` DESC
     LIMIT ' . $pp['per_page'] . ' OFFSET ' . $pp['offset'],
    $params
);

// Edit deep-link (also reopens after a validation error redirect).
$editChalani = null;
if (isset($_GET['edit_id'])) {
    $editChalani = $db->selectOne(
        'SELECT c.*, fy.`title` AS fy_title,
                d.`darta_no` AS ref_darta_no, dfy.`title` AS ref_darta_fy_title
         FROM `tbl_chalani_entries` c
         JOIN `tbl_fiscal_years` fy ON fy.`id` = c.`fiscal_year_id`
         LEFT JOIN `tbl_darta_entries` d ON d.`id` = c.`ref_darta_id`
         LEFT JOIN `tbl_fiscal_years` dfy ON dfy.`id` = d.`fiscal_year_id`
         WHERE c.`id` = ?',
        [(int) $_GET['edit_id']]
    );
}
// ?ref_darta=ID opens a NEW chalani pre-linked to that darta (dispose flow);
// an existing entry carries its own link. Both must survive the select even
// when the darta is already closed: dispose → "reserve reply चलानी number
// now" links the darta that was *just* marked Disposed (§6).
$prefillDarta = $editChalani
    ? (int) ($editChalani['ref_darta_id'] ?? 0)
    : (int) ($_GET['ref_darta'] ?? 0);
$drawerOpen = ($editChalani !== null) || isset($_GET['new']) || $prefillDarta > 0;

// Link-to-darta options: Pending / In Progress darta of the current FY (D8),
// plus the pre-filled link (closed or from another FY) so it stays selectable.
$dartaWhere = "d.`status` IN ('Pending','In Progress')";
$dartaParams = [];
if ($correspondenceCurrentFy) {
    $dartaWhere .= ' AND d.`fiscal_year_id` = ?';
    $dartaParams[] = (int) $correspondenceCurrentFy['id'];
}
if ($prefillDarta > 0) {
    // Mirrors the save check in operation/chalani.php: a cancelled darta can
    // never be linked, so it must not stay selectable either.
    $dartaWhere = '(' . $dartaWhere . ") OR (d.`id` = ? AND d.`status` <> 'Cancelled')";
    $dartaParams[] = $prefillDarta;
}
$dartaOptions = $db->select(
    "SELECT d.`id`, d.`darta_no`, d.`sender_name`, d.`subject`, d.`status`, fy.`title` AS fy_title
     FROM `tbl_darta_entries` d
     JOIN `tbl_fiscal_years` fy ON fy.`id` = d.`fiscal_year_id`
     WHERE $dartaWhere
     ORDER BY d.`darta_no` DESC
     LIMIT 300",
    $dartaParams
);

// Month filter options (letters of the selected FY).
$monthOptions = [];
$ymRows = $db->select(
    "SELECT DISTINCT DATE_FORMAT(c.`letter_date`, '%Y-%m') AS ym
     FROM `tbl_chalani_entries` c" . ($filterFy > 0 ? ' WHERE c.`fiscal_year_id` = ?' : '') . ' ORDER BY ym DESC',
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
    'chalani',
    (int) $row['chalani_no'],
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
$fyChoices = $editChalani ? $correspondenceFiscalYears : ($correspondenceOpenFy ?: $correspondenceFiscalYears);
$defaultNewFyId = ($correspondenceCurrentFy && $correspondenceCurrentFy['closing'] === 'Open')
    ? (int) $correspondenceCurrentFy['id']
    : (int) ($fyChoices[0]['id'] ?? 0);

$queryBase = 'module=correspondence&page=chalani'
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
if ($chalaniRows) {
    $rowIds = array_map('intval', array_column($chalaniRows, 'id'));
    $ph = implode(',', array_fill(0, count($rowIds), '?'));
    foreach ($db->select(
        'SELECT `id`, `entry_id`, `file_name`, `file_location`
         FROM `tbl_correspondence_files` WHERE `register` = \'chalani\' AND `entry_id` IN (' . $ph . ')
         ORDER BY `id` ASC',
        $rowIds
    ) as $file) {
        $fileMap[(int) $file['entry_id']][] = $corrFileShape($file);
    }
}
$editFiles = ($editChalani !== null)
    ? array_map($corrFileShape, $db->select(
        'SELECT `id`, `entry_id`, `file_name`, `file_location`
         FROM `tbl_correspondence_files` WHERE `register` = \'chalani\' AND `entry_id` = ? ORDER BY `id` ASC',
        [(int) $editChalani['id']]
    ))
    : [];

// Printable/exportable output (P2 + PDF/Word follow-up): ?print=1 = landscape
// ledger of the filtered rows, ?entry=ID&print=1 = single-entry slip, and the
// same URLs with ?pdf=1 / ?word=1 stream a PDF or .docx. The include renders
// and exits.
$printRegister = 'chalani';
$printRows = $chalaniRows;
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
foreach ($chalaniRows as $r) {
    $jsRows[] = [
        'id'               => (int) $r['id'],
        'fiscal_year_id'   => (int) $r['fiscal_year_id'],
        'chalani_no'       => (int) $r['chalani_no'],
        'display_no'       => $displayNo($r),
        'letter_date'      => $inputDate((string) $r['letter_date']),
        'recipient_type'   => (string) $r['recipient_type'],
        'recipient_name'   => (string) $r['recipient_name'],
        'recipient_address'=> (string) ($r['recipient_address'] ?? ''),
        'cc_recipients'    => (string) ($r['cc_recipients'] ?? ''),
        'dispatch_mode'    => (string) $r['dispatch_mode'],
        'subject'          => (string) $r['subject'],
        // Sanitized: loaded into the Summernote editor as live HTML.
        'description'      => sanitizeRichHtml((string) ($r['description'] ?? '')),
        'ref_darta_id'     => $r['ref_darta_id'] !== null ? (int) $r['ref_darta_id'] : '',
        'ref_darta_label'  => ($r['ref_darta_id'] !== null && $r['ref_darta_no'] !== null)
            ? correspondence_display_no('darta', (int) $r['ref_darta_no'], (string) $r['ref_darta_fy_title'])
            : '',
        'ref_darta_status' => (string) ($r['ref_darta_status'] ?? ''),
        'status'           => (string) $r['status'],
        'files'            => $fileMap[(int) $r['id']] ?? [],
    ];
}
?>

<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-share-square mr-1"></i>Chalani Register — Outward (<?= number_format($total) ?>)</h3>
        <div class="card-tools">
            <a href="<?= e(pageUrl('correspondence', 'chalani') . '&fy=' . $filterFy . '&status=' . urlencode($filterStatus) . '&month=' . urlencode($filterMonth) . '&q=' . urlencode($search) . '&print=1') ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Print the filtered register (A4 landscape)"><i class="fas fa-print mr-1"></i>Print</a>
            <a href="<?= e(pageUrl('correspondence', 'chalani') . '&fy=' . $filterFy . '&status=' . urlencode($filterStatus) . '&month=' . urlencode($filterMonth) . '&q=' . urlencode($search) . '&pdf=1') ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-danger" title="Download the filtered register as PDF"><i class="fas fa-file-pdf mr-1"></i>PDF</a>
            <a href="<?= e(pageUrl('correspondence', 'chalani') . '&fy=' . $filterFy . '&status=' . urlencode($filterStatus) . '&month=' . urlencode($filterMonth) . '&q=' . urlencode($search) . '&word=1') ?>" class="btn btn-sm btn-outline-success" title="Download the filtered register as Word (.docx)"><i class="fas fa-file-word mr-1"></i>Word</a>
            <button type="button" class="btn btn-primary btn-sm" onclick="openDrawer()" <?= $correspondenceCanRegister ? '' : 'disabled' ?>>
                <i class="fas fa-plus mr-1"></i>Reserve Number
            </button>
        </div>
    </div>
    <div class="card-body">
        <?php if (!$correspondenceCanRegister): ?>
        <div class="callout callout-warning mb-3">
            <h5><i class="fas fa-exclamation-triangle mr-1"></i><?= !$correspondenceCurrentFy ? 'No fiscal year covers today' : 'No open fiscal year' ?></h5>
            <p class="mb-0">New chalani numbers cannot be allocated<?= !$correspondenceCurrentFy ? '' : ' into a closed fiscal year' ?>. Create/open the current fiscal year under
                <a href="<?= e(pageUrl('accounts', 'fiscal_years')) ?>">Setup → Fiscal Years</a>.</p>
        </div>
        <?php endif; ?>
        <form class="form-inline mb-0" method="get">
            <input type="hidden" name="module" value="correspondence">
            <input type="hidden" name="page" value="chalani">
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
            <input type="text" name="q" class="form-control form-control-sm mr-2 mb-2" placeholder="Recipient / subject…" value="<?= e($search) ?>">
            <button class="btn btn-sm btn-primary mb-2"><i class="fas fa-search"></i> Filter</button>
            <a href="<?= e(pageUrl('correspondence', 'chalani')) ?>" class="btn btn-sm btn-outline-secondary mb-2 ml-1">Reset</a>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>चलानी नं. / Chalani No.</th>
                        <th>मिति / Letter Date</th>
                        <th>प्राप्तकर्ता / Recipient</th>
                        <th>विषय / Subject</th>
                        <th>Status</th>
                        <th>Mode</th>
                        <th>Linked Darta</th>
                        <th>Dispatched</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$chalaniRows): ?>
                    <tr><td colspan="9" class="text-center text-muted">No chalani entries found.</td></tr>
                <?php else: foreach ($chalaniRows as $row): ?>
                    <tr<?= $row['status'] === 'Cancelled' ? ' class="text-muted"' : '' ?>>
                        <td><strong><?= e($displayNo($row)) ?></strong></td>
                        <td class="small"><?= e(formatDateView($row['letter_date'])) ?></td>
                        <td><?= e($row['recipient_name']) ?><br><span class="small text-muted"><?= e($row['recipient_type']) ?><?= $row['recipient_address'] ? ' · ' . e($row['recipient_address']) : '' ?></span></td>
                        <td style="max-width:240px"><?= e(mb_strimwidth((string) $row['subject'], 0, 80, '…')) ?></td>
                        <td><span class="badge badge-<?= $correspondenceStatusBadges['chalani'][$row['status']] ?? 'secondary' ?>"><?= e($row['status']) ?></span></td>
                        <td class="small"><?= e($row['dispatch_mode']) ?></td>
                        <td class="small">
                            <?php if ($row['ref_darta_id'] !== null): ?>
                                <span title="<?= e($row['ref_darta_sender']) ?> — <?= e($row['ref_darta_subject'] ?? '') ?>">
                                    <?= e($row['ref_darta_fy_title'] !== null
                                        ? correspondence_display_no('darta', (int) $row['ref_darta_no'], (string) $row['ref_darta_fy_title'])
                                        : '—') ?>
                                </span>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="small"><?= $row['dispatched_on'] ? e(date('Y-m-d', strtotime((string) $row['dispatched_on']))) : '—' ?></td>
                        <td class="text-right text-nowrap">
                            <a href="<?= e(pageUrl('correspondence', 'chalani') . '&entry=' . (int) $row['id'] . '&print=1') ?>" target="_blank" rel="noopener" class="btn btn-xs btn-outline-secondary" title="Print slip"><i class="fas fa-print"></i></a>
                            <a href="<?= e(pageUrl('correspondence', 'chalani') . '&entry=' . (int) $row['id'] . '&pdf=1') ?>" target="_blank" rel="noopener" class="btn btn-xs btn-outline-danger" title="Download slip as PDF"><i class="fas fa-file-pdf"></i></a>
                            <a href="<?= e(pageUrl('correspondence', 'chalani') . '&entry=' . (int) $row['id'] . '&word=1') ?>" class="btn btn-xs btn-outline-success" title="Download slip as Word (.docx)"><i class="fas fa-file-word"></i></a>
                            <button type="button" class="btn btn-xs btn-outline-primary" title="<?= $row['status'] === 'Reserved' ? 'View / Edit' : 'View (closed entry)' ?>" onclick="openDrawer(<?= (int) $row['id'] ?>)"><i class="fas fa-edit"></i></button>
                            <?php if ($row['status'] === 'Reserved'): ?>
                            <button type="button" class="btn btn-xs btn-outline-success" title="Mark dispatched" onclick="openDispatch(<?= (int) $row['id'] ?>, <?= e(json_encode($displayNo($row), JSON_HEX_TAG)) ?>, <?= e(json_encode($row['dispatch_mode'], JSON_HEX_TAG)) ?>)"><i class="fas fa-paper-plane"></i></button>
                            <form action="operation.php?module=correspondence&page=chalani" method="post" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Cancel chalani <?= e($displayNo($row)) ?>? The number stays visible in the register." title="Cancel"><i class="fas fa-ban"></i></button>
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
<div class="cms-drawer-backdrop" id="drawerBackdrop" onclick="closeDrawer(); closeDispatch();"></div>

<!-- Chalani entry drawer (add / edit) -->
<div class="cms-drawer" id="formDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas fa-share-square"></i><span id="drawerTitle"><?= $editChalani ? 'Edit Chalani' : 'Reserve Chalani Number' ?></span></h3>
        <button type="button" class="cms-drawer-close" onclick="closeDrawer()" aria-label="Close"><i class="fas fa-times"></i></button>
    </div>
    <div class="cms-drawer-body">
        <div class="alert alert-secondary py-2 mb-3" id="readonlyBanner" style="display:none">
            <i class="fas fa-lock mr-1"></i>This chalani is <strong id="readonlyStatus">closed</strong> — closed entries can be viewed but not edited.
        </div>
        <form action="operation.php?module=correspondence&page=chalani" method="post" id="chalaniForm" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="formId" value="<?= $editChalani ? (int) $editChalani['id'] : 0 ?>">

            <div class="form-group" id="chalaniNoWrap" <?= $editChalani ? '' : 'style="display:none"' ?>>
                <label>Chalani No.</label>
                <div class="form-control" id="formChalaniNo"><?= $editChalani ? e($displayNo($editChalani)) : '' ?></div>
            </div>

            <div class="form-group">
                <label>Fiscal Year *<?= $editChalani ? '' : ' <span class="text-muted">(open years only)</span>' ?></label>
                <select name="fiscal_year_id" class="form-control" id="formFy" required <?= $editChalani ? 'disabled' : '' ?>>
                    <?php foreach ($fyChoices as $fyOption): ?>
                    <option value="<?= (int) $fyOption['id'] ?>"
                        <?= (int) ($editChalani ? $editChalani['fiscal_year_id'] : old('fiscal_year_id', $defaultNewFyId)) === (int) $fyOption['id'] ? 'selected' : '' ?>>
                        <?= e($fyOption['title']) ?><?= $fyOption['closing'] === 'Closed' ? ' (Closed)' : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($editChalani): ?><input type="hidden" id="formFyLocked" name="fiscal_year_id" value="<?= (int) $editChalani['fiscal_year_id'] ?>"><?php endif; ?>
            </div>
            <div class="form-group">
                <label>Letter Date * <span class="text-muted"><?= e($calLabel) ?></span></label>
                <input type="<?= useBsDates() ? 'text' : 'date' ?>" name="letter_date" class="form-control<?= useBsDates() ? ' datepicker' : '' ?>" id="formLetterDate" required
                    <?= useBsDates() ? 'data-datepicker-placeholder-off placeholder="YYYY-MM-DD (BS)"' : '' ?>
                    value="<?= e(old('letter_date', $editChalani ? $inputDate((string) $editChalani['letter_date']) : $todayInput)) ?>">
            </div>
            <div class="form-row">
                <div class="form-group col-6">
                    <label>Recipient Type</label>
                    <select name="recipient_type" class="form-control" id="formRecipientType">
                        <?php foreach (['Office', 'Person', 'Other'] as $t): ?>
                        <option value="<?= e($t) ?>" <?= old('recipient_type', $editChalani['recipient_type'] ?? 'Office') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-6">
                    <label>Dispatch Mode</label>
                    <select name="dispatch_mode" class="form-control" id="formDispatchMode">
                        <?php foreach (['Hand', 'Post', 'Email', 'Courier', 'Other'] as $v): ?>
                        <option value="<?= e($v) ?>" <?= old('dispatch_mode', $editChalani['dispatch_mode'] ?? 'Hand') === $v ? 'selected' : '' ?>><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>प्राप्तकर्ता / Recipient Name *</label>
                <input type="text" name="recipient_name" class="form-control" id="formRecipientName" maxlength="255" required value="<?= e(old('recipient_name', $editChalani['recipient_name'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label>Recipient Address</label>
                <input type="text" name="recipient_address" class="form-control" id="formRecipientAddress" maxlength="255" value="<?= e(old('recipient_address', $editChalani['recipient_address'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label>बोधार्थ / CC Recipients <span class="text-muted">(one per line)</span></label>
                <textarea name="cc_recipients" class="form-control" id="formCc" rows="2" placeholder="Office of the Registrar&#10;Accounts Section"><?= e(old('cc_recipients', $editChalani['cc_recipients'] ?? '')) ?></textarea>
            </div>
            <div class="form-group">
                <label>विषय / Subject *</label>
                <input type="text" name="subject" class="form-control" id="formSubject" maxlength="500" required value="<?= e(old('subject', $editChalani['subject'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label>Description / Enclosures</label>
                <textarea name="description" class="form-control summernote-field" id="formDescription" rows="3"><?= e(old('description', $editChalani['description'] ?? '')) ?></textarea>
            </div>
            <div class="form-group">
                <label>Attachments <span class="text-muted">(PDF / JPG / PNG, max 10 MB each)</span></label>
                <input type="file" name="files[]" id="formFiles" class="form-control-file" multiple accept=".pdf,.jpg,.jpeg,.png">
                <div id="filesList" class="mt-2"></div>
            </div>
            <div class="form-group">
                <label>Linked Darta (reply to)</label>
                <select name="ref_darta_id" class="form-control" id="formRefDarta">
                    <option value="0">— None —</option>
                    <?php
                    // A link whose darta is no longer selectable (cancelled) must
                    // keep its current value instead of silently falling back to
                    // "None" and unlinking the darta on save.
                    $chosenRefDarta = (int) old('ref_darta_id', $prefillDarta);
                    $chosenInList = false;
                    foreach ($dartaOptions as $dartaOption) {
                        if ((int) $dartaOption['id'] === $chosenRefDarta) {
                            $chosenInList = true;
                            break;
                        }
                    }
                    ?>
                    <?php foreach ($dartaOptions as $dartaOption): ?>
                    <option value="<?= (int) $dartaOption['id'] ?>"
                        <?= $chosenRefDarta === (int) $dartaOption['id'] ? 'selected' : '' ?>>
                        <?= e(correspondence_display_no('darta', (int) $dartaOption['darta_no'], (string) $dartaOption['fy_title'])) ?>
                        — <?= e($dartaOption['sender_name']) ?> (<?= e(mb_strimwidth((string) $dartaOption['subject'], 0, 40, '…')) ?>)<?= in_array((string) $dartaOption['status'], ['Pending', 'In Progress'], true) ? '' : ' — ' . e((string) $dartaOption['status']) ?>
                    </option>
                    <?php endforeach; ?>
                    <?php if ($chosenRefDarta > 0 && !$chosenInList): ?>
                    <option value="<?= $chosenRefDarta ?>" selected>
                        <?= ($editChalani !== null && (int) $editChalani['ref_darta_id'] === $chosenRefDarta && $editChalani['ref_darta_no'] !== null)
                            ? e(correspondence_display_no('darta', (int) $editChalani['ref_darta_no'], (string) $editChalani['ref_darta_fy_title']))
                            : 'Darta #' . $chosenRefDarta ?> — not selectable (cancelled)
                    </option>
                    <?php endif; ?>
                </select>
                <?php if (!$dartaOptions): ?><small class="form-text text-muted">No open darta<?= $correspondenceCurrentFy ? ' in FY ' . e($correspondenceCurrentFy['title']) : '' ?> to link.</small><?php endif; ?>
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="chalaniForm" class="btn btn-primary btn-block">
            <i class="fas fa-save mr-1"></i><?= $editChalani ? 'Update Chalani' : 'Reserve Number' ?>
        </button>
    </div>
</div>

<!-- Dispatch drawer -->
<div class="cms-drawer" id="dispatchDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas fa-paper-plane"></i>Mark Dispatched</h3>
        <button type="button" class="cms-drawer-close" onclick="closeDispatch()" aria-label="Close"><i class="fas fa-times"></i></button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=correspondence&page=chalani" method="post" id="dispatchForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="dispatch">
            <input type="hidden" name="id" id="dispatchId" value="0">
            <div class="form-group">
                <label>Chalani</label>
                <div class="form-control" id="dispatchNo">&nbsp;</div>
            </div>
            <div class="form-group">
                <label>Dispatched On * <span class="text-muted"><?= e($calLabel) ?></span></label>
                <input type="<?= useBsDates() ? 'text' : 'date' ?>" name="dispatched_on" class="form-control<?= useBsDates() ? ' datepicker' : '' ?>" id="formDispatchedOn" required
                    <?= useBsDates() ? 'data-datepicker-placeholder-off placeholder="YYYY-MM-DD (BS)"' : '' ?>
                    value="<?= e($todayInput) ?>">
            </div>
            <div class="form-group">
                <label>Mode</label>
                <select name="dispatch_mode" class="form-control" id="dispatchMode">
                    <?php foreach (['Hand', 'Post', 'Email', 'Courier', 'Other'] as $v): ?>
                    <option value="<?= e($v) ?>"><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="custom-control custom-switch" id="disposeDartaWrap" style="display:none">
                <input type="checkbox" name="dispose_darta" value="1" class="custom-control-input" id="disposeDarta">
                <label class="custom-control-label" for="disposeDarta">Also mark linked darta <strong id="disposeDartaNo"></strong> Disposed (कारबाही)</label>
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="dispatchForm" class="btn btn-success btn-block">
            <i class="fas fa-paper-plane mr-1"></i>Mark Dispatched
        </button>
    </div>
</div>

<!-- Attachment delete (standalone PRG post from the drawer file list) -->
<form action="operation.php?module=correspondence&page=chalani" method="post" id="deleteFileForm" class="d-none">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="delete_file">
    <input type="hidden" name="file_id" value="0">
</form>

<script>
var chalaniRowsData = <?= json_encode($jsRows, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
// Status of the server-rendered entry (deep link / validation-error reopen),
// used when the row is not in the current table page.
var serverEditStatus = <?= json_encode((string) ($editChalani['status'] ?? '')) ?>;
var serverEditFiles = <?= json_encode($editFiles, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
var editableStatuses = ['Reserved'];

function fillChalaniForm(row) {
    document.getElementById('formId').value = row.id;
    var fySelect = document.getElementById('formFy');
    if (fySelect) { fySelect.value = row.fiscal_year_id; }
    document.getElementById('formLetterDate').value = row.letter_date;
    document.getElementById('formRecipientType').value = row.recipient_type;
    document.getElementById('formDispatchMode').value = row.dispatch_mode;
    document.getElementById('formRecipientName').value = row.recipient_name;
    document.getElementById('formRecipientAddress').value = row.recipient_address;
    document.getElementById('formCc').value = row.cc_recipients;
    document.getElementById('formSubject').value = row.subject;
    document.getElementById('formDescription').value = row.description || '';
    syncDescEditor(row.description);
    document.getElementById('formRefDarta').value = row.ref_darta_id || '0';
}

function resetChalaniForm() {
    document.getElementById('formId').value = '0';
    var fySelect = document.getElementById('formFy');
    if (fySelect && fySelect.options.length && <?= json_encode($defaultNewFyId) ?>) {
        fySelect.value = <?= json_encode($defaultNewFyId) ?>;
    }
    document.getElementById('formLetterDate').value = <?= json_encode($todayInput) ?>;
    document.getElementById('formRecipientType').value = 'Office';
    document.getElementById('formDispatchMode').value = 'Hand';
    document.getElementById('formRecipientName').value = '';
    document.getElementById('formRecipientAddress').value = '';
    document.getElementById('formCc').value = '';
    document.getElementById('formSubject').value = '';
    document.getElementById('formDescription').value = '';
    syncDescEditor('');
    document.getElementById('formRefDarta').value = '0';
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

function syncDescEditor(value) {
    if (window.jQuery && jQuery.fn.summernote && jQuery('#formDescription').data('summernote')) {
        jQuery('#formDescription').summernote('code', value || '');
    }
}

function initDescEditor() {
    var el = document.getElementById('formDescription');
    if (!el || !window.jQuery || !jQuery.fn.summernote) { return; }
    jQuery(el).summernote({
        height: 160,
        placeholder: 'Description / Enclosures…',
        toolbar: [
            ['style', ['bold', 'italic', 'underline', 'clear']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['fontsize', ['fontsize']],
            ['color', ['color']],
            ['link', ['link']],
            ['view', ['codeview']]
        ]
    });
}
document.addEventListener('DOMContentLoaded', initDescEditor);

/** Closed entries are view-only: disable every field and hide the save bar. */
function applyFormMode(status) {
    var form = document.getElementById('chalaniForm');
    var readonly = !!status && editableStatuses.indexOf(status) === -1;
    var fields = form.querySelectorAll('input, select, textarea');
    for (var i = 0; i < fields.length; i++) { fields[i].disabled = readonly; }
    if (window.jQuery && jQuery.fn.summernote && jQuery('#formDescription').data('summernote')) {
        jQuery('#formDescription').summernote(readonly ? 'disable' : 'enable');
    }
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

function setChalaniNoDisplay(text) {
    var wrap = document.getElementById('chalaniNoWrap');
    if (text) {
        document.getElementById('formChalaniNo').textContent = text;
        wrap.style.display = '';
    } else {
        wrap.style.display = 'none';
    }
}

function openDrawer(editId, keepServerValues) {
    editId = editId || 0;
    var title = document.querySelector('#drawerTitle');
    var row = null;
    for (var i = 0; i < chalaniRowsData.length; i++) {
        if (chalaniRowsData[i].id === editId) { row = chalaniRowsData[i]; break; }
    }
    var status = row ? row.status : serverEditStatus;
    if (editId) {
        title.textContent = 'Edit Chalani';
        // keepServerValues: the drawer was rendered server-side for this row
        // (deep link or validation-error reopen), so its old() values are the
        // current ones — refilling from the table snapshot would discard the
        // input the user just corrected.
        if (row && !keepServerValues) {
            fillChalaniForm(row);
        }
        setChalaniNoDisplay(row ? row.display_no : document.getElementById('formChalaniNo').textContent);
    } else {
        title.textContent = 'Reserve Chalani Number';
        if (!keepServerValues) {
            resetChalaniForm();
            setChalaniNoDisplay('');
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
    if (!document.getElementById('dispatchDrawer').classList.contains('open')) {
        document.getElementById('drawerBackdrop').classList.remove('active');
        document.body.style.overflow = '';
    }
}

function openDispatch(id, displayNo, mode) {
    document.getElementById('dispatchId').value = id;
    document.getElementById('dispatchNo').textContent = displayNo;
    document.getElementById('dispatchMode').value = mode;
    // P2: offer to dispose the linked darta on dispatch (never automatic —
    // an acknowledgement reply should not close the darta by itself).
    var row = null;
    for (var i = 0; i < chalaniRowsData.length; i++) {
        if (chalaniRowsData[i].id === id) { row = chalaniRowsData[i]; break; }
    }
    var wrap = document.getElementById('disposeDartaWrap');
    var checkbox = document.getElementById('disposeDarta');
    checkbox.checked = false;
    if (row && row.ref_darta_id && ['Pending', 'In Progress'].indexOf(row.ref_darta_status) !== -1) {
        document.getElementById('disposeDartaNo').textContent = row.ref_darta_label;
        wrap.style.display = '';
    } else {
        wrap.style.display = 'none';
    }
    document.getElementById('dispatchDrawer').classList.add('open');
    document.getElementById('drawerBackdrop').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDispatch() {
    document.getElementById('dispatchDrawer').classList.remove('open');
    if (!document.getElementById('formDrawer').classList.contains('open')) {
        document.getElementById('drawerBackdrop').classList.remove('active');
        document.body.style.overflow = '';
    }
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeDrawer(); closeDispatch(); }
});

<?php if ($drawerOpen): ?>
document.addEventListener('DOMContentLoaded', function () {
    openDrawer(<?= (int) ($editChalani['id'] ?? 0) ?>, true);
});
<?php endif; ?>
</script>
