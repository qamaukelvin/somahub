<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/plan.php';
$user = require_school_login();
$db = get_db();

$stmt = $db->prepare("SELECT * FROM schools WHERE id = ?");
$stmt->execute([$user['school_id']]);
$school = $stmt->fetch();

$enrollCount = $db->prepare("SELECT COUNT(*) c FROM enrollment_applications WHERE school_id=? AND status='new'");
$enrollCount->execute([$user['school_id']]);
$newEnrollments = $enrollCount->fetch()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — <?= htmlspecialchars($school['name']) ?></title>
<?php include __DIR__ . '/_styles.php'; ?>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>Welcome back, <?= htmlspecialchars($user['name']) ?></h1>
  <p class="sub"><?= htmlspecialchars($school['name']) ?> · <?= htmlspecialchars($school['slug']) ?>.somahub.top</p>

  <?php
    $daysLeft = days_until_lockout($school);
    $locked = is_premium_locked($school);
  ?>
  <?php if ($locked && $school['plan'] === 'promo_paid'): ?>
    <div style="background:#FBE8E4;color:#8C3B2E;padding:16px 20px;border-radius:8px;margin-bottom:24px;font-size:0.9rem;">
      <strong>Your paid features are currently paused.</strong> Your free term ended and payment wasn't received in time.
      Results checking, online enrollment, and fees are hidden from your website until this is resolved.
      <a href="https://wa.me/254707306888?text=<?= urlencode('Hi Somahub, I would like to reactivate my paid plan for ' . $school['name']) ?>" target="_blank" style="color:#8C3B2E;font-weight:700;">Message us to reactivate &rarr;</a>
    </div>
  <?php elseif ($daysLeft !== null && $daysLeft <= 14): ?>
    <div style="background:#FBF0D1;color:#8C6D1F;padding:16px 20px;border-radius:8px;margin-bottom:24px;font-size:0.9rem;">
      <strong>Your free term ends in <?= $daysLeft ?> day<?= $daysLeft == 1 ? '' : 's' ?>.</strong>
      After that, results checking, online enrollment, and fees will be paused until payment is received.
      <a href="https://wa.me/254707306888?text=<?= urlencode('Hi Somahub, I would like to continue on the paid plan for ' . $school['name']) ?>" target="_blank" style="color:#8C6D1F;font-weight:700;">Message us about payment &rarr;</a>
    </div>
  <?php endif; ?>

  <div class="cards">
    <a href="sections.php" class="card-link">
      <h3>Edit Website</h3>
      <p>Manage your site's sections — reorder, edit, duplicate, or hide.</p>
    </a>
    <a href="enrollment.php" class="card-link">
      <h3>Enrollment Enquiries</h3>
      <p><?= $newEnrollments ?> new application<?= $newEnrollments == 1 ? '' : 's' ?> waiting for review.</p>
    </a>
    <?php if ($school['plan'] !== 'free'): ?>
    <a href="results.php" class="card-link">
      <h3>Results</h3>
      <p>Upload term results for parents to check online.</p>
    </a>
    <a href="fees.php" class="card-link">
      <h3>Fee Structure</h3>
      <p>Update fees shown to parents on your site.</p>
    </a>
    <?php else: ?>
    <div class="card-link locked">
      <h3>Results &amp; Fees 🔒</h3>
      <p>Available on the paid plan. <a href="upgrade.php">Learn more</a></p>
    </div>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
