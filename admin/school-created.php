<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
$db = get_db();

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM schools WHERE id = ?");
$stmt->execute([$id]);
$school = $stmt->fetch();
$tempPassword = $_GET['pw'] ?? '';

$owner = $db->prepare("SELECT * FROM users WHERE school_id = ? AND role = 'school_owner' LIMIT 1");
$owner->execute([$id]);
$owner = $owner->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>School Created</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .box{background:#fff;border-radius:8px;padding:28px;max-width:480px;}
  .creds{background:#F4F1E6;padding:16px;border-radius:6px;font-family:monospace;margin:16px 0;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <div class="box">
    <h1>✓ <?= htmlspecialchars($school['name']) ?> created</h1>
    <p>Site is live at: <strong><?= htmlspecialchars($school['slug']) ?>.somahub.top</strong></p>

    <div class="creds">
      Login: <?= htmlspecialchars($owner['email']) ?><br>
      Temp password: <?= htmlspecialchars($tempPassword) ?>
    </div>

    <p style="font-size:0.85rem;color:#8C3B2E;">
      ⚠️ This password is shown once. Send it to the school directly (WhatsApp/SMS/email) now — it won't be shown again.
      Tell them to change it after first login.
    </p>

    <a href="index.php" class="btn">Back to Schools</a>
  </div>
</main>
</body>
</html>
