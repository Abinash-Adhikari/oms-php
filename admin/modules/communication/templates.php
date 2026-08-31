<?php
/**
 * SB-Tech — Communication / Templates (US-COM-01).
 * Manage email/SMS templates with placeholders.
 */
$db = Database::instance();
$templates = $db->select(
    'SELECT t.*, u.fullname AS added_by_name FROM `tbl_communication_templates` t
     LEFT JOIN `tbl_users_login` u ON u.id = t.added_by
     ORDER BY t.type, t.name'
);

$editId = (int) ($_GET['edit_id'] ?? 0);
$edit = null;
if ($editId) {
    $edit = $db->selectOne('SELECT * FROM `tbl_communication_templates` WHERE `id` = ?', [$editId]);
}

$knownEvents = [
    'new_lead'          => 'New Lead Created',
    'leave_submitted'   => 'Leave Application Submitted',
    'leave_approved'    => 'Leave Application Approved',
    'leave_rejected'    => 'Leave Application Rejected',
    'task_assigned'     => 'Task Assigned',
    'task_updated'      => 'Task Status Updated',
    'grievance_assigned'=> 'Grievance Assigned',
    'grievance_updated' => 'Grievance Status Updated',
    'expense_approved'  => 'Expense Claim Approved',
    'expense_rejected'  => 'Expense Claim Rejected',
    'voucher_approved'  => 'Voucher Approved',
];
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-file-alt mr-1"></i>Communication Templates</h3>
        <div class="card-tools">
            <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#templateModal" onclick="resetTemplateForm()"><i class="fas fa-plus mr-1"></i>Add Template</button>
        </div>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm table-striped">
            <thead><tr><th>Name</th><th>Type</th><th>Subject</th><th>Event</th><th>Active</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (!$templates): ?>
                <tr><td colspan="6" class="text-muted text-center">No templates yet. Create one to wire notifications.</td></tr>
            <?php else: foreach ($templates as $t): ?>
                <tr>
                    <td><strong><?= e($t['name']) ?></strong></td>
                    <td><span class="badge badge-<?= $t['type'] === 'Email' ? 'primary' : 'success' ?>"><?= e($t['type']) ?></span></td>
                    <td><?= e($t['subject']) ?></td>
                    <td><code><?= e($t['placeholders']) ?></code></td>
                    <td><?= $t['is_active'] ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-secondary">No</span>' ?></td>
                    <td>
                        <button class="btn btn-xs btn-info" data-toggle="modal" data-target="#templateModal" onclick="editTemplate(<?= e(json_encode($t)) ?>)"><i class="fas fa-edit"></i></button>
                        <form action="operation.php?module=communication&page=templates_operation" method="post" style="display:inline" onsubmit="return confirm('Delete this template?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="templateModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form action="operation.php?module=communication&page=templates_operation" method="post">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="tpl_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="tplModalTitle">Add Template</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group"><label>Template Name *</label>
                                <input type="text" name="name" id="tpl_name" class="form-control" required placeholder="e.g. leave_approved"></div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group"><label>Type *</label>
                                <select name="type" id="tpl_type" class="form-control">
                                    <option value="Email">Email</option>
                                    <option value="SMS">SMS</option>
                                </select></div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group"><label>Active</label>
                                <select name="is_active" id="tpl_active" class="form-control">
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select></div>
                        </div>
                    </div>
                    <div class="form-group"><label>Subject (Email only)</label>
                        <input type="text" name="subject" id="tpl_subject" class="form-control" placeholder="e.g. Your leave application has been {{action}}"></div>
                    <div class="form-group"><label>Body *</label>
                        <textarea name="body" id="tpl_body" class="form-control" rows="6" required placeholder="Hi {{name}}, your request has been {{action}}. Details: {{details}}"></textarea>
                        <small class="text-muted">Placeholders: {{name}}, {{email}}, {{phone}}, {{details}}, {{org_name}}, {{date}}, {{time}}</small>
                    </div>
                    <div class="form-group"><label>SMS Body</label>
                        <textarea name="sms_body" id="tpl_sms_body" class="form-control" rows="3" placeholder="Hi {{name}}, {{details}}"></textarea></div>
                    <div class="form-group"><label>Event (optional — for workflow wiring)</label>
                        <select name="placeholders" id="tpl_placeholders" class="form-control">
                            <option value="">— None —</option>
                            <?php foreach ($knownEvents as $key => $label): ?>
                                <option value="<?= e($key) ?>"><?= e($label) ?> (<?= e($key) ?>)</option>
                            <?php endforeach; ?>
                        </select></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetTemplateForm() {
    document.getElementById('tplModalTitle').textContent = 'Add Template';
    document.getElementById('tpl_id').value = '';
    document.getElementById('tpl_name').value = '';
    document.getElementById('tpl_type').value = 'Email';
    document.getElementById('tpl_active').value = '1';
    document.getElementById('tpl_subject').value = '';
    document.getElementById('tpl_body').value = '';
    document.getElementById('tpl_sms_body').value = '';
    document.getElementById('tpl_placeholders').value = '';
}
function editTemplate(t) {
    document.getElementById('tplModalTitle').textContent = 'Edit Template';
    document.getElementById('tpl_id').value = t.id;
    document.getElementById('tpl_name').value = t.name;
    document.getElementById('tpl_type').value = t.type;
    document.getElementById('tpl_active').value = t.is_active;
    document.getElementById('tpl_subject').value = t.subject || '';
    document.getElementById('tpl_body').value = t.body || '';
    document.getElementById('tpl_sms_body').value = t.sms_body || '';
    document.getElementById('tpl_placeholders').value = t.placeholders || '';
}
</script>
