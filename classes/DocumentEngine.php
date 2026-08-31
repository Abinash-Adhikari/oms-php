<?php
/**
 * SB-Tech — Document Engine
 *
 * Shared engine powering all business documents: quotations, invoices,
 * proposals, contracts, proforma invoices, price lists, brochures, credit notes.
 *
 * Handles: CRUD, numbering, status transitions, file attachments, line items.
 */

class DocumentEngine
{
    private Database $db;
    private array $types;

    public function __construct()
    {
        $this->db = Database::instance();
        $this->types = require __DIR__ . '/../config/document_types.php';
    }

    // ═══════════════════════════════════════════════════════════════
    // TYPE REGISTRY
    // ═══════════════════════════════════════════════════════════════

    /** Get all document types. */
    public function allTypes(): array
    {
        return $this->types;
    }

    /** Get a specific document type config. */
    public function getType(string $type): ?array
    {
        return $this->types[$type] ?? null;
    }

    /** Get types grouped by sidebar section. */
    public function typesBySection(): array
    {
        $grouped = [];
        foreach ($this->types as $key => $config) {
            $section = $config['sidebar_section'] ?? 'OTHER';
            $grouped[$section][$key] = $config;
        }
        return $grouped;
    }

    // ═══════════════════════════════════════════════════════════════
    // NUMBERING
    // ═══════════════════════════════════════════════════════════════

    /** Generate next document number for a type. E.g., QTN-2026-0001 */
    public function nextNumber(string $type): string
    {
        $config = $this->getType($type);
        if (!$config) {
            throw new InvalidArgumentException("Unknown document type: $type");
        }

        $prefix = $config['prefix'];
        $year = date('Y');
        $last = $this->db->selectOne(
            "SELECT document_number FROM `tbl_documents` WHERE document_number LIKE ? ORDER BY id DESC LIMIT 1",
            ["$prefix-$year-%"]
        );

        if ($last) {
            $parts = explode('-', $last['document_number']);
            $seq = (int) end($parts) + 1;
        } else {
            $seq = 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $seq);
    }

    // ═══════════════════════════════════════════════════════════════
    // CRUD OPERATIONS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Create or update a document with items.
     *
     * @param string $type     Document type key (quotation, invoice, etc.)
     * @param int    $id       Document ID (0 for create)
     * @param array  $data     Header data
     * @param array  $items    Line items array
     * @param int    $userId   Current user ID
     * @return int   Document ID
     */
    public function save(string $type, int $id, array $data, array $items, int $userId): int
    {
        $config = $this->getType($type);
        if (!$config) {
            throw new InvalidArgumentException("Unknown document type: $type");
        }

        $showItems = !empty($data['show_items']);

        // Validate required fields
        foreach ($config['required_fields'] as $field) {
            if (empty($data[$field])) {
                throw new InvalidArgumentException(ucfirst(str_replace('_', ' ', $field)) . ' is required.');
            }
        }

        // Validate unique number
        $existing = $this->db->selectOne(
            'SELECT id FROM `tbl_documents` WHERE document_number = ? AND id != ?',
            [$data['document_number'], $id]
        );
        if ($existing) {
            throw new InvalidArgumentException('Document number already exists.');
        }

        // Calculate totals from items
        $subtotal = 0;
        $cleanItems = [];
        foreach ($items as $i => $item) {
            $name = trim($item['item_name'] ?? '');
            if ($name === '') continue;

            $qty = max(0, (float) ($item['quantity'] ?? 1));
            $price = max(0, (float) ($item['unit_price'] ?? 0));
            $amount = $qty * $price;
            $subtotal += $amount;

            $cleanItems[] = [
                'item_name'   => $name,
                'description' => trim($item['description'] ?? '') ?: null,
                'quantity'    => $qty,
                'unit'        => trim($item['unit'] ?? '') ?: null,
                'unit_price'  => $price,
                'amount'      => $amount,
                'sort_order'  => $i,
            ];
        }

        if (empty($cleanItems) && $config['has_items'] && $showItems) {
            throw new InvalidArgumentException('At least one line item is required.');
        }

        // Discount
        $discountType = $data['discount_type'] ?? null;
        $discountValue = (float) ($data['discount_value'] ?? 0);
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
        $taxType = $data['tax_type'] ?? null;
        $taxValue = (float) ($data['tax_value'] ?? 0);
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

        // Build header data (common fields)
        $headerData = [
            'document_type'   => $type,
            'document_number' => $data['document_number'],
            'client_id'       => (int) ($data['client_id'] ?? 0) ?: null,
            'client_name'     => $data['client_name'] ?? null,
            'client_email'    => $data['client_email'] ?? null,
            'client_phone'    => $data['client_phone'] ?? null,
            'client_address'  => $data['client_address'] ?? null,
            'subject'         => $data['subject'] ?? null,
            'document_date'   => $data['document_date'] ?? date('Y-m-d'),
            'valid_until'     => $data['valid_until'] ?? null,
            'due_date'        => $data['due_date'] ?? null,
            'subtotal'        => $subtotal,
            'discount_type'   => $discountType,
            'discount_value'  => $discountValue,
            'tax_type'        => $taxType,
            'tax_value'       => $taxValue,
            'total'           => $total,
            'notes'           => $data['notes'] ?? null,
            'terms'           => $data['terms'] ?? null,
            'status'          => $data['status'] ?? $config['default_status'],
            'show_items'      => $showItems ? 1 : 0,
            'lead_id'         => (int) ($data['lead_id'] ?? 0) ?: null,
            'reference_id'    => (int) ($data['reference_id'] ?? 0) ?: null,
            'updated_by'      => $userId,
        ];

        // Type-specific fields
        $typeSpecific = [
            // Invoice
            'payment_terms'      => $data['payment_terms'] ?? null,
            'bank_name'          => $data['bank_name'] ?? null,
            'bank_account'       => $data['bank_account'] ?? null,
            'bank_routing'       => $data['bank_routing'] ?? null,
            'late_fee_pct'       => !empty($data['late_fee_pct']) ? (float) $data['late_fee_pct'] : null,
            // Proposal
            'exec_summary'       => $data['exec_summary'] ?? null,
            'problem_statement'  => $data['problem_statement'] ?? null,
            'proposed_solution'  => $data['proposed_solution'] ?? null,
            'timeline_text'      => $data['timeline_text'] ?? null,
            'team_text'          => $data['team_text'] ?? null,
            'case_studies'       => $data['case_studies'] ?? null,
            'why_us'             => $data['why_us'] ?? null,
            // Contract
            'contract_clauses'   => $data['contract_clauses'] ?? null,
            'payment_schedule'   => $data['payment_schedule'] ?? null,
            'signature_left_name'  => $data['signature_left_name'] ?? null,
            'signature_left_title' => $data['signature_left_title'] ?? null,
            'signature_left_date'  => $data['signature_left_date'] ?? null,
            'signature_right_name'  => $data['signature_right_name'] ?? null,
            'signature_right_title' => $data['signature_right_title'] ?? null,
            'signature_right_date'  => $data['signature_right_date'] ?? null,
            // Price List
            'pl_category'        => $data['pl_category'] ?? null,
            // Brochure
            'brochure_sections'  => $data['brochure_sections'] ?? null,
            'hero_image'         => $data['hero_image'] ?? null,
            // Credit Note
            'original_invoice_id' => (int) ($data['original_invoice_id'] ?? 0) ?: null,
            'credit_reason'      => $data['credit_reason'] ?? null,
        ];
        $headerData = array_merge($headerData, $typeSpecific);

        if ($id) {
            // Update existing
            $existing = $this->db->selectOne('SELECT * FROM `tbl_documents` WHERE `id` = ? AND document_type = ?', [$id, $type]);
            if (!$existing) {
                throw new InvalidArgumentException('Document not found.');
            }
            $this->db->update('tbl_documents', $headerData, '`id` = ?', [$id]);
            $this->db->delete('tbl_document_items', '`document_id` = ?', [$id]);
            $docId = $id;
        } else {
            // Create new
            $headerData['added_by'] = $userId;
            $docId = $this->db->insert('tbl_documents', $headerData);
        }

        // Insert items
        foreach ($cleanItems as $item) {
            $this->db->insert('tbl_document_items', array_merge($item, ['document_id' => $docId]));
        }

        auditLog(
            $config['module_key'],
            $id ? "{$type}_updated" : "{$type}_created",
            $type,
            $docId,
            null,
            $headerData
        );

        return $docId;
    }

    /** Get a document by ID with items and files. */
    public function get(int $id, string $type = ''): ?array
    {
        $sql = 'SELECT d.*, u.fullname AS created_by_name
                FROM `tbl_documents` d
                LEFT JOIN `tbl_users_login` u ON u.id = d.added_by
                WHERE d.id = ?';
        $params = [$id];

        if ($type !== '') {
            $sql .= ' AND d.document_type = ?';
            $params[] = $type;
        }

        $doc = $this->db->selectOne($sql, $params);
        if (!$doc) return null;

        $doc['items'] = $this->db->select(
            'SELECT * FROM `tbl_document_items` WHERE document_id = ? ORDER BY sort_order, id',
            [$id]
        );
        $doc['files'] = $this->db->select(
            'SELECT * FROM `tbl_document_files` WHERE document_id = ? ORDER BY added_on DESC',
            [$id]
        );

        return $doc;
    }

    /** List documents with filters, pagination. */
    public function list(string $type, array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where = ['d.document_type = ?'];
        $params = [$type];

        if (!empty($filters['status'])) {
            $where[] = 'd.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['keyword'])) {
            $kw = '%' . $this->db->escapeLike($filters['keyword']) . '%';
            $where[] = '(d.document_number LIKE ? OR d.client_name LIKE ? OR d.subject LIKE ?)';
            array_push($params, $kw, $kw, $kw);
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) $this->db->selectOne(
            "SELECT COUNT(*) AS c FROM `tbl_documents` d WHERE $whereSql",
            $params
        )['c'];

        $offset = ($page - 1) * $perPage;
        $documents = $this->db->select(
            "SELECT d.*, u.fullname AS created_by_name
             FROM `tbl_documents` d
             LEFT JOIN `tbl_users_login` u ON u.id = d.added_by
             WHERE $whereSql
             ORDER BY d.added_on DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        return [
            'documents'  => $documents,
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $perPage,
            'pages'      => (int) ceil($total / $perPage),
            'offset'     => $offset,
        ];
    }

    /** Update document status. */
    public function updateStatus(int $id, string $type, string $newStatus, int $userId): void
    {
        $config = $this->getType($type);
        if (!$config) throw new InvalidArgumentException("Unknown document type: $type");

        $doc = $this->db->selectOne('SELECT * FROM `tbl_documents` WHERE `id` = ? AND document_type = ?', [$id, $type]);
        if (!$doc) throw new InvalidArgumentException('Document not found.');

        if (!in_array($newStatus, $config['statuses'], true)) {
            throw new InvalidArgumentException('Invalid status: ' . $newStatus);
        }

        $this->db->update('tbl_documents', ['status' => $newStatus, 'updated_by' => $userId], '`id` = ?', [$id]);

        auditLog(
            $config['module_key'],
            "{$type}_status_changed",
            $type,
            $id,
            ['status' => $doc['status']],
            ['status' => $newStatus]
        );
    }

    /** Delete a document and its items/files. */
    public function delete(int $id, string $type): void
    {
        $config = $this->getType($type);
        if (!$config) throw new InvalidArgumentException("Unknown document type: $type");

        // Delete attached files from disk
        foreach ($this->db->select('SELECT `file_location` FROM `tbl_document_files` WHERE document_id = ?', [$id]) as $f) {
            if (!empty($f['file_location'])) {
                $path = dirname(__DIR__) . '/user_uploads/' . $f['file_location'];
                if (is_file($path)) @unlink($path);
            }
        }

        $this->db->delete('tbl_document_files', '`document_id` = ?', [$id]);
        $this->db->delete('tbl_document_items', '`document_id` = ?', [$id]);
        $this->db->delete('tbl_documents', '`id` = ?', [$id]);

        auditLog($config['module_key'], "{$type}_deleted", $type, $id);
    }

    /** Add file attachment to a document. */
    public function addFile(int $docId, array $file, int $userId, string $title = ''): void
    {
        $up = validateUpload($file);
        if (!$up['ok']) {
            throw new InvalidArgumentException($up['message']);
        }

        // Build display name from title (fallback to original filename)
        $displayName = $title !== '' ? $title : pathinfo($file['name'], PATHINFO_FILENAME);
        $ext = $up['extension'];

        // Sanitize for filesystem: remove special chars, keep alphanumeric + dash/underscore
        $safeName = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $displayName);
        $safeName = preg_replace('/\s+/', '_', trim($safeName));
        if ($safeName === '') $safeName = 'file';

        // Check for duplicate names within this document and add numbering
        $baseName = $safeName;
        $counter = 1;
        while ($this->isFileDuplicate($docId, $safeName . '.' . $ext)) {
            $safeName = $baseName . '_' . $counter;
            $counter++;
        }
        $finalName = $safeName . '.' . $ext;

        // Store with renamed file
        $loc = $this->storeFileRenamed($file, 'documents', $finalName);
        if ($loc) {
            $this->db->insert('tbl_document_files', [
                'document_id'    => $docId,
                'title'          => $title ?: basename($file['name']),
                'file_name'      => $finalName,
                'file_location'  => $loc,
                'file_extension' => $ext,
                'file_size'      => (int) $file['size'],
                'added_by'       => $userId,
            ]);
        }
    }

    /** Check if a file with this name already exists for the document. */
    private function isFileDuplicate(int $docId, string $fileName): bool
    {
        $existing = $this->db->selectOne(
            'SELECT id FROM `tbl_document_files` WHERE `document_id` = ? AND `file_name` = ?',
            [$docId, $fileName]
        );
        return (bool) $existing;
    }

    /** Store uploaded file with a custom name. */
    private function storeFileRenamed(array $file, string $module, string $newName): ?string
    {
        $module = preg_replace('#[^a-zA-Z0-9_/]#', '', $module);
        $module = trim($module, '/');
        if ($module === '') return null;
        $dir = dirname(__DIR__) . '/user_uploads/' . $module;
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) return null;
        $path = $dir . '/' . $newName;
        if (!move_uploaded_file($file['tmp_name'], $path)) return null;
        return $module . '/' . $newName;
    }

    /** Delete a file attachment. */
    public function deleteFile(int $fileId, int $docId): void
    {
        $file = $this->db->selectOne('SELECT * FROM `tbl_document_files` WHERE `id` = ? AND `document_id` = ?', [$fileId, $docId]);
        if (!$file) {
            throw new InvalidArgumentException('File not found.');
        }
        // Delete physical file
        $physicalPath = dirname(__DIR__) . '/user_uploads/' . $file['file_location'];
        if (file_exists($physicalPath)) {
            @unlink($physicalPath);
        }
        // Delete DB record
        $this->db->delete('tbl_document_files', 'id = ?', [$fileId]);
    }

    // ═══════════════════════════════════════════════════════════════
    // CALCULATIONS (shared by all item-based documents)
    // ═══════════════════════════════════════════════════════════════

    /** Calculate totals from items + discount + tax. */
    public function calculateTotals(array $items, ?string $discountType, ?float $discountValue, ?string $taxType, ?float $taxValue): array
    {
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
        }

        $discount = 0;
        if ($discountType === 'percentage' && $discountValue > 0) {
            $discount = $subtotal * $discountValue / 100;
        } elseif ($discountType === 'fixed' && $discountValue > 0) {
            $discount = $discountValue;
        }

        $afterDiscount = $subtotal - $discount;
        $tax = 0;
        if ($taxType === 'percentage' && $taxValue > 0) {
            $tax = $afterDiscount * $taxValue / 100;
        } elseif ($taxType === 'fixed' && $taxValue > 0) {
            $tax = $taxValue;
        }

        return [
            'subtotal'  => $subtotal,
            'discount'  => $discount,
            'tax'       => $tax,
            'total'     => $afterDiscount + $tax,
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // KPI / STATS
    // ═══════════════════════════════════════════════════════════════

    /** Get stats for a document type. */
    public function stats(string $type): array
    {
        $config = $this->getType($type);
        if (!$config) return [];

        $stats = $this->db->selectOne(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = '{$config['default_status']}' THEN 1 ELSE 0 END) AS drafts,
                SUM(CASE WHEN status = 'Sent' THEN 1 ELSE 0 END) AS sent,
                SUM(CASE WHEN total > 0 THEN total ELSE 0 END) AS total_value
             FROM `tbl_documents` WHERE document_type = ?",
            [$type]
        );

        return $stats ?: [];
    }
}
