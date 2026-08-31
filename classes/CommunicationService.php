<?php

/**
 * SB-Tech — Communication service (email via PHPMailer, SMS via HTTP provider).
 *
 * Usage:
 *   CommunicationService::sendEmail($to, $subject, $body, $isHtml);
 *   CommunicationService::sendSms($to, $message);
 *   CommunicationService::sendWorkflowNotification($event, $receiverId, $details, $refId, $actorId);
 *
 * Delivery failures are logged to tbl_communication_logs but never throw —
 * a broken email must never block the main workflow (AC-COM-01.3).
 *
 * PHPMailer is loaded from vendor/ if present; otherwise a built-in
 * mail() fallback is used so the system works without composer install.
 */
class CommunicationService
{
    /**
     * Send an email. Returns ['ok' => bool, 'message' => string].
     */
    public static function sendEmail(string $to, string $subject, string $body, bool $isHtml = true): array
    {
        $settings = self::getSettings();
        if (empty($settings['smtp_host']) || empty($settings['smtp_from_email'])) {
            return ['ok' => false, 'message' => 'Email not configured — SMTP settings are empty.'];
        }

        // Try PHPMailer from vendor first.
        $phpmailerPath = dirname(__DIR__) . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
        if (file_exists($phpmailerPath)) {
            return self::sendViaPhpMailer($to, $subject, $body, $isHtml, $settings);
        }

        // Fallback: PHP built-in mail().
        return self::sendViaBuiltinMail($to, $subject, $body, $isHtml, $settings);
    }

    /**
     * Send an SMS. Returns ['ok' => bool, 'message' => string].
     * Uses a simple HTTP API (Sparrow-compatible).
     */
    public static function sendSms(string $to, string $message): array
    {
        $settings = self::getSettings();
        if (empty($settings['sms_api_key']) || empty($settings['sms_provider'])) {
            return ['ok' => false, 'message' => 'SMS not configured — provider settings are empty.'];
        }

        // Normalize phone: strip spaces, ensure + prefix for international.
        $to = preg_replace('/\s+/', '', $to);
        if (substr($to, 0, 1) !== '+') {
            $countryCode = (string) config('country_code', '+977');
            if (substr($to, 0, strlen($countryCode)) !== $countryCode) {
                $to = $countryCode . ltrim($to, '0');
            }
        }

        try {
            $provider = strtolower((string) $settings['sms_provider']);
            if ($provider === 'sparrow') {
                return self::sendViaSparrow($to, $message, $settings);
            }
            // Generic HTTP SMS provider (any REST API).
            return self::sendViaGenericSms($to, $message, $settings);
        } catch (Throwable $e) {
            self::logDelivery('SMS', $to, null, 'Failed', $e->getMessage());
            return ['ok' => false, 'message' => 'SMS failed: ' . $e->getMessage()];
        }
    }

    /**
     * Send a workflow notification via all configured channels (in-app + email + SMS).
     * Logs delivery to tbl_communication_logs.
     */
    public static function sendWorkflowNotification(string $event, int $receiverId, string $details, ?string $refId = null, ?int $actorId = null): void
    {
        // 1. In-app notification (always works).
        notifyUser($receiverId, $details, $event, $refId, $actorId);

        // 2. Check if this event type has email/SMS wired.
        if (!self::isEventWired($event)) {
            return;
        }

        $receiver = Database::instance()->selectOne(
            'SELECT `fullname`, `email`, `phone1` FROM `tbl_users_login` WHERE `id` = ?',
            [$receiverId]
        );
        if (!$receiver) {
            return;
        }

        // 3. Resolve template for this event.
        $template = self::getTemplateForEvent($event);

        // 4. Send email if configured and receiver has email.
        if (!empty($receiver['email'])) {
            $subject = self::renderTemplate($template['subject'] ?? $event, $receiver, $details);
            $body = self::renderTemplate($template['body'] ?? $details, $receiver, $details);
            $result = self::sendEmail($receiver['email'], $subject, $body);
            self::logDelivery('Email', $receiver['email'], null, $result['ok'] ? 'Sent' : 'Failed', $result['message'], $event);
        }

        // 5. Send SMS if configured and receiver has phone.
        if (!empty($receiver['phone1'])) {
            $smsBody = self::renderTemplate($template['sms_body'] ?? $details, $receiver, $details);
            $result = self::sendSms($receiver['phone1'], $smsBody);
            self::logDelivery('SMS', $receiver['phone1'], null, $result['ok'] ? 'Sent' : 'Failed', $result['message'], $event);
        }
    }

    /**
     * Bulk send a campaign (email or SMS) to a list of recipients.
     * Returns ['sent' => int, 'failed' => int].
     */
    public static function sendCampaign(int $campaignId): array
    {
        $db = Database::instance();
        $campaign = $db->selectOne('SELECT * FROM `tbl_communication_campaigns` WHERE `id` = ?', [$campaignId]);
        if (!$campaign) {
            return ['sent' => 0, 'failed' => 0];
        }

        $template = null;
        if ($campaign['template_id']) {
            $template = $db->selectOne('SELECT * FROM `tbl_communication_templates` WHERE `id` = ?', [(int) $campaign['template_id']]);
        }

        $recipients = array_filter(array_map('trim', explode("\n", (string) $campaign['recipients'])));
        $type = $campaign['type'];
        $sent = 0;
        $failed = 0;

        $db->update('tbl_communication_campaigns', ['status' => 'Sending', 'sent_at' => date('Y-m-d H:i:s')], '`id` = ?', [$campaignId]);

        foreach ($recipients as $recipient) {
            if ($recipient === '') {
                continue;
            }
            $subject = $template ? self::renderTemplate($template['subject'] ?? '', [], $template['body'] ?? '') : $campaign['name'];
            $body = $template ? self::renderTemplate($template['body'] ?? '', [], '') : $campaign['name'];

            if ($type === 'Email') {
                $result = self::sendEmail($recipient, $subject, $body);
            } else {
                $result = self::sendSms($recipient, $body);
            }

            $status = $result['ok'] ? 'Sent' : 'Failed';
            $db->insert('tbl_communication_logs', [
                'campaign_id'    => $campaignId,
                'type'           => $type,
                'recipient'      => $recipient,
                'subject'        => $type === 'Email' ? $subject : null,
                'status'         => $status,
                'error_message'  => $result['ok'] ? null : $result['message'],
                'sent_on'        => $result['ok'] ? date('Y-m-d H:i:s') : null,
                'added_by'       => Auth::id(),
            ]);

            if ($result['ok']) {
                $sent++;
            } else {
                $failed++;
            }
        }

        $db->update('tbl_communication_campaigns', [
            'status' => $failed === 0 ? 'Sent' : ($sent === 0 ? 'Failed' : 'Sent'),
        ], '`id` = ?', [$campaignId]);

        return ['sent' => $sent, 'failed' => $failed];
    }

    // ---- Internal helpers ----

    private static function getSettings(): array
    {
        static $settings = null;
        if ($settings === null) {
            $db = Database::instance();
            $row = $db->selectOne('SELECT * FROM `tbl_communication_settings` WHERE `is_active` = 1 ORDER BY `id` DESC LIMIT 1');
            $settings = $row ?: [];
            // Decrypt sensitive fields — let exceptions propagate so the
            // caller (sendEmail/sendSms) can report the failure clearly.
            if (!empty($settings['smtp_password_enc'])) {
                $settings['smtp_password'] = self::decrypt($settings['smtp_password_enc']);
            }
            if (!empty($settings['sms_api_key_enc'])) {
                $settings['sms_api_key'] = self::decrypt($settings['sms_api_key_enc']);
            }
        }
        return $settings;
    }

    private static function getTemplateForEvent(string $event): array
    {
        $db = Database::instance();
        $row = $db->selectOne(
            "SELECT * FROM `tbl_communication_templates` WHERE `name` = ? AND `is_active` = 1 LIMIT 1",
            [$event]
        );
        return $row ?: ['subject' => $event, 'body' => '', 'sms_body' => ''];
    }

    private static function isEventWired(string $event): bool
    {
        $wiredEvents = [
            'new_lead', 'leave_submitted', 'leave_approved', 'leave_rejected',
            'task_assigned', 'task_updated', 'grievance_assigned', 'grievance_updated',
            'expense_approved', 'expense_rejected', 'voucher_approved',
        ];
        return in_array($event, $wiredEvents, true);
    }

    private static function renderTemplate(string $text, array $user, string $details): string
    {
        $placeholders = [
            '{{name}}'       => $user['fullname'] ?? '',
            '{{email}}'      => $user['email'] ?? '',
            '{{phone}}'      => $user['phone1'] ?? '',
            '{{details}}'    => $details,
            '{{org_name}}'   => config('organization_name', 'Office'),
            '{{date}}'       => date('Y-m-d'),
            '{{time}}'       => date('H:i:s'),
        ];
        return str_replace(array_keys($placeholders), array_values($placeholders), $text);
    }

    private static function logDelivery(string $type, string $recipient, ?int $campaignId, string $status, ?string $error = null, ?string $event = null): void
    {
        try {
            Database::instance()->insert('tbl_communication_logs', [
                'campaign_id'   => $campaignId,
                'type'          => $type,
                'recipient'     => $recipient,
                'subject'       => $event,
                'status'        => $status,
                'error_message' => mb_substr((string) $error, 0, 500),
                'sent_on'       => $status === 'Sent' ? date('Y-m-d H:i:s') : null,
                'added_by'      => Auth::check() ? Auth::id() : null,
            ]);
        } catch (Throwable $e) {
            // Never block.
        }
    }

    // ---- PHPMailer transport ----

    private static function sendViaPhpMailer(string $to, string $subject, string $body, bool $isHtml, array $settings): array
    {
        try {
            $phpmailerPath = dirname(__DIR__) . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
            $smtpPath = dirname(__DIR__) . '/vendor/phpmailer/phpmailer/src/SMTP.php';
            $exceptionPath = dirname(__DIR__) . '/vendor/phpmailer/phpmailer/src/Exception.php';

            require_once $exceptionPath;
            require_once $smtpPath;
            require_once $phpmailerPath;

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = (string) $settings['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = (string) $settings['smtp_username'];
            $mail->Password = (string) ($settings['smtp_password'] ?? '');
            $mail->Port = (int) ($settings['smtp_port'] ?? 587);
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->CharSet = 'UTF-8';

            $fromName = (string) ($settings['smtp_from_name'] ?? config('organization_name', 'Office'));
            $fromEmail = (string) $settings['smtp_from_email'];
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);
            $mail->isHTML($isHtml);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);

            $mail->send();
            return ['ok' => true, 'message' => 'Email sent'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private static function sendViaBuiltinMail(string $to, string $subject, string $body, bool $isHtml, array $settings): array
    {
        $fromName = (string) ($settings['smtp_from_name'] ?? config('organization_name', 'Office'));
        $fromEmail = (string) $settings['smtp_from_email'];
        $headers = "From: {$fromName} <{$fromEmail}>\r\n";
        $headers .= "Reply-To: {$fromEmail}\r\n";
        if ($isHtml) {
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        }
        $ok = @mail($to, $subject, $body, $headers);
        return ['ok' => $ok, 'message' => $ok ? 'Email sent (built-in mail)' : 'mail() returned false'];
    }

    // ---- SMS transports ----

    private static function sendViaSparrow(string $to, string $message, array $settings): array
    {
        $apiKey = (string) ($settings['sms_api_key'] ?? '');
        $senderId = (string) ($settings['sms_sender_id'] ?? '');
        $url = 'https://sms_api.sparrow.com.np/api/v2/sms/send';
        $data = [
            'auth_token' => $apiKey,
            'to'         => $to,
            'from'       => $senderId,
            'text'       => $message,
        ];
        return self::httpPost($url, $data);
    }

    private static function sendViaGenericSms(string $to, string $message, array $settings): array
    {
        // Generic: expects sms_api_url, sms_api_key, sms_sender_id in settings.
        $apiKey = (string) ($settings['sms_api_key'] ?? '');
        $senderId = (string) ($settings['sms_sender_id'] ?? '');
        // Build URL from settings or use a placeholder.
        $url = 'https://api.sms-provider.com/send';
        $data = [
            'api_key'  => $apiKey,
            'sender'   => $senderId,
            'to'       => $to,
            'message'  => $message,
        ];
        return self::httpPost($url, $data);
    }

    private static function httpPost(string $url, array $data): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['ok' => false, 'message' => 'cURL error: ' . $error];
        }
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['ok' => true, 'message' => 'SMS sent (HTTP ' . $httpCode . ')'];
        }
        return ['ok' => false, 'message' => 'SMS API returned HTTP ' . $httpCode . ': ' . mb_substr((string) $response, 0, 200)];
    }

    // ---- Encryption helpers (simple AES for stored credentials) ----

    private static function encrypt(string $plain): string
    {
        $key = self::getEncryptionKey();
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            return $plain;
        }
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt an AES-256-CBC encrypted value.
     * Throws RuntimeException on failure instead of returning the raw blob,
     * which would silently fail SMTP/SMS auth.
     */
    private static function decrypt(string $encoded): string
    {
        $key = self::getEncryptionKey();
        $data = base64_decode($encoded, true);
        if ($data === false || strlen($data) < 17) {
            throw new RuntimeException('Decryption failed: invalid encoded data.');
        }
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            throw new RuntimeException('Decryption failed: wrong key or corrupted data.');
        }
        return $decrypted;
    }

    private static function getEncryptionKey(): string
    {
        // Use a config key or derive from app secret.
        $secret = config('app_encryption_key', 'sb-tech-default-key-change-me!');
        return hash('sha256', $secret, true);
    }

    /**
     * Encrypt and store a credential value (for settings UI).
     */
    public static function encryptSetting(string $plain): string
    {
        return self::encrypt($plain);
    }
}
