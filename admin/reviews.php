<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $reviewId = (int)($_POST['review_id'] ?? 0);

    if ($action === 'approve') {
        $db->prepare("UPDATE reviews SET status = 'approved' WHERE id = ?")->execute([$reviewId]);
    } elseif ($action === 'reject') {
        $db->prepare("UPDATE reviews SET status = 'rejected' WHERE id = ?")->execute([$reviewId]);
    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM reviews WHERE id = ?")->execute([$reviewId]);
    }
}

$pending = $db->query("
    SELECT r.*, s.name AS school_name, s.slug AS school_slug
    FROM reviews r LEFT JOIN schools s ON s.id = r.reviewable_id AND r.reviewable_type = 'school'
    WHERE r.status = 'pending' ORDER BY r.created_at ASC
")->fetchAll();

$approved = $db->query("
    SELECT r.*, s.name AS school_name, s.slug AS school_slug
    FROM reviews r LEFT JOIN schools s ON s.id = r.reviewable_id AND r.reviewable_type = 'school'
    WHERE r.status = 'approved' ORDER BY r.created_at DESC LIMIT 30
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reviews</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .review-card{background:#fff;border-radius:8px;padding:16px 20px;margin-bottom:12px;box-shadow:0 1px 3px rgba(0,0,0,0.08);}
  .review-stars{color:#F2A65A;letter-spacing:2px;font-size:1rem;}
  .review-target{display:inline-block;font-size:0.7rem;font-weight:700;text-transform:uppercase;padding:2px 8px;border-radius:10px;background:#F4F1E6;color:#6E6A5C;margin-bottom:8px;}
  .review-actions{margin-top:12px;display:flex;gap:10px;}
  .review-actions button{padding:7px 16px;border-radius:5px;border:none;cursor:pointer;font-weight:700;font-size:0.82rem;}
  .btn-approve{background:#1B4D3E;color:#fff;}
  .btn-reject{background:#FBE8E4;color:#8C3B2E;}
  .btn-delete{background:#eee;color:#666;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>Reviews</h1>

  <h3>Pending (<?= count($pending) ?>)</h3>
  <?php foreach ($pending as $r): ?>
    <div class="review-card">
      <span class="review-target"><?= $r['reviewable_type'] === 'platform' ? 'Somahub' : htmlspecialchars($r['school_name'] ?? 'Unknown School') ?></span>
      <div class="review-stars"><?= str_repeat('★', $r['rating']) . str_repeat('☆', 5 - $r['rating']) ?></div>
      <p style="margin:6px 0;">"<?= htmlspecialchars($r['comment']) ?>"</p>
      <p style="font-size:0.85rem;color:#888;"><?= htmlspecialchars($r['reviewer_name']) ?><?= $r['reviewer_role'] ? ' — '.htmlspecialchars($r['reviewer_role']) : '' ?> · <?= date('d M Y', strtotime($r['created_at'])) ?></p>
      <div class="review-actions">
        <form method="POST"><input type="hidden" name="action" value="approve"><input type="hidden" name="review_id" value="<?= $r['id'] ?>"><button type="submit" class="btn-approve">Approve</button></form>
        <form method="POST"><input type="hidden" name="action" value="reject"><input type="hidden" name="review_id" value="<?= $r['id'] ?>"><button type="submit" class="btn-reject">Reject</button></form>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$pending): ?><p style="color:#888;">Nothing pending review.</p><?php endif; ?>

  <h3 style="margin-top:32px;">Recently Approved</h3>
  <?php foreach ($approved as $r): ?>
    <div class="review-card">
      <span class="review-target"><?= $r['reviewable_type'] === 'platform' ? 'Somahub' : htmlspecialchars($r['school_name'] ?? 'Unknown School') ?></span>
      <div class="review-stars"><?= str_repeat('★', $r['rating']) . str_repeat('☆', 5 - $r['rating']) ?></div>
      <p style="margin:6px 0;">"<?= htmlspecialchars($r['comment']) ?>"</p>
      <p style="font-size:0.85rem;color:#888;"><?= htmlspecialchars($r['reviewer_name']) ?> · <?= date('d M Y', strtotime($r['created_at'])) ?></p>
      <div class="review-actions">
        <form method="POST" onsubmit="return confirm('Delete this review?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="review_id" value="<?= $r['id'] ?>"><button type="submit" class="btn-delete">Delete</button></form>
      </div>
    </div>
  <?php endforeach; ?>
</main>
</body>
</html>
