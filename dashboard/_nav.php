<?php
require_once __DIR__ . '/../includes/plan.php';
require_once __DIR__ . '/../includes/notifications.php';
$user = current_user();
$impersonatingSchoolId = $_SESSION['impersonating_school_id'] ?? null;
$effectiveSchoolId = $impersonatingSchoolId ?: ($user['school_id'] ?? null);
$db = get_db();

$mySlug = '';
$impersonatingSchoolName = '';
$schoolPlanInfo = null;
if ($effectiveSchoolId) {
    $schoolLookup = $db->prepare("SELECT slug, name, plan, promo_ends_at, verification_status, created_at FROM schools WHERE id = ?");
    $schoolLookup->execute([$effectiveSchoolId]);
    $row = $schoolLookup->fetch();
    $mySlug = $row['slug'] ?? '';
    $impersonatingSchoolName = $row['name'] ?? '';
    $schoolPlanInfo = $row;
}

const DASH_VERIFICATION_GRACE_PERIOD_DAYS = 7;
$verificationDaysLeft = null;
if ($schoolPlanInfo && ($schoolPlanInfo['verification_status'] ?? '') !== 'verified' && !empty($schoolPlanInfo['created_at'])) {
    $daysSince = (time() - strtotime($schoolPlanInfo['created_at'])) / 86400;
    $verificationDaysLeft = max(0, ceil(DASH_VERIFICATION_GRACE_PERIOD_DAYS - $daysSince));
}

$hasPlaceholderEmail = $user && !filter_var($user['email'] ?? '', FILTER_VALIDATE_EMAIL);

$hasTempPassword = false;
if ($user && !$impersonatingSchoolId) {
    $tempCheck = $db->prepare("SELECT password_is_temp FROM users WHERE id = ?");
    $tempCheck->execute([$user['id']]);
    $hasTempPassword = (bool)$tempCheck->fetchColumn();
}

// Every dashboard-wide nudge becomes a notification-center entry instead of
// a stacked banner. Re-runs on every page load but create_notification()
// only ever keeps one unread copy per type, so this is safe to call here.
if ($effectiveSchoolId && !$impersonatingSchoolId) {
    if ($verificationDaysLeft !== null) {
        if ($verificationDaysLeft > 0) {
            create_notification($db, $effectiveSchoolId, 'verification_reminder',
                'Verify your school',
                "Your site is live! Verify within {$verificationDaysLeft} day" . ($verificationDaysLeft == 1 ? '' : 's') . " to keep it that way.",
                'verify.php');
        } else {
            create_notification($db, $effectiveSchoolId, 'verification_reminder',
                'Verification needed',
                'Your free preview period has ended — your site is offline until verification is complete.',
                'verify.php');
        }
    } else {
        dismiss_notifications_by_type($db, $effectiveSchoolId, 'verification_reminder');
    }

    if ($hasPlaceholderEmail) {
        create_notification($db, $effectiveSchoolId, 'add_email',
            'Add your email',
            'Add your email to finish setting up your account.',
            'account.php');
    }
    if ($hasTempPassword) {
        create_notification($db, $effectiveSchoolId, 'set_password',
            'Set a password',
            'Set a password so you can log in normally next time.',
            'account.php');
    }
}

$unreadCount = $effectiveSchoolId ? get_unread_notification_count($db, $effectiveSchoolId) : 0;

function plan_badge_text(array $school): array {
    $locked = is_premium_locked($school);
    $daysLeft = days_until_lockout($school);

    // Distinct color per plan tier so it reads at a glance, not just by text.
    if ($school['plan'] === 'paid') {
        return ['label' => 'Premium', 'color' => '#fff', 'bg' => '#0F5257'];
    }
    if ($school['plan'] === 'promo_paid' && !$locked) {
        $suffix = $daysLeft !== null ? " · {$daysLeft}d left" : '';
        return ['label' => "Trial{$suffix}", 'color' => '#0A3A3E', 'bg' => '#F2A65A'];
    }
    if ($school['plan'] === 'promo_paid' && $locked) {
        return ['label' => 'Trial Ended', 'color' => '#fff', 'bg' => '#8C3B2E'];
    }
    return ['label' => 'Free', 'color' => '#555', 'bg' => '#E5E5E0'];
}
?>
<?php if ($impersonatingSchoolId): ?>
<div style="background:#F2A65A;color:#0A3A3E;padding:10px 20px;text-align:center;font-size:0.85rem;font-weight:700;">
  🔧 Admin Mode — editing <?= htmlspecialchars($impersonatingSchoolName) ?>'s website
  <a href="exit-impersonation.php" style="color:#0A3A3E;text-decoration:underline;margin-left:10px;">Exit</a>
</div>
<?php endif; ?>
<nav class="topnav">
  <div class="wrap navbar">
    <div style="display:flex;align-items:center;gap:12px;">
      <strong>Somahub Dashboard</strong>
      <?php if ($schoolPlanInfo): $badge = plan_badge_text($schoolPlanInfo); ?>
        <a href="checkout.php" style="background:<?= $badge['bg'] ?>;color:<?= $badge['color'] ?>;padding:4px 12px;border-radius:14px;font-size:0.74rem;font-weight:800;text-decoration:none;white-space:nowrap;"><?= htmlspecialchars($badge['label']) ?></a>
      <?php endif; ?>
    </div>

    <div style="display:flex;align-items:center;gap:14px;">
      <?php if ($effectiveSchoolId): ?>
      <div class="notif-bell-wrap">
        <button class="notif-bell" onclick="toggleNotifPanel()" aria-label="Notifications">
          🔔<?php if ($unreadCount > 0): ?><span class="notif-badge"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span><?php endif; ?>
        </button>
        <div class="notif-panel" id="notifPanel">
          <div class="notif-panel-header">
            <span>Notifications</span>
            <?php if ($unreadCount > 0): ?><a href="notifications.php?mark_all_read=1" style="font-size:0.76rem;">Mark all read</a><?php endif; ?>
          </div>
          <?php $recent = get_recent_notifications($db, $effectiveSchoolId, 6); ?>
          <?php foreach ($recent as $n): ?>
            <a href="notifications.php?open=<?= $n['id'] ?>" class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>">
              <div class="notif-title"><?= htmlspecialchars($n['title']) ?></div>
              <div class="notif-msg"><?= htmlspecialchars($n['message']) ?></div>
            </a>
          <?php endforeach; ?>
          <?php if (!$recent): ?><div class="notif-empty">No notifications yet.</div><?php endif; ?>
          <a href="notifications.php" class="notif-view-all">View all</a>
        </div>
      </div>
      <?php endif; ?>

      <div class="navlinks" id="dashnavlinks">
        <a href="index.php">Home</a>
        <a href="sections.php">Website</a>
        <div class="nav-group">
          <span class="nav-group-label">School Data ▾</span>
          <div class="nav-dropdown">
            <a href="enrollment.php">Enrollment</a>
            <a href="results.php">Results</a>
            <a href="attendance.php">Attendance</a>
            <a href="fees.php">Fees</a>
          </div>
        </div>
        <div class="nav-group">
          <span class="nav-group-label">Account ▾</span>
          <div class="nav-dropdown">
            <a href="verify.php">Verification</a>
            <a href="checkout.php">Upgrade</a>
            <a href="invoices.php">Invoices</a>
            <a href="google-business.php">Google Business</a>
            <a href="account.php">Account</a>
          </div>
        </div>
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
  </div>
</nav>
<style>
  .notif-bell-wrap{position:relative;}
  .notif-bell{background:none;border:none;font-size:1.2rem;cursor:pointer;position:relative;padding:4px;color:var(--sand);}
  .notif-badge{position:absolute;top:-2px;right:-4px;background:#E4573D;color:#fff;font-size:0.62rem;font-weight:800;border-radius:10px;padding:1px 5px;line-height:1.3;}
  .notif-panel{display:none;position:absolute;top:calc(100% + 10px);right:0;background:#fff;color:#1B1B18;width:300px;max-height:400px;overflow-y:auto;border-radius:10px;box-shadow:0 14px 34px rgba(0,0,0,0.2);z-index:50;}
  .notif-panel.open{display:block;}
  .notif-panel-header{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid #eee;font-weight:700;font-size:0.88rem;}
  .notif-item{display:block;padding:12px 16px;border-bottom:1px solid #f2f2f0;text-decoration:none;color:inherit;}
  .notif-item.unread{background:#FBF8F2;}
  .notif-item.unread .notif-title::before{content:'●';color:#F2A65A;font-size:0.6rem;margin-right:6px;}
  .notif-title{font-size:0.85rem;font-weight:700;}
  .notif-msg{font-size:0.78rem;color:#666;margin-top:2px;}
  .notif-empty{padding:20px 16px;color:#888;font-size:0.85rem;text-align:center;}
  .notif-view-all{display:block;text-align:center;padding:10px;font-size:0.8rem;font-weight:700;color:#0F5257;text-decoration:none;}
  @media(max-width:760px){ .notif-panel{position:fixed;top:auto;bottom:0;left:0;right:0;width:100%;max-height:70vh;border-radius:16px 16px 0 0;} }
</style>
<script>
function toggleNotifPanel() {
  document.getElementById('notifPanel').classList.toggle('open');
}
document.addEventListener('click', (e) => {
  const wrap = document.querySelector('.notif-bell-wrap');
  if (wrap && !wrap.contains(e.target)) document.getElementById('notifPanel')?.classList.remove('open');
});
</script>
<?php
$SOMAHUB_CHAT_CONTEXT = 'dashboard';
include __DIR__ . '/../_chat_widget.php';
?>