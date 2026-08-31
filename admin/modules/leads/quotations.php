<?php
/**
 * SB-Tech — Quotations (Office Setup → Quotations).
 *
 * Full quotation lifecycle: list, add/edit, detail view, and print/PDF-ready
 * document shell. Uses documentShellStart/documentShellEnd from functions/documents.php
 * for consistent header, footer, watermark, and page geometry.
 */
$db = Database::instance();
$me = (int) Auth::id();
$canManage = Auth::isSuperAdmin() || Auth::hasSpecial('manage_office');

// Fetch clients for dropdown
$clients = $db->select("SELECT id, name, contact_person, email, phone, address FROM `tbl_clients` ORDER BY name");

// Fetch services for item suggestions
$services = $db->select("SELECT id, name AS title, selling_price AS sale_price FROM `tbl_inv_items` WHERE is_active = 1 ORDER BY name");

// Generate next quotation number
function nextQuotationNumber(Database $db): string
{
    $year = date('Y');
    $last = $db->selectOne(
        "SELECT quotation_number FROM `tbl_quotations` WHERE quotation_number LIKE ? ORDER BY id DESC LIMIT 1",
        ["QTN-$year-%"]
    );
    if ($last) {
        $parts = explode('-', $last['quotation_number']);
        $seq = (int) end($parts) + 1;
    } else {
        $seq = 1;
    }
    return sprintf('QTN-%s-%04d', $year, $seq);
}

// --------------------------------------------------------------- detail/print
if (isset($_GET['id'])) {
    $quotation = $db->selectOne(
        'SELECT q.*, c.name AS client_name_full, c.contact_person AS client_contact,
                u.fullname AS created_by_name
         FROM `tbl_quotations` q
         LEFT JOIN `tbl_clients` c ON c.id = q.client_id
         LEFT JOIN `tbl_users_login` u ON u.id = q.added_by
         WHERE q.id = ?',
        [(int) $_GET['id']]
    );
    if (!$quotation) {
        echo '<div class="callout callout-danger"><h5>Quotation not found</h5></div>';
        return;
    }
    $items = $db->select(
        'SELECT * FROM `tbl_quotation_items` WHERE quotation_id = ? ORDER BY sort_order, id',
        [(int) $quotation['id']]
    );
    $files = $db->select(
        'SELECT * FROM `tbl_quotation_files` WHERE quotation_id = ? ORDER BY added_on DESC',
        [(int) $quotation['id']]
    );

    // Print / PDF / Word / Preview mode: render document shell only
    $isPrint  = !empty($_GET['print']);
    $isPdf    = !empty($_GET['pdf']);
    $isPreview = !empty($_GET['preview']);
    $isWord   = !empty($_GET['word']);
    if ($isPrint || $isPdf || $isPreview || $isWord) {
        ob_start();
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Quotation ' . e($quotation['quotation_number']) . '</title></head><body>';
        documentShellStart('Quotation', e($quotation['quotation_number']));
        ?>
        <table style="width:100%;margin-bottom:12px;font-size:.9em">
            <tr>
                <td style="width:50%;vertical-align:top">
                    <strong>To:</strong><br>
                    <?= e($quotation['client_name']) ?><br>
                    <?php if ($quotation['client_email']): ?><?= e($quotation['client_email']) ?><br><?php endif; ?>
                    <?php if ($quotation['client_phone']): ?><?= e($quotation['client_phone']) ?><br><?php endif; ?>
                    <?php if ($quotation['client_address']): ?><?= nl2br(e($quotation['client_address'])) ?><?php endif; ?>
                </td>
                <td style="width:50%;vertical-align:top;text-align:right">
                    <strong>Date:</strong> <?= e($quotation['quotation_date']) ?><br>
                    <?php if ($quotation['valid_until']): ?><strong>Valid Until:</strong> <?= e($quotation['valid_until']) ?><br><?php endif; ?>
                    <strong>Status:</strong> <?= e($quotation['status']) ?>
                </td>
            </tr>
        </table>

        <p style="margin:0 0 12px"><strong>Subject:</strong> <?= e($quotation['subject']) ?></p>

        <table style="width:100%;border-collapse:collapse;margin-bottom:16px;font-size:.9em">
            <thead>
                <tr style="background:#f3f4f6">
                    <th style="border:1px solid #d1d5db;padding:6px 8px;text-align:left">#</th>
                    <th style="border:1px solid #d1d5db;padding:6px 8px;text-align:left">Item</th>
                    <th style="border:1px solid #d1d5db;padding:6px 8px;text-align:center">Qty</th>
                    <th style="border:1px solid #d1d5db;padding:6px 8px;text-align:right">Unit Price</th>
                    <th style="border:1px solid #d1d5db;padding:6px 8px;text-align:right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $i => $item): ?>
                    <tr>
                        <td style="border:1px solid #d1d5db;padding:6px 8px"><?= $i + 1 ?></td>
                        <td style="border:1px solid #d1d5db;padding:6px 8px">
                            <strong><?= e($item['item_name']) ?></strong>
                            <?php if ($item['description']): ?><br><small style="color:#6b7280"><?= e($item['description']) ?></small><?php endif; ?>
                        </td>
                        <td style="border:1px solid #d1d5db;padding:6px 8px;text-align:center"><?= e($item['quantity']) ?> <?= e($item['unit'] ?? '') ?></td>
                        <td style="border:1px solid #d1d5db;padding:6px 8px;text-align:right">NPR <?= e(formatMoney($item['unit_price'])) ?></td>
                        <td style="border:1px solid #d1d5db;padding:6px 8px;text-align:right">NPR <?= e(formatMoney($item['amount'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <table style="width:300px;margin-left:auto;margin-bottom:16px;font-size:.9em">
            <tr>
                <td style="padding:4px 8px;text-align:right"><strong>Subtotal:</strong></td>
                <td style="padding:4px 8px;text-align:right">NPR <?= e(formatMoney($quotation['subtotal'])) ?></td>
            </tr>
            <?php if ($quotation['discount_value']): ?>
                <tr>
                    <td style="padding:4px 8px;text-align:right">Discount (<?= $quotation['discount_type'] === 'percentage' ? e($quotation['discount_value']) . '%' : 'Fixed' ?>):</td>
                    <td style="padding:4px 8px;text-align:right">- NPR <?= e(formatMoney($quotation['discount_type'] === 'percentage' ? $quotation['subtotal'] * $quotation['discount_value'] / 100 : $quotation['discount_value'])) ?></td>
                </tr>
            <?php endif; ?>
            <?php if ($quotation['tax_value']): ?>
                <tr>
                    <td style="padding:4px 8px;text-align:right">Tax (<?= $quotation['tax_type'] === 'percentage' ? e($quotation['tax_value']) . '%' : 'Fixed' ?>):</td>
                    <td style="padding:4px 8px;text-align:right">NPR <?= e(formatMoney($quotation['tax_type'] === 'percentage' ? ($quotation['subtotal'] - ($quotation['discount_type'] === 'percentage' ? $quotation['subtotal'] * $quotation['discount_value'] / 100 : ($quotation['discount_value'] ?? 0))) * $quotation['tax_value'] / 100 : $quotation['tax_value'])) ?></td>
                </tr>
            <?php endif; ?>
            <tr style="border-top:2px solid #1f2937">
                <td style="padding:6px 8px;text-align:right;font-weight:700">Total:</td>
                <td style="padding:6px 8px;text-align:right;font-weight:700">NPR <?= e(formatMoney($quotation['total'])) ?></td>
            </tr>
        </table>

        <?php if ($quotation['notes']): ?>
            <p style="margin:0 0 8px"><strong>Notes:</strong></p>
            <p style="margin:0 0 12px"><?= nl2br(e($quotation['notes'])) ?></p>
        <?php endif; ?>

        <?php if ($quotation['terms']): ?>
            <p style="margin:0 0 8px"><strong>Terms &amp; Conditions:</strong></p>
            <p style="margin:0 0 12px"><?= nl2br(e($quotation['terms'])) ?></p>
        <?php endif; ?>

        <?php
        documentShellEnd();
        echo '</body></html>';

        if ($isPdf || $isPreview) {
            $html = ob_get_clean();
            $pdf = new PdfGenerator();
            $pdf->html($html);
            $filename = $quotation['quotation_number'] . '.pdf';
            if ($isPreview) {
                $pdf->inline($filename);
            } else {
                $pdf->download($filename);
            }
        }

        if ($isWord) {
            makeWord(ob_get_clean(), $quotation['quotation_number'], 'Quotation', e($quotation['quotation_number']));
        }

        ob_end_flush();
        return;
    }

    // Screen detail view
    ?>
    <div class="row">
        <div class="col-md-8">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-file-invoice mr-1"></i><?= e($quotation['quotation_number']) ?></h3>
                    <div class="card-tools">
                        <?php
                        $statusBadges = ['Draft' => 'secondary', 'Sent' => 'info', 'Accepted' => 'success', 'Rejected' => 'danger', 'Expired' => 'warning'];
                        ?>
                        <span class="badge badge-<?= $statusBadges[$quotation['status']] ?? 'secondary' ?> mr-1"><?= e($quotation['status']) ?></span>
                        <a href="<?= pageUrl('leads', 'quotations') ?>" class="btn btn-xs btn-default">Back to list</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Client:</strong> <?= e($quotation['client_name']) ?><br>
                            <?php if ($quotation['client_email']): ?><?= e($quotation['client_email']) ?><br><?php endif; ?>
                            <?php if ($quotation['client_phone']): ?><?= e($quotation['client_phone']) ?><br><?php endif; ?>
                            <?php if ($quotation['client_address']): ?><?= nl2br(e($quotation['client_address'])) ?><?php endif; ?>
                        </div>
                        <div class="col-md-6 text-right">
                            <strong>Date:</strong> <?= e($quotation['quotation_date']) ?><br>
                            <?php if ($quotation['valid_until']): ?><strong>Valid Until:</strong> <?= e($quotation['valid_until']) ?><br><?php endif; ?>
                            <strong>Created by:</strong> <?= e($quotation['created_by_name'] ?? '—') ?>
                        </div>
                    </div>

                    <p><strong>Subject:</strong> <?= e($quotation['subject']) ?></p>

                    <table class="table table-sm table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Item</th>
                                <th class="text-center">Qty</th>
                                <th class="text-right">Unit Price</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $i => $item): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td>
                                        <strong><?= e($item['item_name']) ?></strong>
                                        <?php if ($item['description']): ?><br><small class="text-muted"><?= e($item['description']) ?></small><?php endif; ?>
                                    </td>
                                    <td class="text-center"><?= e($item['quantity']) ?> <?= e($item['unit'] ?? '') ?></td>
                                    <td class="text-right">NPR <?= e(formatMoney($item['unit_price'])) ?></td>
                                    <td class="text-right">NPR <?= e(formatMoney($item['amount'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="text-right" style="max-width:300px;margin-left:auto">
                        <table class="table table-sm">
                            <tr><td><strong>Subtotal:</strong></td><td class="text-right">NPR <?= e(formatMoney($quotation['subtotal'])) ?></td></tr>
                            <?php if ($quotation['discount_value']): ?>
                                <tr><td>Discount (<?= $quotation['discount_type'] === 'percentage' ? e($quotation['discount_value']) . '%' : 'Fixed' ?>):</td><td class="text-right text-danger">- NPR <?= e(formatMoney($quotation['discount_type'] === 'percentage' ? $quotation['subtotal'] * $quotation['discount_value'] / 100 : $quotation['discount_value'])) ?></td></tr>
                            <?php endif; ?>
                            <?php if ($quotation['tax_value']): ?>
                                <tr><td>Tax (<?= $quotation['tax_type'] === 'percentage' ? e($quotation['tax_value']) . '%' : 'Fixed' ?>):</td><td class="text-right">NPR <?= e(formatMoney($quotation['tax_type'] === 'percentage' ? ($quotation['subtotal'] - ($quotation['discount_type'] === 'percentage' ? $quotation['subtotal'] * $quotation['discount_value'] / 100 : ($quotation['discount_value'] ?? 0))) * $quotation['tax_value'] / 100 : $quotation['tax_value'])) ?></td></tr>
                            <?php endif; ?>
                            <tr style="border-top:2px solid #1f2937"><td><strong>Total:</strong></td><td class="text-right"><strong>NPR <?= e(formatMoney($quotation['total'])) ?></strong></td></tr>
                        </table>
                    </div>

                    <?php if ($quotation['notes']): ?>
                        <hr><p><strong>Notes:</strong><br><?= nl2br(e($quotation['notes'])) ?></p>
                    <?php endif; ?>
                    <?php if ($quotation['terms']): ?>
                        <p><strong>Terms &amp; Conditions:</strong><br><?= nl2br(e($quotation['terms'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <?php if ($canManage): ?>
                <div class="card card-outline">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-cog mr-1"></i>Actions</h3></div>
                    <div class="card-body">
                        <a href="<?= pageUrl('leads', 'quotations') ?>&edit=<?= (int) $quotation['id'] ?>" class="btn btn-sm btn-outline-primary btn-block mb-2"><i class="fas fa-edit mr-1"></i>Edit</a>
                        <a href="<?= pageUrl('leads', 'quotations') ?>&id=<?= (int) $quotation['id'] ?>&print=1" class="btn btn-sm btn-outline-secondary btn-block mb-2" target="_blank"><i class="fas fa-print mr-1"></i>Print Preview</a>
                        <a href="<?= pageUrl('leads', 'quotations') ?>&id=<?= (int) $quotation['id'] ?>&preview=1" class="btn btn-sm btn-outline-primary btn-block mb-2" target="_blank"><i class="fas fa-eye mr-1"></i>Preview PDF</a>
                        <a href="<?= pageUrl('leads', 'quotations') ?>&id=<?= (int) $quotation['id'] ?>&pdf=1" class="btn btn-sm btn-danger btn-block mb-2"><i class="fas fa-download mr-1"></i>Download PDF</a>
                        <a href="<?= pageUrl('leads', 'quotations') ?>&id=<?= (int) $quotation['id'] ?>&word=1" class="btn btn-sm btn-outline-success btn-block mb-2"><i class="fas fa-file-word mr-1"></i>Download Word</a>

                        <form action="operation.php?module=leads&page=quotations" method="post" class="mb-2">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="id" value="<?= (int) $quotation['id'] ?>">
                            <select name="status" class="form-control form-control-sm mb-1">
                                <?php foreach (['Draft', 'Sent', 'Accepted', 'Rejected', 'Expired'] as $st): ?>
                                    <option value="<?= $st ?>" <?= $quotation['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-sm btn-primary btn-block">Update Status</button>
                        </form>

                        <form action="operation.php?module=leads&page=quotations" method="post">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete_quotation">
                            <input type="hidden" name="id" value="<?= (int) $quotation['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger btn-block confirm-submit" data-confirm="Delete this quotation permanently?"><i class="fas fa-trash mr-1"></i>Delete</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($files): ?>
                <div class="card card-outline">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-paperclip mr-1"></i>Attachments (<?= count($files) ?>)</h3></div>
                    <div class="card-body p-0">
                        <?php foreach ($files as $f): ?>
                            <?php
                            $fUrl = assetUrl('user_uploads/' . $f['file_location']);
                            $fExt = strtolower(pathinfo($f['file_name'], PATHINFO_EXTENSION));
                            $isImage = in_array($fExt, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
                            $isPdf = $fExt === 'pdf';
                            $fSize = isset($f['file_size']) ? $f['file_size'] : 0;
                            $fSizeStr = $fSize >= 1048576 ? round($fSize / 1048576, 1) . ' MB' : ($fSize >= 1024 ? round($fSize / 1024, 1) . ' KB' : $fSize . ' B');
                            $iconMap = ['pdf' => 'fas fa-file-pdf text-danger', 'doc' => 'fas fa-file-word text-primary', 'docx' => 'fas fa-file-word text-primary', 'xls' => 'fas fa-file-excel text-success', 'xlsx' => 'fas fa-file-excel text-success'];
                            $fIcon = $iconMap[$fExt] ?? 'fas fa-file text-secondary';
                            ?>
                            <div class="d-flex align-items-center border-bottom px-3 py-2" style="gap:12px">
                                <?php if ($isImage): ?>
                                    <a href="<?= $fUrl ?>" target="_blank" style="flex-shrink:0;width:48px;height:48px;border-radius:6px;overflow:hidden;background:#f3f4f6;display:flex;align-items:center;justify-content:center">
                                        <img src="<?= $fUrl ?>" alt="" style="max-width:100%;max-height:100%;object-fit:cover">
                                    </a>
                                <?php else: ?>
                                    <div style="flex-shrink:0;width:48px;height:48px;border-radius:6px;background:#f3f4f6;display:flex;align-items:center;justify-content:center">
                                        <i class="<?= $fIcon ?>" style="font-size:1.4rem"></i>
                                    </div>
                                <?php endif; ?>
                                <div style="min-width:0;flex:1">
                                    <div class="text-truncate" style="font-size:.85rem;font-weight:500" title="<?= e($f['file_name']) ?>"><?= e($f['file_name']) ?></div>
                                    <div style="font-size:.75rem;color:#9ca3af"><?= strtoupper($fExt) ?> · <?= $fSizeStr ?></div>
                                </div>
                                <div class="d-flex" style="gap:4px;flex-shrink:0">
                                    <?php if ($isImage || $isPdf): ?><a href="<?= $fUrl ?>" target="_blank" class="btn btn-xs btn-outline-primary" title="Preview"><i class="fas fa-eye"></i></a><?php endif; ?>
                                    <a href="<?= $fUrl ?>" download="<?= e($f['file_name']) ?>" class="btn btn-xs btn-outline-success" title="Download"><i class="fas fa-download"></i></a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card card-outline">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-upload mr-1"></i>Attach File</h3></div>
                <div class="card-body">
                    <form action="operation.php?module=leads&page=quotations" method="post" enctype="multipart/form-data">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="add_quotation_file">
                        <input type="hidden" name="id" value="<?= (int) $quotation['id'] ?>">
                        <div class="file-upload-widget" data-preview="true" data-title="false">
                            <div class="file-upload-preview"></div>
                            <div class="form-group mb-2 mt-2">
                                <label class="btn btn-outline-primary btn-block mb-0" style="cursor:pointer">
                                    <i class="fas fa-cloud-upload-alt mr-1"></i>Choose file
                                    <input type="file" class="file-upload-input d-none" name="quotation_file" required>
                                </label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary btn-block mt-2"><i class="fas fa-upload mr-1"></i>Upload</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
    return;
}

// ------------------------------------------------------------ add / edit
if (isset($_GET['add']) || isset($_GET['edit'])) {
    $edit = null;
    $editItems = [];
    if (isset($_GET['edit'])) {
        $edit = $db->selectOne('SELECT * FROM `tbl_quotations` WHERE `id` = ?', [(int) $_GET['edit']]);
        if ($edit) {
            $editItems = $db->select(
                'SELECT * FROM `tbl_quotation_items` WHERE quotation_id = ? ORDER BY sort_order, id',
                [(int) $edit['id']]
            );
        }
    }
    $defaultTerms = documentSettings()['default_terms'] ?? '';
    ?>
    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-file-invoice mr-1"></i><?= $edit ? 'Edit quotation' : 'New quotation' ?></h3>
            <a href="<?= pageUrl('leads', 'quotations') ?>" class="btn btn-xs btn-default float-right">Back to list</a>
        </div>
        <form action="operation.php?module=leads&page=quotations" method="post" id="quotationForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_quotation">
            <input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Client</label>
                            <select name="client_id" class="form-control" id="clientSelect">
                                <option value="">— Select client —</option>
                                <?php foreach ($clients as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>"
                                        data-email="<?= e($c['email'] ?? '') ?>"
                                        data-phone="<?= e($c['phone'] ?? '') ?>"
                                        data-address="<?= e($c['address'] ?? '') ?>"
                                        data-contact="<?= e($c['contact_person'] ?? '') ?>"
                                        <?= $edit && (int) $edit['client_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Client name *</label>
                            <input type="text" name="client_name" class="form-control" id="clientNameField" required value="<?= $edit ? e($edit['client_name']) : '' ?>">
                        </div>
                        <div class="row">
                            <div class="col-6 form-group">
                                <label>Email</label>
                                <input type="email" name="client_email" class="form-control" id="clientEmailField" value="<?= $edit ? e($edit['client_email']) : '' ?>">
                            </div>
                            <div class="col-6 form-group">
                                <label>Phone</label>
                                <input type="text" name="client_phone" class="form-control" id="clientPhoneField" value="<?= $edit ? e($edit['client_phone']) : '' ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="client_address" class="form-control" rows="2" id="clientAddressField"><?= $edit ? e($edit['client_address']) : '' ?></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-6 form-group">
                                <label>Quotation # *</label>
                                <input type="text" name="quotation_number" class="form-control" required
                                       value="<?= $edit ? e($edit['quotation_number']) : e(nextQuotationNumber($db)) ?>">
                            </div>
                            <div class="col-6 form-group">
                                <label>Status</label>
                                <select name="status" class="form-control">
                                    <?php foreach (['Draft', 'Sent', 'Accepted', 'Rejected', 'Expired'] as $st): ?>
                                        <option value="<?= $st ?>" <?= $edit && $edit['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 form-group">
                                <label>Quotation date *</label>
                                <input type="date" name="quotation_date" class="form-control" required
                                       value="<?= $edit ? e($edit['quotation_date']) : date('Y-m-d') ?>">
                            </div>
                            <div class="col-6 form-group">
                                <label>Valid until</label>
                                <input type="date" name="valid_until" class="form-control"
                                       value="<?= $edit ? e($edit['valid_until']) : date('Y-m-d', strtotime('+30 days')) ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Subject *</label>
                            <input type="text" name="subject" class="form-control" required maxlength="255"
                                   value="<?= $edit ? e($edit['subject']) : '' ?>" placeholder="e.g. Website Development Proposal">
                        </div>
                    </div>
                </div>

                <h6 class="text-muted text-uppercase mb-2 mt-3" style="font-size:.75rem">Line items</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered" id="itemsTable">
                        <thead class="thead-light">
                            <tr>
                                <th style="width:30%">Item name</th>
                                <th style="width:20%">Description</th>
                                <th style="width:10%">Qty</th>
                                <th style="width:8%">Unit</th>
                                <th style="width:15%">Unit price</th>
                                <th style="width:15%" class="text-right">Amount</th>
                                <th style="width:5%"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($editItems): ?>
                                <?php foreach ($editItems as $ei): ?>
                                    <tr class="item-row">
                                        <td><input type="text" name="item_name[]" class="form-control form-control-sm" required value="<?= e($ei['item_name']) ?>"></td>
                                        <td><input type="text" name="item_desc[]" class="form-control form-control-sm" value="<?= e($ei['description']) ?>"></td>
                                        <td><input type="number" name="item_qty[]" class="form-control form-control-sm item-qty" step="0.01" min="0" value="<?= e($ei['quantity']) ?>"></td>
                                        <td><input type="text" name="item_unit[]" class="form-control form-control-sm" value="<?= e($ei['unit'] ?? '') ?>"></td>
                                        <td><input type="number" name="item_price[]" class="form-control form-control-sm item-price" step="0.01" min="0" value="<?= e($ei['unit_price']) ?>"></td>
                                        <td class="text-right align-middle item-amount">NPR <?= e(formatMoney($ei['amount'])) ?></td>
                                        <td class="text-center align-middle"><button type="button" class="btn btn-xs btn-outline-danger remove-row"><i class="fas fa-times"></i></button></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="item-row">
                                    <td><input type="text" name="item_name[]" class="form-control form-control-sm" required></td>
                                    <td><input type="text" name="item_desc[]" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="item_qty[]" class="form-control form-control-sm item-qty" step="0.01" min="0" value="1"></td>
                                    <td><input type="text" name="item_unit[]" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="item_price[]" class="form-control form-control-sm item-price" step="0.01" min="0" value="0"></td>
                                    <td class="text-right align-middle item-amount">NPR 0.00</td>
                                    <td class="text-center align-middle"><button type="button" class="btn btn-xs btn-outline-danger remove-row"><i class="fas fa-times"></i></button></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <button type="button" id="addRow" class="btn btn-sm btn-outline-primary mb-3"><i class="fas fa-plus mr-1"></i>Add item</button>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea name="notes" class="form-control" rows="3"><?= $edit ? e($edit['notes']) : '' ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Terms &amp; conditions</label>
                            <textarea name="terms" class="form-control" rows="4" placeholder="Leave blank to use default from PDF/Word Setup"><?= $edit ? e($edit['terms']) : e($defaultTerms) ?></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted text-uppercase mb-2" style="font-size:.75rem">Calculation</h6>
                        <div class="form-group">
                            <label>Discount</label>
                            <div class="input-group input-group-sm">
                                <input type="number" name="discount_value" class="form-control" step="0.01" min="0" value="<?= $edit ? e($edit['discount_value'] ?? '') : '' ?>">
                                <div class="input-group-append">
                                    <select name="discount_type" class="form-control form-control-sm" style="max-width:100px">
                                        <option value="">None</option>
                                        <option value="percentage" <?= $edit && $edit['discount_type'] === 'percentage' ? 'selected' : '' ?>>% </option>
                                        <option value="fixed" <?= $edit && $edit['discount_type'] === 'fixed' ? 'selected' : '' ?>>Fixed</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Tax</label>
                            <div class="input-group input-group-sm">
                                <input type="number" name="tax_value" class="form-control" step="0.01" min="0" value="<?= $edit ? e($edit['tax_value'] ?? '') : '' ?>">
                                <div class="input-group-append">
                                    <select name="tax_type" class="form-control form-control-sm" style="max-width:100px">
                                        <option value="">None</option>
                                        <option value="percentage" <?= $edit && $edit['tax_type'] === 'percentage' ? 'selected' : '' ?>>%</option>
                                        <option value="fixed" <?= $edit && $edit['tax_type'] === 'fixed' ? 'selected' : '' ?>>Fixed</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="card bg-light">
                            <div class="card-body py-2">
                                <div class="d-flex justify-content-between"><span>Subtotal:</span><strong id="subtotalDisplay">NPR 0.00</strong></div>
                                <div class="d-flex justify-content-between"><span>Discount:</span><span id="discountDisplay" class="text-danger">- NPR 0.00</span></div>
                                <div class="d-flex justify-content-between"><span>Tax:</span><span id="taxDisplay">NPR 0.00</span></div>
                                <hr class="my-1">
                                <div class="d-flex justify-content-between"><strong>Total:</strong><strong id="totalDisplay">NPR 0.00</strong></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i><?= $edit ? 'Update quotation' : 'Create quotation' ?></button>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Client autofill
        var clientSelect = document.getElementById('clientSelect');
        if (clientSelect) {
            clientSelect.addEventListener('change', function() {
                var opt = this.options[this.selectedIndex];
                if (this.value) {
                    document.getElementById('clientNameField').value = opt.textContent.trim();
                    document.getElementById('clientEmailField').value = opt.dataset.email || '';
                    document.getElementById('clientPhoneField').value = opt.dataset.phone || '';
                    document.getElementById('clientAddressField').value = opt.dataset.address || '';
                }
            });
        }

        // Add row
        var tbody = document.querySelector('#itemsTable tbody');
        document.getElementById('addRow').addEventListener('click', function() {
            var tpl = document.querySelector('#itemsTable tbody tr:last-child');
            var row = tpl.cloneNode(true);
            row.querySelectorAll('input').forEach(function(inp) { inp.value = inp.name === 'item_qty[]' ? '1' : ''; });
            row.querySelector('.item-amount').textContent = 'NPR 0.00';
            tbody.appendChild(row);
            bindCalc();
        });

        // Remove row
        tbody.addEventListener('click', function(e) {
            if (e.target.closest('.remove-row')) {
                var rows = tbody.querySelectorAll('.item-row');
                if (rows.length > 1) {
                    e.target.closest('.item-row').remove();
                    recalc();
                }
            }
        });

        // Auto-calculate
        function bindCalc() {
            tbody.querySelectorAll('.item-qty, .item-price').forEach(function(el) {
                el.removeEventListener('input', recalc);
                el.addEventListener('input', recalc);
            });
            document.querySelectorAll('input[name="discount_value"], input[name="tax_value"], select[name="discount_type"], select[name="tax_type"]').forEach(function(el) {
                el.removeEventListener('input', recalc);
                el.addEventListener('input', recalc);
                el.removeEventListener('change', recalc);
                el.addEventListener('change', recalc);
            });
        }

        function recalc() {
            var subtotal = 0;
            tbody.querySelectorAll('.item-row').forEach(function(row) {
                var qty = parseFloat(row.querySelector('.item-qty').value) || 0;
                var price = parseFloat(row.querySelector('.item-price').value) || 0;
                var amt = qty * price;
                row.querySelector('.item-amount').textContent = 'NPR ' + amt.toFixed(2);
                subtotal += amt;
            });

            var discVal = parseFloat(document.querySelector('input[name="discount_value"]').value) || 0;
            var discType = document.querySelector('select[name="discount_type"]').value;
            var discount = discType === 'percentage' ? subtotal * discVal / 100 : (discType === 'fixed' ? discVal : 0);

            var afterDisc = subtotal - discount;
            var taxVal = parseFloat(document.querySelector('input[name="tax_value"]').value) || 0;
            var taxType = document.querySelector('select[name="tax_type"]').value;
            var tax = taxType === 'percentage' ? afterDisc * taxVal / 100 : (taxType === 'fixed' ? taxVal : 0);

            var total = afterDisc + tax;
            document.getElementById('subtotalDisplay').textContent = 'NPR ' + subtotal.toFixed(2);
            document.getElementById('discountDisplay').textContent = '- NPR ' + discount.toFixed(2);
            document.getElementById('taxDisplay').textContent = 'NPR ' + tax.toFixed(2);
            document.getElementById('totalDisplay').textContent = 'NPR ' + total.toFixed(2);
        }

        bindCalc();
        recalc();
    });
    </script>
    <?php
    return;
}

// ------------------------------------------------------------------- list
$statusFilter = (string) ($_GET['status'] ?? '');
$keyword = trim((string) ($_GET['keyword'] ?? ''));

$where = ['1=1'];
$params = [];
if (in_array($statusFilter, ['Draft', 'Sent', 'Accepted', 'Rejected', 'Expired'], true)) {
    $where[] = 'q.status = ?';
    $params[] = $statusFilter;
}
if ($keyword !== '') {
    $where[] = '(q.quotation_number LIKE ? OR q.client_name LIKE ? OR q.subject LIKE ?)';
    $kw = '%' . $db->escapeLike($keyword) . '%';
    array_push($params, $kw, $kw, $kw);
}

$total = (int) $db->selectOne('SELECT COUNT(*) AS c FROM `tbl_quotations` q WHERE ' . implode(' AND ', $where), $params)['c'];
$pg = paginationParams($total, (int) ($_GET['p'] ?? 1));

$quotations = $db->select(
    'SELECT q.*, u.fullname AS created_by_name
     FROM `tbl_quotations` q
     LEFT JOIN `tbl_users_login` u ON u.id = q.added_by
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY q.added_on DESC
     LIMIT ' . $pg['per_page'] . ' OFFSET ' . $pg['offset'],
    $params
);

$statusBadges = ['Draft' => 'secondary', 'Sent' => 'info', 'Accepted' => 'success', 'Rejected' => 'danger', 'Expired' => 'warning'];
?>
<div class="row">
    <div class="col-md-12">
        <div class="card card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-file-invoice mr-1"></i>Quotations</h3>
                <div class="card-tools">
                    <?php if ($canManage): ?>
                        <a href="<?= pageUrl('leads', 'quotations') ?>&add=1" class="btn btn-sm btn-primary"><i class="fas fa-plus mr-1"></i>New quotation</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <form method="get" class="form-inline mb-3">
                    <input type="hidden" name="module" value="office_setup">
                    <input type="hidden" name="page" value="quotations">
                    <select name="status" class="form-control form-control-sm mr-1" onchange="this.form.submit()">
                        <option value="">All statuses</option>
                        <?php foreach (['Draft', 'Sent', 'Accepted', 'Rejected', 'Expired'] as $st): ?>
                            <option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="keyword" class="form-control form-control-sm mr-1" placeholder="Search…" value="<?= e($keyword) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Quotation #</th>
                                <th>Client</th>
                                <th>Subject</th>
                                <th>Date</th>
                                <th class="text-right">Total</th>
                                <th>Status</th>
                                <th>Created by</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($quotations as $i => $q): ?>
                            <tr>
                                <td><?= $pg['offset'] + $i + 1 ?></td>
                                <td><a href="<?= pageUrl('leads', 'quotations') ?>&id=<?= (int) $q['id'] ?>"><strong><?= e($q['quotation_number']) ?></strong></a></td>
                                <td><?= e($q['client_name']) ?></td>
                                <td><?= e(mb_strimwidth($q['subject'], 0, 40, '…')) ?></td>
                                <td><?= e($q['quotation_date']) ?></td>
                                <td class="text-right">NPR <?= e(formatMoney($q['total'])) ?></td>
                                <td><span class="badge badge-<?= $statusBadges[$q['status']] ?? 'secondary' ?>"><?= e($q['status']) ?></span></td>
                                <td><?= e($q['created_by_name'] ?? '—') ?></td>
                                <td class="text-right">
                                    <a href="<?= pageUrl('leads', 'quotations') ?>&id=<?= (int) $q['id'] ?>" class="btn btn-xs btn-outline-primary" title="View"><i class="fas fa-eye"></i></a>
                                    <a href="<?= pageUrl('leads', 'quotations') ?>&id=<?= (int) $q['id'] ?>&print=1" class="btn btn-xs btn-outline-secondary" title="Print" target="_blank"><i class="fas fa-print"></i></a>
                                    <a href="<?= pageUrl('leads', 'quotations') ?>&id=<?= (int) $q['id'] ?>&preview=1" class="btn btn-xs btn-outline-primary" title="Preview PDF" target="_blank"><i class="fas fa-eye"></i></a>
                                    <a href="<?= pageUrl('leads', 'quotations') ?>&id=<?= (int) $q['id'] ?>&pdf=1" class="btn btn-xs btn-outline-danger" title="Download PDF"><i class="fas fa-download"></i></a>
                                    <a href="<?= pageUrl('leads', 'quotations') ?>&id=<?= (int) $q['id'] ?>&word=1" class="btn btn-xs btn-outline-success" title="Download Word"><i class="fas fa-file-word"></i></a>
                                    <?php if ($canManage): ?>
                                        <a href="<?= pageUrl('leads', 'quotations') ?>&edit=<?= (int) $q['id'] ?>" class="btn btn-xs btn-outline-secondary" title="Edit"><i class="fas fa-edit"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$quotations): ?>
                            <tr><td colspan="9" class="text-center text-muted">No quotations found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($pg['pages'] > 1): ?>
                    <nav class="mt-3"><ul class="pagination pagination-sm mb-0 justify-content-center">
                        <?php for ($p = 1; $p <= $pg['pages']; $p++): ?>
                            <li class="page-item <?= $p === $pg['page'] ? 'active' : '' ?>">
                                <a class="page-link" href="<?= pageUrl('leads', 'quotations') ?>&p=<?= $p ?>&status=<?= e($statusFilter) ?>&keyword=<?= e($keyword) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul></nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
