<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
$user = require_school_login();
$db = get_db();

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$currentUser = $stmt->fetch();

$roleLabels = ['school_owner' => 'School Owner', 'school_editor' => 'Editor', 'platform_admin' => 'Platform Admin'];

$error = '';
$success = '';
$isImpersonating = !empty($user['is_admin_impersonating']);

// A lead converted straight to a school (see admin/leads.php) gets a
// Phone-based login (no real email yet) — detect by checking if the
// stored login value isn't a valid email at all, since phone numbers
// are now used directly as the login when a lead has no email on file.
$hasPlaceholderEmail = !filter_var($currentUser['email'] ?? '', FILTER_VALIDATE_EMAIL);

function store_avatar($fileKey, $userId, &$error) {
    if (empty($_FILES[$fileKey]['tmp_name'])) return null;
    $tmpPath = $_FILES[$fileKey]['tmp_name'];
    $originalName = $_FILES[$fileKey]['name'];
    $fileSize = $_FILES[$fileKey]['size'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if ($fileSize > 3 * 1024 * 1024) { $error = 'Image is too large. Maximum size is 3MB.'; return null; }
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) { $error = 'Please upload a JPG, PNG, or WEBP image.'; return null; }
    if (@getimagesize($tmpPath) === false) { $error = 'That file does not appear to be a valid image.'; return null; }

    $destDir = __DIR__ . '/../uploads/avatars/';
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
    $safeName = 'user_' . $userId . '_' . time() . '.' . $ext;
    move_uploaded_file($tmpPath, $destDir . $safeName);
    return 'uploads/avatars/' . $safeName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isImpersonating) {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $newEmail = trim($_POST['email'] ?? '');

        if (!$name) {
            $error = 'Name cannot be empty.';
        } elseif ($hasPlaceholderEmail && $newEmail && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif ($hasPlaceholderEmail && $newEmail) {
            $dupeCheck = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $dupeCheck->execute([$newEmail, $user['id']]);
            if ($dupeCheck->fetch()) {
                $error = 'That email is already in use by another account.';
            }
        }

        if (!$error) {
            $avatarPath = store_avatar('avatar', $user['id'], $error);
            if (!$error) {
                $wasPlaceholder = $hasPlaceholderEmail;
                $finalEmail = ($hasPlaceholderEmail && $newEmail) ? $newEmail : $currentUser['email'];
                if ($avatarPath) {
                    $db->prepare("UPDATE users SET name = ?, phone = ?, email = ?, avatar_path = ? WHERE id = ?")
                       ->execute([$name, $phone, $finalEmail, $avatarPath, $user['id']]);
                    $currentUser['avatar_path'] = $avatarPath;
                } else {
                    $db->prepare("UPDATE users SET name = ?, phone = ?, email = ? WHERE id = ?")
                       ->execute([$name, $phone, $finalEmail, $user['id']]);
                }
                $currentUser['name'] = $name;
                $currentUser['phone'] = $phone;
                $currentUser['email'] = $finalEmail;
                $hasPlaceholderEmail = !filter_var($finalEmail, FILTER_VALIDATE_EMAIL);

                if ($wasPlaceholder && !$hasPlaceholderEmail) {
                    require_once __DIR__ . '/../includes/mailer.php';
                    $welcomeBody = "
                        <h2 style='color:#0F5257;margin-top:0;'>Welcome to Somahub, {$name}!</h2>
                        <p>Your email is now saved and you're all set. You can use it to log in and reset your password going forward.</p>
                    ";
                    send_somahub_email($finalEmail, 'Welcome to Somahub', $welcomeBody);
                }
                $success = ($finalEmail !== $currentUser['email']) ? 'Profile updated — your login email is now ' . htmlspecialchars($finalEmail) . '.' : 'Profile updated.';
            }
        }
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $skipCurrentCheck = !empty($currentUser['password_is_temp']);

        if (!$skipCurrentCheck && !password_verify($currentPassword, $currentUser['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'New password must be at least 6 characters.';
        } else {
            $db->prepare("UPDATE users SET password_hash = ?, password_is_temp = 0 WHERE id = ?")
               ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
            $currentUser['password_is_temp'] = 0;
            $success = 'Password set successfully.';

            if (filter_var($currentUser['email'], FILTER_VALIDATE_EMAIL)) {
                $changeBody = "
                    <h2 style='color:#0F5257;margin-top:0;'>Your Password Was Changed</h2>
                    <p>This confirms your Somahub password was just changed from your account settings.</p>
                    <p>If this wasn't you, message us immediately.</p>
                ";
                send_somahub_email($currentUser['email'], 'Your Somahub password was changed', $changeBody);
            }
        }
    } elseif ($action === 'request_removal') {
        $reason = trim($_POST['removal_reason'] ?? '');
        $db->prepare("INSERT INTO account_removal_requests (user_id, school_id, reason) VALUES (?, ?, ?)")
           ->execute([$user['id'], $user['school_id'], $reason]);
        $success = 'Removal request submitted. We\'ll be in touch before anything is deleted.';

        $adminBody = "
            <h2 style='color:#8C3B2E;margin-top:0;'>Account Removal Requested</h2>
            <table style='width:100%;font-size:14px;margin:16px 0;'>
                <tr><td style='color:#6E6A5C;padding:4px 0;'>User</td><td><strong>" . htmlspecialchars($currentUser['name']) . "</strong> (" . htmlspecialchars($currentUser['email']) . ")</td></tr>
                <tr><td style='color:#6E6A5C;padding:4px 0;'>Reason</td><td>" . htmlspecialchars($reason ?: 'Not provided') . "</td></tr>
            </table>
            <p><a href='https://somahub.top/admin/account-requests.php' style='color:#0F5257;font-weight:700;'>Review in Admin &rarr;</a></p>
        ";
        send_somahub_email('admin@somahub.top', 'Account removal requested', $adminBody, $currentUser['email']);
    }
}

// Check for an existing pending removal request, to show status instead of the form again
$pendingRemoval = $db->prepare("SELECT * FROM account_removal_requests WHERE user_id = ? AND status = 'pending' ORDER BY requested_at DESC LIMIT 1");
$pendingRemoval->execute([$user['id']]);
$pendingRemoval = $pendingRemoval->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Account Settings</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .box{background:#fff;border-radius:8px;padding:24px;max-width:480px;margin-bottom:20px;}
  .error{background:#FBE8E4;color:#8C3B2E;padding:10px 14px;border-radius:6px;margin-bottom:16px;font-size:0.88rem;}
  .success{background:#E4F0E7;color:#1B4D3E;padding:10px 14px;border-radius:6px;margin-bottom:16px;font-size:0.88rem;}
  label{display:block;font-size:0.85rem;font-weight:600;margin-bottom:6px;}
  input, textarea{width:100%;padding:9px;border:1px solid #ccc;border-radius:4px;margin-bottom:14px;box-sizing:border-box;font-family:inherit;}
  .avatar-row{display:flex;align-items:center;gap:14px;margin-bottom:14px;}
  .avatar-preview{width:56px;height:56px;border-radius:50%;object-fit:cover;background:#F4F1E6;display:flex;align-items:center;justify-content:center;font-weight:800;color:#0F5257;font-size:1.2rem;}
  .role-badge{display:inline-block;background:#F4F1E6;color:#0F5257;padding:4px 12px;border-radius:12px;font-size:0.78rem;font-weight:700;}
  .danger-box{border:1px solid #F5C6C0;}
  .btn-danger{background:#8C3B2E;color:#fff;border:none;padding:9px 18px;border-radius:6px;font-weight:700;cursor:pointer;font-size:0.88rem;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>Account Settings</h1>

  <?php if ($isImpersonating): ?>
    <p style="color:#888;font-size:0.9rem;">Account settings aren't available while previewing as this school in Admin Mode.</p>
  <?php else: ?>

  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <div class="box">
    <h3 style="margin-bottom:16px;">Profile</h3>
    <div class="avatar-row">
      <?php if (!empty($currentUser['avatar_path'])): ?>
        <img src="../<?= htmlspecialchars($currentUser['avatar_path']) ?>" class="avatar-preview">
      <?php else: ?>
        <div class="avatar-preview"><?= htmlspecialchars(strtoupper(substr($currentUser['name'] ?? '?', 0, 1))) ?></div>
      <?php endif; ?>
      <div>
        <strong><?= htmlspecialchars($currentUser['name']) ?></strong><br>
        <span class="role-badge"><?= htmlspecialchars($roleLabels[$currentUser['role']] ?? $currentUser['role']) ?></span>
      </div>
    </div>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="update_profile">
      <label>Full Name</label>
      <input type="text" name="name" value="<?= htmlspecialchars($currentUser['name']) ?>" required>
      <label>Phone</label>
      <input type="text" name="phone" value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>" placeholder="07XXXXXXXX">
      <?php if ($hasPlaceholderEmail): ?>
        <label>Email <span style="color:#8C3B2E;font-weight:400;">— add yours to finish setup</span></label>
        <input type="email" name="email" value="" placeholder="you@example.com">
        <p style="font-size:0.78rem;color:#8C6D1F;margin-top:-10px;margin-bottom:14px;">You're currently logging in with your phone number. Add your email to finish setting up your account.</p>
      <?php else: ?>
        <label>Email</label>
        <input type="email" value="<?= htmlspecialchars($currentUser['email']) ?>" disabled style="background:#f4f4f4;color:#888;">
        <p style="font-size:0.78rem;color:#888;margin-top:-10px;margin-bottom:14px;">Email is your login and can't be changed here — contact Somahub if you need it updated.</p>
      <?php endif; ?>
      <label>Profile Picture</label>
      <input type="file" name="avatar" accept=".jpg,.jpeg,.png,.webp">
      <button type="submit" class="btn">Save Profile</button>
    </form>
  </div>

  <div class="box">
    <h3 style="margin-bottom:16px;"><?= !empty($currentUser['password_is_temp']) ? 'Set a Password' : 'Change Password' ?></h3>
    <?php if (!empty($currentUser['password_is_temp'])): ?>
      <p style="font-size:0.85rem;color:#8C6D1F;margin-bottom:14px;">You logged in via a one-time link. Set a password now so you can log in normally next time.</p>
    <?php endif; ?>
    <form method="POST">
      <input type="hidden" name="action" value="change_password">
      <?php if (empty($currentUser['password_is_temp'])): ?>
      <label>Current Password</label>
      <input type="password" name="current_password" required>
      <?php endif; ?>
      <label>New Password</label>
      <input type="password" name="new_password" required minlength="6">
      <label>Confirm New Password</label>
      <input type="password" name="confirm_password" required minlength="6">
      <button type="submit" class="btn"><?= !empty($currentUser['password_is_temp']) ? 'Set Password' : 'Update Password' ?></button>
    </form>
  </div>

  <div class="box danger-box">
    <h3 style="margin-bottom:10px;color:#8C3B2E;">Remove My Account</h3>
    <?php if ($pendingRemoval): ?>
      <p style="font-size:0.88rem;color:#666;">A removal request is pending review, submitted <?= date('d M Y', strtotime($pendingRemoval['requested_at'])) ?>. We'll reach out before anything is deleted.</p>
    <?php else: ?>
      <p style="font-size:0.85rem;color:#666;margin-bottom:14px;">Nothing is deleted automatically. We'll confirm with you before removing your account and data.</p>
      <form method="POST">
        <input type="hidden" name="action" value="request_removal">
        <label>Reason (optional)</label>
        <textarea name="removal_reason" rows="3" placeholder="Let us know why, so we can improve"></textarea>
        <button type="submit" class="btn-danger" onclick="return confirm('Submit a removal request? Nothing is deleted right away — we\'ll follow up first.')">Request Account Removal</button>
      </form>
    <?php endif; ?>
  </div>

  <?php endif; ?>
</main>
</body>
</html>
