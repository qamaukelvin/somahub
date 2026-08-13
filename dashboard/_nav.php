<?php
require_once __DIR__ . '/../includes/plan.php';
$user = current_user();
$impersonatingSchoolId = $_SESSION['impersonating_school_id'] ?? null;
$effectiveSchoolId = $impersonatingSchoolId ?: ($user['school_id'] ?? null);

$mySlug = '';
$impersonatingSchoolName = '';
$schoolPlanInfo = null;
if ($effectiveSchoolId) {
    $schoolLookup = get_db()->prepare("SELECT slug, name, plan, promo_ends_at FROM schools WHERE id = ?");
    $schoolLookup->execute([$effectiveSchoolId]);
    $row = $schoolLookup->fetch();
    $mySlug = $row['slug'] ?? '';
    $impersonatingSchoolName = $row['name'] ?? '';
    $schoolPlanInfo = $row;
}

function plan_badge_text(array $school): array {
    $locked = is_premium_locked($school);
    $daysLeft = days_until_lockout($school);

    if ($school['plan'] === 'paid') {
        return ['label' => 'Paid Plan', 'color' => '#1B4D3E', 'bg' => '#DCEFE1'];
    }
    if ($school['plan'] === 'promo_paid' && !$locked) {
        $suffix = $daysLeft !== null ? " · $daysLeft day" . ($daysLeft == 1 ? '' : 's') . ' left' : '';
        return ['label' => "Free Trial (Premium){$suffix}", 'color' => '#8C6D1F', 'bg' => '#FBF0D1'];
    }
    if ($school['plan'] === 'promo_paid' && $locked) {
        return ['label' => 'Free Plan (trial ended)', 'color' => '#8C3B2E', 'bg' => '#FBE8E4'];
    }
    return ['label' => 'Free Plan', 'color' => '#555', 'bg' => '#eee'];
}
?>
<?php if ($schoolPlanInfo): $badge = plan_badge_text($schoolPlanInfo); ?>
<div style="background:<?= $badge['bg'] ?>;color:<?= $badge['color'] ?>;padding:8px 20px;text-align:center;font-size:0.8rem;font-weight:700;border-bottom:1px solid rgba(0,0,0,0.06);">
  You're on: <?= htmlspecialchars($badge['label']) ?>
  <?php if (strpos($badge['label'], 'Trial') !== false || strpos($badge['label'], 'ended') !== false): ?>
    <a href="https://wa.me/254707306888?text=<?= urlencode('Hi Somahub, I would like to ask about my plan for ' . $impersonatingSchoolName) ?>" target="_blank" style="color:<?= $badge['color'] ?>;text-decoration:underline;margin-left:8px;">Ask about upgrading</a>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php if ($impersonatingSchoolId): ?>
<div style="background:#F2A65A;color:#0A3A3E;padding:10px 20px;text-align:center;font-size:0.85rem;font-weight:700;">
  🔧 Admin Mode — editing <?= htmlspecialchars($impersonatingSchoolName) ?>'s website
  <a href="exit-impersonation.php" style="color:#0A3A3E;text-decoration:underline;margin-left:10px;">Exit</a>
</div>
<?php endif; ?>
<nav class="topnav">
  <div class="wrap navbar">
    <strong>Somahub Dashboard</strong>
    <div class="navlinks" id="dashnavlinks">
      <a href="index.php">Home</a>
      <a href="sections.php">Website</a>
      <a href="enrollment.php">Enrollment</a>
      <a href="results.php">Results</a>
      <a href="fees.php">Fees</a>
      <a href="verify.php">Verification</a>
      <a href="account.php">Account</a>
      <?php if ($mySlug): ?><a href="https://<?= urlencode($mySlug) ?>.somahub.top/" target="_blank">View My Site ↗</a><?php endif; ?>
      <a href="../index.php">Somahub Home</a>
      <?php if ($impersonatingSchoolId): ?>
        <a href="exit-impersonation.php">Exit Admin Mode</a>
      <?php else: ?>
        <a href="logout.php">Log Out</a>
      <?php endif; ?>
    </div>
    <button class="menu-toggle" onclick="document.getElementById('dashnavlinks').classList.toggle('open')">☰</button>
  </div>
</nav>
<?php
$SOMAHUB_CHAT_CONTEXT = 'dashboard';
include __DIR__ . '/../_chat_widget.php';
?>