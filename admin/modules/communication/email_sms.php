<?php
/**
 * SB-Tech — Communication / Email & SMS settings (US-COM-01).
 * Configure SMTP, SMS provider, and view delivery stats.
 */
$db = Database::instance();
$settings = $db->selectOne('SELECT * FROM `tbl_communication_settings` WHERE `is_active` = 1 ORDER BY `id` DESC LIMIT 1') ?: [];

$totalSent = (int) ($db->selectOne("SELECT COUNT(*) AS c FROM `tbl_communication_logs` WHERE `status` = 'Sent'")['c'] ?? 0);
$totalFailed = (int) ($db->selectOne("SELECT COUNT(*) AS c FROM `tbl_communication_logs` WHERE `status` = 'Failed'")['c'] ?? 0);
$totalEmail = (int) ($db->selectOne("SELECT COUNT(*) AS c FROM `tbl_communication_logs` WHERE `type` = 'Email'")['c'] ?? 0);
$totalSms = (int) ($db->selectOne("SELECT COUNT(*) AS c FROM `tbl_communication_logs` WHERE `type` = 'SMS'")['c'] ?? 0);

$recentLogs = $db->select(
    'SELECT l.*, u.fullname AS actor_name FROM `tbl_communication_logs` l
     LEFT JOIN `tbl_users_login` u ON u.id = l.added_by
     ORDER BY l.id DESC LIMIT 20'
);
?>

<div class="row">
    <div class="col-md-3">
        <div class="info-box"><span class="info-box-icon bg-info"><i class="fas fa-envelope"></i></span>
            <div class="info-box-content"><span class="info-box-text">Emails Sent</span><span class="info-box-number"><?= $totalEmail ?></span></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="info-box"><span class="info-box-icon bg-success"><i class="fas fa-sms"></i></span>
            <div class="info-box-content"><span class="info-box-text">SMS Sent</span><span class="info-box-number"><?= $totalSms ?></span></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="info-box"><span class="info-box-icon bg-warning"><i class="fas fa-check-circle"></i></span>
            <div class="info-box-content"><span class="info-box-text">Delivered</span><span class="info-box-number"><?= $totalSent ?></span></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="info-box"><span class="info-box-icon bg-danger"><i class="fas fa-times-circle"></i></span>
            <div class="info-box-content"><span class="info-box-text">Failed</span><span class="info-box-number"><?= $totalFailed ?></span></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-cog mr-1"></i>SMTP / Email Settings</h3></div>
    <div class="card-body">
        <div class="callout callout-info">
            <h5><i class="fas fa-info-circle mr-1"></i>Brevo (Sendinblue) Setup</h5>
            <p class="mb-1">SMTP is pre-configured for Brevo. To complete setup:</p>
            <ol class="mb-0">
                <li>Login to <a href="https://app.brevo.com/smtp/" target="_blank">Brevo SMTP Settings</a></li>
                <li>Copy your <strong>SMTP Username</strong> (your Brevo login email)</li>
                <li>Generate an <strong>SMTP Key</strong> under Settings → SMTP & API → SMTP Key</li>
                <li>Verify your <strong>sender email</strong> under Settings → Senders & IP</li>
                <li>Fill in the fields below and save</li>
            </ol>
        </div>
        <form action="operation.php?module=communication&page=email_sms_operation" method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_email_settings">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group"><label>SMTP Host</label>
                        <input type="text" name="smtp_host" class="form-control" value="<?= e($settings['smtp_host'] ?? 'smtp-relay.brevo.com') ?>" placeholder="smtp-relay.brevo.com"></div>
                    <div class="form-group"><label>SMTP Port</label>
                        <input type="number" name="smtp_port" class="form-control" value="<?= e($settings['smtp_port'] ?? '587') ?>"></div>
                    <div class="form-group"><label>SMTP Username</label>
                        <input type="text" name="smtp_username" class="form-control" value="<?= e($settings['smtp_username'] ?? '') ?>" placeholder="your-email@gmail.com"></div>
                </div>
                <div class="col-md-6">
                    <div class="form-group"><label>SMTP Password (API Key)</label>
                        <input type="password" name="smtp_password" class="form-control" value="" placeholder="<?= $settings['smtp_password_enc'] ? '•••••••• (set)' : 'Paste your Brevo SMTP key here' ?>" autocomplete="new-password"></div>
                    <div class="form-group"><label>From Name</label>
                        <input type="text" name="smtp_from_name" class="form-control" value="<?= e($settings['smtp_from_name'] ?? config('organization_name', 'SB-Tech')) ?>"></div>
                    <div class="form-group"><label>From Email</label>
                        <input type="email" name="smtp_from_email" class="form-control" value="<?= e($settings['smtp_from_email'] ?? '') ?>" placeholder="noreply@yourdomain.com"></div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save Email Settings</button>
            <a href="operation.php?module=communication&page=test_email_operation" class="btn btn-outline-info ml-2" onclick="return confirm('Send a test email to your admin email?')"><i class="fas fa-paper-plane mr-1"></i>Send Test Email</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-sms mr-1"></i>SMS Settings</h3></div>
    <div class="card-body">
        <form action="operation.php?module=communication&page=email_sms_operation" method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_sms_settings">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group"><label>SMS Provider</label>
                        <select name="sms_provider" class="form-control">
                            <option value="">— Select —</option>
                            <option value="Sparrow" <?= ($settings['sms_provider'] ?? '') === 'Sparrow' ? 'selected' : '' ?>>Sparrow</option>
                            <option value="Generic" <?= ($settings['sms_provider'] ?? '') === 'Generic' ? 'selected' : '' ?>>Generic HTTP API</option>
                        </select></div>
                </div>
                <div class="col-md-4">
                    <div class="form-group"><label>API Key</label>
                        <input type="password" name="sms_api_key" class="form-control" value="" placeholder="<?= $settings['sms_api_key_enc'] ? '•••••••• (set)' : 'Not set' ?>"></div>
                </div>
                <div class="col-md-4">
                    <div class="form-group"><label>Sender ID</label>
                        <input type="text" name="sms_sender_id" class="form-control" value="<?= e($settings['sms_sender_id'] ?? '') ?>"></div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save SMS Settings</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-history mr-1"></i>Recent Delivery Logs</h3></div>
    <div class="card-body p-0">
        <table class="table table-sm table-striped">
            <thead><tr><th>Type</th><th>Recipient</th><th>Subject/Event</th><th>Status</th><th>Error</th><th>Date</th></tr></thead>
            <tbody>
            <?php if (!$recentLogs): ?>
                <tr><td colspan="6" class="text-muted text-center">No logs yet.</td></tr>
            <?php else: foreach ($recentLogs as $log): ?>
                <tr>
                    <td><span class="badge badge-<?= $log['type'] === 'Email' ? 'primary' : 'success' ?>"><?= e($log['type']) ?></span></td>
                    <td><?= e($log['recipient']) ?></td>
                    <td><?= e($log['subject']) ?></td>
                    <td><span class="badge badge-<?= $log['status'] === 'Sent' ? 'success' : 'danger' ?>"><?= e($log['status']) ?></span></td>
                    <td class="text-danger small"><?= e($log['error_message']) ?></td>
                    <td><?= e($log['sent_on'] ?? $log['added_on']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
