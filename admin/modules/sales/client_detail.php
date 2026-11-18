<?php

/**
 * SB-Tech — Client detail page (separate view for a single client).
 * Shows the client's overview, contact persons, won projects and leads.
 * Rendered at ?module=sales&page=client_detail&id=N.
 */
$db = Database::instance();
$canManage = Auth::isSuperAdmin() || Auth::hasSpecial('manage_leads');
// Payments change invoice status, so they need the invoice permission too
// (manage_office, as in sales/documents.php) — not just manage_leads.
$canPay = $canManage && (Auth::isSuperAdmin() || Auth::hasSpecial('manage_office'));

$id = (int) ($_GET['id'] ?? 0);
$edit = $id ? $db->selectOne('SELECT * FROM `tbl_clients` WHERE `id` = ?', [$id]) : null;
if (!$edit) {
    echo '<div class="callout callout-warning"><h5>Client not found</h5><p>No client exists with that ID.</p></div>';
    return;
}

$contacts = $db->select(
    'SELECT * FROM `tbl_client_contacts`
     WHERE `client_id` = ? ORDER BY `is_primary` DESC, `id` ASC',
    [(int) $edit['id']]
);
$wonProjects = $db->select(
    'SELECT cp.*, p.name AS catalog_name
     FROM `tbl_client_projects` cp
     LEFT JOIN `tbl_projects` p ON p.id = cp.project_id
     WHERE cp.client_id = ? ORDER BY cp.is_won DESC, cp.start_date DESC',
    [(int) $edit['id']]
);
$sourceLeads = $db->select(
    'SELECT l.*, o.fullname AS owner_name
     FROM `tbl_leads` l
     LEFT JOIN `tbl_users_login` o ON o.id = l.assigned_to
     WHERE l.client_id = ? OR l.won_client_id = ? ORDER BY l.added_on DESC',
    [(int) $edit['id'], (int) $edit['id']]
);
$quotations = $db->select(
    "SELECT d.id, d.document_number, d.subject, d.document_date, d.total, d.status, u.fullname AS created_by_name
     FROM `tbl_documents` d
     LEFT JOIN `tbl_users_login` u ON u.id = d.added_by
     WHERE d.client_id = ? AND d.document_type = 'quotation' ORDER BY d.added_on DESC",
    [(int) $edit['id']]
);
// Payments & balance (ClientPaymentService; maths in functions/sales.php).
$paymentService = new ClientPaymentService($db);
$clientInvoices = $paymentService->invoicesFor((int) $edit['id']);
$clientPayments = $paymentService->paymentsFor((int) $edit['id']);
$statementRows = client_statement($clientInvoices, $clientPayments);
$invoicedTotal = 0.0;
foreach ($clientInvoices as $_inv) {
    if (!in_array($_inv['status'], CLIENT_BILLABLE_INVOICE_EXCLUDED, true)) {
        $invoicedTotal += (float) $_inv['total'];
    }
}
$receivedTotal = round(array_sum(array_map(static fn ($p) => (float) $p['amount'], $clientPayments)), 2);
$outstandingTotal = round($invoicedTotal - $receivedTotal, 2);
$overdueTotal = client_overdue_amount($clientInvoices, date('Y-m-d'));
$balanceStatus = client_balance_status($invoicedTotal, $outstandingTotal, $overdueTotal);
// Invoices that can still take a payment (issued, not cancelled, money left).
$payableInvoices = array_values(array_filter(
    $clientInvoices,
    static fn ($i) => !in_array($i['status'], ['Cancelled'], true) && (float) $i['total'] - (float) $i['paid'] > 0.01
));
$accountTree = $canPay ? accountingTerminalOptions() : [];
$defaultDeposit = accountingTerminalByTitle('Bank Accounts');
// Invoices are not posted to the ledger, so the receipt credits income by
// default; pick Accounts Receivable only if a sales voucher already booked it.
$defaultCredit = accountingTerminalByTitle('Software Development Income');
$openPaymentDrawer = $canPay && !empty($_GET['pay']);

$leadIdsForActivity = [];
foreach ($sourceLeads as $_l) {
    $leadIdsForActivity[] = (int) $_l['id'];
}
$clientActivities = [];
if ($leadIdsForActivity) {
    $in = rtrim(str_repeat('?,', count($leadIdsForActivity)), ',');
    $clientActivities = $db->select(
        'SELECT a.*, u.fullname AS actor, l.company AS lead_title
         FROM `tbl_lead_activities` a
         LEFT JOIN `tbl_users_login` u ON u.id = a.added_by
         LEFT JOIN `tbl_leads` l ON l.id = a.lead_id
         WHERE a.lead_id IN (' . $in . ')
         ORDER BY a.added_on DESC LIMIT 25',
        $leadIdsForActivity
    );
}

// Snapshot for client-side drawer prefill.
$sourcesJson = [[
    'id'             => (int) $edit['id'],
    'type'           => $edit['type'],
    'name'           => $edit['name'],
    'contact_person' => $edit['contact_person'],
    'email'          => $edit['email'],
    'phone'          => $edit['phone'],
    'address'        => $edit['address'],
    'pan_num'        => $edit['pan_num'],
    'notes'          => $edit['notes'],
]];
$contactsJson = [];
foreach ($contacts as $t) {
    $contactsJson[] = [
        'id'                => (int) $t['id'],
        'name'              => $t['name'],
        'designation'       => $t['designation'],
        'email'             => $t['email'],
        'phone'             => $t['phone'],
        'is_primary'        => (int) $t['is_primary'],
        'notes'             => $t['notes'],
    ];
}
$wonProjectsJson = [];
foreach ($wonProjects as $p) {
    $wonProjectsJson[] = [
        'id'            => (int) $p['id'],
        'client_id'     => (int) $p['client_id'],
        'project_id'    => $p['project_id'] ? (int) $p['project_id'] : '',
        'is_won'        => (int) $p['is_won'],
        'title'         => $p['title'],
        'package'       => $p['package'],
        'url'           => $p['url'],
        'value'         => $p['value'] !== null ? (float) $p['value'] : '',
        'status'        => $p['status'],
        'start_date'    => $p['start_date'],
        'end_date'      => $p['end_date'],
        'description'   => $p['description'],
    ];
}
$catalogJson = [];
// Active products, plus inactive ones still linked to a project (so editing
// never silently clears a project's product).
$catalogList = $db->select(
    "SELECT `id`, `name`, `status` FROM `tbl_projects`
     WHERE `status` = 'Active' OR `id` IN (SELECT `project_id` FROM `tbl_client_projects` WHERE `project_id` IS NOT NULL)
     ORDER BY `name` ASC"
);
foreach ($catalogList as $c) {
    $catalogJson[] = ['id' => (int) $c['id'], 'name' => $c['name'] . ($c['status'] !== 'Active' ? ' (inactive)' : '')];
}

// Demo/trial rows (is_won = 0) are listed but never counted as revenue.
$totalValue = 0.0;
$activeProjects = 0;
$wonCount = 0;
foreach ($wonProjects as $_p) {
    if (!(int) $_p['is_won']) {
        continue;
    }
    $wonCount++;
    if ($_p['value'] !== null) {
        $totalValue += (float) $_p['value'];
    }
    if ($_p['status'] === 'Active') {
        $activeProjects++;
    }
}
$initials = '';
foreach (preg_split('/\s+/', trim($edit['name'] ?? '')) as $_w) {
    if ($_w !== '' && mb_strlen($initials) < 2) {
        $initials .= mb_strtoupper(mb_substr($_w, 0, 1));
    }
}
$initials = $initials !== '' ? $initials : '?';
$creator = null;
if ($edit['added_by']) {
    $creator = $db->selectOne('SELECT fullname FROM `tbl_users_login` WHERE `id` = ?', [(int) $edit['added_by']]);
}
$originLead = null;
if ($edit['lead_id']) {
    $originLead = $db->selectOne('SELECT `id`, `company`, `contact_name`, `referrer_name` FROM `tbl_leads` WHERE `id` = ?', [(int) $edit['lead_id']]);
}
?>
<div id="cl-detail">
    <!-- Page header -->
    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb bg-transparent p-0 mb-0 small">
            <li class="breadcrumb-item"><a href="<?= pageUrl('sales', 'clients') ?>">Clients</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($edit['name']) ?></li>
        </ol>
    </nav>

    <!-- Hero banner -->
    <div class="cl-hero mb-3">
        <div class="cl-hero-glow cl-hero-glow--a"></div>
        <div class="cl-hero-glow cl-hero-glow--b"></div>
        <div class="cl-hero-inner">
            <a href="<?= pageUrl('sales', 'clients') ?>" class="cl-hero-back" aria-label="Back to clients"><i class="fas fa-arrow-left" aria-hidden="true"></i></a>
            <span class="cl-avatar" aria-hidden="true"><?= e($initials) ?></span>
            <div class="cl-hero-meta">
                <div class="cl-hero-title-row">
                    <h2 class="cl-hero-name"><?= e($edit['name']) ?></h2>
                    <span class="badge-soft badge-soft--light" title="Customer = has a won project"><?= e(client_lifecycle($wonCount)) ?></span>
                    <span class="badge-soft badge-soft--light"><i class="fas fa-<?= $edit['type'] === 'Individual' ? 'user' : 'building' ?> mr-1" aria-hidden="true"></i><?= e($edit['type']) ?></span>
                    <?php if ($originLead): ?>
                        <a href="<?= pageUrl('sales', 'leads') ?>&id=<?= (int) $originLead['id'] ?>" class="badge-soft badge-soft--light text-decoration-none" title="Origin lead"><i class="fas fa-funnel-dollar mr-1" aria-hidden="true"></i><?= e($originLead['company'] ?: $originLead['contact_name'] ?: ($originLead['referrer_name'] ?? '')) ?></a>
                    <?php endif; ?>
                </div>
                <div class="cl-hero-contact">
                    <i class="fas fa-user-tie mr-1" aria-hidden="true"></i><?= e($edit['contact_person'] ?: '—') ?>
                    <?php if ($edit['email']): ?> · <a href="mailto:<?= e($edit['email']) ?>"><i class="fas fa-envelope mr-1" aria-hidden="true"></i><?= e($edit['email']) ?></a><?php endif; ?>
                    <?php if ($edit['phone']): ?> · <a href="tel:<?= e($edit['phone']) ?>"><i class="fas fa-phone mr-1" aria-hidden="true"></i><?= e($edit['phone']) ?></a><?php endif; ?>
                    <?php if ($edit['pan_num']): ?> · <code style="color:rgba(255,255,255,.9);background:rgba(255,255,255,.14);border-radius:4px;padding:0 .35rem">PAN <?= e($edit['pan_num']) ?></code><?php endif; ?>
                    <?php if ($edit['added_on']): ?> · <i class="fas fa-calendar-alt mr-1" aria-hidden="true"></i>Client since <?= e(date('M j, Y', strtotime($edit['added_on']))) ?><?php endif; ?>
                </div>
            </div>
            <div class="cl-hero-actions">
                <?php if ($canManage): ?>
                    <button type="button" class="btn btn-sm btn-ghost" onclick="openSourceDrawer(<?= (int) $edit['id'] ?>)"><i class="fas fa-edit mr-1" aria-hidden="true"></i>Edit Client</button>
                    <button type="button" class="btn btn-sm btn-ghost btn-soft-warn" onclick="openContactDrawer()"><i class="fas fa-user-plus mr-1" aria-hidden="true"></i>Add Contact</button>
                    <form action="operation.php?module=sales&page=clients" method="post" class="d-inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_client">
                        <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger-ghost btn-icon-only confirm-submit" data-confirm="Delete client <?= e($edit['name']) ?> and all related data?" aria-label="Delete client"><i class="fas fa-trash" aria-hidden="true"></i></button>
                    </form>
                <?php endif; ?>
                <?php if ($canPay): ?>
                    <button type="button" class="btn btn-sm btn-ghost" onclick="openPaymentDrawer()"><i class="fas fa-hand-holding-usd mr-1" aria-hidden="true"></i>Record Payment</button>
                <?php endif; ?>
                <a href="<?= pageUrl('sales', 'documents') ?>&type=quotation&add=1&client_id=<?= (int) $edit['id'] ?>" class="btn btn-sm btn-ghost"><i class="fas fa-file-invoice mr-1" aria-hidden="true"></i>New Quotation</a>
                <a href="<?= pageUrl('sales', 'client_projects') ?>&source=<?= (int) $edit['id'] ?>" class="btn btn-sm btn-ghost"><i class="fas fa-folder-open mr-1" aria-hidden="true"></i>Client Projects</a>
            </div>
        </div>
    </div>

    <!-- KPI strip -->
    <div class="tms-kpi-grid">
        <a href="#client-contacts" class="tms-kpi-card bg-info">
            <div class="tms-kpi-icon"><i class="fas fa-id-card" aria-hidden="true"></i></div>
            <div class="tms-kpi-meta">
                <p class="tms-kpi-label">Contact Persons</p>
                <p class="tms-kpi-value"><?= count($contacts) ?></p>
            </div>
        </a>
        <a href="#client-projects" class="tms-kpi-card bg-success">
            <div class="tms-kpi-icon"><i class="fas fa-project-diagram" aria-hidden="true"></i></div>
            <div class="tms-kpi-meta">
                <p class="tms-kpi-label">Won Projects</p>
                <p class="tms-kpi-value"><?= $wonCount ?><small class="tms-kpi-sub"><?= $activeProjects ?> active<?= count($wonProjects) > $wonCount ? ' · ' . (count($wonProjects) - $wonCount) . ' demo/trial' : '' ?></small></p>
            </div>
        </a>
        <a href="#client-projects" class="tms-kpi-card bg-primary">
            <div class="tms-kpi-icon"><i class="fas fa-money-bill-wave" aria-hidden="true"></i></div>
            <div class="tms-kpi-meta">
                <p class="tms-kpi-label">Total Project Value</p>
                <p class="tms-kpi-value">NRs. <?= e(formatMoney($totalValue)) ?></p>
            </div>
        </a>
        <a href="#client-payments" class="tms-kpi-card bg-<?= $balanceStatus === 'Overdue' ? 'danger' : ($balanceStatus === 'Due' ? 'warning' : 'success') ?>">
            <div class="tms-kpi-icon"><i class="fas fa-hand-holding-usd" aria-hidden="true"></i></div>
            <div class="tms-kpi-meta">
                <p class="tms-kpi-label"><?= $outstandingTotal < -0.01 ? 'Advance Held' : 'Outstanding' ?></p>
                <p class="tms-kpi-value">NRs. <?= e(formatMoney(abs($outstandingTotal))) ?><small class="tms-kpi-sub"><?= $overdueTotal > 0.01 ? e(formatMoney($overdueTotal)) . ' overdue' : 'Received ' . e(formatMoney($receivedTotal)) ?></small></p>
            </div>
        </a>
        <a href="#client-leads" class="tms-kpi-card bg-secondary">
            <div class="tms-kpi-icon"><i class="fas fa-link" aria-hidden="true"></i></div>
            <div class="tms-kpi-meta">
                <p class="tms-kpi-label">Linked Leads</p>
                <p class="tms-kpi-value"><?= count($sourceLeads) ?></p>
            </div>
        </a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <!-- Client Overview -->
            <div class="card card-outline mb-3">
                <div class="card-header">
                    <h3 class="card-title"><span class="tms-chip tms-chip--info"><i class="fas fa-info-circle" aria-hidden="true"></i></span>Client Overview</h3>
                </div>
                <div class="card-body">
                    <div class="cl-facts">
                        <div class="cl-fact cl-fact--accent">
                            <span class="cl-fact-icon"><i class="fas fa-user-tie" aria-hidden="true"></i></span>
                            <div class="cl-fact-body">
                                <span class="cl-fact-label">Primary Contact</span>
                                <span class="cl-fact-value"><?= e($edit['contact_person'] ?: '—') ?></span>
                            </div>
                        </div>
                        <div class="cl-fact cl-fact--accent">
                            <span class="cl-fact-icon"><i class="fas fa-envelope" aria-hidden="true"></i></span>
                            <div class="cl-fact-body">
                                <span class="cl-fact-label">Email</span>
                                <span class="cl-fact-value"><?= $edit['email'] ? '<a href="mailto:' . e($edit['email']) . '">' . e($edit['email']) . '</a>' : '—' ?></span>
                            </div>
                        </div>
                        <div class="cl-fact cl-fact--accent">
                            <span class="cl-fact-icon"><i class="fas fa-phone-alt" aria-hidden="true"></i></span>
                            <div class="cl-fact-body">
                                <span class="cl-fact-label">Phone</span>
                                <span class="cl-fact-value"><?= $edit['phone'] ? '<a href="tel:' . e($edit['phone']) . '">' . e($edit['phone']) . '</a>' : '—' ?></span>
                            </div>
                        </div>
                        <div class="cl-fact">
                            <span class="cl-fact-icon"><i class="fas fa-map-marker-alt" aria-hidden="true"></i></span>
                            <div class="cl-fact-body">
                                <span class="cl-fact-label">Address</span>
                                <span class="cl-fact-value"><?= e($edit['address'] ?: '—') ?></span>
                            </div>
                        </div>
                    </div>
                    <?php if ($edit['notes']): ?>
                        <div class="border rounded p-3 mt-3 bg-primary-light">
                            <strong class="small text-uppercase text-muted" style="font-size:.68rem;letter-spacing:.05em">Notes</strong>
                            <p class="mb-0 small mt-1"><?= nl2br(e($edit['notes'])) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payments & balance -->
            <?php $invoiceSoft = ['Draft' => 'muted', 'Sent' => 'info', 'Partially Paid' => 'warning', 'Paid' => 'success', 'Overdue' => 'danger', 'Cancelled' => 'muted']; ?>
            <div class="card card-outline mb-3" id="client-payments">
                <div class="card-header">
                    <h3 class="card-title"><span class="tms-chip tms-chip--success"><i class="fas fa-hand-holding-usd" aria-hidden="true"></i></span>Payments &amp; Balance</h3>
                    <div class="card-tools">
                        <?php if ($canPay): ?>
                            <button type="button" class="btn btn-outline-success btn-sm" onclick="openPaymentDrawer()"><i class="fas fa-plus mr-1"></i>Record Payment</button>
                        <?php endif; ?>
                        <a href="<?= pageUrl('sales', 'documents') ?>&type=invoice&add=1&client_id=<?= (int) $edit['id'] ?>" class="btn btn-outline-primary btn-sm"><i class="fas fa-receipt mr-1"></i>New Invoice</a>
                    </div>
                </div>
                <div class="card-body pb-0">
                    <div class="d-flex flex-wrap small mb-2" style="gap:1.5rem">
                        <span>Invoiced <strong>NPR <?= e(formatMoney($invoicedTotal)) ?></strong></span>
                        <span>Received <strong class="text-success">NPR <?= e(formatMoney($receivedTotal)) ?></strong></span>
                        <span><?= $outstandingTotal < -0.01 ? 'Advance held' : 'Outstanding' ?> <strong class="text-warning">NPR <?= e(formatMoney(abs($outstandingTotal))) ?></strong></span>
                        <?php if ($overdueTotal > 0.01): ?><span>Overdue <strong class="text-danger">NPR <?= e(formatMoney($overdueTotal)) ?></strong></span><?php endif; ?>
                    </div>
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#cp-invoices" role="tab">Invoices <span class="badge badge-light border"><?= count($clientInvoices) ?></span></a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#cp-payments" role="tab">Payments <span class="badge badge-light border"><?= count($clientPayments) ?></span></a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#cp-statement" role="tab">Statement</a></li>
                    </ul>
                </div>
                <div class="card-body p-0">
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="cp-invoices" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table cl-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Invoice</th>
                                            <th>Date / Due</th>
                                            <th class="text-right">Total</th>
                                            <th class="text-right">Paid</th>
                                            <th class="text-right">Left to pay</th>
                                            <th>Status</th>
                                            <th class="text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($clientInvoices as $inv): ?>
                                            <?php $left = round((float) $inv['total'] - (float) $inv['paid'], 2);
                                            $isLate = $left > 0.01 && $inv['due_date'] && $inv['due_date'] < date('Y-m-d') && !in_array($inv['status'], CLIENT_BILLABLE_INVOICE_EXCLUDED, true); ?>
                                            <tr>
                                                <td><span class="cl-cell-main"><?= e($inv['document_number']) ?></span>
                                                    <?php if ($inv['subject']): ?><br><span class="cl-cell-sub"><?= e(mb_strimwidth($inv['subject'], 0, 44, '…')) ?></span><?php endif; ?></td>
                                                <td><?= e($inv['document_date']) ?><br><span class="cl-cell-sub <?= $isLate ? 'text-danger' : '' ?>">Due <?= e($inv['due_date'] ?: '—') ?></span></td>
                                                <td class="text-right"><span class="cl-money"><?= e(formatMoney($inv['total'])) ?></span></td>
                                                <td class="text-right"><?= (float) $inv['paid'] > 0 ? e(formatMoney($inv['paid'])) : '—' ?></td>
                                                <td class="text-right font-weight-bold <?= $isLate ? 'text-danger' : '' ?>"><?= $left > 0.01 ? e(formatMoney($left)) : '—' ?></td>
                                                <td><span class="badge-soft badge-soft--<?= $invoiceSoft[$inv['status']] ?? 'muted' ?>"><?= e($inv['status']) ?></span></td>
                                                <td class="text-right text-nowrap">
                                                    <?php if ($canPay && $left > 0.01 && $inv['status'] !== 'Cancelled'): ?>
                                                        <button type="button" class="btn btn-xs btn-outline-success" title="Record payment" aria-label="Record payment for <?= e($inv['document_number']) ?>" onclick="openPaymentDrawer(<?= (int) $inv['id'] ?>)"><i class="fas fa-hand-holding-usd" aria-hidden="true"></i></button>
                                                    <?php endif; ?>
                                                    <a href="<?= pageUrl('sales', 'documents') ?>&type=invoice&id=<?= (int) $inv['id'] ?>" class="btn btn-xs btn-outline-primary" title="View" aria-label="View invoice <?= e($inv['document_number']) ?>"><i class="fas fa-eye" aria-hidden="true"></i></a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (!$clientInvoices): ?><tr>
                                                <td colspan="7">
                                                    <div class="tms-empty-state"><i class="fas fa-receipt" aria-hidden="true"></i>
                                                        <p>No invoices for this client yet. Payments recorded without an invoice count as advances.</p>
                                                    </div>
                                                </td>
                                            </tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="cp-payments" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table cl-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th class="text-right">Amount</th>
                                            <th>Method / Ref</th>
                                            <th>For</th>
                                            <th>Accounts</th>
                                            <th class="text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $voucherSoft = ['Pending' => 'warning', 'Approved' => 'success', 'Rejected' => 'danger']; ?>
                                        <?php foreach ($clientPayments as $p): ?>
                                            <tr>
                                                <td><?= e($p['payment_date']) ?><?php if ($p['added_by_name']): ?><br><span class="cl-cell-sub"><?= e($p['added_by_name']) ?></span><?php endif; ?></td>
                                                <td class="text-right"><span class="cl-money text-success"><?= e(formatMoney($p['amount'])) ?></span></td>
                                                <td><?= e($p['method']) ?><?php if ($p['reference_no']): ?><br><span class="cl-cell-sub"><?= e($p['reference_no']) ?></span><?php endif; ?>
                                                    <?php if ($p['notes']): ?><br><span class="cl-cell-sub" title="<?= e($p['notes']) ?>"><?= e(mb_strimwidth($p['notes'], 0, 40, '…')) ?></span><?php endif; ?></td>
                                                <td><?= $p['document_number'] ? e($p['document_number']) : '<span class="badge-soft badge-soft--info">Advance</span>' ?></td>
                                                <td>
                                                    <?php if ($p['voucher_no']): ?>
                                                        <span class="badge-soft badge-soft--<?= $voucherSoft[$p['voucher_status']] ?? 'muted' ?>"><?= e($p['voucher_no']) ?> · <?= e($p['voucher_status']) ?></span>
                                                    <?php elseif ($p['receipt_voucher_id']): ?>
                                                        <span class="badge-soft badge-soft--muted" title="The receipt voucher was deleted in Accounts">Voucher removed</span>
                                                    <?php else: ?>
                                                        <span class="cl-cell-sub">Not posted</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-right">
                                                    <?php if ($canPay && ($p['voucher_status'] ?? 'Pending') !== 'Approved'): ?>
                                                        <form action="operation.php?module=sales&page=clients" method="post" class="d-inline">
                                                            <?= csrfField() ?>
                                                            <input type="hidden" name="action" value="delete_payment">
                                                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                                            <input type="hidden" name="client_id" value="<?= (int) $edit['id'] ?>">
                                                            <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" aria-label="Delete payment of <?= e(formatMoney($p['amount'])) ?>" data-confirm="Delete this payment of NPR <?= e(formatMoney($p['amount'])) ?>?<?= $p['voucher_no'] ? ' Its pending receipt voucher ' . e($p['voucher_no']) . ' is deleted too.' : '' ?>"><i class="fas fa-trash" aria-hidden="true"></i></button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (!$clientPayments): ?><tr>
                                                <td colspan="6">
                                                    <div class="tms-empty-state"><i class="fas fa-hand-holding-usd" aria-hidden="true"></i>
                                                        <p>No payments recorded yet.</p>
                                                    </div>
                                                </td>
                                            </tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="cp-statement" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table cl-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Entry</th>
                                            <th class="text-right">Charge</th>
                                            <th class="text-right">Payment</th>
                                            <th class="text-right">Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($statementRows as $s): ?>
                                            <tr>
                                                <td><?= e($s['date']) ?></td>
                                                <td><span class="cl-cell-main"><?= e($s['kind']) ?><?= $s['ref'] !== '' ? ' · ' . e($s['ref']) : '' ?></span>
                                                    <?php if ($s['detail'] !== ''): ?><br><span class="cl-cell-sub"><?= e($s['detail']) ?></span><?php endif; ?></td>
                                                <td class="text-right"><?= $s['charge'] > 0 ? e(formatMoney($s['charge'])) : '' ?></td>
                                                <td class="text-right text-success"><?= $s['payment'] > 0 ? e(formatMoney($s['payment'])) : '' ?></td>
                                                <td class="text-right font-weight-bold"><?= e(formatMoney(abs($s['balance']))) ?><?= $s['balance'] < -0.01 ? ' <span class="cl-cell-sub">adv.</span>' : '' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (!$statementRows): ?><tr>
                                                <td colspan="5">
                                                    <div class="tms-empty-state"><i class="fas fa-file-alt" aria-hidden="true"></i>
                                                        <p>Nothing to show yet. Issued invoices (not Draft) and payments appear here.</p>
                                                    </div>
                                                </td>
                                            </tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Persons -->
            <div class="card card-outline mb-3" id="client-contacts">
                <div class="card-header">
                    <h3 class="card-title"><span class="tms-chip tms-chip--success"><i class="fas fa-id-card" aria-hidden="true"></i></span>Contact Persons</h3>
                    <div class="card-tools">
                        <?php if ($canManage): ?>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="openContactDrawer()"><i class="fas fa-plus mr-1"></i>Add Contact</button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table cl-table mb-0">
                            <thead>
                                <tr>
                                    <th>Contact</th>
                                    <th>Email / Phone</th>
                                    <th>Primary</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contacts as $t): ?>
                                    <?php $ci = '';
                                    foreach (preg_split('/\s+/', trim((string) $t['name'])) as $_w) {
                                        if ($_w !== '' && mb_strlen($ci) < 2) {
                                            $ci .= mb_strtoupper(mb_substr($_w, 0, 1));
                                        }
                                    }
                                    $ci = $ci !== '' ? $ci : '?'; ?>
                                    <tr>
                                        <td>
                                            <span class="cl-person">
                                                <span class="cl-mini-avatar"><?= e($ci) ?></span>
                                                <span><span class="cl-cell-main"><?= e($t['name']) ?></span><?php if ($t['designation']): ?><br><span class="cl-cell-sub"><?= e($t['designation']) ?></span><?php endif; ?></span>
                                            </span>
                                        </td>
                                        <td>
                                            <?= $t['email'] ? '<span class="cl-cell-main">' . e($t['email']) . '</span>' : '—' ?>
                                            <?php if ($t['phone']): ?><br><span class="cl-cell-sub"><?= e($t['phone']) ?></span><?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($canManage && !$t['is_primary']): ?>
                                                <form action="operation.php?module=sales&page=clients" method="post" class="d-inline">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="set_primary_contact">
                                                    <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                                    <input type="hidden" name="client_id" value="<?= (int) $edit['id'] ?>">
                                                    <button type="submit" class="btn btn-xs btn-outline-primary">Make Primary</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="badge-soft badge-soft--success"><i class="fas fa-check" aria-hidden="true"></i>Primary</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-right">
                                            <?php if ($canManage): ?>
                                                <button type="button" class="btn btn-xs btn-outline-secondary" title="Edit" aria-label="Edit <?= e($t['name']) ?>?" onclick="openContactDrawer(<?= (int) $t['id'] ?>)"><i class="fas fa-edit" aria-hidden="true"></i></button>
                                                <form action="operation.php?module=sales&page=clients" method="post" class="d-inline">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="delete_contact">
                                                    <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                                    <input type="hidden" name="client_id" value="<?= (int) $edit['id'] ?>">
                                                    <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" aria-label="Delete contact <?= e($t['name']) ?>?" data-confirm="Delete contact <?= e($t['name']) ?>?"><i class="fas fa-trash" aria-hidden="true"></i></button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$contacts): ?><tr>
                                        <td colspan="4">
                                            <div class="tms-empty-state"><i class="fas fa-users" aria-hidden="true"></i>
                                                <p>No contacts yet. The first contact added becomes the primary contact.</p>
                                            </div>
                                        </td>
                                    </tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Client Projects: won + demo/trial (detail) -->
            <div class="card card-outline mb-3" id="client-projects">
                <div class="card-header">
                    <h3 class="card-title"><span class="tms-chip tms-chip--primary"><i class="fas fa-project-diagram" aria-hidden="true"></i></span>Client Projects</h3>
                    <div class="card-tools">
                        <a href="<?= pageUrl('sales', 'client_projects') ?>&source=<?= (int) $edit['id'] ?>" class="btn btn-sm btn-outline-secondary mb-0"><i class="fas fa-external-link-alt mr-1"></i>Open Client Projects</a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table cl-table mb-0">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>URL</th>
                                    <th class="text-right">Value</th>
                                    <th>Status</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $projectSoft = ['Active' => 'success', 'Completed' => 'info', 'On Hold' => 'warning', 'Cancelled' => 'muted']; ?>
                                <?php foreach ($wonProjects as $p): ?>
                                    <tr>
                                        <td>
                                            <span class="cl-cell-main"><?= e($p['title']) ?></span>
                                            <?php if (!(int) $p['is_won']): ?><span class="badge-soft badge-soft--info ml-1">Demo/Trial</span><?php endif; ?>
                                            <?php if ($p['catalog_name']): ?><br><span class="cl-cell-sub"><?= e($p['catalog_name']) ?></span><?php endif; ?>
                                        </td>
                                        <td><?= $p['url'] ? '<a href="' . e($p['url']) . '" target="_blank" rel="noopener noreferrer">' . e($p['url']) . '</a>' : '—' ?></td>
                                        <td class="text-right"><span class="cl-money"><?= $p['value'] !== null ? 'NPR ' . e(formatMoney($p['value'])) : '—' ?></span></td>
                                        <td><span class="badge-soft badge-soft--<?= $projectSoft[$p['status']] ?? 'muted' ?>"><?= e($p['status']) ?></span></td>
                                        <td class="text-right">
                                            <?php if ($canManage): ?>
                                                <button type="button" class="btn btn-xs btn-outline-secondary" title="Edit" aria-label="Edit project <?= e($p['title']) ?>" onclick="openClientProjectDrawer(<?= (int) $p['id'] ?>)"><i class="fas fa-edit" aria-hidden="true"></i></button>
                                                <form action="operation.php?module=sales&page=client_projects" method="post" class="d-inline">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="delete_client_project">
                                                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                                    <input type="hidden" name="from_client_id" value="<?= (int) $edit['id'] ?>">
                                                    <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" aria-label="Delete project <?= e($p['title']) ?>?" data-confirm="Delete project <?= e($p['title']) ?>?"><i class="fas fa-trash" aria-hidden="true"></i></button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$wonProjects): ?><tr>
                                        <td colspan="5">
                                            <div class="tms-empty-state"><i class="fas fa-project-diagram" aria-hidden="true"></i>
                                                <p>This client has no projects yet.</p>
                                            </div>
                                        </td>
                                    </tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Linked Leads (detail) -->
            <div class="card card-outline mb-3" id="client-leads">
                <div class="card-header">
                    <h3 class="card-title"><span class="tms-chip tms-chip--warning"><i class="fas fa-link" aria-hidden="true"></i></span>Linked Leads</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table cl-table mb-0">
                            <thead>
                                <tr>
                                    <th>Lead</th>
                                    <th>Service</th>
                                    <th>Stage</th>
                                    <th>Priority</th>
                                    <th>Owner</th>
                                    <th>Added</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $stageSoft = ['New' => 'info', 'Contacted' => 'info', 'Qualified' => 'warning', 'Proposal' => 'muted', 'Won' => 'success', 'Lost' => 'danger']; ?>
                                <?php $prioritySoft = ['Hot' => 'danger', 'Warm' => 'warning', 'Cold' => 'muted']; ?>
                                <?php foreach ($sourceLeads as $l): ?>
                                    <tr>
                                        <td>
                                            <span class="cl-cell-main"><?= e($l['company'] ?: $l['contact_name'] ?: $l['referrer_name']) ?></span>
                                            <br><span class="cl-cell-sub"><?= e($l['contact_name'] ?: $l['referrer_name'] ?: '—') ?> · <?= e($l['email'] ?: '—') ?></span>
                                        </td>
                                        <td><?= e($l['service_interest'] ?: '—') ?></td>
                                        <td><span class="badge-soft badge-soft--<?= $stageSoft[$l['stage']] ?? 'muted' ?>"><?= e($l['stage']) ?></span></td>
                                        <td><span class="badge-soft badge-soft--<?= $prioritySoft[$l['priority']] ?? 'muted' ?>"><?= e($l['priority']) ?></span></td>
                                        <td><?= e($l['owner_name'] ?: '—') ?></td>
                                        <td><?= e(date('M j, Y', strtotime($l['added_on']))) ?></td>
                                        <td class="text-right">
                                            <a href="<?= pageUrl('sales', 'leads') ?>&id=<?= (int) $l['id'] ?>" class="btn btn-xs btn-outline-primary" aria-label="View lead <?= e($l['company'] ?: $l['contact_name'] ?: $l['referrer_name']) ?>"><i class="fas fa-eye" aria-hidden="true"></i></a>
                                            <?php if ($canManage): ?>
                                                <form action="operation.php?module=sales&page=leads" method="post" class="d-inline">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="delete_lead">
                                                    <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                                                    <input type="hidden" name="from_client_id" value="<?= (int) $edit['id'] ?>">
                                                    <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" title="Delete" aria-label="Delete lead <?= e($l['company'] ?: $l['contact_name'] ?: $l['referrer_name']) ?>?" data-confirm="Delete this lead? Files and activities are removed."><i class="fas fa-trash" aria-hidden="true"></i></button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$sourceLeads): ?><tr>
                                        <td colspan="7">
                                            <div class="tms-empty-state"><i class="fas fa-link" aria-hidden="true"></i>
                                                <p>This client has no linked leads.</p>
                                            </div>
                                        </td>
                                    </tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Quotations -->
            <div class="card card-outline mb-3" id="client-quotations">
                <div class="card-header">
                    <h3 class="card-title"><span class="tms-chip tms-chip--muted"><i class="fas fa-file-invoice" aria-hidden="true"></i></span>Quotations</h3>
                    <div class="card-tools">
                        <a href="<?= pageUrl('sales', 'documents') ?>&type=quotation&add=1&client_id=<?= (int) $edit['id'] ?>" class="btn btn-outline-primary btn-sm"><i class="fas fa-plus mr-1"></i>New Quotation</a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table cl-table mb-0">
                            <thead>
                                <tr>
                                    <th>Quotation</th>
                                    <th>Date</th>
                                    <th class="text-right">Total</th>
                                    <th>Status</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $statusSoft = ['Draft' => 'muted', 'Sent' => 'info', 'Accepted' => 'success', 'Rejected' => 'danger', 'Expired' => 'warning']; ?>
                                <?php foreach ($quotations as $q): ?>
                                    <tr>
                                        <td>
                                            <span class="cl-cell-main"><?= e($q['document_number']) ?></span>
                                            <?php if ($q['subject']): ?><br><span class="cl-cell-sub"><?= e(mb_strimwidth($q['subject'], 0, 44, '…')) ?></span><?php endif; ?>
                                        </td>
                                        <td><?= e($q['document_date']) ?></td>
                                        <td class="text-right"><span class="cl-money">NPR <?= e(formatMoney($q['total'])) ?></span></td>
                                        <td><span class="badge-soft badge-soft--<?= $statusSoft[$q['status']] ?? 'muted' ?>"><?= e($q['status']) ?></span></td>
                                        <td class="text-right">
                                            <a href="<?= pageUrl('sales', 'documents') ?>&type=quotation&id=<?= (int) $q['id'] ?>" class="btn btn-xs btn-outline-primary" title="View" aria-label="View quotation <?= e($q['document_number']) ?>"><i class="fas fa-eye" aria-hidden="true"></i></a>
                                            <a href="<?= pageUrl('sales', 'documents') ?>&type=quotation&id=<?= (int) $q['id'] ?>&pdf=1" class="btn btn-xs btn-outline-danger" title="PDF" aria-label="Download quotation <?= e($q['document_number']) ?> PDF"><i class="fas fa-download" aria-hidden="true"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$quotations): ?><tr>
                                        <td colspan="5">
                                            <div class="tms-empty-state"><i class="fas fa-file-invoice" aria-hidden="true"></i>
                                                <p>No quotations for this client yet.</p>
                                            </div>
                                        </td>
                                    </tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Client Activity Timeline (aggregated across linked leads) -->
            <div class="card card-outline mb-3" id="client-activity">
                <div class="card-header">
                    <h3 class="card-title"><span class="tms-chip tms-chip--warning"><i class="fas fa-stream" aria-hidden="true"></i></span>Activity Timeline</h3>
                    <span class="badge-soft badge-soft--muted"><i class="fas fa-list-ol mr-1" aria-hidden="true"></i><?= count($clientActivities) ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if ($clientActivities): ?>
                        <?php $actDot = ['Call' => 'Call', 'Email' => 'Email', 'Meeting' => 'Meeting', 'Note' => 'Note', 'Status Change' => 'StatusChange', 'Task' => 'Task']; ?>
                        <?php $actSoft = ['Call' => 'info', 'Email' => 'info', 'Meeting' => 'warning', 'Note' => 'muted', 'Status Change' => 'success', 'Task' => 'danger']; ?>
                        <ol class="cl-timeline">
                            <?php foreach ($clientActivities as $a): ?>
                                <li class="cl-timeline-item">
                                    <span class="cl-timeline-dot cl-timeline-dot--<?= $actDot[$a['type']] ?? 'Note' ?>" aria-hidden="true"></span>
                                    <div class="cl-timeline-content">
                                        <div class="cl-timeline-head">
                                            <span class="badge-soft badge-soft--<?= $actSoft[$a['type']] ?? 'muted' ?>"><?= e($a['type']) ?></span>
                                            <span class="cl-timeline-title"><?= e($a['actor'] ?? '—') ?><?= $a['lead_title'] ? ' · ' . e($a['lead_title']) : '' ?></span>
                                            <time class="cl-timeline-time" datetime="<?= e($a['added_on']) ?>"><?= e(date('M j, g:i A', strtotime($a['added_on']))) ?></time>
                                        </div>
                                        <?php if ($a['note']): ?><div class="cl-timeline-note"><?= nl2br(e($a['note'])) ?></div><?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php else: ?>
                        <div class="tms-empty-state"><i class="fas fa-inbox" aria-hidden="true"></i>
                            <p>No activity yet across this client's leads.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- About -->
            <div class="card card-outline mb-3">
                <div class="card-header">
                    <h3 class="card-title"><span class="tms-chip tms-chip--muted"><i class="fas fa-address-card" aria-hidden="true"></i></span>About</h3>
                </div>
                <div class="card-body">
                    <div class="cl-facts cl-facts--stacked">
                        <?php if ($originLead): ?>
                            <div class="cl-fact cl-fact--accent">
                                <span class="cl-fact-icon"><i class="fas fa-route" aria-hidden="true"></i></span>
                                <div class="cl-fact-body">
                                    <span class="cl-fact-label">Origin Lead</span>
                                    <span class="cl-fact-value"><a href="<?= pageUrl('sales', 'leads') ?>&id=<?= (int) $originLead['id'] ?>"><?= e($originLead['company'] ?: $originLead['contact_name'] ?: ($originLead['referrer_name'] ?? '')) ?></a></span>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="cl-fact">
                            <span class="cl-fact-icon"><i class="fas fa-calendar-alt" aria-hidden="true"></i></span>
                            <div class="cl-fact-body">
                                <span class="cl-fact-label">Client Since</span>
                                <span class="cl-fact-value"><?= e(date('M j, Y', strtotime($edit['added_on']))) ?><?= $creator ? ' <span class="cl-cell-sub">· ' . e($creator['fullname']) . '</span>' : '' ?></span>
                            </div>
                        </div>
                        <?php if ($edit['updated_on'] && $edit['updated_on'] !== $edit['added_on']): ?>
                            <div class="cl-fact">
                                <span class="cl-fact-icon"><i class="fas fa-history" aria-hidden="true"></i></span>
                                <div class="cl-fact-body">
                                    <span class="cl-fact-label">Last Updated</span>
                                    <span class="cl-fact-value"><?= e(date('M j, g:i A', strtotime($edit['updated_on']))) ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="cl-fact">
                            <span class="cl-fact-icon"><i class="fas fa-hashtag" aria-hidden="true"></i></span>
                            <div class="cl-fact-body">
                                <span class="cl-fact-label">PAN No</span>
                                <span class="cl-fact-value"><?= $edit['pan_num'] ? '<code>' . e($edit['pan_num']) . '</code>' : '—' ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card card-outline mb-3">
                <div class="card-header">
                    <h3 class="card-title"><span class="tms-chip tms-chip--primary"><i class="fas fa-bolt" aria-hidden="true"></i></span>Quick Actions</h3>
                </div>
                <div class="card-body p-0">
                    <ul class="cl-quick">
                        <?php if ($canManage): ?>
                            <li>
                                <button type="button" class="cl-quick-item" onclick="openSourceDrawer(<?= (int) $edit['id'] ?>)">
                                    <span class="tms-chip tms-chip--primary"><i class="fas fa-edit" aria-hidden="true"></i></span>
                                    <span class="cl-quick-item-text"><span class="cl-quick-item-label">Edit Client</span><br><span class="cl-quick-item-sub">Profile, PAN &amp; notes</span></span>
                                    <i class="fas fa-chevron-right cl-quick-item-chevron" aria-hidden="true"></i>
                                </button>
                            </li>
                            <li>
                                <button type="button" class="cl-quick-item" onclick="openContactDrawer()">
                                    <span class="tms-chip tms-chip--success"><i class="fas fa-user-plus" aria-hidden="true"></i></span>
                                    <span class="cl-quick-item-text"><span class="cl-quick-item-label">Add Contact Person</span><br><span class="cl-quick-item-sub">Team member for this client</span></span>
                                    <i class="fas fa-chevron-right cl-quick-item-chevron" aria-hidden="true"></i>
                                </button>
                            </li>
                            <li>
                                <form action="operation.php?module=sales&page=clients" method="post">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_client">
                                    <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
                                    <button type="submit" class="cl-quick-item cl-quick-item--danger confirm-submit" data-confirm="Delete client <?= e($edit['name']) ?> and all related data?">
                                        <span class="tms-chip tms-chip--danger"><i class="fas fa-trash" aria-hidden="true"></i></span>
                                        <span class="cl-quick-item-text"><span class="cl-quick-item-label">Delete Client</span><br><span class="cl-quick-item-sub">Removes client &amp; related data</span></span>
                                        <i class="fas fa-chevron-right cl-quick-item-chevron" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </li>
                        <?php endif; ?>
                        <li>
                            <a class="cl-quick-item" href="<?= pageUrl('sales', 'documents') ?>&type=quotation&add=1&client_id=<?= (int) $edit['id'] ?>">
                                <span class="tms-chip tms-chip--warning"><i class="fas fa-file-invoice" aria-hidden="true"></i></span>
                                <span class="cl-quick-item-text"><span class="cl-quick-item-label">New Quotation</span><br><span class="cl-quick-item-sub">Create a quotation for this client</span></span>
                                <i class="fas fa-chevron-right cl-quick-item-chevron" aria-hidden="true"></i>
                            </a>
                        </li>
                        <li>
                            <a class="cl-quick-item" href="<?= pageUrl('sales', 'client_projects') ?>&source=<?= (int) $edit['id'] ?>">
                                <span class="tms-chip tms-chip--info"><i class="fas fa-folder-open" aria-hidden="true"></i></span>
                                <span class="cl-quick-item-text"><span class="cl-quick-item-label">Client Projects</span><br><span class="cl-quick-item-sub">Modules, DB &amp; access</span></span>
                                <i class="fas fa-chevron-right cl-quick-item-chevron" aria-hidden="true"></i>
                            </a>
                        </li>
                        <li>
                            <a class="cl-quick-item" href="<?= pageUrl('sales', 'leads') ?>">
                                <span class="tms-chip tms-chip--muted"><i class="fas fa-search" aria-hidden="true"></i></span>
                                <span class="cl-quick-item-text"><span class="cl-quick-item-label">Browse All Leads</span><br><span class="cl-quick-item-sub">Full lead pipeline</span></span>
                                <i class="fas fa-chevron-right cl-quick-item-chevron" aria-hidden="true"></i>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ═══ Project Drawer (detail page) ═══ -->
<?php if ($canManage): ?>
    <div class="cms-drawer" id="clientProjectDrawer">
        <div class="cms-drawer-header">
            <h3><i class="fas fa-project-diagram"></i><span id="cpDetailDrawerTitle">Add Client Project</span></h3>
            <button type="button" class="cms-drawer-close" onclick="closeAllDrawers()" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <div class="cms-drawer-body">
            <form action="operation.php?module=sales&page=client_projects" method="post" id="cpDetailForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save_client_project">
                <input type="hidden" name="id" id="cpDetailId" value="0">
                <input type="hidden" name="client_id" id="cpDetailClientId" value="<?= (int) $edit['id'] ?>">
                <input type="hidden" name="from_client_id" value="<?= (int) $edit['id'] ?>">

                <h6 class="text-muted text-uppercase mb-2" style="font-size:.7rem;letter-spacing:.05em">Project Details</h6>
                <div class="row">
                    <div class="col-6 form-group">
                        <label class="small font-weight-bold">Product</label>
                        <select name="project_id" id="cpDetailCatalog" class="form-control form-control-sm">
                            <option value="">— None —</option>
                            <?php foreach ($catalogJson as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 form-group">
                        <label class="small font-weight-bold">Title *</label>
                        <input type="text" name="title" id="cpDetailTitle" class="form-control form-control-sm" required value="<?= e(old_scoped('save_client_project:0', 'title')) ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-6 form-group">
                        <label class="small font-weight-bold">Package</label>
                        <input type="text" name="package" id="cpDetailPackage" class="form-control form-control-sm" value="<?= e(old_scoped('save_client_project:0', 'package')) ?>">
                    </div>
                    <div class="col-6 form-group">
                        <label class="small font-weight-bold">URL</label>
                        <input type="text" name="url" id="cpDetailUrl" class="form-control form-control-sm" placeholder="https://project.example.com" value="<?= e(old_scoped('save_client_project:0', 'url')) ?>">
                    </div>
                </div>

                <hr class="my-3">
                <h6 class="text-muted text-uppercase mb-2" style="font-size:.7rem;letter-spacing:.05em">Financials & Dates</h6>
                <div class="row">
                    <div class="col-6 form-group">
                        <label class="small font-weight-bold">Value (NPR)</label>
                        <input type="number" name="value" id="cpDetailValue" class="form-control form-control-sm" step="0.01" min="0" placeholder="0.00" value="<?= e(old_scoped('save_client_project:0', 'value')) ?>">
                    </div>
                    <div class="col-6 form-group">
                        <label class="small font-weight-bold">Status</label>
                        <select name="status" id="cpDetailStatus" class="form-control form-control-sm">
                            <?php foreach (['Active', 'Completed', 'On Hold', 'Cancelled'] as $s): ?>
                                <option value="<?= $s ?>"><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6 form-group">
                        <label class="small font-weight-bold">Start</label>
                        <input type="date" name="start_date" id="cpDetailStart" class="form-control form-control-sm" value="<?= e(old_scoped('save_client_project:0', 'start_date')) ?>">
                    </div>
                    <div class="col-6 form-group">
                        <label class="small font-weight-bold">End</label>
                        <input type="date" name="end_date" id="cpDetailEnd" class="form-control form-control-sm" value="<?= e(old_scoped('save_client_project:0', 'end_date')) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <input type="hidden" name="is_won" value="0">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" name="is_won" id="cpDetailIsWon" value="1" checked>
                        <label class="custom-control-label small" for="cpDetailIsWon">Won deal <span class="text-muted">(untick for demo/trial)</span></label>
                    </div>
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Description</label>
                    <textarea name="description" id="cpDetailDescription" class="form-control form-control-sm" rows="2"><?= e(old_scoped('save_client_project:0', 'description')) ?></textarea>
                </div>
            </form>
        </div>
        <div class="cms-drawer-footer">
            <button type="submit" form="cpDetailForm" class="btn btn-primary btn-block"><i class="fas fa-save mr-1"></i><span id="cpDetailSubmitText">Add Client Project</span></button>
        </div>
    </div>
<?php endif; ?>

<!-- ═══ Record Payment Drawer ═══ -->
<?php if ($canPay): ?>
    <?php $payOld = static fn (string $k, $default = '') => old_scoped('save_payment:' . (int) $edit['id'], $k, $default); ?>
    <div class="cms-drawer" id="paymentDrawer">
        <div class="cms-drawer-header">
            <h3><i class="fas fa-hand-holding-usd"></i><span>Record Payment</span></h3>
            <button type="button" class="cms-drawer-close" onclick="closeAllDrawers()" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <div class="cms-drawer-body">
            <form action="operation.php?module=sales&page=clients" method="post" id="paymentForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save_payment">
                <input type="hidden" name="_scope" value="save_payment:<?= (int) $edit['id'] ?>">
                <input type="hidden" name="client_id" value="<?= (int) $edit['id'] ?>">
                <div class="form-group">
                    <label for="payInvoice">Paying for</label>
                    <select name="document_id" id="payInvoice" class="form-control" onchange="fillPaymentAmount()">
                        <option value="0" data-left="">Advance / on account (no invoice)</option>
                        <?php foreach ($payableInvoices as $inv): ?>
                            <?php $left = round((float) $inv['total'] - (float) $inv['paid'], 2); ?>
                            <option value="<?= (int) $inv['id'] ?>" data-left="<?= e(number_format($left, 2, '.', '')) ?>" <?= (int) $payOld('document_id', 0) === (int) $inv['id'] ? 'selected' : '' ?>>
                                <?= e($inv['document_number']) ?> — NPR <?= e(formatMoney($left)) ?> left<?= $inv['due_date'] ? ' · due ' . e($inv['due_date']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">Picking an invoice marks it Partially Paid or Paid automatically.</small>
                </div>
                <div class="row">
                    <div class="col-6 form-group">
                        <label for="payDate">Date *</label>
                        <input type="date" name="payment_date" id="payDate" class="form-control" required max="<?= e(date('Y-m-d')) ?>" value="<?= e($payOld('payment_date', date('Y-m-d'))) ?>">
                    </div>
                    <div class="col-6 form-group">
                        <label for="payAmount">Amount (NPR) *</label>
                        <input type="number" name="amount" id="payAmount" class="form-control" required step="0.01" min="0.01" value="<?= e($payOld('amount')) ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-6 form-group">
                        <label for="payMethod">Method *</label>
                        <select name="method" id="payMethod" class="form-control">
                            <?php foreach (client_payment_methods() as $m): ?>
                                <option value="<?= e($m) ?>" <?= $payOld('method', 'Bank Transfer') === $m ? 'selected' : '' ?>><?= e($m === 'Wallet' ? 'Wallet (eSewa / Khalti)' : $m) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 form-group">
                        <label for="payRef">Reference</label>
                        <input type="text" name="reference_no" id="payRef" class="form-control" maxlength="128" placeholder="Cheque / txn no." value="<?= e($payOld('reference_no')) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="payNotes">Notes</label>
                    <textarea name="notes" id="payNotes" class="form-control" rows="2" maxlength="1024"><?= e($payOld('notes')) ?></textarea>
                </div>

                <hr class="my-3">
                <div class="custom-control custom-checkbox mb-2">
                    <input type="checkbox" class="custom-control-input" name="post_to_accounts" id="payPost" value="1" <?= old_checkbox('save_payment:' . (int) $edit['id'], 'post_to_accounts', true) ? 'checked' : '' ?> onchange="togglePaymentAccounts()">
                    <label class="custom-control-label" for="payPost">Post to Accounts <span class="text-muted">(pending receipt voucher)</span></label>
                </div>
                <div id="payAccounts">
                    <div class="form-group">
                        <label for="payDeposit">Received into (debit)</label>
                        <select name="deposit_terminal_id" id="payDeposit" class="form-control">
                            <?php $depSel = (int) $payOld('deposit_terminal_id', $defaultDeposit['id'] ?? 0); ?>
                            <?php foreach ($accountTree as $group => $subs): ?>
                                <?php foreach ($subs as $sub => $terms): ?>
                                    <optgroup label="<?= e($group . ' › ' . $sub) ?>">
                                        <?php foreach ($terms as $t): ?>
                                            <option value="<?= (int) $t['id'] ?>" <?= $depSel === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['title']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="payCredit">Credit account</label>
                        <select name="credit_terminal_id" id="payCredit" class="form-control">
                            <?php $crSel = (int) $payOld('credit_terminal_id', $defaultCredit['id'] ?? 0); ?>
                            <?php foreach ($accountTree as $group => $subs): ?>
                                <?php foreach ($subs as $sub => $terms): ?>
                                    <optgroup label="<?= e($group . ' › ' . $sub) ?>">
                                        <?php foreach ($terms as $t): ?>
                                            <option value="<?= (int) $t['id'] ?>" <?= $crSel === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['title']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Usually the income account (e.g. Maintenance &amp; Support Income for AMC). Use Accounts Receivable only if the invoice was already booked as a sales voucher.</small>
                    </div>
                </div>
            </form>
        </div>
        <div class="cms-drawer-footer">
            <button type="submit" form="paymentForm" class="btn btn-success btn-block"><i class="fas fa-save mr-1"></i>Save Payment</button>
        </div>
    </div>
<?php endif; ?>

<!-- Drawer Backdrop -->
<div class="cms-drawer-backdrop" id="drawerBackdrop" onclick="closeAllDrawers()"></div>

<!-- Client Drawer -->
<div class="cms-drawer" id="sourceDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas fa-handshake"></i><span id="sourceDrawerTitle">Add Client</span></h3>
        <button type="button" class="cms-drawer-close" onclick="closeAllDrawers()" aria-label="Close"><i class="fas fa-times"></i></button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=sales&page=clients" method="post" id="sourceForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_client">
            <input type="hidden" name="id" id="sourceId" value="<?= (int) $edit['id'] ?>">
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" id="sourceName" class="form-control" required value="<?= e(old_scoped('save_client:' . (int) $edit['id'], 'name', $edit['name'])) ?>">
            </div>
            <div class="form-group">
                <label>Type</label>
                <select name="type" id="sourceType" class="form-control">
                    <?php foreach (['Company', 'Individual'] as $t): ?>
                        <option value="<?= $t ?>" <?= old_scoped('save_client:' . (int) $edit['id'], 'type', $edit['type'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Primary contact person</label>
                <input type="text" name="contact_person" id="sourceContact" class="form-control" value="<?= e(old_scoped('save_client:' . (int) $edit['id'], 'contact_person', $edit['contact_person'])) ?>">
                <small class="form-text text-muted">Mirrors the primary Contact Person entry below.</small>
            </div>
            <div class="row">
                <div class="col-6 form-group"><label>Email</label><input type="email" name="email" id="sourceEmail" class="form-control" value="<?= e(old_scoped('save_client:' . (int) $edit['id'], 'email', $edit['email'])) ?>"></div>
                <div class="col-6 form-group"><label>Phone</label><input type="text" name="phone" id="sourcePhone" class="form-control" value="<?= e(old_scoped('save_client:' . (int) $edit['id'], 'phone', $edit['phone'])) ?>"></div>
            </div>
            <div class="form-group"><label>Address</label><input type="text" name="address" id="sourceAddress" class="form-control" value="<?= e(old_scoped('save_client:' . (int) $edit['id'], 'address', $edit['address'])) ?>"></div>
            <div class="form-group"><label>PAN no</label><input type="text" name="pan_num" id="sourcePan" class="form-control" value="<?= e(old_scoped('save_client:' . (int) $edit['id'], 'pan_num', $edit['pan_num'])) ?>"></div>
            <div class="form-group"><label>Notes</label><textarea name="notes" id="sourceNotes" class="form-control" rows="2"><?= e(old_scoped('save_client:' . (int) $edit['id'], 'notes', $edit['notes'])) ?></textarea></div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="sourceForm" class="btn btn-primary btn-block"><i class="fas fa-save mr-1"></i><span id="sourceDrawerSubmit">Add Client</span></button>
    </div>
</div>

<!-- Contact Drawer -->
<?php if ($canManage): ?>
    <div class="cms-drawer" id="contactDrawer">
        <div class="cms-drawer-header">
            <h3><i class="fas fa-id-card"></i><span id="contactDrawerTitle">Add Contact</span></h3>
            <button type="button" class="cms-drawer-close" onclick="closeAllDrawers()" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <div class="cms-drawer-body">
            <form action="operation.php?module=sales&page=clients" method="post" id="contactForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save_contact">
                <input type="hidden" name="id" id="contactId" value="0">
                <input type="hidden" name="client_id" id="contactSourceId" value="<?= (int) $edit['id'] ?>">
                <div class="form-group"><label>Name *</label><input type="text" name="name" id="contactName" class="form-control" required value="<?= e(old_scoped('save_contact:0', 'name')) ?>"></div>
                <div class="form-group"><label>Designation</label><input type="text" name="designation" id="contactDesignation" class="form-control" value="<?= e(old_scoped('save_contact:0', 'designation')) ?>"></div>
                <div class="row">
                    <div class="col-6 form-group"><label>Email</label><input type="email" name="email" id="contactEmail" class="form-control" value="<?= e(old_scoped('save_contact:0', 'email')) ?>"></div>
                    <div class="col-6 form-group"><label>Phone</label><input type="text" name="phone" id="contactPhone" class="form-control" value="<?= e(old_scoped('save_contact:0', 'phone')) ?>"></div>
                </div>
                <div class="form-group"><label>Notes</label><textarea name="notes" id="contactNotes" class="form-control" rows="2"><?= e(old_scoped('save_contact:0', 'notes')) ?></textarea></div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_primary" id="contactPrimary" value="1" <?= old_checkbox('save_contact:0', 'is_primary', false) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="contactPrimary">Set as primary contact</label>
                </div>
            </form>
        </div>
        <div class="cms-drawer-footer">
            <button type="submit" form="contactForm" class="btn btn-primary btn-block"><i class="fas fa-save mr-1"></i><span id="contactDrawerSubmit">Add Contact</span></button>
        </div>
    </div>
<?php endif; ?>

<script>
    var sourcesData = <?= json_encode($sourcesJson) ?>;
    var contactsData = <?= json_encode($contactsJson) ?>;
    var wonProjectsData = <?= json_encode($wonProjectsJson) ?>;
    var currentClientId = <?= (int) $edit['id'] ?>;

    /* ═══ Client Drawer ═══ */
    function fillSourceForm(source) {
        document.getElementById('sourceName').value = source ? (source.name || '') : '';
        document.getElementById('sourceType').value = source ? (source.type || 'Company') : 'Company';
        document.getElementById('sourceContact').value = source ? (source.contact_person || '') : '';
        document.getElementById('sourceEmail').value = source ? (source.email || '') : '';
        document.getElementById('sourcePhone').value = source ? (source.phone || '') : '';
        document.getElementById('sourceAddress').value = source ? (source.address || '') : '';
        document.getElementById('sourcePan').value = source ? (source.pan_num || '') : '';
        document.getElementById('sourceNotes').value = source ? (source.notes || '') : '';
    }

    function openSourceDrawer(sourceId) {
        closeAllDrawers();
        document.getElementById('drawerBackdrop').classList.add('active');
        document.getElementById('sourceDrawer').classList.add('open');
        document.body.style.overflow = 'hidden';

        document.getElementById('sourceId').value = sourceId ? sourceId : 0;
        var source = null;
        if (sourceId) {
            for (var i = 0; i < sourcesData.length; i++) {
                if (sourcesData[i].id == sourceId) {
                    source = sourcesData[i];
                    break;
                }
            }
        }
        fillSourceForm(source);
        document.getElementById('sourceDrawerTitle').textContent = source ? 'Edit Client' : 'Add Client';
        document.getElementById('sourceDrawerSubmit').textContent = source ? 'Update Client' : 'Add Client';
    }

    /* ═══ Contact Drawer ═══ */
    function fillContactForm(contact) {
        document.getElementById('contactId').value = contact ? contact.id : 0;
        document.getElementById('contactName').value = contact ? (contact.name || '') : '';
        document.getElementById('contactDesignation').value = contact ? (contact.designation || '') : '';
        document.getElementById('contactEmail').value = contact ? (contact.email || '') : '';
        document.getElementById('contactPhone').value = contact ? (contact.phone || '') : '';
        document.getElementById('contactNotes').value = contact ? (contact.notes || '') : '';
        document.getElementById('contactPrimary').checked = contact ? (contact.is_primary == 1) : false;
    }

    function openContactDrawer(contactId) {
        closeAllDrawers();
        document.getElementById('drawerBackdrop').classList.add('active');
        document.getElementById('contactDrawer').classList.add('open');
        document.body.style.overflow = 'hidden';

        var contact = null;
        if (contactId) {
            for (var i = 0; i < contactsData.length; i++) {
                if (contactsData[i].id == contactId) {
                    contact = contactsData[i];
                    break;
                }
            }
        }
        fillContactForm(contact);
        document.getElementById('contactDrawerTitle').textContent = contact ? 'Edit Contact' : 'Add Contact';
        document.getElementById('contactDrawerSubmit').textContent = contact ? 'Update Contact' : 'Add Contact';
    }

    /* ═══ Client Project Drawer ═══ */
    function openClientProjectDrawer(projectId) {
        closeAllDrawers();
        document.getElementById('drawerBackdrop').classList.add('active');
        document.getElementById('clientProjectDrawer').classList.add('open');
        document.body.style.overflow = 'hidden';

        var p = null;
        if (projectId) {
            for (var i = 0; i < wonProjectsData.length; i++) {
                if (wonProjectsData[i].id == projectId) {
                    p = wonProjectsData[i];
                    break;
                }
            }
        }
        document.getElementById('cpDetailId').value = p ? p.id : 0;
        document.getElementById('cpDetailClientId').value = currentClientId;
        document.getElementById('cpDetailCatalog').value = p ? (p.project_id || '') : '';
        document.getElementById('cpDetailTitle').value = p ? (p.title || '') : '';
        document.getElementById('cpDetailPackage').value = p ? (p.package || '') : '';
        document.getElementById('cpDetailUrl').value = p ? (p.url || '') : '';
        document.getElementById('cpDetailValue').value = p ? (p.value || '') : '';
        document.getElementById('cpDetailStatus').value = p ? (p.status || 'Active') : 'Active';
        document.getElementById('cpDetailIsWon').checked = p ? p.is_won === 1 : true;
        document.getElementById('cpDetailStart').value = p ? (p.start_date || '') : '';
        document.getElementById('cpDetailEnd').value = p ? (p.end_date || '') : '';
        document.getElementById('cpDetailDescription').value = p ? (p.description || '') : '';
        document.getElementById('cpDetailDrawerTitle').textContent = p ? 'Edit Client Project' : 'Add Client Project';
        document.getElementById('cpDetailSubmitText').textContent = p ? 'Update Client Project' : 'Add Client Project';
    }

    /* ═══ Record Payment Drawer ═══ */
    function fillPaymentAmount() {
        var sel = document.getElementById('payInvoice');
        var left = sel.options[sel.selectedIndex].getAttribute('data-left');
        var amount = document.getElementById('payAmount');
        amount.value = left || '';
        amount.max = left || '';
    }

    function togglePaymentAccounts() {
        document.getElementById('payAccounts').style.display = document.getElementById('payPost').checked ? '' : 'none';
    }

    function openPaymentDrawer(invoiceId) {
        closeAllDrawers();
        document.getElementById('drawerBackdrop').classList.add('active');
        document.getElementById('paymentDrawer').classList.add('open');
        document.body.style.overflow = 'hidden';
        if (invoiceId !== undefined) {
            document.getElementById('payInvoice').value = String(invoiceId);
            fillPaymentAmount();
        }
        togglePaymentAccounts();
    }

    /* ═══ Shared Drawer Controls ═══ */
    function closeAllDrawers() {
        document.querySelectorAll('.cms-drawer').forEach(function(d) {
            d.classList.remove('open');
        });
        var bd = document.getElementById('drawerBackdrop');
        if (bd) bd.classList.remove('active');
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeAllDrawers();
    });

    <?php if ($canPay && ($openPaymentDrawer || old_scoped('save_payment:' . (int) $edit['id'], 'amount', '') !== '')): ?>
    // Opened from the client list (?pay=1) or re-opened after a failed save.
    document.addEventListener('DOMContentLoaded', function() { openPaymentDrawer(); });
    <?php endif; ?>
</script>