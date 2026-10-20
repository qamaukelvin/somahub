<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/plan.php';
require_platform_admin();
$db = get_db();

$search = trim($_GET['q'] ?? '');
$planFilter = $_GET['plan'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$verificationFilter = $_GET['verification'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

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

$sortOptions = [
    'newest' => 's.created_at DESC',
    'oldest' => 's.created_at ASC',
    'name_asc' => 's.name ASC',
    'name_desc' => 's.name DESC',
    'enrollments' => 'new_enrollments DESC',
];
$orderBy = $sortOptions[$sort] ?? $sortOptions['newest'];

$countStmt = $db->prepare("SELECT COUNT(*) FROM schools s $whereSql");
$countStmt->execute($params);
$matchingCount = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($matchingCount / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("
    SELECT s.*,
        (SELECT COUNT(*) FROM enrollment_applications e WHERE e.school_id = s.id AND e.status='new') as new_enrollments
    FROM schools s
    $whereSql
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$schools = $stmt->fetchAll();

$totalCount = $db->query("SELECT COUNT(*) c FROM schools")->fetch()['c'];

// Preserves current filters/sort when changing page or clicking column headers
function qs_with($overrides) {
    return htmlspecialchars('index.php?' . http_build_query(array_merge($_GET, $overrides)));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Somahub Admin</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .fbar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:22px;background:#fff;border:1px solid var(--line);border-radius:14px;padding:12px;}
  .fbar input[type=text]{flex:1;min-width:200px;padding:10px 14px;border:1.5px solid var(--line);border-radius:99px;font:inherit;}
  .fbar select{padding:9px 12px;border:1.5px solid var(--line);font:inherit;font-size:.85rem;background:#fff;}
  .sch{display:flex;align-items:center;gap:12px;} .sch .av{width:40px;height:40px;border-radius:12px;background:#E3F1F2;color:var(--teal);display:flex;align-items:center;justify-content:center;font-weight:800;flex:0 0 auto;}
  .sch b{display:block;color:var(--teal-deep);} .sch small{color:var(--muted);}
  .vchip{display:inline-block;padding:3px 10px;border-radius:99px;font-size:.72rem;font-weight:800;} .v-verified{background:#DCEFE1;color:#1B4D3E;} .v-pending{background:#FFF1DC;color:#8A5A12;} .v-rejected{background:#FBE8E4;color:#8C3B2E;}
  .rowacts a{display:inline-block;margin-right:6px;padding:6px 12px;border-radius:99px;border:1.5px solid var(--teal);font-size:.78rem;} .rowacts a.fill{background:var(--teal);color:#fff !important;}
  .pagination{display:flex;gap:6px;justify-content:center;margin-top:20px;flex-wrap:wrap;}
  .pagination a, .pagination span{padding:6px 12px;border-radius:5px;font-size:0.85rem;text-decoration:none;color:#0F5257;background:#F4F1E6;}
  .pagination .current{background:#0F5257;color:#fff;font-weight:700;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <div class="header-row">
    <h1>All Schools (<?= $matchingCount ?><?= $totalCount != $matchingCount ? ' of ' . $totalCount : '' ?>)</h1>
    <a href="school-new.php" class="btn">+ Add School</a>
  </div>

  <form method="GET" class="fbar">
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, subdomain, or county…" style="flex:1;min-width:200px;padding:9px 12px;border:1px solid #ddd;border-radius:4px;">
    <select name="plan">
      <option value="">All Plans</option>
      <option value="free" <?= $planFilter === 'free' ? 'selected' : '' ?>>Free</option>
      <option value="promo_paid" <?= $planFilter === 'promo_paid' ? 'selected' : '' ?>>Trial</option>
      <option value="paid" <?= $planFilter === 'paid' ? 'selected' : '' ?>>Premium</option>
    </select>
    <select name="status">
      <option value="">All Statuses</option>
      <option value="trial" <?= $statusFilter === 'trial' ? 'selected' : '' ?>>Trial</option>
      <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
      <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
    </select>
    <select name="verification">
      <option value="">All Verification</option>
      <option value="pending" <?= $verificationFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
      <option value="verified" <?= $verificationFilter === 'verified' ? 'selected' : '' ?>>Verified</option>
      <option value="rejected" <?= $verificationFilter === 'rejected' ? 'selected' : '' ?>>Needs Attention</option>
    </select>
    <select name="sort">
      <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
      <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
      <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name A-Z</option>
      <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name Z-A</option>
      <option value="enrollments" <?= $sort === 'enrollments' ? 'selected' : '' ?>>Most New Enquiries</option>
    </select>
    <button type="submit" class="btn">Filter</button>
    <?php if ($search || $planFilter || $statusFilter || $verificationFilter || $sort !== 'newest'): ?>
      <a href="index.php" class="btn" style="background:#888;">Clear</a>
    <?php endif; ?>
  </form>

  <?php if (!$schools): ?>
    <p style="color:#888;padding:20px;text-align:center;">No schools match your search or filters.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>School</th><th>Plan</th><th>Verification</th><th>Status</th><th>Enquiries</th><th></th></tr></thead>
    <?php foreach ($schools as $s): ?>
    <tr>
      <td data-label="School"><div class="sch"><span class="av"><?= htmlspecialchars(strtoupper(mb_substr($s['name'], 0, 1))) ?></span><span><b><?= htmlspecialchars($s['name']) ?></b><small><?= htmlspecialchars($s['slug']) ?>.somahub.top<?= !empty($s['county']) ? ' · ' . htmlspecialchars($s['county']) : '' ?></small></span></div></td>
      <td data-label="Plan">
        <span class="plan-badge plan-<?= $s['plan'] ?>"><?= ucfirst(str_replace('_',' ',$s['plan'])) ?></span>
        <?php if (is_premium_locked($s) && $s['plan'] === 'promo_paid'): ?>
          <span class="plan-badge" style="background:#FBE8E4;color:#8C3B2E;">Overdue</span>
        <?php elseif ($s['plan'] === 'promo_paid' && $s['promo_ends_at']): ?>
          <br><small>until <?= date('d M Y', strtotime($s['promo_ends_at'])) ?></small>
        <?php endif; ?>
      </td>
      <td data-label="Verification"><span class="vchip v-<?= htmlspecialchars($s['verification_status']) ?>"><?= $s['verification_status'] === 'rejected' ? 'Needs attention' : ucfirst($s['verification_status']) ?></span></td>
      <td data-label="Status"><?= ucfirst($s['status']) ?></td>
      <td data-label="Enquiries"><?= $s['new_enrollments'] > 0 ? '<strong>'.$s['new_enrollments'].' new</strong>' : '—' ?></td>
      <td data-label="" class="rowacts"><a class="fill" href="school-edit.php?id=<?= $s['id'] ?>">Manage</a><a href="https://<?= htmlspecialchars($s['slug']) ?>.somahub.top/" target="_blank">View</a></td>
    </tr>
    <?php endforeach; ?>
  </table>

  <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?><a href="<?= qs_with(['page' => $page - 1]) ?>">← Prev</a><?php endif; ?>
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <?php if ($p == $page): ?><span class="current"><?= $p ?></span>
        <?php else: ?><a href="<?= qs_with(['page' => $p]) ?>"><?= $p ?></a><?php endif; ?>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?><a href="<?= qs_with(['page' => $page + 1]) ?>">Next →</a><?php endif; ?>
    </div>
  <?php endif; ?>
  <?php endif; ?>
</main>
</body>
</html>