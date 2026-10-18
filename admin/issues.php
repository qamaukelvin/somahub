<?php
require_once __DIR__ . '/auth.php';
require_admin_login();

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_id'], $_POST['new_status'])) {
    $allowed = ['open', 'in_progress', 'resolved'];
    if (in_array($_POST['new_status'], $allowed, true)) {
        $stmt = $pdo->prepare("UPDATE issue_reports SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $_POST['new_status'], ':id' => (int)$_POST['issue_id']]);
    }
    header('Location: issues.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
    exit;
}

$filter = $_GET['status'] ?? 'open';
$sql = "SELECT * FROM issue_reports";
$params = [];
if (in_array($filter, ['open', 'in_progress', 'resolved'], true)) {
    $sql .= " WHERE status = :status";
    $params[':status'] = $filter;
}
$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$issues = $stmt->fetchAll(PDO::FETCH_ASSOC);

$counts = $pdo->query("SELECT status, COUNT(*) c FROM issue_reports GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reported Issues | Quattro Homes Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,600;1,500&family=Jost:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../css/style.css?v=<?php echo $_siteCssVer; ?>">
<link rel="stylesheet" href="admin.css?v=<?php echo $_adminCssVer; ?>">
<?php
$_adminCssVer = file_exists(__DIR__.'/admin.css')   ? substr(md5(filemtime(__DIR__.'/admin.css')),0,8)     : '1';
$_siteCssVer  = file_exists(__DIR__.'/../css/style.css') ? substr(md5(filemtime(__DIR__.'/../css/style.css')),0,8) : '1';
$_siteJsVer   = file_exists(__DIR__.'/../js/script.js')  ? substr(md5(filemtime(__DIR__.'/../js/script.js')),0,8)  : '1';
?>
</head>
<body class="admin-body">

<header class="admin-header">
  <div class="brand" style="color:var(--cream)">
    <div class="mark">Q</div>
    <div class="name">Quattro Homes<small>Reported Issues</small></div>
  </div>
  <nav class="admin-nav">
    <a href="dashboard.php">Bookings</a>
    <a href="reviews.php">Reviews</a>
    <a href="issues.php" class="active">Issues</a>
    <a href="referrals.php">Referrals</a>
    <a href="blog.php">Blog</a>
    <a href="settings.php">Settings</a>
    <a href="logout.php">Log out</a>
  </nav>
</header>

<div class="container" style="padding-top:36px;padding-bottom:60px;">

  <div class="admin-stats">
    <a href="?status=open" class="stat-card <?php echo $filter==='open'?'active':''; ?>">
      <span class="stat-num"><?php echo $counts['open'] ?? 0; ?></span><span>Open</span>
    </a>
    <a href="?status=in_progress" class="stat-card <?php echo $filter==='in_progress'?'active':''; ?>">
      <span class="stat-num"><?php echo $counts['in_progress'] ?? 0; ?></span><span>In Progress</span>
    </a>
    <a href="?status=resolved" class="stat-card <?php echo $filter==='resolved'?'active':''; ?>">
      <span class="stat-num"><?php echo $counts['resolved'] ?? 0; ?></span><span>Resolved</span>
    </a>
    <a href="?status=all" class="stat-card <?php echo $filter==='all'?'active':''; ?>">
      <span class="stat-num"><?php echo array_sum($counts); ?></span><span>Total</span>
    </a>
  </div>

  <div class="card" style="padding:0;overflow-x:auto;">
    <table class="admin-table">
      <thead>
        <tr><th>Reporter</th><th>Category</th><th>Booking Ref</th><th>Description</th><th>Submitted</th><th>Status</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php if (!$issues): ?>
          <tr><td colspan="7" style="text-align:center;padding:30px;color:#999;">No issues found.</td></tr>
        <?php endif; ?>
        <?php foreach ($issues as $i): ?>
          <tr>
            <td data-label="Reporter"><?php echo htmlspecialchars($i['full_name']); ?><br><span style="color:#999;font-size:0.8rem;"><?php echo htmlspecialchars($i['email']); ?><?php echo $i['phone'] ? ' · ' . htmlspecialchars($i['phone']) : ''; ?></span></td>
            <td data-label="Category"><?php echo htmlspecialchars(ucfirst($i['category'])); ?></td>
            <td data-label="Booking Ref"><?php echo $i['booking_id'] ? '#' . (int)$i['booking_id'] : '<span style="color:#bbb;">-</span>'; ?></td>
            <td data-label="Description"><?php echo nl2br(htmlspecialchars($i['description'])); ?></td>
            <td data-label="Submitted"><?php echo htmlspecialchars(date('M j, Y', strtotime($i['created_at']))); ?></td>
            <td data-label="Status"><span class="badge badge-<?php echo $i['status'] === 'resolved' ? 'confirmed' : ($i['status'] === 'open' ? 'cancelled' : 'pending'); ?>"><?php echo ucfirst(str_replace('_', ' ', $i['status'])); ?></span></td>
            <td data-label="Action">
              <form method="post" style="display:flex;gap:6px;">
                <input type="hidden" name="issue_id" value="<?php echo (int)$i['id']; ?>">
                <select name="new_status" class="mini-select">
                  <option value="open" <?php echo $i['status']==='open'?'selected':''; ?>>Open</option>
                  <option value="in_progress" <?php echo $i['status']==='in_progress'?'selected':''; ?>>In Progress</option>
                  <option value="resolved" <?php echo $i['status']==='resolved'?'selected':''; ?>>Resolved</option>
                </select>
                <button type="submit" class="mini-btn">Save</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>
<script src="admin.js"></script>
</body>
</html>
