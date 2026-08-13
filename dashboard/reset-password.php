<?php
require_once __DIR__ . '/../includes/auth.php';
$db = get_db();

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!$email || !$code || !$newPassword) {
        $error = 'Please fill in all fields.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && verify_password_reset_code($db, $user['id'], $code)) {
            $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
               ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
            $success = true;
        } else {
            $error = 'That code is invalid or has expired. Please request a new one.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password — Somahub</title>
<link rel="icon" type="image/x-icon" href="../favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{ --teal:#0F5257; --teal-deep:#0A3A3E; --amber:#F2A65A; --sand:#F7F2E7; }
  *{box-sizing:border-box;}
  body{font-family:'Manrope',sans-serif;background:var(--sand);display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;}
  .card{background:#fff;padding:36px 32px;border-radius:16px;box-shadow:0 8px 30px rgba(0,0,0,0.08);width:100%;max-width:380px;}
  .brand{display:flex;align-items:center;justify-content:center;gap:8px;font-weight:800;font-size:1.15rem;color:var(--teal-deep);margin-bottom:6px;}
  .brand .dot{width:9px;height:9px;background:var(--amber);border-radius:50%;}
  .subtitle{text-align:center;color:#6E6A5C;font-size:0.82rem;margin-bottom:28px;}
  label{display:block;font-size:0.85rem;font-weight:700;margin-bottom:6px;color:#1C1C16;}
  input{width:100%;padding:11px 14px;margin-bottom:16px;border:1.5px solid #E5DFCC;border-radius:8px;box-sizing:border-box;font-family:inherit;font-size:0.92rem;}
  input:focus{outline:none;border-color:var(--teal);}
  button{width:100%;padding:12px;background:var(--teal);color:#fff;border:none;border-radius:24px;font-weight:700;cursor:pointer;font-size:0.92rem;}
  button:hover{background:var(--teal-deep);}
  .error{background:#FBE8E4;color:#8C3B2E;font-size:0.85rem;padding:10px 14px;border-radius:8px;margin-bottom:16px;}
  .notice{background:#E4F5EA;color:#1B4D3E;font-size:0.85rem;padding:10px 14px;border-radius:8px;margin-bottom:16px;}
  .back{display:block;text-align:center;margin-top:20px;color:#6E6A5C;font-size:0.82rem;text-decoration:none;}
</style>
</head>
<body>
  <form class="card" method="POST">
    <div class="brand"><span class="dot"></span> somahub</div>
    <div class="subtitle">Enter your reset code</div>

    <?php if ($success): ?>
      <div class="notice">✓ Password reset successfully. You can now log in.</div>
      <p style="text-align:center;font-size:0.85rem;"><a href="login.php" style="color:var(--teal);font-weight:700;">Go to login →</a></p>
    <?php else: ?>
      <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <label>Email</label>
      <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      <label>6-Digit Code</label>
      <input type="text" name="code" required maxlength="6" pattern="[0-9]{6}" placeholder="123456">
      <label>New Password</label>
      <input type="password" name="new_password" required minlength="6">
      <label>Confirm New Password</label>
      <input type="password" name="confirm_password" required minlength="6">
      <button type="submit">Reset Password</button>
    <?php endif; ?>
  </form>
  <a href="forgot-password.php" class="back">&larr; Request a new code</a>
</body>
</html>
