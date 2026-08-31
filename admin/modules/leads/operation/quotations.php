<?php
/**
 * SB-Tech — Quotations operations handler.
 * save_quotation / update_status / delete_quotation / add_quotation_file.
 */
$db = Database::instance();
$me = (int) Auth::id();
$canManage = Auth::isSuperAdmin() || Auth::hasSpecial('manage_office');
if (!$canManage) {
    http_response_code(403);
    die('Access denied: you need the manage_office permission.');
}
$action = (string) ($_POST['action'] ?? '');
$back = 'show_page.php?module=leads&page=quotations';

try {
    if ($action === 'save_quotation') {
        $id = (int) ($_POST['id'] ?? 0);
        $clientName = trim((string) ($_POST['client_name'] ?? ''));
        if ($clientName === '') {
            setFlash('error', 'Client name is required.');
            redirect($back);
        }
        $subject = trim((string) ($_POST['subject'] ?? ''));
        if ($subject === '') {
            setFlash('error', 'Subject is required.');
            redirect($back);
        }
        $quotationNumber = trim((string) ($_POST['quotation_number'] ?? ''));
        if ($quotationNumber === '') {
            setFlash('error', 'Quotation number is required.');
            redirect($back);
        }
        $quotationDate = trim((string) ($_POST['quotation_date'] ?? ''));
        if ($quotationDate === '') {
            setFlash('error', 'Quotation date is required.');
            redirect($back);
        }

        // Check unique quotation number
        $existing = $db->selectOne(
            'SELECT id FROM `tbl_quotations` WHERE quotation_number = ? AND id != ?',
            [$quotationNumber, $id]
        );
        if ($existing) {
            setFlash('error', 'Quotation number already exists.');
            redirect($back);
        }

        $status = (string) ($_POST['status'] ?? 'Draft');
        if (!in_array($status, ['Draft', 'Sent', 'Accepted', 'Rejected', 'Expired'], true)) {
            $status = 'Draft';
        }

        // Calculate totals from items
        $itemNames = $_POST['item_name'] ?? [];
        $itemDescs = $_POST['item_desc'] ?? [];
        $itemQtys = $_POST['item_qty'] ?? [];
        $itemUnits = $_POST['item_unit'] ?? [];
        $itemPrices = $_POST['item_price'] ?? [];

        $subtotal = 0;
        $items = [];
        for ($i = 0; $i < count($itemNames); $i++) {
            $name = trim((string) ($itemNames[$i] ?? ''));
            if ($name === '') {
                continue;
            }
            $qty = max(0, (float) ($itemQtys[$i] ?? 1));
            $price = max(0, (float) ($itemPrices[$i] ?? 0));
            $amount = $qty * $price;
            $subtotal += $amount;
            $items[] = [
                'item_name' => $name,
                'description' => trim((string) ($itemDescs[$i] ?? '')) ?: null,
                'quantity' => $qty,
                'unit' => trim((string) ($itemUnits[$i] ?? '')) ?: null,
                'unit_price' => $price,
                'amount' => $amount,
                'sort_order' => $i,
            ];
        }

        if (empty($items)) {
            setFlash('error', 'At least one line item is required.');
            redirect($back);
        }

        // Discount
        $discountType = trim((string) ($_POST['discount_type'] ?? ''));
        $discountValue = max(0, (float) ($_POST['discount_value'] ?? 0));
        if (!in_array($discountType, ['percentage', 'fixed'], true) || $discountValue <= 0) {
            $discountType = null;
            $discountValue = null;
        }
        $discount = 0;
        if ($discountType === 'percentage') {
            $discount = $subtotal * $discountValue / 100;
        } elseif ($discountType === 'fixed') {
            $discount = $discountValue;
        }

        // Tax
        $taxType = trim((string) ($_POST['tax_type'] ?? ''));
        $taxValue = max(0, (float) ($_POST['tax_value'] ?? 0));
        if (!in_array($taxType, ['percentage', 'fixed'], true) || $taxValue <= 0) {
            $taxType = null;
            $taxValue = null;
        }
        $afterDiscount = $subtotal - $discount;
        $tax = 0;
        if ($taxType === 'percentage') {
            $tax = $afterDiscount * $taxValue / 100;
        } elseif ($taxType === 'fixed') {
            $tax = $taxValue;
        }

        $total = $afterDiscount + $tax;

        $data = [
            'quotation_number' => $quotationNumber,
            'client_id'        => (int) ($_POST['client_id'] ?? 0) ?: null,
            'client_name'      => $clientName,
            'client_email'     => trim((string) ($_POST['client_email'] ?? '')) ?: null,
            'client_phone'     => trim((string) ($_POST['client_phone'] ?? '')) ?: null,
            'client_address'   => trim((string) ($_POST['client_address'] ?? '')) ?: null,
            'subject'          => $subject,
            'quotation_date'   => $quotationDate,
            'valid_until'      => trim((string) ($_POST['valid_until'] ?? '')) ?: null,
            'subtotal'         => $subtotal,
            'discount_type'    => $discountType,
            'discount_value'   => $discountValue,
            'tax_type'         => $taxType,
            'tax_value'        => $taxValue,
            'total'            => $total,
            'notes'            => trim((string) ($_POST['notes'] ?? '')) ?: null,
            'terms'            => trim((string) ($_POST['terms'] ?? '')) ?: null,
            'status'           => $status,
            'updated_by'       => $me,
        ];

        $quotationId = $id;
        if ($id) {
            $existing = $db->selectOne('SELECT * FROM `tbl_quotations` WHERE `id` = ?', [$id]);
            if (!$existing) {
                setFlash('error', 'Quotation not found.');
                redirect($back);
            }
            $db->update('tbl_quotations', $data, '`id` = ?', [$id]);
            // Replace items
            $db->delete('tbl_quotation_items', '`quotation_id` = ?', [$id]);
        } else {
            $data['added_by'] = $me;
            $quotationId = $db->insert('tbl_quotations', $data);
        }

        // Insert items
        foreach ($items as $item) {
            $db->insert('tbl_quotation_items', array_merge($item, ['quotation_id' => $quotationId]));
        }

        auditLog('leads', $id ? 'quotation_updated' : 'quotation_created', 'quotation', $quotationId, null, $data);
        setFlash('success', $id ? 'Quotation updated.' : 'Quotation created.');
        redirect($back . '&id=' . $quotationId);
    }

    if ($action === 'update_status') {
        $id = (int) ($_POST['id'] ?? 0);
        $quotation = $db->selectOne('SELECT * FROM `tbl_quotations` WHERE `id` = ?', [$id]);
        if (!$quotation) {
            setFlash('error', 'Quotation not found.');
            redirect($back);
        }
        $status = (string) ($_POST['status'] ?? $quotation['status']);
        if (!in_array($status, ['Draft', 'Sent', 'Accepted', 'Rejected', 'Expired'], true)) {
            $status = $quotation['status'];
        }
        $db->update('tbl_quotations', ['status' => $status, 'updated_by' => $me], '`id` = ?', [$id]);
        auditLog('leads', 'quotation_status_changed', 'quotation', $id, ['status' => $quotation['status']], ['status' => $status]);
        setFlash('success', 'Quotation status updated to ' . $status . '.');
        redirect($back . '&id=' . $id);
    }

    if ($action === 'delete_quotation') {
        $id = (int) ($_POST['id'] ?? 0);
        // Delete attached files
        foreach ($db->select('SELECT `file_location` FROM `tbl_quotation_files` WHERE quotation_id = ?', [$id]) as $f) {
            if (!empty($f['file_location'])) {
                $path = dirname(__DIR__, 3) . '/user_uploads/' . $f['file_location'];
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
        $db->delete('tbl_quotation_files', '`quotation_id` = ?', [$id]);
        $db->delete('tbl_quotation_items', '`quotation_id` = ?', [$id]);
        $db->delete('tbl_quotations', '`id` = ?', [$id]);
        auditLog('leads', 'quotation_deleted', 'quotation', $id);
        setFlash('success', 'Quotation deleted.');
        redirect($back);
    }

    if ($action === 'add_quotation_file') {
        $id = (int) ($_POST['id'] ?? 0);
        if (!empty($_FILES['quotation_file']['name'])) {
            $up = validateUpload($_FILES['quotation_file']);
            if (!$up['ok']) {
                setFlash('error', $up['message']);
                redirect($back . '&id=' . $id);
            }
            $loc = storeUpload($_FILES['quotation_file'], 'quotations', $up['extension']);
            if ($loc) {
                $db->insert('tbl_quotation_files', [
                    'quotation_id'    => $id,
                    'file_name'       => basename((string) $_FILES['quotation_file']['name']),
                    'file_location'   => $loc,
                    'file_extension'  => $up['extension'],
                    'file_size'       => (int) $_FILES['quotation_file']['size'],
                    'added_by'        => $me,
                ]);
                setFlash('success', 'File attached.');
            }
        }
        redirect($back . '&id=' . $id);
    }

    setFlash('error', 'Unknown quotation action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Quotation operation failed: ' . $e->getMessage());
    redirect($back);
}
