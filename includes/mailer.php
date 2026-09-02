<?php
require_once __DIR__ . '/../vendor/phpmailer/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/settings.php';

/**
 * Resolves the real SMTP values to use: whatever's set in admin/settings.php
 * takes priority; config/mail.php's constants are the fallback if a setting
 * is left blank (or if the settings table/DB isn't reachable for some
 * reason). This is why these are local variables, not constants — you can't
 * redefine SMTP_HOST etc twice, so config/mail.php keeps its original
 * values as the safety net instead of being overwritten.
 */
function resolve_smtp_config(): array {
    $config = [
        'host' => SMTP_HOST,
        'port' => SMTP_PORT,
        'username' => SMTP_USERNAME,
        'password' => SMTP_PASSWORD,
        'encryption' => SMTP_ENCRYPTION,
    ];

    try {
        if (function_exists('get_db')) {
            $db = get_db();
            $config['host'] = get_setting($db, 'smtp_host', $config['host']);
            $config['port'] = (int)get_setting($db, 'smtp_port', (string)$config['port']);
            $config['username'] = get_setting($db, 'smtp_username', $config['username']);
            $config['password'] = get_setting($db, 'smtp_password', $config['password']);
            $encryptionSetting = get_setting($db, 'smtp_encryption', '');
            if ($encryptionSetting === 'tls') {
                $config['encryption'] = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($encryptionSetting === 'ssl') {
                $config['encryption'] = PHPMailer::ENCRYPTION_SMTPS;
            }
        }
    } catch (\Throwable $e) {
        // keep config/mail.php's values — this must never block sending
    }

    return $config;
}

/**
 * Sends an HTML email using PHPMailer over SMTP through your real
 * no-reply@somahub.top mailbox. Far more reliable than native mail()
 * on shared hosting, and won't hang or silently fail the same way.
 *
 * @param string $to
 * @param string $subject
 * @param string $bodyHtml
 * @param string $replyTo   Defaults to hello@somahub.top
 * @return bool             True if accepted for delivery
 */
function send_somahub_email(string $to, string $subject, string $bodyHtml, string $replyTo = 'hello@somahub.top'): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $smtp = resolve_smtp_config();
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $smtp['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp['username'];
        $mail->Password = $smtp['password'];
        $mail->SMTPSecure = $smtp['encryption'];
        $mail->Port = $smtp['port'];

        $mail->setFrom('no-reply@somahub.top', 'Somahub');
        $mail->addAddress($to);
        $mail->addReplyTo($replyTo);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = email_wrapper($bodyHtml);
        $mail->AltBody = strip_tags($bodyHtml);

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        // Log quietly rather than breaking the page the user is on —
        // a failed email should never be the reason a form submission fails
        error_log('Somahub mail failed: ' . $mail->ErrorInfo);
        error_log('Somahub SMTP config: Host=' . $smtp['host'] . ' Port=' . $smtp['port'] . ' User=' . $smtp['username']);
        return false;
    }
}

/**
 * Wraps email content in simple, brand-consistent HTML styling.
 */
function email_wrapper(string $innerHtml): string {
    return '
    <div style="font-family: Arial, sans-serif; background:#F7F2E7; padding:30px 20px;">
      <div style="max-width:480px; margin:0 auto; background:#fff; border-radius:12px; overflow:hidden;">
        <div style="background:#0F5257; padding:20px 24px;">
          <span style="color:#F2A65A; font-weight:800; font-size:18px;">●</span>
          <span style="color:#F7F2E7; font-weight:800; font-size:18px; margin-left:6px;">somahub</span>
        </div>
        <div style="padding:24px; color:#1C1C16; font-size:14px; line-height:1.6;">
          ' . $innerHtml . '
        </div>
        <div style="padding:16px 24px; background:#F7F2E7; color:#6E6A5C; font-size:12px; text-align:center;">
          Somahub · Websites for Kenyan schools · somahub.top
        </div>
      </div>
    </div>';
}