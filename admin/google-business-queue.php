<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_completed') {
    $db->prepare("UPDATE service_requests SET status='completed', completed_at=NOW() WHERE id=?")->execute([(int)$_POST['request_id']]);
}

$requests = $db->query("
    SELECT sr.*, o.reference_code, o.paid_at, s.name AS school_name, s.slug
    FROM service_requests sr
    JOIN orders o ON o.id = sr.order_id
    JOIN schools s ON s.id = o.school_id
    WHERE sr.service_key = 'google_business_setup' AND o.status = 'paid'
    ORDER BY sr.status ASC, sr.created_at ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Google Business Setup Queue</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .gb-card{background:#fff;border-radius:8px;padding:16px 20px;margin-bottom:12px;box-shadow:0 1px 3px rgba(0,0,0,0.08);}
  .gb-actions{margin-top:10px;}
  .gb-actions button{background:#1B4D3E;color:#fff;border:none;padding:7px 16px;border-radius:5px;font-weight:700;cursor:pointer;font-size:0.82rem;}
  .done{color:#1B4D3E;font-weight:700;font-size:0.82rem;}
  .map-link{color:#0F5257;font-weight:700;font-size:0.85rem;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>Google Business Profile Queue</h1>
  <?php foreach ($requests as $r): $details = json_decode($r['details_json'], true); ?>
    <div class="gb-card">
      <strong><?= htmlspecialchars($r['school_name']) ?></strong> — Ref: <?= htmlspecialchars($r['reference_code']) ?><br>
      <small style="color:#888;">Paid <?= date('d M Y', strtotime($r['paid_at'])) ?></small>
      <p style="margin-top:8px;">
        Location: <a class="map-link" href="https://www.google.com/maps?q=<?= urlencode($details['lat'] . ',' . $details['lng']) ?>" target="_blank"><?= htmlspecialchars($details['lat']) ?>, <?= htmlspecialchars($details['lng']) ?> →</a><br>
        Business Phone: <?= htmlspecialchars($details['business_phone'] ?? '—') ?><br>
        <?php if (!empty($details['notes'])): ?>Notes: <?= htmlspecialchars($details['notes']) ?><?php endif; ?>
      </p>
      <div class="gb-actions">
        <?php if ($r['status'] === 'completed'): ?>
          <span class="done">✓ Completed <?= date('d M Y', strtotime($r['completed_at'])) ?></span>
        <?php else: ?>
          <form method="POST"><input type="hidden" name="action" value="mark_completed"><input type="hidden" name="request_id" value="<?= $r['id'] ?>"><button type="submit">Mark Completed</button></form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$requests): ?><p style="color:#888;">Nothing in the queue.</p><?php endif; ?>
</main>
</body>
</html>
