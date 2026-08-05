<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/plan.php';
require_platform_admin();
$db = get_db();

$search = trim($_GET['q'] ?? '');
$planFilter = $_GET['plan'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$verificationFilter = $_GET['verification'] ?? '';

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(s.name LIKE ? OR s.slug LIKE ? OR s.county LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($planFilter !== '') {
    $where[] = "s.plan = ?";
    $params[] = $planFilter;
}
if ($statusFilter !== '') {
    $where[] = "s.status = ?";
    $params[] = $statusFilter;
}
if ($verificationFilter !== '') {
    $where[] = "s.verification_status = ?";
    $params[] = $verificationFilter;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("
    SELECT s.*,
        (SELECT COUNT(*) FROM enrollment_applications e WHERE e.school_id = s.id AND e.status='new') as new_enrollments
    FROM schools s
    $whereSql
    ORDER BY s.created_at DESC
");
$stmt->execute($params);
$schools = $stmt->fetchAll();

$totalCount = $db->query("SELECT COUNT(*) c FROM schools")->fetch()['c'];
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
    <h1>All Schools (<?= count($schools) ?><?= $totalCount != count($schools) ? ' of ' . $totalCount : '' ?>)</h1>
    <a href="school-new.php" class="btn">+ Add School</a>
  </div>

  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;">
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, subdomain, or county…" style="flex:1;min-width:200px;padding:9px 12px;border:1px solid #ddd;border-radius:4px;">
    <select name="plan" style="padding:9px;border:1px solid #ddd;border-radius:4px;">
      <option value="">All Plans</option>
      <option value="free" <?= $planFilter === 'free' ? 'selected' : '' ?>>Free</option>
      <option value="promo_paid" <?= $planFilter === 'promo_paid' ? 'selected' : '' ?>>Paid (Promo)</option>
      <option value="paid" <?= $planFilter === 'paid' ? 'selected' : '' ?>>Paid (Full)</option>
    </select>
    <select name="status" style="padding:9px;border:1px solid #ddd;border-radius:4px;">
      <option value="">All Statuses</option>
      <option value="trial" <?= $statusFilter === 'trial' ? 'selected' : '' ?>>Trial</option>
      <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
      <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
    </select>
    <select name="verification" style="padding:9px;border:1px solid #ddd;border-radius:4px;">
      <option value="">All Verification</option>
      <option value="pending" <?= $verificationFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
      <option value="verified" <?= $verificationFilter === 'verified' ? 'selected' : '' ?>>Verified</option>
      <option value="rejected" <?= $verificationFilter === 'rejected' ? 'selected' : '' ?>>Needs Attention</option>
    </select>
    <button type="submit" class="btn">Filter</button>
    <?php if ($search || $planFilter || $statusFilter || $verificationFilter): ?>
      <a href="index.php" class="btn" style="background:#888;">Clear</a>
    <?php endif; ?>
  </form>

  <?php if (!$schools): ?>
    <p style="color:#888;padding:20px;text-align:center;">No schools match your search or filters.</p>
  <?php else: ?>
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
  <?php endif; ?>
</main>
</body>
</html>