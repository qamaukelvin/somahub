<?php
// ============================================================
// TEMPORARY EMAIL DIAGNOSTIC TOOL
// Delete this file from your server immediately after use.
// ============================================================

require_once __DIR__ . '/vendor/phpmailer/Exception.php';
require_once __DIR__ . '/vendor/phpmailer/PHPMailer.php';
require_once __DIR__ . '/vendor/phpmailer/SMTP.php';
require_once __DIR__ . '/config/mail.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

$result = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $testTo = trim($_POST['test_email'] ?? '');

    $mail = new PHPMailer(true);
    $mail->SMTPDebug = 2; // verbose — shows the full SMTP conversation, not just the final error
    $debugOutput = [];
    $mail->Debugoutput = function($str, $level) use (&$debugOutput) {
        $debugOutput[] = htmlspecialchars($str);
    };

    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;

        $mail->setFrom('no-reply@somahub.top', 'Somahub Test');
        $mail->addAddress($testTo);
        $mail->isHTML(true);
        $mail->Subject = 'Somahub test email — ' . date('H:i:s');
        $mail->Body = '<p>If you received this, SMTP sending is working correctly.</p>';

        $mail->send();
        $success = true;
        $result = "Sent successfully to $testTo. Check that inbox (and spam folder) now.";
    } catch (PHPMailerException $e) {
        $result = "FAILED. PHPMailer's exact error:\n\n" . $mail->ErrorInfo;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Email Diagnostic — TEMPORARY</title>
<style>
  body{font-family:Arial,sans-serif;background:#1B1B18;padding:20px;color:#fff;}
  .card{background:#fff;color:#1a1a1a;padding:28px;border-radius:8px;max-width:600px;margin:0 auto;}
  .warn{background:#FBE8E4;color:#8C3B2E;padding:12px 14px;border-radius:6px;font-size:0.82rem;margin-bottom:20px;}
  input{width:100%;padding:10px;margin-bottom:14px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;}
  button{padding:12px 24px;background:#0F5257;color:#fff;border:none;border-radius:4px;font-weight:600;cursor:pointer;}
  .result{margin-top:20px;padding:16px;border-radius:6px;white-space:pre-wrap;font-family:monospace;font-size:0.85rem;}
  .result.success{background:#E4F0E7;color:#1B4D3E;}
  .result.fail{background:#FBE8E4;color:#8C3B2E;}
  .debug{margin-top:16px;background:#1a1a1a;color:#0f0;padding:14px;border-radius:6px;font-family:monospace;font-size:0.75rem;max-height:300px;overflow-y:auto;white-space:pre-wrap;}
</style>
</head>
<body>
  <div class="card">
    <div class="warn">⚠ Temporary diagnostic tool — delete this file when done.</div>
    <h2>Email Sending Test</h2>
    <p>Current config: <?= defined('SMTP_HOST') ? htmlspecialchars(SMTP_HOST) : 'NOT SET' ?> : <?= defined('SMTP_PORT') ? SMTP_PORT : '?' ?> as <?= defined('SMTP_USERNAME') ? htmlspecialchars(SMTP_USERNAME) : 'NOT SET' ?></p>
    <form method="POST">
      <input type="email" name="test_email" placeholder="Send test to this address" required value="<?= htmlspecialchars($_POST['test_email'] ?? '') ?>">
      <button type="submit">Send Test Email</button>
    </form>
    <?php if ($result): ?>
      <div class="result <?= $success ? 'success' : 'fail' ?>"><?= htmlspecialchars($result) ?></div>
    <?php endif; ?>
    <?php if (!empty($debugOutput)): ?>
      <div class="debug"><?= implode('', $debugOutput) ?></div>
    <?php endif; ?>
  </div>
</body>
</html>