<?php
/**
 * SB-Tech — Accounts / Chart of Accounts (US-FIN-02).
 * 4-level hierarchy: groups → sub-groups → terminals → sub-terminals.
 * Delete is blocked while a level is in use (AC-FIN-02.1).
 */
$db = Database::instance();

$groupId = (int) ($_GET['group_id'] ?? 0);
$subgroupId = (int) ($_GET['subgroup_id'] ?? 0);
$terminalId = (int) ($_GET['terminal_id'] ?? 0);

$groups = $db->select('SELECT * FROM `tbl_account_groups` ORDER BY `position`, `id`');
$subgroups = $groupId ? $db->select('SELECT * FROM `tbl_account_sub_groups` WHERE `group_id` = ? ORDER BY `position`, `id`', [$groupId]) : [];
$terminals = $subgroupId ? $db->select('SELECT * FROM `tbl_account_terminals` WHERE `account_subgroup_id` = ? ORDER BY `position`, `id`', [$subgroupId]) : [];
$subterminals = $terminalId ? $db->select('SELECT * FROM `tbl_account_sub_terminals` WHERE `account_terminal_id` = ? ORDER BY `position`, `id`', [$terminalId]) : [];

$activeGroup = $groupId ? ($db->selectOne('SELECT * FROM `tbl_account_groups` WHERE `id` = ?', [$groupId]) ?: null) : null;
$activeSubgroup = $subgroupId ? ($db->selectOne('SELECT * FROM `tbl_account_sub_groups` WHERE `id` = ?', [$subgroupId]) ?: null) : null;
$activeTerminal = $terminalId ? ($db->selectOne('SELECT * FROM `tbl_account_terminals` WHERE `id` = ?', [$terminalId]) ?: null) : null;
?>
<div class="row">
    <?php
    $cols = [
        'Groups' => 'group',
        'Sub-groups' => 'subgroup',
        'Terminals' => 'terminal',
        'Sub-terminals' => 'subterminal',
    ];
    ?>
    <?php foreach ($cols as $colLabel => $colKey): ?>
        <div class="col-lg-3 col-md-6">
            <div class="card card-outline mb-3">
                <div class="card-header">
                    <h3 class="card-title"><?= e($colLabel) ?>
                        <?php if ($colKey === 'subgroup' && $activeGroup): ?><small class="text-muted">(<?= e($activeGroup['title']) ?>)</small><?php endif; ?>
                        <?php if ($colKey === 'terminal' && $activeSubgroup): ?><small class="text-muted">(<?= e($activeSubgroup['title']) ?>)</small><?php endif; ?>
                        <?php if ($colKey === 'subterminal' && $activeTerminal): ?><small class="text-muted">(<?= e($activeTerminal['title']) ?>)</small><?php endif; ?>
                    </h3>
                </div>
                <div class="card-body p-0">
                    <?php
                    $items = ['group' => $groups, 'subgroup' => $subgroups, 'terminal' => $terminals, 'subterminal' => $subterminals][$colKey];
                    $sel = ['group' => $groupId, 'subgroup' => $subgroupId, 'terminal' => $terminalId, 'subterminal' => 0][$colKey];
                    $q = ['module' => 'accounts', 'page' => 'chart_of_account'];
                    if ($groupId) {
                        $q['group_id'] = $groupId;
                    }
                    if ($subgroupId) {
                        $q['subgroup_id'] = $subgroupId;
                    }
                    if ($terminalId) {
                        $q['terminal_id'] = $terminalId;
                    }
                    ?>
                    <ul class="list-group list-group-flush" style="max-height:340px;overflow:auto">
                        <?php foreach ($items as $item): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center p-2 <?= $sel === (int) $item['id'] ? 'list-group-item-primary' : '' ?>">
                                <?php
                                $link = $q;
                                if ($colKey === 'group') {
                                    $link['group_id'] = $item['id'];
                                } elseif ($colKey === 'subgroup') {
                                    $link['subgroup_id'] = $item['id'];
                                } elseif ($colKey === 'terminal') {
                                    $link['terminal_id'] = $item['id'];
                                } else {
                                    $link = ['module' => 'accounts', 'page' => 'chart_of_account', 'group_id' => $groupId, 'subgroup_id' => $subgroupId, 'terminal_id' => $terminalId];
                                }
                                ?>
                                <a href="show_page.php?<?= e(http_build_query($link)) ?>" class="text-truncate" style="max-width:180px"><?= e($item['title']) ?></a>
                                <form action="operation.php?module=accounts&page=chart_of_account" method="post" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_<?= $colKey ?>">
                                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                    <input type="hidden" name="group_id" value="<?= (int) $groupId ?>">
                                    <input type="hidden" name="subgroup_id" value="<?= (int) $subgroupId ?>">
                                    <input type="hidden" name="terminal_id" value="<?= (int) $terminalId ?>">
                                    <button class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete '<?= e($item['title']) ?>'? Blocked while in use."><i class="fas fa-trash"></i></button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                        <?php if (!$items): ?><li class="list-group-item text-muted small">None yet — add one below.</li><?php endif; ?>
                    </ul>
                </div>
                <div class="card-footer">
                    <form action="operation.php?module=accounts&page=chart_of_account" method="post" class="input-group input-group-sm">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="save_<?= $colKey ?>">
                        <input type="hidden" name="group_id" value="<?= (int) $groupId ?>">
                        <input type="hidden" name="subgroup_id" value="<?= (int) $subgroupId ?>">
                        <input type="hidden" name="terminal_id" value="<?= (int) $terminalId ?>">
                        <input type="text" name="title" class="form-control" placeholder="New <?= strtolower($colLabel) ?>" required>
                        <input type="number" name="position" class="form-control" style="max-width:70px" placeholder="Pos" value="0">
                        <div class="input-group-append">
                            <button class="btn btn-primary" <?= $colKey === 'subgroup' && !$activeGroup ? 'disabled title="Select a group first"' : ($colKey === 'terminal' && !$activeSubgroup ? 'disabled title="Select a sub-group first"' : ($colKey === 'subterminal' && !$activeTerminal ? 'disabled title="Select a terminal first"' : '')) ?>>
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
