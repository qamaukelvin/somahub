<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/school-provision.php';
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
    $safeName = 'user_' . $userId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    move_uploaded_file($tmpPath, $destDir . $safeName);
    return 'uploads/avatars/' . $safeName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isImpersonating) {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $jobTitle = trim($_POST['job_title'] ?? '');
        if ($jobTitle === 'Other') $jobTitle = mb_substr(trim($_POST['job_other'] ?? ''), 0, 60);
        $jobTitle = mb_substr($jobTitle, 0, 60);

        if (!$name) {
            $error = 'Name cannot be empty.';
        }

        if (!$error) {
            $avatarPath = store_avatar('avatar', $user['id'], $error);
            if (!$error) {
                $set = ['name' => $name, 'phone' => $phone];
                if ($avatarPath) $set['avatar_path'] = $avatarPath;
                if ($jobTitle !== '' && db_has_column($db, 'users', 'job_title')) $set['job_title'] = $jobTitle;
                $db->prepare('UPDATE users SET ' . implode(', ', array_map(fn($c) => "$c = ?", array_keys($set))) . ' WHERE id = ?')
                   ->execute(array_merge(array_values($set), [$user['id']]));
                foreach ($set as $k => $v) $currentUser[$k] = $v;
                $success = 'Profile updated.';
            }
        }
    } elseif ($action === 'request_email_change') {
        $newEmail = strtolower(trim($_POST['new_email'] ?? ''));
        $pw = $_POST['email_password'] ?? '';
        $isTemp = !empty($currentUser['password_is_temp']);
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strtolower($currentUser['email']) === $newEmail) {
            $error = 'That is already your login email.';
        } elseif (!$isTemp && !password_verify($pw, $currentUser['password_hash'])) {
            $error = 'Your password is incorrect, so the email was not changed.';
        } else {
            $dupe = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $dupe->execute([$newEmail, $user['id']]);
            $recent = $db->prepare("SELECT COUNT(*) FROM email_change_requests WHERE user_id = ? AND created_at > (NOW() - INTERVAL 1 HOUR)");
            try { $recent->execute([$user['id']]); $tooMany = (int)$recent->fetchColumn() >= 3; } catch (\Throwable $e) { $tooMany = false; }
            if ($dupe->fetch()) {
                $error = 'That email is already in use by another account.';
            } elseif ($tooMany) {
                $error = 'Too many requests. Please wait an hour and try again.';
            } else {
                try {
                    $token = bin2hex(random_bytes(32));
                    $db->prepare("UPDATE email_change_requests SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")->execute([$user['id']]);
                    $db->prepare("INSERT INTO email_change_requests (user_id, new_email, token_hash, expires_at) VALUES (?, ?, ?, NOW() + INTERVAL 1 HOUR)")
                       ->execute([$user['id'], $newEmail, hash('sha256', $token)]);
                    $link = 'https://somahub.top/dashboard/confirm-email-change.php?token=' . $token;
                    send_somahub_email($newEmail, 'Confirm your new Somahub login email',
                        "<h2 style='color:#0F5257;margin-top:0;'>Confirm your new email</h2><p>Click the button to make this your Somahub login email. The link works for 1 hour.</p><p><a href='{$link}' style='background:#0F5257;color:#fff;padding:12px 22px;border-radius:24px;text-decoration:none;font-weight:700;display:inline-block;'>Confirm email</a></p><p style='color:#6E6A5C;font-size:13px;'>If you did not ask for this, ignore this email and nothing changes.</p>");
                    if (filter_var($currentUser['email'], FILTER_VALIDATE_EMAIL)) {
                        send_somahub_email($currentUser['email'], 'Somahub: email change requested',
                            "<h2 style='color:#0F5257;margin-top:0;'>Email change requested</h2><p>Someone asked to change the login email on your Somahub account to <strong>" . htmlspecialchars($newEmail) . "</strong>. Nothing changes until that address is confirmed. If this was not you, change your password now and contact admin@somahub.top.</p>");
                    }
                    $success = 'We sent a confirmation link to ' . htmlspecialchars($newEmail) . '. Your login email changes once you click it (valid for 1 hour).';
                } catch (\Throwable $e) {
                    app_log('request_email_change failed: ' . $e->getMessage());
                    $error = 'Could not start the email change. Please try again later.';
                }
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
        send_somahub_email(get_setting($db, 'admin_notify_email', 'admin@somahub.top'), 'Account removal requested', $adminBody, $currentUser['email']);
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
  .acc{padding:0;overflow:hidden;} .acc>*:not(summary){margin-left:20px;margin-right:20px;} .acc>form,.acc>p:last-child,.acc>div:last-child{margin-bottom:20px;}
  .acc summary{list-style:none;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 20px;}
  .acc summary::-webkit-details-marker{display:none;}
  .acc summary b{display:block;font-size:.98rem;} .acc summary small{display:block;color:#777;font-size:.82rem;margin-top:2px;word-break:break-all;}
  .acc summary em{font-style:normal;font-weight:700;font-size:.82rem;color:#0F5257;background:#F4F1E6;padding:6px 14px;border-radius:16px;flex:none;}
  .acc[open] summary{border-bottom:1px solid #eee;} .acc[open] summary em{display:none;}
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
      <?php $curJob = (string)($currentUser['job_title'] ?? ''); $roleList = get_signup_roles(); $jobIsListed = in_array($curJob, $roleList, true); ?>
      <label>Your role at the school</label>
      <select name="job_title" id="jobSel" onchange="document.getElementById('jobOther').style.display=this.value==='Other'?'block':'none'" style="width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;margin-bottom:14px;">
        <option value="">Select your role</option>
        <?php foreach ($roleList as $r): ?><option value="<?= htmlspecialchars($r) ?>" <?= ($jobIsListed ? $curJob === $r : ($curJob !== '' && $r === 'Other')) ? 'selected' : '' ?>><?= htmlspecialchars($r) ?></option><?php endforeach; ?>
      </select>
      <input type="text" name="job_other" id="jobOther" value="<?= $jobIsListed ? '' : htmlspecialchars($curJob) ?>" placeholder="Your job title" style="display:<?= ($curJob !== '' && !$jobIsListed) ? 'block' : 'none' ?>;width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;margin-bottom:14px;">
      <label>Profile Picture</label>
      <input type="file" name="avatar" accept=".jpg,.jpeg,.png,.webp">
      <button type="submit" class="btn">Save Profile</button>
    </form>
  </div>

  <details class="box acc" <?= (($_POST['action'] ?? '') === 'request_email_change') ? 'open' : '' ?>>
    <summary><span><b>Login email</b><small><?= $hasPlaceholderEmail ? 'None yet' : htmlspecialchars($currentUser['email']) ?></small></span><em>Change</em></summary>
    <p style="font-size:0.85rem;color:#666;margin:14px 0;">We email a confirmation link to the new address and tell your old one.</p>
    <form method="POST">
      <input type="hidden" name="action" value="request_email_change">
      <label>New email</label>
      <input type="email" name="new_email" required placeholder="you@example.com">
      <?php if (empty($currentUser['password_is_temp'])): ?>
        <label>Your password (to confirm it is you)</label>
        <input type="password" name="email_password" required autocomplete="current-password">
      <?php endif; ?>
      <button type="submit" class="btn"><?= $hasPlaceholderEmail ? 'Add email' : 'Send confirmation link' ?></button>
    </form>
  </details>

  <details class="box acc" <?= (!empty($currentUser['password_is_temp']) || ($_POST['action'] ?? '') === 'change_password') ? 'open' : '' ?>>
    <summary><span><b>Password</b><small><?= !empty($currentUser['password_is_temp']) ? 'Not set yet' : 'Keep your account safe' ?></small></span><em><?= !empty($currentUser['password_is_temp']) ? 'Set' : 'Change' ?></em></summary>
    <div style="height:14px"></div>
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
  </details>

  <details class="box acc danger-box" <?= $pendingRemoval ? 'open' : '' ?>>
    <summary><span><b style="color:#8C3B2E;">Remove my account</b><small><?= $pendingRemoval ? 'Request pending' : 'We confirm with you first' ?></small></span><em>Open</em></summary>
    <div style="height:14px"></div>
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
  </details>

  <?php endif; ?>
</main>
<?php include __DIR__ . '/../includes/pw-toggle.php'; ?>
</body>
</html>
