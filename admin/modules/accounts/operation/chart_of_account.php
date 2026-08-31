<?php
/**
 * SB-Tech — Accounts / Chart of Accounts operations (US-FIN-02).
 * save_<level> / delete_<level> for group, subgroup, terminal, subterminal.
 * Delete blocked while the level is in use (AC-FIN-02.1).
 */
$db = Database::instance();
$me = (int) Auth::id();
$action = (string) ($_POST['action'] ?? '');
$back = pageUrl('accounts', 'chart_of_account');

$groupId = (int) ($_POST['group_id'] ?? 0);
$subgroupId = (int) ($_POST['subgroup_id'] ?? 0);
$terminalId = (int) ($_POST['terminal_id'] ?? 0);
if ($groupId) {
    $back .= '&group_id=' . $groupId;
}
if ($subgroupId) {
    $back .= '&subgroup_id=' . $subgroupId;
}
if ($terminalId) {
    $back .= '&terminal_id=' . $terminalId;
}

$levelMap = [
    'group'      => ['table' => 'tbl_account_groups'],
    'subgroup'   => ['table' => 'tbl_account_sub_groups', 'parent' => 'group_id', 'parentVal' => $groupId],
    'terminal'   => ['table' => 'tbl_account_terminals', 'parent' => 'account_subgroup_id', 'parentVal' => $subgroupId],
    'subterminal'=> ['table' => 'tbl_account_sub_terminals', 'parent' => 'account_terminal_id', 'parentVal' => $terminalId],
];

try {
    if (preg_match('/^save_(group|subgroup|terminal|subterminal)$/', $action, $m)) {
        $level = $m[1];
        $cfg = $levelMap[$level];
        $title = trim((string) ($_POST['title'] ?? ''));
        $position = (int) ($_POST['position'] ?? 0);
        if ($title === '') {
            setFlash('error', 'Title is required.');
            redirect($back);
        }
        $data = ['title' => $title, 'position' => $position, 'added_by' => $me, 'updated_by' => $me];
        if (!empty($cfg['parent'])) {
            if (!$cfg['parentVal']) {
                setFlash('error', 'Select a parent first.');
                redirect($back);
            }
            $data[$cfg['parent']] = $cfg['parentVal'];
        }
        $db->insert($cfg['table'], $data);
        setFlash('success', ucfirst($level) . ' added.');
        redirect($back);
    }

    if (preg_match('/^delete_(group|subgroup|terminal|subterminal)$/', $action, $m)) {
        $level = $m[1];
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            setFlash('error', 'Missing id.');
            redirect($back);
        }
        switch ($level) {
            case 'group':
                $children = $db->count('tbl_account_sub_groups', '`group_id` = ?', [$id]);
                if ($children > 0) {
                    setFlash('error', 'Cannot delete — ' . $children . ' sub-group(s) belong to this group.');
                } else {
                    $db->delete('tbl_account_groups', '`id` = ?', [$id]);
                    setFlash('success', 'Group deleted.');
                }
                break;
            case 'subgroup':
                $children = $db->count('tbl_account_terminals', '`account_subgroup_id` = ?', [$id]);
                if ($children > 0) {
                    setFlash('error', 'Cannot delete — ' . $children . ' terminal(s) belong to this sub-group.');
                } else {
                    $db->delete('tbl_account_sub_groups', '`id` = ?', [$id]);
                    setFlash('success', 'Sub-group deleted.');
                }
                break;
            case 'terminal':
                $lines = $db->count('tbl_ledger_particulars', '`account_terminal_id` = ?', [$id]);
                $children = $db->count('tbl_account_sub_terminals', '`account_terminal_id` = ?', [$id]);
                if ($lines > 0 || $children > 0) {
                    setFlash('error', 'Cannot delete — ' . $lines . ' ledger line(s) and ' . $children . ' sub-terminal(s) use this account.');
                } else {
                    $db->delete('tbl_account_terminals', '`id` = ?', [$id]);
                    setFlash('success', 'Terminal deleted.');
                }
                break;
            case 'subterminal':
                $lines = $db->count('tbl_sub_ledger_particulars', '`account_terminal_id` = ?', [$id]);
                if ($lines > 0) {
                    setFlash('error', 'Cannot delete — ' . $lines . ' sub-ledger line(s) use this sub-terminal.');
                } else {
                    $db->delete('tbl_account_sub_terminals', '`id` = ?', [$id]);
                    setFlash('success', 'Sub-terminal deleted.');
                }
                break;
        }
        redirect($back);
    }

    setFlash('error', 'Unknown chart-of-accounts action.');
    redirect(pageUrl('accounts', 'chart_of_account'));
} catch (Throwable $e) {
    setFlash('error', 'Chart of accounts operation failed: ' . $e->getMessage());
    redirect($back);
}
