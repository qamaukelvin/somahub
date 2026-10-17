<?php
// Landing page for the link emailed to the NEW address. Owning that inbox
// is the proof; the password was already checked when the change was requested.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/app_log.php';
$db = get_db();
$token = (string)($_GET['token'] ?? '');
$ok = false;
$msg = 'This confirmation link is invalid or has expired. Request the change again from your account page.';

if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    try {
        $q = $db->prepare("SELECT * FROM email_change_requests WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1");
        $q->execute([hash('sha256', $token)]);
        $req = $q->fetch();
        if ($req) {
            $dupe = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $dupe->execute([$req['new_email'], $req['user_id']]);
            if ($dupe->fetch()) {
                $msg = 'That email address is now used by another account, so it cannot be set.';
            } else {
                $u = $db->prepare("SELECT id, name, email FROM users WHERE id = ?");
                $u->execute([$req['user_id']]);
                $user = $u->fetch();
                $oldEmail = $user['email'] ?? '';
                $db->beginTransaction();
                $db->prepare("UPDATE users SET email = ? WHERE id = ?")->execute([$req['new_email'], $req['user_id']]);
                $db->prepare("UPDATE email_change_requests SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")->execute([$req['user_id']]);
                $db->commit();
                $ok = true;
                $msg = 'Your login email is now ' . htmlspecialchars($req['new_email']) . '. Use it the next time you log in.';
                app_log('email changed for user ' . $req['user_id']);
                if (filter_var($oldEmail, FILTER_VALIDATE_EMAIL)) {
                    send_somahub_email($oldEmail, 'Your Somahub login email was changed',
                        "<h2 style='color:#0F5257;margin-top:0;'>Login email changed</h2><p>The login email for your Somahub account was changed to <strong>" . htmlspecialchars($req['new_email']) . "</strong>. If this was not you, contact us straight away at admin@somahub.top.</p>");
                }
            }
        }
    } catch (\Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        app_log('confirm-email-change failed: ' . $e->getMessage());
        $msg = 'Something went wrong. Please try again from your account page.';
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Confirm email change</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
<?php if (current_user()) include __DIR__ . '/_styles.php'; ?>
<style>body{font-family:Arial,sans-serif;background:#F7F2E7;color:#1C1C16;margin:0;}
.box{background:#fff;border-radius:16px;padding:32px;max-width:420px;text-align:center;box-shadow:0 8px 30px rgba(0,0,0,.08);}
.material-symbols-outlined{font-size:44px;color:<?= $ok ? '#1B7A4E' : '#8C3B2E' ?>;} a{display:inline-block;margin-top:18px;background:#0F5257;color:#fff;text-decoration:none;padding:11px 22px;border-radius:24px;font-weight:700;}</style></head>
<body style="display:block;padding:0;">
<?php if (current_user()) { include __DIR__ . '/_nav.php'; } else { $navRoot = '..'; include __DIR__ . '/../_public_nav.php'; } ?>
<div style="display:flex;align-items:center;justify-content:center;min-height:70vh;padding:20px;"><div class="box"><span class="material-symbols-outlined"><?= $ok ? 'check_circle' : 'error' ?></span><h2><?= $ok ? 'Email updated' : 'Could not confirm' ?></h2><p><?= $msg ?></p><a href="account.php">Go to my account</a></div></div></body></html>
