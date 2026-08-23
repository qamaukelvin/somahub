<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/mailer.php';
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');
$agreedToTerms = isset($_POST['agreed_to_terms']) ? 1 : 0;

if (!$email || !$subject || !$message) {
    die('Please fill in the required fields and try again.');
}

if (!$agreedToTerms) {
    die('Please confirm you agree to the Terms of Service and Privacy Policy before submitting.');
}

$stmt = $db->prepare("
    INSERT INTO contact_messages (email, phone, subject, message)
    VALUES (?, ?, ?, ?)
");
$stmt->execute([$email, $phone, $subject, $message]);

$body = "
    <h2 style='color:#0F5257;margin-top:0;'>New Contact Message</h2>
    <table style='width:100%;font-size:14px;margin:16px 0;'>
        <tr><td style='color:#6E6A5C;padding:4px 0;'>Email</td><td><strong>" . htmlspecialchars($email) . "</strong></td></tr>
        <tr><td style='color:#6E6A5C;padding:4px 0;'>Phone</td><td>" . htmlspecialchars($phone ?: 'Not provided') . "</td></tr>
        <tr><td style='color:#6E6A5C;padding:4px 0;'>Subject</td><td>" . htmlspecialchars($subject) . "</td></tr>
    </table>
    <p style='color:#6E6A5C;'>Message:</p><p>" . nl2br(htmlspecialchars($message)) . "</p>
";
send_somahub_email('admin@somahub.top', "New contact message: {$subject}", $body, $email);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Thank You — Somahub</title>
<style>
  body{font-family:'Manrope',sans-serif;background:#F7F2E7;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:24px;}
  .card{background:#fff;border-radius:20px;padding:40px;max-width:420px;text-align:center;border:1px solid #E5DFCC;}
  h1{color:#0F5257;font-size:1.4rem;margin-bottom:12px;}
  p{color:#6E6A5C;font-size:0.95rem;line-height:1.6;}
  a{display:inline-block;margin-top:24px;background:#0F5257;color:#F7F2E7;padding:12px 24px;border-radius:24px;text-decoration:none;font-weight:700;font-size:0.9rem;}
</style>
</head>
<body>
  <div class="card">
    <h1>Thank you</h1>
    <p>We have received your message and will get back to you shortly.</p>
    <a href="index.php">Back to Home</a>
  </div>
</body>
</html>
