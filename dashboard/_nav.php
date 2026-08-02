<?php
$user = current_user();
$mySlug = '';
if ($user && !empty($user['school_id'])) {
    $slugStmt = get_db()->prepare("SELECT slug FROM schools WHERE id = ?");
    $slugStmt->execute([$user['school_id']]);
    $mySlug = $slugStmt->fetch()['slug'] ?? '';
}
?>
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
      <?php if ($mySlug): ?><a href="../site.php?school=<?= urlencode($mySlug) ?>" target="_blank">View My Site ↗</a><?php endif; ?>
      <a href="../index.php">Somahub Home</a>
      <a href="logout.php">Log Out</a>
    </div>
    <button class="menu-toggle" onclick="document.getElementById('dashnavlinks').classList.toggle('open')">☰</button>
  </div>
</nav>
<?php
$SOMAHUB_CHAT_CONTEXT = 'dashboard';
include __DIR__ . '/../_chat_widget.php';
?>
