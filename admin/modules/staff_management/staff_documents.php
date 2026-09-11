<?php
/**
 * SB-Tech — Staff Management / Staff Documents (US-STF-02).
 * Per-staff document registry (upload + delete), reachable from the Files
 * button on the staff list or from the staff selector.
 */
$db = Database::instance();

$viewUser = null;
if (isset($_GET['id'])) {
    $viewUser = $db->selectOne('SELECT * FROM `tbl_users_login` WHERE `id` = ?', [(int) $_GET['id']]);
}
$staff = $db->select('SELECT id, fullname, username, status FROM `tbl_users_login` ORDER BY fullname');
?>
<div class="row">
    <div class="col-md-3">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Staff</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover">
                    <tbody>
                    <?php foreach ($staff as $s): ?>
                        <tr class="<?= $viewUser && (int) $viewUser['id'] === (int) $s['id'] ? 'table-primary' : '' ?>">
                            <td><a href="<?= pageUrl('staff_management', 'staff_documents') ?>&id=<?= (int) $s['id'] ?>"><?= e($s['fullname']) ?></a>
                                <small class="d-block text-muted">@<?= e($s['username']) ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-9">
        <?php if (!$viewUser): ?>
            <div class="callout callout-info"><h5>Select a staff member</h5><p>Pick a staff member to manage their files, or use the Files button on the staff list.</p></div>
        <?php else: ?>
            <div class="card">
                <div class="card-header"><h3 class="card-title">Documents (<?= (int) ($db->count('tbl_staff_documents', 'staff_id = ?', [(int) $viewUser['id']])) ?>) — <?= e($viewUser['fullname']) ?></h3></div>
                <div class="card-body">
                    <table class="table table-sm table-striped">
                        <thead><tr><th>Title</th><th>Type</th><th>File</th><th>Size</th><th class="text-right">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($db->select('SELECT * FROM `tbl_staff_documents` WHERE `staff_id` = ? ORDER BY added_on DESC', [(int) $viewUser['id']]) as $doc): ?>
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
                                        <input type="hidden" name="staff_id" value="<?= (int) $viewUser['id'] ?>">
                                        <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete document?"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$db->count('tbl_staff_documents', 'staff_id = ?', [(int) $viewUser['id']])): ?><tr><td colspan="5" class="text-center text-muted">No documents.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                    <form action="operation.php?module=staff_management&page=staff_documents" method="post" enctype="multipart/form-data" class="row mt-3">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="staff_id" value="<?= (int) $viewUser['id'] ?>">
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
    </div>
</div>