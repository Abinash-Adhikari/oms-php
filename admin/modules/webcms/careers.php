<?php
/**
 * SB-Tech — Website CMS / Careers: job postings (generic CRUD) +
 * the applications queue (status pipeline: New → Shortlisted → Interview
 * → Offer → Rejected).
 */
$_pageTitle = 'Website Careers';
$_pageSections = ['career'];
$_pageKey = 'careers';
include __DIR__ . '/includes/cms_page.php';

$db = Database::instance();
$apps = $db->select(
    'SELECT a.*, c.title AS job_title
     FROM `tbl_cms_career_applications` a
     JOIN `tbl_cms_careers` c ON c.id = a.career_id
     ORDER BY FIELD(a.status, "New", "Shortlisted", "Interview", "Offer", "Rejected"), a.added_on DESC'
);
$appStatuses = ['New', 'Shortlisted', 'Interview', 'Offer', 'Rejected'];
?>
<div class="card card-outline mt-3">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-inbox mr-1"></i>Career applications (<?= count($apps) ?>)</h3></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead><tr><th>#</th><th>Applicant</th><th>Job</th><th>Email / Phone</th><th>Resume</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($apps as $i => $a): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($a['applicant_name']) ?><br><small class="text-muted"><?= e(date('M j, Y', strtotime($a['added_on']))) ?></small></td>
                        <td><?= e($a['job_title']) ?></td>
                        <td><?= e($a['email'] ?: '—') ?><br><small><?= e($a['phone'] ?: '—') ?></small></td>
                        <td><?= $a['resume_location'] ? '<a href="' . assetUrl('user_uploads/' . $a['resume_location']) . '" target="_blank"><i class="fas fa-file mr-1"></i>' . e($a['resume_name']) . '</a>' : '—' ?></td>
                        <td><span class="badge badge-<?= ['New' => 'primary', 'Shortlisted' => 'info', 'Interview' => 'warning', 'Offer' => 'success', 'Rejected' => 'danger'][$a['status']] ?? 'secondary' ?>"><?= e($a['status']) ?></span></td>
                        <td class="text-right">
                            <form action="operation.php?module=webcms&page=careers" method="post" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="update_career_app">
                                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                                    <?php foreach ($appStatuses as $st): ?>
                                        <option value="<?= $st ?>" <?= $a['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$apps): ?><tr><td colspan="7" class="text-center text-muted">No applications yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
