<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['request_id'] ?? 0);
    $note = trim($_POST['admin_note'] ?? '');

    if ($action === 'mark_completed') {
        $db->prepare("UPDATE account_removal_requests SET status='completed', admin_note=?, processed_at=NOW() WHERE id=?")->execute([$note, $id]);
    } elseif ($action === 'decline') {
        $db->prepare("UPDATE account_removal_requests SET status='declined', admin_note=?, processed_at=NOW() WHERE id=?")->execute([$note, $id]);
    }
}

$pending = $db->query("
    SELECT r.*, u.name AS user_name, u.email AS user_email, s.name AS school_name
    FROM account_removal_requests r
    JOIN users u ON u.id = r.user_id
    JOIN schools s ON s.id = r.school_id
    WHERE r.status = 'pending' ORDER BY r.requested_at ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Account Removal Requests</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .req-card{background:#fff;border-radius:8px;padding:16px 20px;margin-bottom:12px;box-shadow:0 1px 3px rgba(0,0,0,0.08);}
  .req-actions{margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;}
  .req-actions button{padding:7px 16px;border-radius:5px;border:none;cursor:pointer;font-weight:700;font-size:0.82rem;}
  .btn-complete{background:#8C3B2E;color:#fff;}
  .btn-decline{background:#F4F1E6;color:#333;}
  .req-actions input[type=text]{padding:6px 10px;border:1px solid #ccc;border-radius:5px;font-size:0.82rem;flex:1;min-width:160px;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>Account Removal Requests (<?= count($pending) ?>)</h1>
  <p style="font-size:0.85rem;color:#666;">Nothing is deleted automatically — mark completed once you've manually removed the school's data, or decline if you've resolved it another way (e.g. talked them out of it, fixed their issue).</p>

  <?php foreach ($pending as $r): ?>
    <div class="req-card">
      <strong><?= htmlspecialchars($r['school_name']) ?></strong> — requested by <?= htmlspecialchars($r['user_name']) ?> (<?= htmlspecialchars($r['user_email']) ?>)<br>
      <small style="color:#888;"><?= date('d M Y', strtotime($r['requested_at'])) ?></small>
      <?php if ($r['reason']): ?><p style="margin-top:8px;">"<?= htmlspecialchars($r['reason']) ?>"</p><?php endif; ?>
      <form method="POST" class="req-actions">
        <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
        <input type="text" name="admin_note" placeholder="Note (optional)">
        <button type="submit" name="action" value="mark_completed" class="btn-complete" onclick="return confirm('Confirm you have removed this school\'s data?')">Mark Completed</button>
        <button type="submit" name="action" value="decline" class="btn-decline">Decline</button>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if (!$pending): ?><p style="color:#888;">No pending removal requests.</p><?php endif; ?>
</main>
</body>
</html>
