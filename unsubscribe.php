<?php
// One-click unsubscribe for bulk/outreach email. Link: /unsubscribe.php?e=<email>&t=<hmac>
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/settings.php';
$db = get_db();
try { $db->exec("CREATE TABLE IF NOT EXISTS email_unsubscribes (email VARCHAR(190) PRIMARY KEY, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)"); } catch (Throwable $e) {}
function unsub_token(PDO $db, string $email): string {
    $key = get_setting($db, 'unsub_key', '');
    if ($key === '') { $key = bin2hex(random_bytes(16)); set_setting($db, 'unsub_key', $key); }
    return substr(hash_hmac('sha256', strtolower($email), $key), 0, 32);
}
if (basename($_SERVER['SCRIPT_NAME']) === 'unsubscribe.php') {
    $e = strtolower(trim($_GET['e'] ?? $_POST['e'] ?? '')); $t = (string)($_GET['t'] ?? $_POST['t'] ?? '');
    $ok = $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL) && hash_equals(unsub_token($db, $e), $t);
    if ($ok) { $db->prepare("INSERT IGNORE INTO email_unsubscribes (email) VALUES (?)")->execute([$e]); }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') { http_response_code($ok ? 200 : 400); exit; } // one-click header
    ?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Unsubscribe</title></head>
<body style="font-family:Arial,sans-serif;background:#F7F2E7;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;">
<div style="background:#fff;border-radius:14px;padding:32px;max-width:420px;text-align:center;">
<h2 style="color:#0F5257;margin-top:0;"><?= $ok ? 'You are unsubscribed' : 'Link not valid' ?></h2>
<p style="color:#4a4a42;"><?= $ok ? 'We will not send you any more bulk emails. Account emails such as payment receipts may still arrive.' : 'This unsubscribe link is invalid or incomplete. Reply to any of our emails and we will remove you.' ?></p>
</div></body></html>
<?php }
