<?php
/**
 * SB-Tech — Website CMS / Contact: the website contact-form inquiries
 * (tbl_cms_contacts_us, source of truth for lead capture) and the general
 * inbox (tbl_cms_messages).
 */
$db = Database::instance();
$inquiries = $db->select(
    'SELECT c.*, l.company AS lead_company, l.stage AS lead_stage
     FROM `tbl_cms_contacts_us` c
     LEFT JOIN `tbl_leads` l ON l.id = c.lead_id
     ORDER BY FIELD(c.status, "New", "Read", "Converted"), c.added_on DESC'
);
$messages = $db->select('SELECT * FROM `tbl_cms_messages` ORDER BY FIELD(status, "New", "Read", "Replied"), added_on DESC');
?>
<div class="card card-primary card-outline">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-envelope-open-text mr-1"></i>Website inquiries (lead source)</h3></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead><tr><th>#</th><th>Name</th><th>Email / Phone</th><th>Service interest</th><th>Message</th><th>Lead</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($inquiries as $i => $q): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($q['name']) ?><br><small class="text-muted"><?= e(date('M j, g:i A', strtotime($q['added_on']))) ?></small></td>
                        <td><?= e($q['email'] ?: '—') ?><br><small><?= e($q['phone'] ?: '—') ?></small></td>
                        <td><?= e($q['service_interest'] ?: '—') ?></td>
                        <td><span class="text-truncate d-inline-block" style="max-width:220px" title="<?= e($q['message']) ?>"><?= e($q['message']) ?></span></td>
                        <td>
                            <?php if ($q['lead_id']): ?>
                                <a href="<?= pageUrl('leads', 'leads') ?>&id=<?= (int) $q['lead_id'] ?>"><?= e($q['lead_company'] ?? ('#' . $q['lead_id'])) ?> (<?= e($q['lead_stage']) ?>)</a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-<?= ['New' => 'primary', 'Read' => 'info', 'Converted' => 'success'][$q['status']] ?? 'secondary' ?>"><?= e($q['status']) ?></span></td>
                        <td class="text-right">
                            <form action="operation.php?module=webcms&page=contact" method="post" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="update_inquiry">
                                <input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
                                <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                                    <?php foreach (['New', 'Read', 'Converted'] as $st): ?>
                                        <option value="<?= $st ?>" <?= $q['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$inquiries): ?><tr><td colspan="8" class="text-center text-muted">No website inquiries yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card card-outline">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-inbox mr-1"></i>Inbox (<?= count($messages) ?>)</h3></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead><tr><th>#</th><th>Name</th><th>Email / Phone</th><th>Subject</th><th>Message</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($messages as $i => $m): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($m['name']) ?></td>
                        <td><?= e($m['email'] ?: '—') ?><br><small><?= e($m['phone'] ?: '—') ?></small></td>
                        <td><?= e($m['subject'] ?: '—') ?></td>
                        <td><span class="text-truncate d-inline-block" style="max-width:240px" title="<?= e($m['message']) ?>"><?= e($m['message']) ?></span></td>
                        <td><span class="badge badge-<?= ['New' => 'primary', 'Read' => 'info', 'Replied' => 'success'][$m['status']] ?? 'secondary' ?>"><?= e($m['status']) ?></span></td>
                        <td class="text-right">
                            <form action="operation.php?module=webcms&page=contact" method="post" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="update_message">
                                <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                                    <?php foreach (['New', 'Read', 'Replied'] as $st): ?>
                                        <option value="<?= $st ?>" <?= $m['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$messages): ?><tr><td colspan="7" class="text-center text-muted">Inbox empty.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
