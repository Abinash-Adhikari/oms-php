<?php
/**
 * SB-Tech — Leave Management / Setup operations (AC-LV-01.1).
 *   save_type    — create or update a leave type (duplicate titles blocked)
 *   delete_type  — delete; blocked by the DB FK when allocations/applications
 *                  reference the type (RESTRICT), reported as a flash error
 */
$db = Database::instance();
$action = (string) ($_POST['action'] ?? '');
$back = 'show_page.php?module=staff_management&page=leave_management&tab=setup_leave';

try {
    if ($action === 'save_type') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $maxAllowed = max(0, (int) ($_POST['max_allowed'] ?? 0));
        $leaveYear = trim((string) ($_POST['leave_year'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $genderSpecific = in_array(($_POST['gender_specific'] ?? 'Both'), ['Male', 'Female', 'Both'], true)
            ? (string) $_POST['gender_specific'] : 'Both';
        $maxCarry = max(0, (int) ($_POST['max_carry_forward'] ?? 0));
        $requiresApproval = !empty($_POST['requires_approval']) ? 1 : 0;
        $carryForward = !empty($_POST['carry_forward']) ? 1 : 0;
        $documentation = !empty($_POST['documentation_required']) ? 1 : 0;
        $isActive = !empty($_POST['is_active']) ? 1 : 0;

        if ($title === '') {
            setFlash('error', 'Leave type title is required.');
            redirect($back);
        }

        $dup = $db->selectOne(
            'SELECT `id` FROM `tbl_office_leave_configs` WHERE BINARY `title` = ? AND `id` != ?',
            [$title, $id]
        );
        if ($dup) {
            setFlash('error', 'A leave type with this title already exists.');
            redirect($back);
        }

        $data = [
            'title'                  => $title,
            'max_allowed'            => $maxAllowed,
            'leave_year'             => $leaveYear ?: null,
            'description'            => $description ?: null,
            'is_active'              => $isActive,
            'carry_forward'          => $carryForward,
            'max_carry_forward'      => $maxCarry,
            'requires_approval'      => $requiresApproval,
            'gender_specific'        => $genderSpecific,
            'documentation_required' => $documentation,
        ];

        if ($id) {
            $data['updated_by'] = (int) Auth::id();
            $db->update('tbl_office_leave_configs', $data, '`id` = ?', [$id]);
            setFlash('success', 'Leave type updated.');
        } else {
            $data['added_by'] = (int) Auth::id();
            $db->insert('tbl_office_leave_configs', $data);
            setFlash('success', 'Leave type created.');
        }
        redirect($back);
    }

    if ($action === 'delete_type') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $db->delete('tbl_office_leave_configs', '`id` = ?', [$id]);
            setFlash('success', 'Leave type deleted.');
        } catch (Throwable $e) {
            setFlash('error', 'Cannot delete: this leave type is referenced by allocations or applications.');
        }
        redirect($back);
    }

    setFlash('error', 'Unknown setup action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Leave type operation failed: ' . $e->getMessage());
    redirect($back);
}
