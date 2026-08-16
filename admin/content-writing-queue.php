<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_delivered') {
    $db->prepare("UPDATE order_items SET delivered_at = NOW() WHERE id = ?")->execute([(int)$_POST['item_id']]);
}

$queue = $db->query("
    SELECT oi.id AS item_id, oi.delivered_at, o.reference_code, o.paid_at, s.name AS school_name, s.id AS school_id
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    JOIN schools s ON s.id = o.school_id
    WHERE oi.product_key = 'content_writing' AND o.status = 'paid'
    ORDER BY oi.delivered_at IS NULL DESC, o.paid_at ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Content Writing Queue</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .cw-card{background:#fff;border-radius:8px;padding:16px 20px;margin-bottom:12px;box-shadow:0 1px 3px rgba(0,0,0,0.08);}
  .cw-actions{margin-top:10px;}
  .cw-actions button{background:#1B4D3E;color:#fff;border:none;padding:7px 16px;border-radius:5px;font-weight:700;cursor:pointer;font-size:0.82rem;}
  .done{color:#1B4D3E;font-weight:700;font-size:0.82rem;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>Content Writing Queue</h1>
  <p style="color:#666;font-size:0.85rem;">Paid content-writing orders — write and add the content via the school's edit page or the content-preset tool, then mark delivered.</p>

  <?php foreach ($queue as $item): ?>
    <div class="cw-card">
      <strong><?= htmlspecialchars($item['school_name']) ?></strong> — Ref: <?= htmlspecialchars($item['reference_code']) ?><br>
      <small style="color:#888;">Paid <?= date('d M Y', strtotime($item['paid_at'])) ?></small>
      <div class="cw-actions">
        <?php if ($item['delivered_at']): ?>
          <span class="done">✓ Delivered <?= date('d M Y', strtotime($item['delivered_at'])) ?></span>
        <?php else: ?>
          <a href="school-edit.php?id=<?= $item['school_id'] ?>" style="margin-right:10px;font-size:0.82rem;color:#0F5257;font-weight:700;">Go write content →</a>
          <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="mark_delivered">
            <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
            <button type="submit">Mark Delivered</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$queue): ?><p style="color:#888;">Nothing in the queue.</p><?php endif; ?>
</main>
</body>
</html>
