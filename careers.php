<?php
require __DIR__ . '/website/includes/site.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $careerId = (int) ($_POST['career_id'] ?? 0);
    $name = trim((string) ($_POST['applicant_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $cover = trim((string) ($_POST['cover_letter'] ?? ''));

    $career = $db->selectOne('SELECT `id`, `title` FROM `tbl_cms_careers` WHERE `id` = ? AND `status` = ?', [$careerId, 'Open']);
    if (!$career) {
        $error = 'This position is no longer open.';
    } elseif ($name === '') {
        $error = 'Your name is required.';
    } elseif (empty($_FILES['resume']['name'])) {
        $error = 'Please attach your resume.';
    } else {
        $up = validateUpload($_FILES['resume'], ['pdf', 'doc', 'docx']);
        if (!$up['ok']) {
            $error = $up['message'];
        } else {
            $loc = storeUpload($_FILES['resume'], 'careers', $up['extension']);
            if (!$loc) {
                $error = 'Could not store your resume.';
            } else {
                $db->insert('tbl_cms_career_applications', [
                    'career_id'       => $careerId,
                    'applicant_name'  => $name,
                    'email'           => $email ?: null,
                    'phone'           => $phone ?: null,
                    'cover_letter'    => $cover ?: null,
                    'resume_name'     => basename((string) $_FILES['resume']['name']),
                    'resume_location' => $loc,
                ]);
                notifyPermissionHolders('manage_leads', 'New career application for "' . e($career['title']) . '" from ' . e($name) . '.', 'Career', (string) $careerId);
                setFlash('success', 'Application submitted. We\'ll get back to you soon.');
                redirect(siteUrl('careers'));
                exit;
            }
        }
    }
}

$jobs = $db->select(
    "SELECT `id`, `title`, `job_type`, `designation`, `location`, `deadline`, `salary`, `description`, `requirements` FROM `tbl_cms_careers` WHERE `status` = 'Open' AND (`deadline` IS NULL OR `deadline` >= CURDATE())
     ORDER BY `added_on` DESC"
);
require __DIR__ . '/website/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <h1>Careers</h1>
        <p>Join a team that builds great things</p>
    </div>
</section>
<section>
    <div class="container">

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if (!$jobs): ?>
            <p class="empty-note">No open positions right now. Check back soon.</p>
        <?php endif; ?>

        <div class="row" data-reveal-group>
            <?php foreach ($jobs as $job): ?>
                <div class="col-md-6 mb-3">
                    <div class="card h-100 reveal">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start">
                                <h5 class="mb-1 card-title-sm"><?= e($job['title']) ?></h5>
                                <span class="badge eyebrow-badge flex-shrink-0 ml-2"><?= e($job['job_type']) ?></span>
                            </div>
                            <p class="mb-2 feed-meta">
                                <i class="fas fa-map-marker-alt mr-1"></i><?= e($job['designation'] ?: '') ?><?= $job['location'] ? ' · ' . e($job['location']) : '' ?>
                                <?= $job['deadline'] ? ' · deadline ' . e(formatDateView($job['deadline'])) : '' ?>
                                <?= $job['salary'] ? ' · ' . e($job['salary']) : '' ?>
                            </p>
                            <?php if ($job['description']): ?><p class="small"><?= nl2br(e($job['description'])) ?></p><?php endif; ?>
                            <?php if ($job['requirements']): ?>
                                <h6 class="small text-uppercase">Requirements</h6>
                                <p class="small"><?= nl2br(e($job['requirements'])) ?></p>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-primary" data-toggle="collapse" data-target="#apply-<?= (int) $job['id'] ?>">Apply now</button>

                            <div class="collapse mt-3" id="apply-<?= (int) $job['id'] ?>">
                                <form action="<?= siteUrl('careers') ?>" method="post" enctype="multipart/form-data" class="border-top pt-3">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="career_id" value="<?= (int) $job['id'] ?>">
                                    <div class="form-group"><label>Full name *</label>
                                        <input type="text" name="applicant_name" class="form-control" required></div>
                                    <div class="row">
                                        <div class="col-6 form-group"><label>Email</label>
                                            <input type="email" name="email" class="form-control"></div>
                                        <div class="col-6 form-group"><label>Phone</label>
                                            <input type="text" name="phone" class="form-control"></div>
                                    </div>
                                    <div class="form-group"><label>Cover letter</label>
                                        <textarea name="cover_letter" class="form-control" rows="3"></textarea></div>
                                    <div class="form-group"><label>Resume (PDF/DOC) *</label>
                                        <input type="file" name="resume" class="form-control-file" required accept=".pdf,.doc,.docx"></div>
                                    <button type="submit" class="btn btn-success">Submit application</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/website/includes/footer.php'; ?>
