<?php $__cur = basename($_SERVER['SCRIPT_NAME']);
function adm_active(array $f): string { global $__cur; return in_array($__cur, $f, true) ? ' active' : ''; }
?>
<nav class="topnav">
  <div class="wrap navbar">
    <a href="home.php" class="brand"><span class="dot">●</span> Somahub <small>Admin</small></a>
    <div class="navlinks" id="adminnavlinks">
      <a href="home.php" class="top<?= adm_active(['home.php']) ?>">Home</a>
      <div class="nav-group">
        <span class="nav-group-label<?= adm_active(['index.php','school-new.php','school-edit.php','verifications.php','account-requests.php','archived-schools.php','demo-school.php']) ?>">Schools ▾</span>
        <div class="nav-dropdown">
          <a href="index.php">All schools</a>
          <a href="school-new.php">Add school</a>
          <a href="verifications.php">Verifications</a>
          <a href="account-requests.php">Account requests</a>
          <a href="archived-schools.php">Archived schools</a>
          <a href="demo-school.php">Demo school</a>
        </div>
      </div>
      <div class="nav-group">
        <span class="nav-group-label<?= adm_active(['payments.php','refunds.php','content-writing-queue.php','google-business-queue.php']) ?>">Money ▾</span>
        <div class="nav-dropdown">
          <a href="payments.php">Payments</a>
          <a href="refunds.php">Refunds</a>
          <a href="content-writing-queue.php">Content writing</a>
          <a href="google-business-queue.php">Google Business</a>
        </div>
      </div>
      <div class="nav-group">
        <span class="nav-group-label<?= adm_active(['leads.php','leads-convert.php','outreach.php','broadcast.php','content-sql.php','blog.php','reviews.php','contact-messages.php']) ?>">Growth ▾</span>
        <div class="nav-dropdown">
          <a href="leads.php">Leads</a>
          <a href="outreach.php">Outreach (one by one)</a>
          <a href="broadcast.php">Broadcast (bulk email)</a>
          <a href="content-sql.php">Content SQL</a>
          <a href="blog.php">Blog</a>
          <a href="reviews.php">Reviews</a>
          <a href="contact-messages.php">Contact messages</a>
        </div>
      </div>
      <div class="nav-group">
        <span class="nav-group-label<?= adm_active(['audit-log.php','settings.php','optimize-images.php']) ?>">System ▾</span>
        <div class="nav-dropdown">
          <a href="audit-log.php">Audit log</a>
          <a href="settings.php">Settings</a>
          <a href="optimize-images.php">Optimise images</a>
          <a href="../index.php" target="_blank">Main site ↗</a>
        </div>
      </div>
      <a href="logout.php" class="top">Log out</a>
    </div>
    <button class="menu-toggle" aria-label="Menu" data-menu-toggle="adminnavlinks">☰</button>
  </div>
</nav>
<?php include __DIR__ . '/../includes/menu-toggle.php'; ?>
