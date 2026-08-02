<?php
// ============================================================
// ONE-TIME ADMIN PASSWORD RESET TOOL
// Delete this file from your server immediately after use.
// Do not leave this on a live site — anyone who finds this URL
// while it exists could attempt to use it.
// ============================================================

require_once __DIR__ . '/config/db.php';
$db = get_db();

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!$email || !$newPassword) {
        $message = 'Please fill in both fields.';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'Passwords do not match.';
    } elseif (strlen($newPassword) < 8) {
        $message = 'Password should be at least 8 characters.';
    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND role = 'platform_admin'");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if (!$admin) {
            $message = 'No platform admin found with that email. Check the email is exactly right.';
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $admin['id']]);
            $success = true;
            $message = 'Password updated successfully. You can now log in with your new password. DELETE THIS FILE NOW.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Password Reset — TEMPORARY TOOL</title>
<style>
  body{font-family:Arial,sans-serif;background:#1B1B18;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;}
  .card{background:#fff;padding:32px;border-radius:8px;width:100%;max-width:400px;}
  h1{font-size:1.1rem;margin-bottom:8px;color:#8C3B2E;}
  .warn{background:#FBE8E4;color:#8C3B2E;padding:12px 14px;border-radius:6px;font-size:0.82rem;margin-bottom:20px;}
  .success{background:#E4F0E7;color:#1B4D3E;padding:14px;border-radius:6px;font-size:0.88rem;margin-bottom:16px;font-weight:600;}
  .error{background:#FBE8E4;color:#8C3B2E;padding:12px 14px;border-radius:6px;font-size:0.85rem;margin-bottom:16px;}
  label{display:block;font-size:0.85rem;margin-bottom:6px;font-weight:600;}
  input{width:100%;padding:10px;margin-bottom:16px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;}
  button{width:100%;padding:12px;background:#1B1B18;color:#fff;border:none;border-radius:4px;font-weight:600;cursor:pointer;}
</style>
</head>
<body>
  <div class="card">
    <h1>⚠ Temporary Password Reset Tool</h1>
    <div class="warn">Delete this file from your server the moment you're done. Do not leave it here.</div>

    <?php if ($success): ?>
      <div class="success"><?= htmlspecialchars($message) ?></div>
    <?php else: ?>
      <?php if ($message): ?><div class="error"><?= htmlspecialchars($message) ?></div><?php endif; ?>
      <form method="POST">
        <label>Admin Email</label>
        <input type="email" name="email" required placeholder="admin@somahub.top">
        <label>New Password</label>
        <input type="password" name="new_password" required minlength="8">
        <label>Confirm New Password</label>
        <input type="password" name="confirm_password" required minlength="8">
        <button type="submit">Reset Password</button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
