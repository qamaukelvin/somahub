<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/plan.php';
require_platform_admin();
$db = get_db();

$schools = $db->query("
    SELECT s.*,
        (SELECT COUNT(*) FROM enrollment_applications e WHERE e.school_id = s.id AND e.status='new') as new_enrollments
    FROM schools s
    ORDER BY s.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Somahub Admin</title>
<?php include __DIR__ . '/_styles.php'; ?>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <div class="header-row">
    <h1>All Schools (<?= count($schools) ?>)</h1>
    <a href="school-new.php" class="btn">+ Add School</a>
  </div>

  <table>
    <tr><th>School</th><th>Subdomain</th><th>Plan</th><th>Status</th><th>Enquiries</th><th></th></tr>
    <?php foreach ($schools as $s): ?>
    <tr>
      <td><?= htmlspecialchars($s['name']) ?></td>
      <td><?= htmlspecialchars($s['slug']) ?>.somahub.top</td>
      <td>
        <span class="plan-badge plan-<?= $s['plan'] ?>"><?= ucfirst(str_replace('_',' ',$s['plan'])) ?></span>
        <?php if (is_premium_locked($s) && $s['plan'] === 'promo_paid'): ?>
          <span class="plan-badge" style="background:#FBE8E4;color:#8C3B2E;">Overdue</span>
        <?php elseif ($s['plan'] === 'promo_paid' && $s['promo_ends_at']): ?>
          <br><small>until <?= date('d M Y', strtotime($s['promo_ends_at'])) ?></small>
        <?php endif; ?>
      </td>
      <td><?= ucfirst($s['status']) ?></td>
      <td><?= $s['new_enrollments'] > 0 ? '<strong>'.$s['new_enrollments'].' new</strong>' : '—' ?></td>
      <td><a href="school-edit.php?id=<?= $s['id'] ?>">Manage</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
</main>
</body>
</html>
