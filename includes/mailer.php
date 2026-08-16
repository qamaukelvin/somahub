<?php
require_once __DIR__ . '/../vendor/phpmailer/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// ============================================================
// IMPORTANT — fill these in with your REAL mailbox credentials
// from cPanel's Email Accounts page before this will work.
// Click "Connect Devices" next to no-reply@somahub.top in cPanel
// to see the exact SMTP host/port/username Truehost gives you.
// ============================================================
define('SMTP_HOST', 'mail.somahub.top');        // <-- confirm exact value in cPanel
define('SMTP_PORT', 465);                        // 465 for SSL, or 587 for TLS
define('SMTP_USERNAME', 'no-reply@somahub.top'); // the full mailbox address
define('SMTP_PASSWORD', '');
define('SMTP_ENCRYPTION', PHPMailer::ENCRYPTION_SMTPS); // matches port 465; use STARTTLS for port 587

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

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;

        // --- TEMPORARY DEBUG: remove once the issue is found ---
        $mail->SMTPDebug = 2; // prints the full SMTP conversation
        $mail->Debugoutput = function ($str, $level) {
            error_log("Somahub SMTP debug: $str");
        };
        // ---------------------------------------------------------

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
        error_log('Somahub SMTP config: Host=' . SMTP_HOST . ' Port=' . SMTP_PORT . ' User=' . SMTP_USERNAME);
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