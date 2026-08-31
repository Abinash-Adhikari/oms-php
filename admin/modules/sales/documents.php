<?php

/**
 * SB-Tech — Shared Document Engine Controller
 *
 * Powers ALL business documents: quotations, invoices, proposals,
 * contracts, proforma invoices, price lists, brochures, credit notes.
 *
 * Route: show_page.php?module=sales&page=documents&type=quotation
 */
$db = Database::instance();
$me = (int) Auth::id();
$engine = new DocumentEngine();

// Resolve document type from query
$docType = (string) ($_GET['type'] ?? 'quotation');
$typeConfig = $engine->getType($docType);

if (!$typeConfig) {
    echo '<div class="callout callout-danger"><h5>Unknown document type</h5><p>Invalid type: ' . e($docType) . '</p></div>';
    return;
}

$canManage = Auth::isSuperAdmin() || Auth::hasSpecial('manage_office');
$moduleName = 'sales';
$pageName = 'documents';

// Helper: build URL with type param
function docUrl($docType, $extra = '')
{
    return './show_page.php?module=sales&page=documents&type=' . urlencode($docType) . $extra;
}

// Type-specific content renderer (invoice bank details, proposal sections,
// contract clauses/signatures, price-list category, brochure content,
// credit-note reference). Used by print/PDF output and the detail view.
require_once __DIR__ . '/includes/document_render_sections.php';

// Fetch clients for dropdown
$clients = $db->select("SELECT id, name, contact_person, email, phone, address FROM `tbl_clients` ORDER BY name");

// ════════════════════════════════════════════════════════════════
// DETAIL / PRINT VIEW
// ════════════════════════════════════════════════════════════════
if (isset($_GET['id'])) {
    $doc = $engine->get((int) $_GET['id'], $docType);
    if (!$doc) {
        echo '<div class="callout callout-danger"><h5>Document not found</h5></div>';
        return;
    }

    $isPrint  = !empty($_GET['print']);
    $isPdf    = !empty($_GET['pdf']);
    $isPreview = !empty($_GET['preview']);
    $isWord   = !empty($_GET['word']);
    if ($isPrint || $isPdf || $isPreview || $isWord) {
        // Capture document HTML for both print-preview and PDF generation.
        ob_start();
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . e($typeConfig['label']) . ' ' . e($doc['document_number']) . '</title></head><body>';
        documentShellStart($typeConfig['label'], e($doc['document_number']));
?>
        <table style="width:100%;margin-bottom:12px;font-size:.9em">
            <tr>
                <td style="width:50%;vertical-align:top">
                    <strong>To:</strong><br>
                    <?= e($doc['client_name'] ?? '—') ?><br>
                    <?php if ($doc['client_email']): ?><?= e($doc['client_email']) ?><br><?php endif; ?>
                <?php if ($doc['client_phone']): ?><?= e($doc['client_phone']) ?><br><?php endif; ?>
            <?php if ($doc['client_address']): ?><?= nl2br(e($doc['client_address'])) ?><?php endif; ?>
                </td>
                <td style="width:50%;vertical-align:top;text-align:right">
                    <strong>Date:</strong> <?= e($doc['document_date']) ?><br>
                    <?php if ($doc['valid_until']): ?><strong>Valid Until:</strong> <?= e($doc['valid_until']) ?><br><?php endif; ?>
                    <?php if ($doc['due_date']): ?><strong>Due Date:</strong> <?= e($doc['due_date']) ?><br><?php endif; ?>
                    <strong>Status:</strong> <?= e($doc['status']) ?>
                </td>
            </tr>
        </table>
        <p style="margin:0 0 12px"><strong>Subject:</strong> <?= e($doc['subject']) ?></p>
        <?php if (!empty($doc['show_items'])): ?>
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
                <?php foreach ($doc['items'] as $i => $item): ?>
                    <tr>
                        <td style="border:1px solid #d1d5db;padding:6px 8px"><?= $i + 1 ?></td>
                        <td style="border:1px solid #d1d5db;padding:6px 8px"><strong><?= e($item['item_name']) ?></strong><?php if ($item['description']): ?><br><small style="color:#6b7280"><?= e($item['description']) ?></small><?php endif; ?></td>
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
                <td style="padding:4px 8px;text-align:right">NPR <?= e(formatMoney($doc['subtotal'])) ?></td>
            </tr>
            <?php if ($doc['discount_value']): ?><tr>
                    <td style="padding:4px 8px;text-align:right">Discount:</td>
                    <td style="padding:4px 8px;text-align:right">- NPR <?= e(formatMoney($doc['discount_type'] === 'percentage' ? $doc['subtotal'] * $doc['discount_value'] / 100 : $doc['discount_value'])) ?></td>
                </tr><?php endif; ?>
            <?php if ($doc['tax_value']): ?><tr>
                    <td style="padding:4px 8px;text-align:right">Tax:</td>
                    <td style="padding:4px 8px;text-align:right">NPR <?= e(formatMoney($doc['tax_type'] === 'percentage' ? ($doc['subtotal'] - ($doc['discount_type'] === 'percentage' ? $doc['subtotal'] * $doc['discount_value'] / 100 : ($doc['discount_value'] ?? 0))) * $doc['tax_value'] / 100 : $doc['tax_value'])) ?></td>
                </tr><?php endif; ?>
            <tr style="border-top:2px solid #1f2937">
                <td style="padding:6px 8px;text-align:right;font-weight:700">Total:</td>
                <td style="padding:6px 8px;text-align:right;font-weight:700">NPR <?= e(formatMoney($doc['total'])) ?></td>
            </tr>
        </table>
        <?php endif; ?>
        <?php echo renderDocumentTypeSections($doc); ?>
        <?php if ($docType !== 'brochure'): ?>
            <?php if ($doc['notes']): ?><p style="margin:0 0 8px"><strong>Notes:</strong></p>
                <div style="margin:0 0 12px"><?= renderRichText($doc['notes']) ?></div><?php endif; ?>
            <?php if ($doc['terms']): ?><p style="margin:0 0 8px"><strong>Terms &amp; Conditions:</strong></p>
                <p style="margin:0 0 12px"><?= nl2br(e($doc['terms'])) ?></p><?php endif; ?>
        <?php endif; ?>
    <?php documentShellEnd();
        echo '</body></html>';

        // ── PDF mode: generate and download/preview ──
        if ($isPdf || $isPreview) {
            $html = ob_get_clean();
            $pdf = new PdfGenerator();
            $pdf->html($html);
            $filename = $doc['document_number'] . '.pdf';
            if ($isPreview) {
                $pdf->inline($filename);
            } else {
                $pdf->download($filename);
            }
            // These call exit, so nothing below runs.
        }

        // ── Word mode: download a real .docx ──
        if ($isWord) {
            makeWord(ob_get_clean(), $doc['document_number'], $typeConfig['label'], e($doc['document_number']));
            // makeWord() streams the attachment and exits.
        }

        // ── Print mode: send ONLY the clean document, no admin chrome ──
        $html = ob_get_clean();
        while (ob_get_level()) {
            ob_end_clean();
        }
        echo $html;
        exit;
    }

    // Screen detail view
    $statusBadges = $typeConfig['status_badges'];
    ?>
    <div class="d-flex align-items-center mb-3">
        <a href="<?= docUrl($docType) ?>" class="btn btn-sm btn-outline-secondary mr-2"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h4 class="mb-0 font-weight-bold"><i class="<?= $typeConfig['icon'] ?> mr-1"></i><?= e($doc['document_number']) ?></h4>
            <small class="text-muted"><?= e($typeConfig['label']) ?> · <?= e($doc['subject']) ?></small>
        </div>
        <div class="ml-auto"><span class="badge badge-pill badge-<?= $statusBadges[$doc['status']] ?? 'secondary' ?>" style="font-size:.82rem;padding:.4em .8em"><?= e($doc['status']) ?></span></div>
    </div>
    <div class="row">
        <div class="col-md-8">
            <div class="card card-outline">
                <div class="card-header">
                    <h3 class="card-title"><i class="<?= $typeConfig['icon'] ?> mr-1"></i>Document Details</h3>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Client:</strong> <?= e($doc['client_name'] ?? '—') ?><br>
                            <?php if ($doc['client_email']): ?><?= e($doc['client_email']) ?><br><?php endif; ?>
                        <?php if ($doc['client_phone']): ?><?= e($doc['client_phone']) ?><br><?php endif; ?>
                    <?php if ($doc['client_address']): ?><?= nl2br(e($doc['client_address'])) ?><?php endif; ?>
                        </div>
                        <div class="col-md-6 text-right">
                            <strong>Date:</strong> <?= e($doc['document_date']) ?><br>
                            <?php if ($doc['valid_until']): ?><strong>Valid Until:</strong> <?= e($doc['valid_until']) ?><br><?php endif; ?>
                            <?php if ($doc['due_date']): ?><strong>Due Date:</strong> <?= e($doc['due_date']) ?><br><?php endif; ?>
                            <strong>By:</strong> <?= e($doc['created_by_name'] ?? '—') ?>
                        </div>
                    </div>
                    <?php if (!empty($doc['show_items']) && !empty($doc['items'])): ?>
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
                            <tbody><?php foreach ($doc['items'] as $i => $item): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td><strong><?= e($item['item_name']) ?></strong><?php if ($item['description']): ?><br><small class="text-muted"><?= e($item['description']) ?></small><?php endif; ?></td>
                                        <td class="text-center"><?= e($item['quantity']) ?> <?= e($item['unit'] ?? '') ?></td>
                                        <td class="text-right">NPR <?= e(formatMoney($item['unit_price'])) ?></td>
                                        <td class="text-right">NPR <?= e(formatMoney($item['amount'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    <?php if (!empty($doc['show_items'])): ?>
                    <div class="text-right" style="max-width:300px;margin-left:auto">
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Subtotal:</strong></td>
                                <td class="text-right">NPR <?= e(formatMoney($doc['subtotal'])) ?></td>
                            </tr>
                            <?php if ($doc['discount_value']): ?><tr>
                                    <td>Discount:</td>
                                    <td class="text-right text-danger">- NPR <?= e(formatMoney($doc['discount_type'] === 'percentage' ? $doc['subtotal'] * $doc['discount_value'] / 100 : $doc['discount_value'])) ?></td>
                                </tr><?php endif; ?>
                            <?php if ($doc['tax_value']): ?><tr>
                                    <td>Tax:</td>
                                    <td class="text-right">NPR <?= e(formatMoney($doc['tax_type'] === 'percentage' ? ($doc['subtotal'] - ($doc['discount_type'] === 'percentage' ? $doc['subtotal'] * $doc['discount_value'] / 100 : ($doc['discount_value'] ?? 0))) * $doc['tax_value'] / 100 : $doc['tax_value'])) ?></td>
                                </tr><?php endif; ?>
                            <tr style="border-top:2px solid #1f2937">
                                <td><strong>Total:</strong></td>
                                <td class="text-right"><strong>NPR <?= e(formatMoney($doc['total'])) ?></strong></td>
                            </tr>
                        </table>
                    </div>
                    <?php endif; ?>
                    <?php if ($docType !== 'brochure' && $doc['notes']): ?>
                        <hr>
                        <p><strong>Notes:</strong></p><div><?= renderRichText($doc['notes']) ?></div><?php endif; ?>
                    <?php if ($docType !== 'brochure' && $doc['terms']): ?><p><strong>Terms:</strong><br><?= nl2br(e($doc['terms'])) ?></p><?php endif; ?>
                </div>
            </div>
            <?php
            $extraHtml = renderDocumentTypeSections($doc);
            if ($extraHtml !== ''):
                $sectionTitles = ['invoice' => 'Payment & Bank Details', 'proposal' => 'Proposal Sections', 'contract' => 'Contract Details', 'price_list' => 'Price List Settings', 'brochure' => 'Brochure Content', 'credit_note' => 'Credit Note Details'];
            ?>
            <div class="card card-outline">
                <div class="card-header">
                    <h3 class="card-title"><i class="<?= $typeConfig['icon'] ?> mr-1"></i><?= $sectionTitles[$docType] ?? 'Additional Details' ?></h3>
                </div>
                <div class="card-body"><?= $extraHtml ?></div>
            </div>
            <?php endif; ?>
        </div>
        <div class="col-md-4">
            <?php if ($canManage): ?>
                <div class="card card-outline">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-cog mr-1"></i>Actions</h3>
                    </div>
                    <div class="card-body">
                        <a href="<?= docUrl($docType, '&edit=' . (int) $doc['id']) ?>" class="btn btn-sm btn-outline-primary btn-block mb-2"><i class="fas fa-edit mr-1"></i>Edit</a>
                        <a href="<?= docUrl($docType, '&id=' . (int) $doc['id'] . '&print=1') ?>" class="btn btn-sm btn-outline-secondary btn-block mb-2" target="_blank"><i class="fas fa-print mr-1"></i>Print Preview</a>
                        <a href="<?= docUrl($docType, '&id=' . (int) $doc['id'] . '&pdf=1') ?>" class="btn btn-sm btn-danger btn-block mb-2" target="_blank"><i class="fas fa-download mr-1"></i>Download PDF</a>
                        <a href="<?= docUrl($docType, '&id=' . (int) $doc['id'] . '&word=1') ?>" class="btn btn-sm btn-outline-success btn-block mb-2"><i class="fas fa-file-word mr-1"></i>Download Word</a>
                        <form action="operation.php?module=sales&page=documents" method="post" class="mb-2">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="id" value="<?= (int) $doc['id'] ?>">
                            <input type="hidden" name="doc_type" value="<?= e($docType) ?>">
                            <select name="status" class="form-control form-control-sm mb-1">
                                <?php foreach ($typeConfig['statuses'] as $st): ?>
                                    <option value="<?= $st ?>" <?= $doc['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-sm btn-primary btn-block">Update Status</button>
                        </form>
                        <form action="operation.php?module=sales&page=documents" method="post">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete_document">
                            <input type="hidden" name="id" value="<?= (int) $doc['id'] ?>">
                            <input type="hidden" name="doc_type" value="<?= e($docType) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger btn-block confirm-submit" data-confirm="Delete this document permanently?"><i class="fas fa-trash mr-1"></i>Delete</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (!empty($doc['files'])): ?>
                <div class="card card-outline">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-paperclip mr-1"></i>Attached Files (<?= count($doc['files']) ?>)</h3>
                    </div>
                    <div class="card-body p-0">
                        <?php foreach ($doc['files'] as $f): ?>
                            <?php
                            $fUrl = assetUrl('user_uploads/' . $f['file_location']);
                            $fExt = strtolower($f['file_extension'] ?? pathinfo($f['file_name'], PATHINFO_EXTENSION));
                            $isImage = in_array($fExt, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
                            $isPdf   = $fExt === 'pdf';
                            $fSize = isset($f['file_size']) ? $f['file_size'] : 0;
                            if ($fSize >= 1048576) $fSizeStr = round($fSize / 1048576, 1) . ' MB';
                            elseif ($fSize >= 1024) $fSizeStr = round($fSize / 1024, 1) . ' KB';
                            else $fSizeStr = $fSize . ' B';
                            $iconMap = ['pdf' => 'fas fa-file-pdf text-danger', 'doc' => 'fas fa-file-word text-primary', 'docx' => 'fas fa-file-word text-primary', 'xls' => 'fas fa-file-excel text-success', 'xlsx' => 'fas fa-file-excel text-success', 'txt' => 'fas fa-file-alt text-secondary', 'zip' => 'fas fa-file-archive text-warning', 'rar' => 'fas fa-file-archive text-warning'];
                            $fIcon = $iconMap[$fExt] ?? 'fas fa-file text-secondary';
                            ?>
                            <div class="d-flex align-items-center border-bottom px-3 py-2" style="gap:12px">
                                <?php if ($isImage): ?>
                                    <a href="<?= $fUrl ?>" target="_blank" style="flex-shrink:0;width:48px;height:48px;border-radius:6px;overflow:hidden;background:#f3f4f6;display:flex;align-items:center;justify-content:center">
                                        <img src="<?= $fUrl ?>" alt="<?= e($f['file_name']) ?>" style="max-width:100%;max-height:100%;object-fit:cover">
                                    </a>
                                <?php else: ?>
                                    <div style="flex-shrink:0;width:48px;height:48px;border-radius:6px;background:#f3f4f6;display:flex;align-items:center;justify-content:center">
                                        <i class="<?= $fIcon ?>" style="font-size:1.4rem"></i>
                                    </div>
                                <?php endif; ?>
                                <div style="min-width:0;flex:1">
                                    <?php if (!empty($f['title']) && $f['title'] !== $f['file_name']): ?>
                                        <div style="font-size:.85rem;font-weight:600;color:#1f2937" title="<?= e($f['title']) ?>"><?= e($f['title']) ?></div>
                                        <div style="font-size:.75rem;color:#9ca3af" title="<?= e($f['file_name']) ?>"><?= e($f['file_name']) ?></div>
                                    <?php else: ?>
                                        <div class="text-truncate" style="font-size:.85rem;font-weight:500" title="<?= e($f['file_name']) ?>"><?= e($f['file_name']) ?></div>
                                    <?php endif; ?>
                                    <div style="font-size:.75rem;color:#9ca3af"><?= strtoupper($fExt) ?> · <?= $fSizeStr ?><?php if (!empty($f['added_on'])): ?> · <?= date('M d, Y', strtotime($f['added_on'])) ?><?php endif; ?></div>
                                </div>
                                <div class="d-flex" style="gap:4px;flex-shrink:0">
                                    <?php if ($isImage): ?>
                                        <a href="<?= $fUrl ?>" target="_blank" class="btn btn-xs btn-outline-primary" title="Preview"><i class="fas fa-eye"></i></a>
                                    <?php endif; ?>
                                    <?php if ($isPdf): ?>
                                        <a href="<?= $fUrl ?>" target="_blank" class="btn btn-xs btn-outline-primary" title="View PDF"><i class="fas fa-eye"></i></a>
                                    <?php endif; ?>
                                    <a href="<?= $fUrl ?>" download="<?= e($f['file_name']) ?>" class="btn btn-xs btn-outline-success" title="Download"><i class="fas fa-download"></i></a>
                                    <?php if ($canManage): ?>
                                        <form action="operation.php?module=sales&page=documents" method="post" class="d-inline" onsubmit="return confirm('Delete this file permanently?')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete_document_file">
                                            <input type="hidden" name="file_id" value="<?= (int) $f['id'] ?>">
                                            <input type="hidden" name="doc_id" value="<?= (int) $doc['id'] ?>">
                                            <button type="submit" class="btn btn-xs btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <div class="card card-outline">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-upload mr-1"></i>Attach File</h3>
                </div>
                <div class="card-body">
                    <form action="operation.php?module=sales&page=documents" method="post" enctype="multipart/form-data">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="add_document_file">
                        <input type="hidden" name="id" value="<?= (int) $doc['id'] ?>">
                        <input type="hidden" name="doc_type" value="<?= e($docType) ?>">
                        <div class="file-upload-widget" data-preview="true" data-title="true">
                            <div class="file-upload-preview"></div>
                            <div class="form-group mb-2 mt-2">
                                <label class="btn btn-outline-primary btn-block mb-0" style="cursor:pointer">
                                    <i class="fas fa-cloud-upload-alt mr-1"></i>Choose file
                                    <input type="file" class="file-upload-input d-none" name="document_file" required accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.pptx,.txt">
                                </label>
                            </div>
                            <div class="file-upload-title" style="display:none">
                                <label style="font-size:.8rem;color:#6b7280;margin-bottom:2px;display:block">File Title / Description</label>
                                <input type="text" name="file_title" class="form-control form-control-sm" placeholder="Optional title for this file" maxlength="255">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary btn-block mt-2"><i class="fas fa-upload mr-1"></i>Upload</button>
                        <small class="text-muted d-block mt-1" style="font-size:.72rem">Max 10 MB · JPG, PNG, PDF, DOC, XLS, TXT</small>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php return;
}

// ════════════════════════════════════════════════════════════════
// ADD / EDIT FORM
// ════════════════════════════════════════════════════════════════
if (isset($_GET['add']) || isset($_GET['edit'])) {
    $edit = null;
    $editItems = [];
    if (isset($_GET['edit'])) {
        $edit = $engine->get((int) $_GET['edit'], $docType);
        if ($edit) $editItems = $edit['items'];
    }
    $defaultTerms = documentSettings()['default_terms'] ?? '';
?>
    <div class="d-flex align-items-center mb-3">
        <a href="<?= docUrl($docType) ?>" class="btn btn-sm btn-outline-secondary mr-2"><i class="fas fa-arrow-left"></i></a>
        <h4 class="mb-0 font-weight-bold"><?= $edit ? 'Edit' : 'New' ?> <?= e($typeConfig['label']) ?></h4>
    </div>
    <div class="card card-outline">
        <form action="operation.php?module=sales&page=documents" method="post" id="docForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_document">
            <input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
            <input type="hidden" name="doc_type" value="<?= e($docType) ?>">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group"><label>Client</label>
                            <select name="client_id" class="form-control" id="clientSelect">
                                <option value="">— Select client —</option>
                                <?php foreach ($clients as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>" data-email="<?= e($c['email'] ?? '') ?>" data-phone="<?= e($c['phone'] ?? '') ?>" data-address="<?= e($c['address'] ?? '') ?>" <?= $edit && (int) ($edit['client_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php $clientRequired = in_array('client_name', $typeConfig['required_fields'], true); ?>
                        <div class="form-group"><label>Client name<?= $clientRequired ? ' *' : '' ?></label><input type="text" name="client_name" class="form-control" id="clientNameField" <?= $clientRequired ? 'required' : '' ?> value="<?= $edit ? e($edit['client_name'] ?? '') : '' ?>"></div>
                        <div class="row">
                            <div class="col-6 form-group"><label>Email</label><input type="email" name="client_email" class="form-control" id="clientEmailField" value="<?= $edit ? e($edit['client_email'] ?? '') : '' ?>"></div>
                            <div class="col-6 form-group"><label>Phone</label><input type="text" name="client_phone" class="form-control" id="clientPhoneField" value="<?= $edit ? e($edit['client_phone'] ?? '') : '' ?>"></div>
                        </div>
                        <div class="form-group"><label>Address</label><textarea name="client_address" class="form-control" rows="2" id="clientAddressField"><?= $edit ? e($edit['client_address'] ?? '') : '' ?></textarea></div>
                    </div>
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-6 form-group"><label>Document # *</label><input type="text" name="document_number" class="form-control" required value="<?= $edit ? e($edit['document_number']) : e($engine->nextNumber($docType)) ?>"></div>
                            <div class="col-6 form-group"><label>Status</label>
                                <select name="status" class="form-control"><?php foreach ($typeConfig['statuses'] as $st): ?><option value="<?= $st ?>" <?= ($edit ? ($edit['status'] ?? '') : $typeConfig['default_status']) === $st ? 'selected' : '' ?>><?= $st ?></option><?php endforeach; ?></select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 form-group"><label>Date *</label><input type="date" name="document_date" class="form-control" required value="<?= $edit ? e($edit['document_date']) : date('Y-m-d') ?>"></div>
                            <?php if ($typeConfig['has_validity']): ?><div class="col-6 form-group"><label>Valid until</label><input type="date" name="valid_until" class="form-control" value="<?= $edit ? e($edit['valid_until'] ?? '') : date('Y-m-d', strtotime('+30 days')) ?>"></div><?php endif; ?>
                            <?php if ($typeConfig['has_payment']): ?><div class="col-6 form-group"><label>Due date</label><input type="date" name="due_date" class="form-control" value="<?= $edit ? e($edit['due_date'] ?? '') : date('Y-m-d', strtotime('+30 days')) ?>"></div><?php endif; ?>
                        </div>
                        <div class="form-group"><label>Subject *</label><input type="text" name="subject" class="form-control" required maxlength="255" value="<?= $edit ? e($edit['subject'] ?? '') : '' ?>"></div>
                    </div>
                </div>
                <?php if ($typeConfig['has_items']): ?>
                    <?php $showItemsChecked = $edit ? (int) ($edit['show_items'] ?? 1) !== 0 : true; ?>
                    <div class="d-flex align-items-center justify-content-between mb-2 mt-3">
                        <h6 class="text-muted text-uppercase mb-0" style="font-size:.75rem">Line items</h6>
                        <label class="mb-0 text-muted" style="font-weight:normal"><input type="checkbox" name="show_items" value="1" id="showItemsToggle" <?= $showItemsChecked ? 'checked' : '' ?>> Show line items &amp; calculation</label>
                    </div>
                    <div id="itemTableBlock"<?= $showItemsChecked ? '' : ' style="display:none"' ?>>
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
                                <?php if ($editItems): foreach ($editItems as $ei): ?>
                                        <tr class="item-row">
                                            <td><input type="text" name="item_name[]" class="form-control form-control-sm" required value="<?= e($ei['item_name']) ?>"></td>
                                            <td><input type="text" name="item_desc[]" class="form-control form-control-sm" value="<?= e($ei['description'] ?? '') ?>"></td>
                                            <td><input type="number" name="item_qty[]" class="form-control form-control-sm item-qty" step="0.01" min="0" value="<?= e($ei['quantity']) ?>"></td>
                                            <td><input type="text" name="item_unit[]" class="form-control form-control-sm" value="<?= e($ei['unit'] ?? '') ?>"></td>
                                            <td><input type="number" name="item_price[]" class="form-control form-control-sm item-price" step="0.01" min="0" value="<?= e($ei['unit_price']) ?>"></td>
                                            <td class="text-right align-middle item-amount">NPR <?= e(formatMoney($ei['amount'])) ?></td>
                                            <td class="text-center align-middle"><button type="button" class="btn btn-xs btn-outline-danger remove-row"><i class="fas fa-times"></i></button></td>
                                        </tr>
                                    <?php endforeach;
                                else: ?>
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
                    </div>
                <?php endif; ?>
                <div class="row">
                    <div class="col-md-6">
                        <?php if ($typeConfig['has_notes'] && $docType !== 'brochure'): ?><div class="form-group"><label>Notes</label><textarea name="notes" id="notesField" class="form-control summernote-field" rows="3"><?= $edit ? ($edit['notes'] ?? '') : '' ?></textarea></div><?php endif; ?>
                        <?php if ($typeConfig['has_terms']): ?><div class="form-group"><label>Terms &amp; conditions</label><textarea name="terms" class="form-control" rows="4"><?= $edit ? e($edit['terms'] ?? '') : e($defaultTerms) ?></textarea></div><?php endif; ?>
                    </div>
                    <?php if ($typeConfig['has_items']): ?>
                        <div class="col-md-6" id="calcBlock"<?= $showItemsChecked ? '' : ' style="display:none"' ?>>
                            <h6 class="text-muted text-uppercase mb-2" style="font-size:.75rem">Calculation</h6>
                            <?php if ($typeConfig['has_discount']): ?>
                                <div class="form-group"><label>Discount</label>
                                    <div class="input-group input-group-sm"><input type="number" name="discount_value" class="form-control" step="0.01" min="0" value="<?= $edit ? e($edit['discount_value'] ?? '') : '' ?>">
                                        <div class="input-group-append"><select name="discount_type" class="form-control form-control-sm" style="max-width:100px">
                                                <option value="">None</option>
                                                <option value="percentage" <?= ($edit ? ($edit['discount_type'] ?? '') : '') === 'percentage' ? 'selected' : '' ?>>%</option>
                                                <option value="fixed" <?= ($edit ? ($edit['discount_type'] ?? '') : '') === 'fixed' ? 'selected' : '' ?>>Fixed</option>
                                            </select></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if ($typeConfig['has_tax']): ?>
                                <div class="form-group"><label>Tax</label>
                                    <div class="input-group input-group-sm"><input type="number" name="tax_value" class="form-control" step="0.01" min="0" value="<?= $edit ? e($edit['tax_value'] ?? '') : '' ?>">
                                        <div class="input-group-append"><select name="tax_type" class="form-control form-control-sm" style="max-width:100px">
                                                <option value="">None</option>
                                                <option value="percentage" <?= ($edit ? ($edit['tax_type'] ?? '') : '') === 'percentage' ? 'selected' : '' ?>>%</option>
                                                <option value="fixed" <?= ($edit ? ($edit['tax_type'] ?? '') : '') === 'fixed' ? 'selected' : '' ?>>Fixed</option>
                                            </select></div>
                                    </div>
                                </div>
                            <?php endif; ?>
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
                    <?php endif; ?>
                </div>
            </div>
    <?php include __DIR__ . '/includes/document_type_sections.php'; ?>
    <div class="card-footer"><button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i><?= $edit ? 'Update' : 'Create' ?> <?= e($typeConfig['label']) ?></button></div>
    </form>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var cs = document.getElementById('clientSelect');
            if (cs) cs.addEventListener('change', function() {
                var o = this.options[this.selectedIndex];
                if (this.value) {
                    document.getElementById('clientNameField').value = o.textContent.trim();
                    document.getElementById('clientEmailField').value = o.dataset.email || '';
                    document.getElementById('clientPhoneField').value = o.dataset.phone || '';
                    document.getElementById('clientAddressField').value = o.dataset.address || '';
                }
            });
            var sit = document.getElementById('showItemsToggle');
            if (sit) sit.addEventListener('change', function() {
                var t = document.getElementById('itemTableBlock');
                var c = document.getElementById('calcBlock');
                if (t) t.style.display = this.checked ? '' : 'none';
                if (c) c.style.display = this.checked ? '' : 'none';
            });

            var notesEl = document.getElementById('notesField');
            if (notesEl && window.jQuery && jQuery.fn.summernote) {
                jQuery(notesEl).summernote({
                    height: 160,
                    placeholder: 'Type notes here...',
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

            var tbody = document.querySelector('#itemsTable tbody');
            if (tbody) {
                document.getElementById('addRow').addEventListener('click', function() {
                    var t = document.querySelector('#itemsTable tbody tr:last-child');
                    var r = t.cloneNode(true);
                    r.querySelectorAll('input').forEach(function(i) {
                        i.value = i.name === 'item_qty[]' ? '1' : '';
                    });
                    r.querySelector('.item-amount').textContent = 'NPR 0.00';
                    tbody.appendChild(r);
                    bindCalc();
                });
                tbody.addEventListener('click', function(e) {
                    if (e.target.closest('.remove-row')) {
                        var rows = tbody.querySelectorAll('.item-row');
                        if (rows.length > 1) {
                            e.target.closest('.item-row').remove();
                            recalc();
                        }
                    }
                });
            }

            function bindCalc() {
                document.querySelectorAll('.item-qty, .item-price').forEach(function(el) {
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
                if (!tbody) return;
                var s = 0;
                tbody.querySelectorAll('.item-row').forEach(function(r) {
                    var q = parseFloat(r.querySelector('.item-qty').value) || 0;
                    var p = parseFloat(r.querySelector('.item-price').value) || 0;
                    var a = q * p;
                    r.querySelector('.item-amount').textContent = 'NPR ' + a.toFixed(2);
                    s += a;
                });
                var dv = parseFloat((document.querySelector('input[name="discount_value"]') || {}).value) || 0;
                var dt = (document.querySelector('select[name="discount_type"]') || {}).value || '';
                var d = dt === 'percentage' ? s * dv / 100 : (dt === 'fixed' ? dv : 0);
                var ad = s - d;
                var tv = parseFloat((document.querySelector('input[name="tax_value"]') || {}).value) || 0;
                var tt = (document.querySelector('select[name="tax_type"]') || {}).value || '';
                var t = tt === 'percentage' ? ad * tv / 100 : (tt === 'fixed' ? tv : 0);
                document.getElementById('subtotalDisplay').textContent = 'NPR ' + s.toFixed(2);
                document.getElementById('discountDisplay').textContent = '- NPR ' + d.toFixed(2);
                document.getElementById('taxDisplay').textContent = 'NPR ' + t.toFixed(2);
                document.getElementById('totalDisplay').textContent = 'NPR ' + (ad + t).toFixed(2);
            }
            bindCalc();
            recalc();
        });
    </script>
<?php return;
}

// ════════════════════════════════════════════════════════════════
// LIST VIEW
// ════════════════════════════════════════════════════════════════
$statusFilter = (string) ($_GET['status'] ?? '');
$keyword = trim((string) ($_GET['keyword'] ?? ''));
$pgNum = max(1, (int) ($_GET['p'] ?? 1));
$result = $engine->list($docType, ['status' => $statusFilter, 'keyword' => $keyword], $pgNum);
$docs = $result['documents'];
$statusBadges = $typeConfig['status_badges'];
$stats = $engine->stats($docType);
?>
<!-- Document Type Selector -->
<div class="card card-outline mb-3">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap" style="gap:.4rem">
            <?php foreach ($engine->allTypes() as $tKey => $tCfg): ?>
                <a href="<?= docUrl($tKey) ?>" class="btn btn-sm <?= $tKey === $docType ? 'btn-' . $tCfg['color'] : 'btn-outline-secondary' ?>" style="font-size:.78rem">
                    <i class="<?= $tCfg['icon'] ?> mr-1"></i><?= e($tCfg['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<div class="tms-kpi-grid" style="grid-template-columns: repeat(4, 1fr);">
    <div class="tms-kpi-card bg-<?= $typeConfig['color'] ?>" style="cursor:default">
        <div class="tms-kpi-icon"><i class="<?= $typeConfig['icon'] ?>"></i></div>
        <div class="tms-kpi-meta">
            <p class="tms-kpi-label">Total <?= $typeConfig['plural'] ?></p>
            <p class="tms-kpi-value"><?= (int) ($stats['total'] ?? 0) ?></p>
        </div>
    </div>
    <div class="tms-kpi-card bg-secondary" style="cursor:default">
        <div class="tms-kpi-icon"><i class="fas fa-file-alt"></i></div>
        <div class="tms-kpi-meta">
            <p class="tms-kpi-label">Drafts</p>
            <p class="tms-kpi-value"><?= (int) ($stats['drafts'] ?? 0) ?></p>
        </div>
    </div>
    <div class="tms-kpi-card bg-info" style="cursor:default">
        <div class="tms-kpi-icon"><i class="fas fa-paper-plane"></i></div>
        <div class="tms-kpi-meta">
            <p class="tms-kpi-label">Sent</p>
            <p class="tms-kpi-value"><?= (int) ($stats['sent'] ?? 0) ?></p>
        </div>
    </div>
    <div class="tms-kpi-card bg-success" style="cursor:default">
        <div class="tms-kpi-icon"><i class="fas fa-coins"></i></div>
        <div class="tms-kpi-meta">
            <p class="tms-kpi-label">Total Value</p>
            <p class="tms-kpi-value" style="font-size:1.1rem">NPR <?= number_format((float) ($stats['total_value'] ?? 0) / 100000, 1) ?>L</p>
        </div>
    </div>
</div>
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="<?= $typeConfig['icon'] ?> mr-1"></i><?= e($typeConfig['plural']) ?></h3>
        <div class="card-tools">
            <?php if ($canManage): ?>
                <a href="<?= docUrl($docType, '&add=1') ?>" class="btn btn-sm btn-primary"><i class="fas fa-plus mr-1"></i>New <?= e($typeConfig['label']) ?></a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <form method="get" class="form-inline mb-3">
            <input type="hidden" name="module" value="sales">
            <input type="hidden" name="page" value="documents">
            <input type="hidden" name="type" value="<?= e($docType) ?>">
            <select name="status" class="form-control form-control-sm mr-1" onchange="this.form.submit()">
                <option value="">All statuses</option><?php foreach ($typeConfig['statuses'] as $st): ?><option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= $st ?></option><?php endforeach; ?>
            </select>
            <input type="text" name="keyword" class="form-control form-control-sm mr-1" placeholder="Search…" value="<?= e($keyword) ?>">
            <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
        </form>
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Number</th>
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
                    <?php foreach ($docs as $i => $d): ?>
                        <tr>
                            <td><?= $result['offset'] + $i + 1 ?></td>
                            <td><a href="<?= docUrl($docType, '&id=' . (int) $d['id']) ?>"><strong><?= e($d['document_number']) ?></strong></a></td>
                            <td><?= e($d['client_name'] ?? '—') ?></td>
                            <td><?= e(mb_strimwidth($d['subject'] ?? '', 0, 40, '…')) ?></td>
                            <td><?= e($d['document_date']) ?></td>
                            <td class="text-right"><?= !empty($d['show_items']) ? 'NPR ' . e(formatMoney($d['total'])) : '—' ?></td>
                            <td><span class="badge badge-<?= $statusBadges[$d['status']] ?? 'secondary' ?>"><?= e($d['status']) ?></span></td>
                            <td><?= e($d['created_by_name'] ?? '—') ?></td>
                            <td class="text-right">
                                <a href="<?= docUrl($docType, '&id=' . (int) $d['id']) ?>" class="btn btn-xs btn-outline-primary" title="View"><i class="fas fa-eye"></i></a>
                                <a href="<?= docUrl($docType, '&id=' . (int) $d['id'] . '&print=1') ?>" class="btn btn-xs btn-outline-secondary" title="Print" target="_blank"><i class="fas fa-print"></i></a>
                                <!-- <a href="<?= docUrl($docType, '&id=' . (int) $d['id'] . '&preview=1') ?>" class="btn btn-xs btn-outline-primary" title="Preview PDF" target="_blank"><i class="fas fa-eye"></i></a> -->
                                <a href="<?= docUrl($docType, '&id=' . (int) $d['id'] . '&pdf=1') ?>" target="_blank" class="btn btn-xs btn-outline-danger" title="Download PDF"><i class="fas fa-download"></i></a>
                                <a href="<?= docUrl($docType, '&id=' . (int) $d['id'] . '&word=1') ?>" class="btn btn-xs btn-outline-success" title="Download Word"><i class="fas fa-file-word"></i></a>
                                <?php if ($canManage): ?><a href="<?= docUrl($docType, '&edit=' . (int) $d['id']) ?>" class="btn btn-xs btn-outline-secondary" title="Edit"><i class="fas fa-edit"></i></a><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$docs): ?><tr>
                            <td colspan="9" class="text-center text-muted">No <?= strtolower($typeConfig['plural']) ?> found.</td>
                        </tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($result['pages'] > 1): ?>
            <nav class="mt-3">
                <ul class="pagination pagination-sm mb-0 justify-content-center">
                    <?php for ($p = 1; $p <= $result['pages']; $p++): ?>
                        <li class="page-item <?= $p === $result['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= docUrl($docType, '&p=' . $p . '&status=' . urlencode($statusFilter) . '&keyword=' . urlencode($keyword)) ?>"><?= $p ?></a></li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>