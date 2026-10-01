<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_school_login();
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['msg_id'])) {
    $stmt = $db->prepare("UPDATE school_contact_messages SET status = 'read' WHERE id = ? AND school_id = ?");
    $stmt->execute([(int)$_POST['msg_id'], $user['school_id']]);
}

$messages = $db->prepare("SELECT * FROM school_contact_messages WHERE school_id = ? ORDER BY submitted_at DESC");
$messages->execute([$user['school_id']]);
$messages = $messages->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Contact Messages</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .msg-row{background:#fff;border-radius:10px;padding:16px 20px;margin-bottom:12px;box-shadow:0 1px 4px rgba(0,0,0,0.05);}
  .msg-row.unread{border-left:4px solid var(--teal);}
  .msg-row .msg-meta{display:flex;justify-content:space-between;font-size:0.85rem;color:#777;margin-bottom:8px;}
  .msg-row .msg-body{white-space:pre-wrap;color:#333;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>Contact Messages</h1>
  <p class="sub">Messages submitted through your website's contact form.</p>

  <?php if (empty($messages)): ?>
    <p style="color:#777;">No messages yet.</p>
  <?php endif; ?>

  <?php foreach ($messages as $m): ?>
    <div class="msg-row<?= $m['status'] === 'new' ? ' unread' : '' ?>">
      <div class="msg-meta">
        <span><strong><?= htmlspecialchars($m['name']) ?></strong> — <a href="mailto:<?= htmlspecialchars($m['email']) ?>"><?= htmlspecialchars($m['email']) ?></a></span>
        <span><?= htmlspecialchars(date('M j, Y g:ia', strtotime($m['submitted_at']))) ?></span>
      </div>
      <div class="msg-body"><?= htmlspecialchars($m['message']) ?></div>
      <?php if ($m['status'] === 'new'): ?>
        <form method="POST" style="margin-top:10px;">
          <input type="hidden" name="msg_id" value="<?= $m['id'] ?>">
          <button type="submit" class="btn">Mark as Read</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</main>
</body>
</html>
