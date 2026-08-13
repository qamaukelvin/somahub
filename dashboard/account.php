<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_school_login();
$db = get_db();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Admin impersonation sessions shouldn't be able to change the real
    // school owner's password — block it explicitly.
    if (!empty($user['is_admin_impersonating'])) {
        $error = "Password changes aren't available while previewing as this school.";
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $realUser = $stmt->fetch();

        if (!password_verify($currentPassword, $realUser['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'New password must be at least 6 characters.';
        } else {
            $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
               ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
            $success = 'Password updated successfully.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Account Settings</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .box{background:#fff;border-radius:8px;padding:24px;max-width:420px;}
  .error{background:#FBE8E4;color:#8C3B2E;padding:10px 14px;border-radius:6px;margin-bottom:16px;font-size:0.88rem;}
  .success{background:#E4F0E7;color:#1B4D3E;padding:10px 14px;border-radius:6px;margin-bottom:16px;font-size:0.88rem;}
  label{display:block;font-size:0.85rem;font-weight:600;margin-bottom:6px;}
  input{width:100%;padding:9px;border:1px solid #ccc;border-radius:4px;margin-bottom:14px;box-sizing:border-box;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>Account Settings</h1>
  <p style="color:#666;margin-bottom:20px;">Logged in as <?= htmlspecialchars($user['email']) ?></p>

  <div class="box">
    <h3 style="margin-bottom:16px;">Change Password</h3>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <?php if (empty($user['is_admin_impersonating'])): ?>
    <form method="POST">
      <label>Current Password</label>
      <input type="password" name="current_password" required>
      <label>New Password</label>
      <input type="password" name="new_password" required minlength="6">
      <label>Confirm New Password</label>
      <input type="password" name="confirm_password" required minlength="6">
      <button type="submit" class="btn">Update Password</button>
    </form>
    <?php else: ?>
      <p style="color:#888;font-size:0.88rem;">Password changes aren't available while previewing as this school.</p>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
