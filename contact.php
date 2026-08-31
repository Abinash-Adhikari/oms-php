<?php
require __DIR__ . '/website/includes/site.php';
$setup = siteSetup();

$sent = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    if ($name === '' || $message === '') {
        $error = 'Name and message are required.';
    } else {
        $db->transaction(function ($db) use ($name, $email, $phone, $subject, $message) {
            $leadId = $db->insert('tbl_leads', [
                'source'            => 'Website',
                'company'           => null,
                'contact_name'      => $name,
                'email'             => $email ?: null,
                'phone'             => $phone ?: null,
                'service_interest'  => $subject ?: null,
                'message'           => $message,
                'priority'          => 'Warm',
                'stage'             => 'New',
                'added_by'          => null,
            ]);
            // Source of truth (AC-LE-01.2): raw inquiry + inbox copy.
            $db->insert('tbl_cms_contacts_us', [
                'name' => $name, 'email' => $email ?: null, 'phone' => $phone ?: null,
                'subject' => $subject ?: null, 'message' => $message,
                'service_interest' => $subject ?: null, 'source_type' => 'Website',
                'lead_id' => $leadId,
            ]);
            $db->insert('tbl_cms_messages', [
                'name' => $name, 'email' => $email ?: null, 'phone' => $phone ?: null,
                'subject' => $subject ?: 'Website inquiry', 'message' => $message,
            ]);
            return $leadId;
        });
        notifyPermissionHolders('manage_leads', 'New website lead: ' . e($name) . ($subject ? ' (' . e($subject) . ')' : '') . ' — ' . e($message), 'Lead', null);
        $sent = true;
    }
}

$services = $db->select('SELECT `id`, `title` FROM `tbl_cms_services` WHERE `is_active` = 1 ORDER BY `position`, `id`');
require __DIR__ . '/website/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <h1>Contact us</h1>
        <p>Tell us about your project — we usually reply within one business day</p>
    </div>
</section>
<section>
    <div class="container" data-reveal-group>
        <div class="row">
            <div class="col-lg-7">
                <?php if ($sent): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle mr-1"></i>Thank you! Your message has been received. We'll reply shortly.</div>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>
                <div class="card shadow-sm reveal">
                    <div class="card-body p-4">
                        <form action="<?= siteUrl('contact') ?>" method="post">
                            <?= csrfField() ?>
                            <div class="form-group"><label>Full name *</label>
                                <input type="text" name="name" class="form-control" required></div>
                            <div class="row">
                                <div class="col-6 form-group"><label>Email</label>
                                    <input type="email" name="email" class="form-control"></div>
                                <div class="col-6 form-group"><label>Phone</label>
                                    <input type="text" name="phone" class="form-control"></div>
                            </div>
                            <div class="form-group"><label>Service of interest</label>
                                <select name="subject" class="form-control">
                                    <option value="">General inquiry</option>
                                    <?php foreach ($services as $s): ?>
                                        <option value="<?= e($s['title']) ?>"><?= e($s['title']) ?></option>
                                    <?php endforeach; ?>
                                </select></div>
                            <div class="form-group"><label>Message *</label>
                                <textarea name="message" class="form-control" rows="4" required></textarea></div>
                            <button type="submit" class="btn btn-primary">Send message</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card shadow-sm h-100 reveal">
                    <div class="card-body p-4">
                        <div class="icon-chip-row">
                            <span class="icon-chip"><i class="fas fa-paper-plane"></i></span>
                            <h5 class="mb-0">Get in touch</h5>
                        </div>
                        <?php if (!empty($setup['contact_email'])): ?><p class="mb-1"><i class="fas fa-envelope mr-2 text-primary"></i><?= e($setup['contact_email']) ?></p><?php endif; ?>
                        <?php if (!empty($setup['contact_phone'])): ?><p class="mb-1"><i class="fas fa-phone mr-2 text-primary"></i><?= e($setup['contact_phone']) ?></p><?php endif; ?>
                        <?php if (!empty($setup['maps_embed'])): ?>
                            <div class="mt-3 embed-responsive embed-responsive-16by9"><?= sanitizeEmbed($setup['maps_embed']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/website/includes/footer.php'; ?>
