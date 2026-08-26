<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';
$user = require_school_login();
$db = get_db();
$schoolId = $user['school_id'];

if (isset($_GET['mark_all_read'])) {
    mark_all_notifications_read($db, $schoolId);
    header('Location: notifications.php');
    exit;
}
if (isset($_GET['open'])) {
    $notifId = (int)$_GET['open'];
    mark_notification_read($db, $notifId, $schoolId);
    $stmt = $db->prepare("SELECT link FROM notifications WHERE id = ? AND school_id = ?");
    $stmt->execute([$notifId, $schoolId]);
    $link = $stmt->fetchColumn();
    header('Location: ' . ($link ?: 'notifications.php'));
    exit;
}

$notifications = get_recent_notifications($db, $schoolId, 50);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .notif-row{background:#fff;border-radius:8px;padding:16px 18px;margin-bottom:10px;box-shadow:0 1px 3px rgba(0,0,0,0.06);}
  .notif-row.unread{border-left:4px solid #F2A65A;}
  .notif-row h4{font-size:0.92rem;margin-bottom:4px;}
  .notif-row p{font-size:0.85rem;color:#666;margin-bottom:6px;}
  .notif-row .date{font-size:0.75rem;color:#999;}
  .notif-row a.action{font-size:0.8rem;font-weight:700;color:#0F5257;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <h1>Notifications</h1>
    <a href="notifications.php?mark_all_read=1" style="font-size:0.85rem;color:#0F5257;font-weight:700;">Mark all read</a>
  </div>

  <?php foreach ($notifications as $n): ?>
    <div class="notif-row <?= $n['is_read'] ? '' : 'unread' ?>">
      <h4><?= htmlspecialchars($n['title']) ?></h4>
      <p><?= htmlspecialchars($n['message']) ?></p>
      <span class="date"><?= date('d M Y, H:i', strtotime($n['created_at'])) ?></span>
      <?php if ($n['link']): ?> · <a href="notifications.php?open=<?= $n['id'] ?>" class="action">View →</a><?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$notifications): ?><p style="color:#888;">No notifications yet.</p><?php endif; ?>
</main>
</body>
</html>
