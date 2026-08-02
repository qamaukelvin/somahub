<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
$db = get_db();

$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM schools WHERE id = ?");
$stmt->execute([$id]);
$school = $stmt->fetch();

if (!$school) {
    die('School not found.');
}

$owner = $db->prepare("SELECT * FROM users WHERE school_id = ? AND role = 'school_owner' LIMIT 1");
$owner->execute([$id]);
$owner = $owner->fetch();

$themes = $db->query("SELECT * FROM themes WHERE is_active=1 ORDER BY name")->fetchAll();

$message = '';
$newTempPassword = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['action']) && $_POST['action'] === 'update_details') {
        $stmt = $db->prepare("
            UPDATE schools
            SET name=?, theme_id=?, accent_override=?, primary_override=?, bg_override=?, plan=?, status=?, promo_ends_at=?, county=?, town=?, phone=?, email=?
            WHERE id=?
        ");
        $stmt->execute([
            trim($_POST['name']),
            (int)$_POST['theme_id'],
            trim($_POST['accent_override']) ?: null,
            trim($_POST['primary_override']) ?: null,
            trim($_POST['bg_override']) ?: null,
            $_POST['plan'],
            $_POST['status'],
            $_POST['promo_ends_at'] ?: null,
            trim($_POST['county']),
            trim($_POST['town']),
            trim($_POST['phone']),
            trim($_POST['email']),
            $id,
        ]);
        $message = 'School details updated.';

        // refresh
        $stmt = $db->prepare("SELECT * FROM schools WHERE id = ?");
        $stmt->execute([$id]);
        $school = $stmt->fetch();
    }

    if (isset($_POST['action']) && $_POST['action'] === 'review_verification') {
        $newStatus = $_POST['verification_status'];
        $notes = trim($_POST['verification_notes'] ?? '');
        $db->prepare("UPDATE schools SET verification_status = ?, verification_notes = ? WHERE id = ?")
           ->execute([$newStatus, $notes ?: null, $id]);
        $message = 'Verification status updated.';

        $stmt = $db->prepare("SELECT * FROM schools WHERE id = ?");
        $stmt->execute([$id]);
        $school = $stmt->fetch();
    }

    if (isset($_POST['action']) && $_POST['action'] === 'verify_owner_id' && $owner) {
        $db->prepare("UPDATE users SET id_verified_at = NOW() WHERE id = ?")->execute([$owner['id']]);
        $message = "Owner's ID marked as verified.";
        $owner['id_verified_at'] = date('Y-m-d H:i:s');
    }

    if (isset($_POST['action']) && $_POST['action'] === 'reset_password' && $owner) {
        $newTempPassword = bin2hex(random_bytes(4));
        $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
           ->execute([password_hash($newTempPassword, PASSWORD_DEFAULT), $owner['id']]);
        $message = 'Password reset. Send the new password below to the school directly.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage <?= htmlspecialchars($school['name']) ?></title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .box{background:#fff;border-radius:8px;padding:28px;margin-bottom:24px;}
  .success{background:#E4F0E7;color:#1B4D3E;padding:12px 16px;border-radius:6px;margin-bottom:20px;font-size:0.9rem;}
  .creds{background:#F4F1E6;padding:14px;border-radius:6px;font-family:monospace;margin:12px 0;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <p style="margin-bottom:16px;"><a href="index.php">&larr; Back to all schools</a></p>
  <h1>Manage: <?= htmlspecialchars($school['name']) ?></h1>
  <p style="color:#666;margin-bottom:24px;">
    Live at: <a href="https://<?= urlencode($school['slug']) ?>.somahub.top/" target="_blank"><?= htmlspecialchars($school['slug']) ?>.somahub.top</a>
  </p>

  <?php if ($message): ?><div class="success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($newTempPassword): ?>
    <div class="creds">
      Login: <?= htmlspecialchars($owner['email']) ?><br>
      New temp password: <?= htmlspecialchars($newTempPassword) ?>
    </div>
  <?php endif; ?>

  <div class="box">
    <h3 style="margin-bottom:16px;">School Details</h3>
    <form method="POST" class="stacked">
      <input type="hidden" name="action" value="update_details">

      <label>School Name</label>
      <input type="text" name="name" value="<?= htmlspecialchars($school['name']) ?>" required>

      <label>Theme</label>
      <select name="theme_id">
        <?php foreach ($themes as $t): ?>
          <option value="<?= $t['id'] ?>" <?= $t['id'] == $school['theme_id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <label>Accent Color Override (optional)</label>
      <input type="text" name="accent_override" value="<?= htmlspecialchars($school['accent_override'] ?? '') ?>" placeholder="e.g. #C9A227 — leave blank to use the theme's default">

      <label>Primary Color Override (optional)</label>
      <input type="text" name="primary_override" value="<?= htmlspecialchars($school['primary_override'] ?? '') ?>" placeholder="e.g. #1F3D2F — for schools whose real brand colors don't match any preset theme">

      <label>Background Color Override (optional)</label>
      <input type="text" name="bg_override" value="<?= htmlspecialchars($school['bg_override'] ?? '') ?>" placeholder="e.g. #FBF8F2 — usually fine to leave blank">

      <label>Plan</label>
      <select name="plan">
        <option value="free" <?= $school['plan'] === 'free' ? 'selected' : '' ?>>Free</option>
        <option value="promo_paid" <?= $school['plan'] === 'promo_paid' ? 'selected' : '' ?>>Paid (Promo / Free Term)</option>
        <option value="paid" <?= $school['plan'] === 'paid' ? 'selected' : '' ?>>Paid (Full)</option>
      </select>

      <label>Status</label>
      <select name="status">
        <option value="trial" <?= $school['status'] === 'trial' ? 'selected' : '' ?>>Trial</option>
        <option value="active" <?= $school['status'] === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="suspended" <?= $school['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
      </select>

      <label>Promo Ends At (if on promo plan)</label>
      <input type="date" name="promo_ends_at" value="<?= htmlspecialchars($school['promo_ends_at'] ?? '') ?>">

      <label>County</label>
      <input type="text" name="county" value="<?= htmlspecialchars($school['county'] ?? '') ?>">

      <label>Town</label>
      <input type="text" name="town" value="<?= htmlspecialchars($school['town'] ?? '') ?>">

      <label>Phone</label>
      <input type="text" name="phone" value="<?= htmlspecialchars($school['phone'] ?? '') ?>">

      <label>Email</label>
      <input type="text" name="email" value="<?= htmlspecialchars($school['email'] ?? '') ?>">

      <button type="submit" class="btn">Save Changes</button>
    </form>
  </div>

  <div class="box">
    <h3 style="margin-bottom:16px;">Verification</h3>
    <?php
      $statusLabels = ['pending' => 'Pending Review', 'verified' => 'Verified', 'rejected' => 'Needs Attention'];
      $currentStatus = $school['verification_status'] ?? 'pending';
    ?>
    <p style="margin-bottom:14px;">
      Current status: <strong><?= $statusLabels[$currentStatus] ?></strong>
    </p>

    <?php if (!empty($school['signed_agreement_path'])): ?>
      <p style="margin-bottom:14px;">
        <a href="../<?= htmlspecialchars($school['signed_agreement_path']) ?>" target="_blank" class="btn" style="display:inline-block;">View Uploaded Document</a>
      </p>
    <?php else: ?>
      <p style="color:#888;margin-bottom:14px;">No document uploaded yet by this school.</p>
    <?php endif; ?>

    <hr style="border:none;border-top:1px solid #eee;margin:18px 0;">

    <p style="font-weight:600;font-size:0.9rem;margin-bottom:10px;">Owner ID Verification</p>
    <?php if ($owner && !empty($owner['id_document_path'])): ?>
      <p style="margin-bottom:10px;">
        ID Number: <strong><?= htmlspecialchars($owner['id_number'] ?? 'Not provided') ?></strong><br>
        <a href="../<?= htmlspecialchars($owner['id_document_path']) ?>" target="_blank">View ID Document</a><br>
        <?php if ($owner['id_verified_at']): ?>
          <span style="color:#1B4D3E;">✓ Verified on <?= date('d M Y', strtotime($owner['id_verified_at'])) ?></span>
        <?php else: ?>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="verify_owner_id">
            <button type="submit" class="btn" style="margin-top:8px;">Mark ID as Verified</button>
          </form>
        <?php endif; ?>
      </p>
    <?php else: ?>
      <p style="color:#888;margin-bottom:14px;">Owner has not uploaded an ID yet.</p>
    <?php endif; ?>

    <hr style="border:none;border-top:1px solid #eee;margin:18px 0;">

    <form method="POST" class="stacked" style="max-width:400px;">
      <input type="hidden" name="action" value="review_verification">
      <label>Set Status</label>
      <select name="verification_status">
        <option value="pending" <?= $currentStatus === 'pending' ? 'selected' : '' ?>>Pending Review</option>
        <option value="verified" <?= $currentStatus === 'verified' ? 'selected' : '' ?>>Verified</option>
        <option value="rejected" <?= $currentStatus === 'rejected' ? 'selected' : '' ?>>Needs Attention</option>
      </select>
      <label>Note to school (shown if status is "Needs Attention")</label>
      <input type="text" name="verification_notes" value="<?= htmlspecialchars($school['verification_notes'] ?? '') ?>" placeholder="e.g. Document was blurry, please re-upload">
      <button type="submit" class="btn">Update Verification</button>
    </form>
  </div>

  <div class="box">
    <h3 style="margin-bottom:16px;">Owner Login</h3>
    <?php if ($owner): ?>
      <p style="margin-bottom:16px;">
        <strong><?= htmlspecialchars($owner['name']) ?></strong><br>
        <?= htmlspecialchars($owner['email']) ?><br>
        <?= htmlspecialchars($owner['phone'] ?? '') ?>
      </p>
      <form method="POST" onsubmit="return confirm('Reset this owner\'s password? A new temporary password will be generated.')">
        <input type="hidden" name="action" value="reset_password">
        <button type="submit" class="btn">Reset Password</button>
      </form>
    <?php else: ?>
      <p style="color:#888;">No owner account found for this school.</p>
    <?php endif; ?>
  </div>
</main>
</body>
</html>