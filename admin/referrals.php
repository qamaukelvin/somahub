<?php
require_once __DIR__ . '/auth.php';
require_admin_login();

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['referral_id'], $_POST['new_status'])) {
    $allowed = ['pending', 'issued'];
    if (in_array($_POST['new_status'], $allowed, true)) {
        $stmt = $pdo->prepare("UPDATE referrals SET reward_status = :status WHERE id = :id");
        $stmt->execute([':status' => $_POST['new_status'], ':id' => (int)$_POST['referral_id']]);
    }
    header('Location: referrals.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
    exit;
}

$filter = $_GET['status'] ?? 'all';
$sql = "SELECT r.*,
               rb.full_name AS referrer_name, rb.email AS referrer_email, rb.status AS referrer_stay_status, rb.checkout_date AS referrer_checkout,
               dd.full_name AS referred_name, dd.status AS referred_status
        FROM referrals r
        JOIN bookings rb ON rb.id = r.referrer_booking_id
        JOIN bookings dd ON dd.id = r.referred_booking_id";
$params = [];
if (in_array($filter, ['pending', 'issued'], true)) {
    $sql .= " WHERE r.reward_status = :status";
    $params[':status'] = $filter;
}
$sql .= " ORDER BY r.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);

$counts = $pdo->query("SELECT reward_status, COUNT(*) c FROM referrals GROUP BY reward_status")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Referrals | Quattro Homes Admin</title>
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
    <div class="name">Quattro Homes<small>Referrals</small></div>
  </div>
  <nav class="admin-nav">
    <a href="dashboard.php">Bookings</a>
    <a href="reviews.php">Reviews</a>
    <a href="issues.php">Issues</a>
    <a href="referrals.php" class="active">Referrals</a>
    <a href="blog.php">Blog</a>
    <a href="settings.php">Settings</a>
    <a href="logout.php">Log out</a>
  </nav>
</header>

<div class="container" style="padding-top:36px;padding-bottom:60px;">

  <div class="admin-stats">
    <a href="?status=pending" class="stat-card <?php echo $filter==='pending'?'active':''; ?>">
      <span class="stat-num"><?php echo $counts['pending'] ?? 0; ?></span><span>Reward Pending</span>
    </a>
    <a href="?status=issued" class="stat-card <?php echo $filter==='issued'?'active':''; ?>">
      <span class="stat-num"><?php echo $counts['issued'] ?? 0; ?></span><span>Reward Issued</span>
    </a>
    <a href="?status=all" class="stat-card <?php echo $filter==='all'?'active':''; ?>">
      <span class="stat-num"><?php echo array_sum($counts); ?></span><span>Total Referrals</span>
    </a>
  </div>

  <p style="color:#888;font-size:0.85rem;margin-bottom:20px;max-width:640px;">
    A referral's reward becomes due once the <strong>referrer's</strong> own stay is marked
    <strong>confirmed</strong> and completed. Mark "Reward Issued" once you've sent them their next-stay discount code.
  </p>

  <div class="card" style="padding:0;overflow-x:auto;">
    <table class="admin-table">
      <thead>
        <tr><th>Code</th><th>Referrer</th><th>Referred Guest</th><th>Referred Stay</th><th>Submitted</th><th>Reward</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php if (!$referrals): ?>
          <tr><td colspan="7" style="text-align:center;padding:30px;color:#999;">No referrals found.</td></tr>
        <?php endif; ?>
        <?php foreach ($referrals as $r): ?>
          <tr>
            <td data-label="Code"><code><?php echo htmlspecialchars($r['code']); ?></code></td>
            <td data-label="Referrer"><?php echo htmlspecialchars($r['referrer_name']); ?><br><span style="color:#999;font-size:0.8rem;">#<?php echo (int)$r['referrer_booking_id']; ?> · <?php echo htmlspecialchars($r['referrer_stay_status']); ?></span></td>
            <td data-label="Referred Guest"><?php echo htmlspecialchars($r['referred_name']); ?></td>
            <td data-label="Referred Stay">#<?php echo (int)$r['referred_booking_id']; ?> · <?php echo htmlspecialchars($r['referred_status']); ?></td>
            <td data-label="Submitted"><?php echo htmlspecialchars(date('M j, Y', strtotime($r['created_at']))); ?></td>
            <td data-label="Reward"><span class="badge badge-<?php echo $r['reward_status'] === 'issued' ? 'confirmed' : 'pending'; ?>"><?php echo ucfirst($r['reward_status']); ?></span></td>
            <td data-label="Action">
              <form method="post" style="display:flex;gap:6px;">
                <input type="hidden" name="referral_id" value="<?php echo (int)$r['id']; ?>">
                <select name="new_status" class="mini-select">
                  <option value="pending" <?php echo $r['reward_status']==='pending'?'selected':''; ?>>Pending</option>
                  <option value="issued" <?php echo $r['reward_status']==='issued'?'selected':''; ?>>Issued</option>
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
