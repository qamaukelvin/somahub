<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';
$user = require_school_login();
$db = get_db();
$schoolId = $user['school_id'];

if (isset($_GET['mark_all_read'])) {
    mark_all_notifications_read($db, $schoolId);
    header('Location: notifications.php'); exit;
}
if (isset($_GET['read'])) {
    mark_notification_read($db, (int)$_GET['read'], $schoolId);
    header('Location: notifications.php' . (isset($_GET['f']) ? '?f=' . urlencode($_GET['f']) : '')); exit;
}
if (isset($_GET['open'])) {
    $notifId = (int)$_GET['open'];
    mark_notification_read($db, $notifId, $schoolId);
    $stmt = $db->prepare("SELECT link FROM notifications WHERE id = ? AND school_id = ?");
    $stmt->execute([$notifId, $schoolId]);
    $link = $stmt->fetchColumn();
    header('Location: ' . ($link ?: 'notifications.php')); exit;
}

$filter = ($_GET['f'] ?? 'all') === 'unread' ? 'unread' : 'all';
$all = get_recent_notifications($db, $schoolId, 100);
$unread = count(array_filter($all, fn($n) => !$n['is_read']));
$list = $filter === 'unread' ? array_values(array_filter($all, fn($n) => !$n['is_read'])) : $all;

$icons = [
    'welcome' => ['celebration', '#F2A65A'], 'payment_confirmed' => ['payments', '#1B8A5A'], 'trial_ending' => ['hourglass_top', '#C98A1B'],
    'trial_ended' => ['hourglass_disabled', '#C0392B'], 'verification' => ['verified', '#0F5257'], 'verified' => ['verified', '#1B8A5A'],
    'enrollment' => ['how_to_reg', '#0F5257'], 'contact' => ['mail', '#0F5257'], 'review' => ['reviews', '#C98A1B'],
];
function notif_icon(array $icons, string $type): array {
    if (isset($icons[$type])) return $icons[$type];
    foreach ($icons as $k => $v) if (str_contains($type, $k)) return $v;
    return ['notifications', '#0F5257'];
}
function notif_day(string $ts): string {
    $d = date('Y-m-d', strtotime($ts));
    if ($d === date('Y-m-d')) return 'Today';
    if ($d === date('Y-m-d', strtotime('-1 day'))) return 'Yesterday';
    return 'Earlier';
}
$groups = [];
foreach ($list as $n) $groups[notif_day($n['created_at'])][] = $n;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .nhead{display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap;margin-bottom:18px;}
  .nhead h1{margin:0;} .nhead .sub{margin:4px 0 0;}
  .ntabs{display:flex;gap:8px;margin-bottom:20px;}
  .ntabs a{padding:8px 16px;border-radius:99px;font-size:.84rem;font-weight:800;text-decoration:none;background:#fff;color:#6E6A5C;border:1.5px solid var(--line);}
  .ntabs a.on{background:var(--teal);color:#fff;border-color:var(--teal);}
  .ngrp{font-size:.74rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#8a8677;margin:22px 0 10px;}
  .ncard{display:flex;gap:14px;align-items:flex-start;background:#fff;border:1px solid var(--line);border-radius:16px;padding:16px 18px;margin-bottom:10px;position:relative;transition:box-shadow .12s;}
  .ncard:hover{box-shadow:0 6px 18px rgba(0,0,0,.07);}
  .ncard.unread{background:#FFFBF4;border-color:#F2D4A6;}
  .ncard.unread::before{content:'';position:absolute;top:16px;right:16px;width:9px;height:9px;border-radius:50%;background:#F2A65A;}
  .nic{flex:0 0 auto;width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:#EEF5F5;}
  .nic .material-symbols-outlined{font-size:22px;}
  .nbody{flex:1;min-width:0;} .nbody h4{margin:0 0 3px;font-size:.96rem;color:var(--teal-deep);padding-right:18px;} .nbody p{margin:0 0 8px;font-size:.88rem;color:#5c5c52;line-height:1.5;}
  .nmeta{display:flex;gap:14px;align-items:center;flex-wrap:wrap;font-size:.78rem;color:#8a8677;}
  .nmeta a{font-weight:800;text-decoration:none;color:var(--teal);} .nmeta a.go{background:var(--teal);color:#fff;padding:5px 14px;border-radius:99px;}
  .nempty{text-align:center;background:#fff;border:1px dashed var(--line);border-radius:16px;padding:44px 20px;color:#8a8677;}
  .nempty .material-symbols-outlined{font-size:44px;color:#c9c3ad;display:block;margin-bottom:8px;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>
<main class="wrap">
  <div class="nhead">
    <div><h1>Notifications</h1><p class="sub"><?= $unread ? $unread . ' unread' : 'You are all caught up' ?></p></div>
    <?php if ($unread): ?><a class="btn secondary" href="notifications.php?mark_all_read=1">Mark all as read</a><?php endif; ?>
  </div>
  <div class="ntabs"><a href="notifications.php" class="<?= $filter === 'all' ? 'on' : '' ?>">All</a><a href="notifications.php?f=unread" class="<?= $filter === 'unread' ? 'on' : '' ?>">Unread<?= $unread ? ' (' . $unread . ')' : '' ?></a></div>

  <?php foreach ($groups as $label => $items): ?>
    <div class="ngrp"><?= $label ?></div>
    <?php foreach ($items as $n): [$ic, $col] = notif_icon($icons, (string)($n['type'] ?? '')); ?>
      <div class="ncard <?= $n['is_read'] ? '' : 'unread' ?>">
        <div class="nic"><span class="material-symbols-outlined" style="color:<?= $col ?>"><?= $ic ?></span></div>
        <div class="nbody">
          <h4><?= htmlspecialchars($n['title']) ?></h4>
          <p><?= htmlspecialchars($n['message']) ?></p>
          <div class="nmeta">
            <span><?= date('d M Y, H:i', strtotime($n['created_at'])) ?></span>
            <?php if (!empty($n['link'])): ?><a class="go" href="notifications.php?open=<?= (int)$n['id'] ?>">Open</a><?php endif; ?>
            <?php if (!$n['is_read']): ?><a href="notifications.php?read=<?= (int)$n['id'] ?><?= $filter === 'unread' ? '&f=unread' : '' ?>">Mark as read</a><?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endforeach; ?>
  <?php if (!$list): ?><div class="nempty"><span class="material-symbols-outlined">notifications_off</span><?= $filter === 'unread' ? 'Nothing unread.' : 'No notifications yet. Updates about your site, payments and enquiries appear here.' ?></div><?php endif; ?>
</main>
</body>
</html>
