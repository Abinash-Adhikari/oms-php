<?php
/**
 * SB-Tech — Shared Document Engine Operations Handler.
 * save_document / update_status / delete_document / add_document_file.
 */
$db = Database::instance();
$me = (int) Auth::id();
$engine = new DocumentEngine();

$canManage = Auth::isSuperAdmin() || Auth::hasSpecial('manage_office');
if (!$canManage) {
    http_response_code(403);
    die('Access denied: you need the manage_office permission.');
}

$action = (string) ($_POST['action'] ?? '');
$docType = (string) ($_POST['doc_type'] ?? 'quotation');
$typeConfig = $engine->getType($docType);

if (!$typeConfig) {
    setFlash('error', 'Unknown document type.');
    redirect('show_page.php?module=sales&page=documents');
}

$back = 'show_page.php?module=sales&page=documents&type=' . urlencode($docType);

try {
    // ── SAVE DOCUMENT ──
    if ($action === 'save_document') {
        $id = (int) ($_POST['id'] ?? 0);

        // Collect items
        $itemNames = $_POST['item_name'] ?? [];
        $itemDescs = $_POST['item_desc'] ?? [];
        $itemQtys = $_POST['item_qty'] ?? [];
        $itemUnits = $_POST['item_unit'] ?? [];
        $itemPrices = $_POST['item_price'] ?? [];

        $items = [];
        for ($i = 0; $i < count($itemNames); $i++) {
            $name = trim((string) ($itemNames[$i] ?? ''));
            if ($name === '') continue;
            $items[] = [
                'item_name'   => $name,
                'description' => trim((string) ($itemDescs[$i] ?? '')) ?: null,
                'quantity'    => max(0, (float) ($itemQtys[$i] ?? 1)),
                'unit'        => trim((string) ($itemUnits[$i] ?? '')) ?: null,
                'unit_price'  => max(0, (float) ($itemPrices[$i] ?? 0)),
            ];
        }

        // Line items are optional when "Show line items" is turned off.
        $showItems = !empty($_POST['show_items']);
        if (!$showItems) {
            $items = [];
        }

        $data = [
            'client_id'       => (int) ($_POST['client_id'] ?? 0) ?: null,
            'client_name'     => trim((string) ($_POST['client_name'] ?? '')),
            'client_email'    => trim((string) ($_POST['client_email'] ?? '')) ?: null,
            'client_phone'    => trim((string) ($_POST['client_phone'] ?? '')) ?: null,
            'client_address'  => trim((string) ($_POST['client_address'] ?? '')) ?: null,
            'document_number' => trim((string) ($_POST['document_number'] ?? '')),
            'document_date'   => trim((string) ($_POST['document_date'] ?? '')),
            'valid_until'     => trim((string) ($_POST['valid_until'] ?? '')) ?: null,
            'due_date'        => trim((string) ($_POST['due_date'] ?? '')) ?: null,
            'subject'         => trim((string) ($_POST['subject'] ?? '')),
            'status'          => trim((string) ($_POST['status'] ?? $typeConfig['default_status'])),
            'discount_type'   => trim((string) ($_POST['discount_type'] ?? '')) ?: null,
            'discount_value'  => (float) ($_POST['discount_value'] ?? 0),
            'tax_type'        => trim((string) ($_POST['tax_type'] ?? '')) ?: null,
            'tax_value'       => (float) ($_POST['tax_value'] ?? 0),
            'notes'           => sanitizeRichHtml(trim((string) ($_POST['notes'] ?? ''))) ?: null,
            'terms'           => trim((string) ($_POST['terms'] ?? '')) ?: null,
            'show_items'      => !empty($_POST['show_items']) ? 1 : 0,
            'lead_id'         => (int) ($_POST['lead_id'] ?? 0) ?: null,
            'reference_id'    => (int) ($_POST['reference_id'] ?? 0) ?: null,
            // Invoice
            'payment_terms'   => trim((string) ($_POST['payment_terms'] ?? '')) ?: null,
            'bank_name'       => trim((string) ($_POST['bank_name'] ?? '')) ?: null,
            'bank_account'    => trim((string) ($_POST['bank_account'] ?? '')) ?: null,
            'bank_routing'    => trim((string) ($_POST['bank_routing'] ?? '')) ?: null,
            'late_fee_pct'    => (float) ($_POST['late_fee_pct'] ?? 0) ?: null,
            // Proposal
            'exec_summary'       => trim((string) ($_POST['exec_summary'] ?? '')) ?: null,
            'problem_statement'  => trim((string) ($_POST['problem_statement'] ?? '')) ?: null,
            'proposed_solution'  => trim((string) ($_POST['proposed_solution'] ?? '')) ?: null,
            'timeline_text'      => trim((string) ($_POST['timeline_text'] ?? '')) ?: null,
            'team_text'          => trim((string) ($_POST['team_text'] ?? '')) ?: null,
            'case_studies'       => trim((string) ($_POST['case_studies'] ?? '')) ?: null,
            'why_us'             => trim((string) ($_POST['why_us'] ?? '')) ?: null,
            // Contract
            'contract_clauses'     => trim((string) ($_POST['contract_clauses'] ?? '')) ?: null,
            'payment_schedule'     => trim((string) ($_POST['payment_schedule'] ?? '')) ?: null,
            'signature_left_name'  => trim((string) ($_POST['signature_left_name'] ?? '')) ?: null,
            'signature_left_title' => trim((string) ($_POST['signature_left_title'] ?? '')) ?: null,
            'signature_left_date'  => trim((string) ($_POST['signature_left_date'] ?? '')) ?: null,
            'signature_right_name'  => trim((string) ($_POST['signature_right_name'] ?? '')) ?: null,
            'signature_right_title' => trim((string) ($_POST['signature_right_title'] ?? '')) ?: null,
            'signature_right_date'  => trim((string) ($_POST['signature_right_date'] ?? '')) ?: null,
            // Price List
            'pl_category'      => trim((string) ($_POST['pl_category'] ?? '')) ?: null,
            // Brochure
            'brochure_sections' => trim((string) ($_POST['brochure_sections'] ?? '')) ?: null,
            'hero_image'        => trim((string) ($_POST['hero_image'] ?? '')) ?: null,
            // Credit Note
            'original_invoice_id' => (int) ($_POST['original_invoice_id'] ?? 0) ?: null,
            'credit_reason'    => trim((string) ($_POST['credit_reason'] ?? '')) ?: null,
        ];

        // Brochure: its marketing fields live in the generic content columns.
        // Generic fallbacks are preserved when a brochure field is left empty.
        if ($docType === 'brochure') {
            $data['subject']           = trim((string) ($_POST['brochure_hero_title'] ?? '')) ?: $data['subject'];
            $data['exec_summary']      = trim((string) ($_POST['brochure_about'] ?? '')) ?: $data['exec_summary'];
            $data['proposed_solution'] = trim((string) ($_POST['brochure_services'] ?? '')) ?: $data['proposed_solution'];
            $data['why_us']            = trim((string) ($_POST['brochure_stats'] ?? '')) ?: $data['why_us'];
            $data['notes']             = trim((string) ($_POST['brochure_contact'] ?? '')) ?: $data['notes'];
            $data['terms']             = trim((string) ($_POST['brochure_cta'] ?? '')) ?: $data['terms'];
        }

        $docId = $engine->save($docType, $id, $data, $items, $me);
        setFlash('success', $id ? ucfirst($typeConfig['label']) . ' updated.' : ucfirst($typeConfig['label']) . ' created.');
        redirect($back . '&id=' . $docId);
    }

    // ── UPDATE STATUS ──
    if ($action === 'update_status') {
        $id = (int) ($_POST['id'] ?? 0);
        $newStatus = (string) ($_POST['status'] ?? '');
        $engine->updateStatus($id, $docType, $newStatus, $me);
        setFlash('success', ucfirst($typeConfig['label']) . ' status updated to ' . $newStatus . '.');
        redirect($back . '&id=' . $id);
    }

    // ── DELETE DOCUMENT ──
    if ($action === 'delete_document') {
        $id = (int) ($_POST['id'] ?? 0);
        $engine->delete($id, $docType);
        setFlash('success', ucfirst($typeConfig['label']) . ' deleted.');
        redirect($back);
    }

    // ── ADD FILE ──
    if ($action === 'add_document_file') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['file_title'] ?? ''));
        if (!empty($_FILES['document_file']['name'])) {
            $engine->addFile($id, $_FILES['document_file'], $me, $title);
            setFlash('success', 'File attached.');
        }
        redirect($back . '&id=' . $id);
    }

    // ── DELETE FILE ──
    if ($action === 'delete_document_file') {
        $fileId = (int) ($_POST['file_id'] ?? 0);
        $docId  = (int) ($_POST['doc_id'] ?? 0);
        if ($fileId > 0 && $docId > 0) {
            try {
                $engine->deleteFile($fileId, $docId);
                setFlash('success', 'File deleted.');
            } catch (Throwable $e) {
                setFlash('error', 'Could not delete file: ' . $e->getMessage());
            }
        }
        redirect($back . '&id=' . $docId);
    }

    setFlash('error', 'Unknown action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Operation failed: ' . $e->getMessage());
    redirect($back);
}
