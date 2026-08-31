<?php
/**
 * SB-Tech — Staff Management / Staffs (US-STF-01, US-STF-02).
 * List view by default; ?add=1 shows the create form; ?id=N edits a staff
 * member (including documents + permission shortcut).
 */
$db = Database::instance();

$edit = null;
$profile = null;
$mode = 'list';
if (isset($_GET['id'])) {
    $mode = 'edit';
    $edit = $db->selectOne('SELECT * FROM `tbl_users_login` WHERE `id` = ?', [(int) $_GET['id']]);
    if ($edit) {
        $profile = $db->selectOne('SELECT * FROM `tbl_user_profiles` WHERE `user_id` = ?', [(int) $edit['id']]);
    }
} elseif (isset($_GET['add'])) {
    $mode = 'add';
}

if ($mode === 'list'):
    $rows = $db->select(
        'SELECT u.*, d.title AS department_title, g.title AS designation_title
         FROM `tbl_users_login` u
         LEFT JOIN `tbl_office_departments` d ON d.id = u.department_id
         LEFT JOIN `tbl_office_designation` g ON g.id = u.designation_id
         WHERE u.status != \'Terminated\'
         ORDER BY u.fullname'
    );
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Staff (<?= count($rows) ?>)</h3>
            <div class="card-tools">
                <form action="operation.php?module=staff_management&page=export_staff" method="post" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="export_staff">
                    <button class="btn btn-success btn-sm"><i class="fas fa-download mr-1"></i>CSV</button>
                </form>
                <a href="<?= pageUrl('staff_management', 'add_staff') ?>&add=1" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>Add Staff</a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive-scroll">
                <table class="table table-sm table-striped table-hover">
                    <thead><tr><th>#</th><th>Name</th><th>Username</th><th>Department</th><th>Designation</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $i => $r): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= e($r['fullname']) ?></td>
                            <td>@<?= e($r['username']) ?></td>
                            <td><?= e($r['department_title'] ?? '—') ?></td>
                            <td><?= e($r['designation_title'] ?? '—') ?></td>
                            <td><span class="badge badge-<?= $r['status'] === 'Active' ? 'success' : ($r['status'] === 'Block' ? 'warning' : 'danger') ?>"><?= e($r['status']) ?></span></td>
                            <td class="text-right">
                                <a href="<?= pageUrl('staff_management', 'add_staff') ?>&id=<?= (int) $r['id'] ?>" class="btn btn-xs btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="<?= pageUrl('staff_management', 'permissions') ?>&id=<?= (int) $r['id'] ?>" class="btn btn-xs btn-outline-secondary" title="Permissions"><i class="fas fa-user-shield"></i></a>
                                <a href="<?= pageUrl('staff_management', 'staff_history') ?>&id=<?= (int) $r['id'] ?>" class="btn btn-xs btn-outline-info" title="History"><i class="fas fa-history"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted">No staff yet. Click "Add Staff".</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php else: ?>
    <?php if (!$edit && $mode === 'edit'): ?>
        <div class="callout callout-danger"><h5>Staff not found</h5><p>The requested staff record does not exist.</p></div>
    <?php else: ?>
        <form action="operation.php?module=staff_management&page=add_staff" method="post" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
            <div class="row">
                <div class="col-md-6">
                    <div class="card card-primary">
                        <div class="card-header"><h3 class="card-title">Personal Details</h3></div>
                        <div class="card-body">
                            <div class="form-group"><label>Full name *</label>
                                <input type="text" name="fullname" class="form-control" required value="<?= $edit ? e($edit['fullname']) : '' ?>"></div>
                            <div class="row">
                                <div class="form-group col-md-6"><label>Gender</label>
                                    <select name="gender" class="form-control">
                                        <option value="">—</option>
                                        <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                                            <option value="<?= $g ?>" <?= $edit && $edit['gender'] === $g ? 'selected' : '' ?>><?= $g ?></option>
                                        <?php endforeach; ?>
                                    </select></div>
                                <div class="form-group col-md-6"><label>Date of birth</label>
                                    <input type="date" name="dob" class="form-control" value="<?= $edit ? e($edit['dob']) : '' ?>"></div>
                            </div>
                            <div class="form-group"><label>Phone</label>
                                <input type="text" name="phone1" class="form-control" value="<?= $edit ? e($edit['phone1']) : '' ?>"></div>
                            <div class="form-group"><label>Address</label>
                                <input type="text" name="address" class="form-control" value="<?= $edit ? e($edit['address']) : '' ?>"></div>
                            <div class="row">
                                <div class="form-group col-md-6"><label>Citizenship</label>
                                    <input type="text" name="citizenship" class="form-control" value="<?= $edit ? e($edit['citizenship']) : '' ?>"></div>
                                <div class="form-group col-md-6"><label>Marital status</label>
                                    <select name="marital_status" class="form-control">
                                        <option value="">—</option>
                                        <?php foreach (['Married', 'Unmarried', 'Divorced'] as $m): ?>
                                            <option value="<?= $m ?>" <?= $edit && $edit['marital_status'] === $m ? 'selected' : '' ?>><?= $m ?></option>
                                        <?php endforeach; ?>
                                    </select></div>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Emergency / Profile (optional)</h3></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="form-group col-md-6"><label>Blood group</label>
                                    <input type="text" name="blood_group" class="form-control" value="<?= $profile ? e($profile['blood_group']) : '' ?>"></div>
                                <div class="form-group col-md-6"><label>Emergency contact</label>
                                    <input type="text" name="emergency_contact_name" class="form-control" value="<?= $profile ? e($profile['emergency_contact_name']) : '' ?>"></div>
                                <div class="form-group col-md-6"><label>Emergency mobile</label>
                                    <input type="text" name="emergency_contact_mobile" class="form-control" value="<?= $profile ? e($profile['emergency_contact_mobile']) : '' ?>"></div>
                                <div class="form-group col-md-6"><label>Relation</label>
                                    <input type="text" name="emergency_contact_relation" class="form-control" value="<?= $profile ? e($profile['emergency_contact_relation']) : '' ?>"></div>
                                <div class="form-group col-md-6"><label>Work experience</label>
                                    <input type="text" name="work_experience" class="form-control" value="<?= $profile ? e($profile['work_experience']) : '' ?>"></div>
                                <div class="form-group col-md-6"><label>Skills</label>
                                    <input type="text" name="skill" class="form-control" value="<?= $profile ? e($profile['skill']) : '' ?>"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Employment</h3></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="form-group col-md-6"><label>Staff type</label>
                                    <select name="staff_type" class="form-control">
                                        <option value="Admin" <?= $edit && $edit['staff_type'] === 'Admin' ? 'selected' : '' ?>>Admin</option>
                                        <option value="Service" <?= $edit && $edit['staff_type'] === 'Service' ? 'selected' : '' ?>>Service</option>
                                    </select></div>
                                <div class="form-group col-md-6"><label>Join date</label>
                                    <input type="date" name="join_date" class="form-control" value="<?= $edit ? e($edit['join_date']) : '' ?>"></div>
                                <div class="form-group col-md-6"><label>Department</label>
                                    <select name="department_id" class="form-control">
                                        <option value="">—</option>
                                        <?php foreach ($db->select('SELECT * FROM `tbl_office_departments` ORDER BY position, title') as $d): ?>
                                            <option value="<?= (int) $d['id'] ?>" <?= $edit && (int) $edit['department_id'] === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['title']) ?></option>
                                        <?php endforeach; ?>
                                    </select></div>
                                <div class="form-group col-md-6"><label>Designation</label>
                                    <select name="designation_id" class="form-control">
                                        <option value="">—</option>
                                        <?php foreach ($db->select('SELECT * FROM `tbl_office_designation` ORDER BY position, title') as $g): ?>
                                            <option value="<?= (int) $g['id'] ?>" <?= $edit && (int) $edit['designation_id'] === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['title']) ?></option>
                                        <?php endforeach; ?>
                                    </select></div>
                                <div class="form-group col-md-6"><label>Daily working hours</label>
                                    <input type="number" name="daily_working_hour" class="form-control" value="<?= $edit && $edit['daily_working_hour'] !== null ? (int) $edit['daily_working_hour'] : '' ?>"></div>
                                <div class="form-group col-md-6"><label>Off day</label>
                                    <input type="text" name="off_day" class="form-control" value="<?= $edit ? e($edit['off_day']) : '' ?>"></div>
                                <div class="form-group col-md-6"><label>PAN no</label>
                                    <input type="text" name="pan_num" class="form-control" value="<?= $edit ? e($edit['pan_num']) : '' ?>"></div>
                                <div class="form-group col-md-6"><label>Bank</label>
                                    <input type="text" name="bank" class="form-control" value="<?= $edit ? e($edit['bank']) : '' ?>"></div>
                                <div class="form-group col-md-6"><label>Bank account no</label>
                                    <input type="text" name="bank_account_num" class="form-control" value="<?= $edit ? e($edit['bank_account_num']) : '' ?>"></div>
                                <div class="form-group col-md-6"><label>Bank account name</label>
                                    <input type="text" name="bank_account_name" class="form-control" value="<?= $edit ? e($edit['bank_account_name']) : '' ?>"></div>
                                <div class="form-group col-md-4"><label>SSF no</label>
                                    <input type="text" name="ssf_number" class="form-control" value="<?= $edit ? e($edit['ssf_number']) : '' ?>"></div>
                                <div class="form-group col-md-4"><label>PF no</label>
                                    <input type="text" name="pf_number" class="form-control" value="<?= $edit ? e($edit['pf_number']) : '' ?>"></div>
                                <div class="form-group col-md-4"><label>CIT no</label>
                                    <input type="text" name="cit_number" class="form-control" value="<?= $edit ? e($edit['cit_number']) : '' ?>"></div>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Login & System</h3></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="form-group col-md-6"><label>Username *</label>
                                    <input type="text" name="username" class="form-control" required value="<?= $edit ? e($edit['username']) : '' ?>"></div>
                                <div class="form-group col-md-6"><label>Email</label>
                                    <input type="email" name="email" class="form-control" value="<?= $edit ? e($edit['email']) : '' ?>"></div>
                                <?php if (!$edit): ?>
                                    <div class="form-group col-md-6"><label>Password *</label>
                                        <input type="password" name="password" class="form-control" required></div>
                                <?php else: ?>
                                    <div class="form-group col-md-6"><label>New password</label>
                                        <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current"></div>
                                <?php endif; ?>
                                <div class="form-group col-md-6"><label>Status</label>
                                    <select name="status" class="form-control">
                                        <?php foreach (['Active', 'Block', 'Terminated'] as $s): ?>
                                            <option value="<?= $s ?>" <?= $edit && $edit['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                                        <?php endforeach; ?>
                                    </select></div>
                                <div class="form-group col-md-6"><label>Termination date</label>
                                    <input type="date" name="termination_date" class="form-control" value="<?= $edit ? e($edit['termination_date']) : '' ?>"></div>
                                <div class="form-group col-md-6"><label>Termination reason</label>
                                    <input type="text" name="termination_reason" class="form-control" placeholder="Required when status = Terminated"></div>
                            </div>
                            <p class="text-muted small mb-0">New staff start with no module access; grant it on the
                                <a href="<?= pageUrl('staff_management', 'permissions') ?>&id=<?= $edit ? (int) $edit['id'] : 0 ?>">Permissions</a> page.</p>
                        </div>
                    </div>
                    <div class="card-footer p-0 pb-2">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i><?= $edit ? 'Update Staff' : 'Create Staff' ?></button>
                        <a href="<?= pageUrl('staff_management', 'add_staff') ?>" class="btn btn-default float-right">Back to list</a>
                    </div>
                </div>
            </div>
        </form>

        <?php if ($edit): ?>
            <div class="card">
                <div class="card-header"><h3 class="card-title">Documents (<?= (int) ($db->count('tbl_staff_documents', 'staff_id = ?', [(int) $edit['id']])) ?>)</h3></div>
                <div class="card-body">
                    <table class="table table-sm table-striped">
                        <thead><tr><th>Title</th><th>Type</th><th>File</th><th>Size</th><th class="text-right">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($db->select('SELECT * FROM `tbl_staff_documents` WHERE `staff_id` = ? ORDER BY added_on DESC', [(int) $edit['id']]) as $doc): ?>
                            <tr>
                                <td><?= e($doc['title']) ?></td>
                                <td><?= e($doc['document_type']) ?></td>
                                <td><a href="<?= assetUrl('user_uploads/' . $doc['file_path']) ?>" target="_blank"><i class="fas fa-file mr-1"></i><?= e($doc['document_name']) ?></a></td>
                                <td><?= e($doc['size']) ?></td>
                                <td class="text-right">
                                    <form action="operation.php?module=staff_management&page=staff_documents" method="post" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $doc['id'] ?>">
                                        <input type="hidden" name="staff_id" value="<?= (int) $edit['id'] ?>">
                                        <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete document?"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$db->count('tbl_staff_documents', 'staff_id = ?', [(int) $edit['id']])): ?><tr><td colspan="5" class="text-center text-muted">No documents.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                    <form action="operation.php?module=staff_management&page=staff_documents" method="post" enctype="multipart/form-data" class="row mt-3">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="staff_id" value="<?= (int) $edit['id'] ?>">
                        <div class="col-md-3"><input type="text" name="title" class="form-control form-control-sm" placeholder="Title"></div>
                        <div class="col-md-2"><input type="text" name="document_type" class="form-control form-control-sm" placeholder="Type (e.g. CV)"></div>
                        <div class="col-md-4"><div class="custom-file">
                            <input type="file" class="custom-file-input" id="staff_doc_file" name="document_file" required>
                            <label class="custom-file-label" for="staff_doc_file">Choose file</label>
                        </div></div>
                        <div class="col-md-3"><button type="submit" class="btn btn-sm btn-primary w-100">Upload</button></div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
