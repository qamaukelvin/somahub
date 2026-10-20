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
            nudge_notification($db, $effectiveSchoolId, 'verification_reminder',
                'Verify your school',
                "Your site is live! Verify within {$verificationDaysLeft} day" . ($verificationDaysLeft == 1 ? '' : 's') . " to keep it that way.",
                'verify.php');
        } else {
            nudge_notification($db, $effectiveSchoolId, 'verification_reminder',
                'Verification needed',
                'Your free preview period has ended — your site is offline until verification is complete.',
                'verify.php');
        }
    } else {
        dismiss_notifications_by_type($db, $effectiveSchoolId, 'verification_reminder');
    }

    if (!$hasPlaceholderEmail) dismiss_notifications_by_type($db, $effectiveSchoolId, 'add_email');
    if (!$hasTempPassword) dismiss_notifications_by_type($db, $effectiveSchoolId, 'set_password');
    if ($hasPlaceholderEmail) {
        nudge_notification($db, $effectiveSchoolId, 'add_email',
            'Add your email',
            'Add your email to finish setting up your account.',
            'account.php');
    }
    if ($hasTempPassword) {
        nudge_notification($db, $effectiveSchoolId, 'set_password',
            'Set a password',
            'Set a password so you can log in normally next time.',
            'account.php');
    }
}

$curPage = basename($_SERVER['SCRIPT_NAME']);
function nav_active(array $files): string { global $curPage; return in_array($curPage, $files, true) ? ' active' : ''; }
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
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
<?php if ($impersonatingSchoolId): ?>
<div class="admin-bar"><span class="material-symbols-outlined">construction</span> Admin mode: editing <?= htmlspecialchars($impersonatingSchoolName) ?>'s website <a href="exit-impersonation.php">Exit</a></div>
<?php endif; ?>
<nav class="topnav dnav">
  <div class="dnav-in">
    <a class="dnav-brand" href="index.php"><span class="material-symbols-outlined">school</span><span class="dnav-name"><?= htmlspecialchars($impersonatingSchoolName ?: 'Somahub') ?></span></a>
    <?php if ($schoolPlanInfo): $badge = plan_badge_text($schoolPlanInfo); ?>
      <a class="dnav-plan" href="checkout.php" style="background:<?= $badge['bg'] ?>;color:<?= $badge['color'] ?>;"><?= htmlspecialchars($badge['label']) ?></a>
    <?php endif; ?>

    <div class="navlinks" id="dashnavlinks">
      <a class="dl<?= nav_active(['index.php']) ?>" href="index.php"><span class="material-symbols-outlined">dashboard</span>Home</a>
      <div class="nav-group">
        <button type="button" class="dl nav-group-label<?= nav_active(['enrollment.php','contact-messages.php','results.php','attendance.php','fees.php']) ?>"><span class="material-symbols-outlined">folder_open</span>School data<span class="material-symbols-outlined caret">expand_more</span></button>
        <div class="nav-dropdown">
          <a href="enrollment.php"><span class="material-symbols-outlined">how_to_reg</span>Enrollment</a>
          <a href="contact-messages.php"><span class="material-symbols-outlined">mail</span>Contact messages</a>
          <a href="results.php"><span class="material-symbols-outlined">grading</span>Results</a>
          <a href="attendance.php"><span class="material-symbols-outlined">event_available</span>Attendance</a>
          <a href="fees.php"><span class="material-symbols-outlined">payments</span>Fees</a>
        </div>
      </div>
      <?php if ($effectiveSchoolId): ?>
    <div class="notif-bell-wrap">
      <button type="button" class="notif-bell" onclick="toggleNotifPanel()" aria-label="Notifications">
        <span class="material-symbols-outlined"><?= $unreadCount > 0 ? 'notifications_unread' : 'notifications' ?></span><?php if ($unreadCount > 0): ?><span class="notif-badge"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span><?php endif; ?>
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
      <div class="nav-group nav-right">
        <button type="button" class="dl nav-group-label<?= nav_active(['verify.php','checkout.php','invoices.php','google-business.php','account.php']) ?>"><span class="material-symbols-outlined">account_circle</span>Account<span class="material-symbols-outlined caret">expand_more</span></button>
        <div class="nav-dropdown">
          <a href="verify.php"><span class="material-symbols-outlined">verified</span>Verification</a>
          <a href="checkout.php"><span class="material-symbols-outlined">workspace_premium</span>Upgrade</a>
          <a href="invoices.php"><span class="material-symbols-outlined">receipt_long</span>Invoices</a>
          <a href="google-business.php"><span class="material-symbols-outlined">storefront</span>Google Business</a>
          <a href="account.php"><span class="material-symbols-outlined">manage_accounts</span>Account settings</a>
          <?php if ($impersonatingSchoolId): ?><a href="exit-impersonation.php"><span class="material-symbols-outlined">logout</span>Exit admin mode</a><?php else: ?><a href="logout.php"><span class="material-symbols-outlined">logout</span>Log out</a><?php endif; ?>
        </div>
      </div>
      <?php if ($mySlug): ?><a class="dl dl-site" href="https://<?= urlencode($mySlug) ?>.somahub.top/" target="_blank" rel="noopener"><span class="material-symbols-outlined">open_in_new</span>View site</a><?php endif; ?>
      <?php if ($mySlug): ?><a class="dl" href="https://<?= htmlspecialchars($mySlug) ?>.somahub.top/?edit=1" style="background:#F2A65A;color:#0A3A3E;border-radius:999px;font-weight:800;"><span class="material-symbols-outlined">edit_square</span>Edit site</a><?php endif; ?>
    </div>

    <button type="button" class="menu-toggle" aria-label="Menu" onclick="document.getElementById('dashnavlinks').classList.toggle('open')"><span class="material-symbols-outlined">menu</span></button>
  </div>
</nav>
<style>
  .material-symbols-outlined{font-size:20px;line-height:1;vertical-align:middle;}
  .admin-bar{background:#F2A65A;color:#0A3A3E;padding:8px 20px;text-align:center;font-size:.84rem;font-weight:700;}
  .admin-bar a{color:#0A3A3E;text-decoration:underline;margin-left:10px;} .admin-bar .material-symbols-outlined{font-size:17px;margin-right:4px;}
  .dnav{position:sticky;top:0;z-index:45;box-shadow:0 1px 0 rgba(255,255,255,.08),0 4px 14px rgba(0,0,0,.12);}
  .dnav-in{max-width:1100px;margin:0 auto;padding:0 20px;height:58px;display:flex;align-items:center;gap:12px;}
  .dnav-brand{display:flex;align-items:center;gap:8px;color:#fff;text-decoration:none;font-weight:800;font-size:.98rem;min-width:0;}
  .dnav-brand .material-symbols-outlined{background:rgba(255,255,255,.14);border-radius:9px;padding:6px;font-size:20px;}
  .dnav-name{max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
  .dnav-plan{padding:4px 11px;border-radius:999px;font-size:.72rem;font-weight:800;text-decoration:none;white-space:nowrap;}
  .dnav .navlinks{margin-left:auto;display:flex;align-items:center;gap:2px;flex-wrap:nowrap;}
  .dnav .dl,.dnav .nav-group-label{display:inline-flex;align-items:center;gap:6px;color:var(--sand);text-decoration:none;font:inherit;font-size:.85rem;font-weight:700;padding:8px 11px;border-radius:9px;background:none;border:none;cursor:pointer;white-space:nowrap;}
  .dnav .dl .material-symbols-outlined{font-size:19px;opacity:.9;} .dnav .caret{font-size:18px;margin-left:-2px;opacity:.7;}
  .dnav .dl:hover,.dnav .nav-group:hover .nav-group-label,.dnav .nav-group:focus-within .nav-group-label{background:rgba(255,255,255,.12);text-decoration:none;}
  .dnav .dl.active{background:rgba(255,255,255,.2);color:#fff;}
  .dnav .nav-right .nav-dropdown{left:auto;right:0;}
  .dnav .navlinks .notif-bell-wrap{display:inline-flex;align-items:center;}
  .dnav-sep{width:1px;height:22px;background:rgba(255,255,255,.2);margin:0 6px;}
  .dnav .dl-site{color:#fff;}
  .dnav .nav-dropdown{top:calc(100% - 2px);left:0;background:#fff;border-radius:12px;padding:6px;min-width:210px;box-shadow:0 14px 34px rgba(0,0,0,.22);}
  .dnav .nav-group:hover .nav-dropdown,.dnav .nav-group:focus-within .nav-dropdown{display:flex;}
  .dnav .nav-dropdown a{display:flex;align-items:center;gap:10px;color:#1C1C16;font-size:.85rem;font-weight:600;padding:9px 12px;border-radius:8px;text-decoration:none;}
  .dnav .nav-dropdown a:hover{background:#F0F6F4;text-decoration:none;} .dnav .nav-dropdown .material-symbols-outlined{color:#0F5257;font-size:19px;}
  .notif-bell-wrap{position:relative;}
  .notif-bell{background:rgba(255,255,255,.1);border:none;cursor:pointer;position:relative;padding:7px;border-radius:50%;color:#fff;display:flex;}
  .notif-bell:hover{background:rgba(255,255,255,.2);}
  .notif-badge{position:absolute;top:-3px;right:-4px;background:#E4573D;color:#fff;font-size:.62rem;font-weight:800;border-radius:10px;padding:1px 5px;line-height:1.3;}
  .notif-panel{display:none;position:absolute;top:calc(100% + 12px);right:0;background:#fff;color:#1B1B18;width:320px;max-height:420px;overflow-y:auto;border-radius:14px;box-shadow:0 14px 34px rgba(0,0,0,.22);z-index:50;}
  .notif-panel.open{display:block;}
  .notif-panel-header{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid #eee;font-weight:800;font-size:.88rem;}
  .notif-item{display:block;padding:12px 16px;border-bottom:1px solid #f2f2f0;text-decoration:none;color:inherit;}
  .notif-item:hover{background:#faf8f3;} .notif-item.unread{background:#FBF8F2;}
  .notif-item.unread .notif-title::before{content:'';display:inline-block;width:8px;height:8px;border-radius:50%;background:#F2A65A;margin-right:7px;}
  .notif-title{font-size:.85rem;font-weight:700;} .notif-msg{font-size:.78rem;color:#666;margin-top:2px;}
  .notif-empty{padding:20px 16px;color:#888;font-size:.85rem;text-align:center;}
  .notif-view-all{display:block;text-align:center;padding:11px;font-size:.8rem;font-weight:800;color:#0F5257;text-decoration:none;}
  .dnav .menu-toggle{display:none;color:#fff;background:rgba(255,255,255,.1);border-radius:9px;padding:6px;}
  @media(max-width:900px){
    .dnav-name{max-width:110px;}
    .dnav .menu-toggle{display:flex;margin-left:0;}
    .dnav .navlinks{display:none;position:absolute;top:58px;left:0;right:0;margin:0;flex-direction:column;align-items:stretch;gap:2px;background:var(--teal);padding:10px 14px 16px;max-height:calc(100vh - 58px);overflow-y:auto;box-shadow:0 14px 24px rgba(0,0,0,.25);}
    .dnav .navlinks.open{display:flex;}
    .dnav .dl,.dnav .nav-group-label{width:100%;padding:11px 12px;}
    .dnav .nav-group{width:100%;} .dnav .nav-group-label .caret{display:none;}
    .dnav .nav-dropdown,.dnav .nav-group:hover .nav-dropdown{display:flex !important;position:static;background:rgba(255,255,255,.07);box-shadow:none;padding:2px 0 4px 14px;min-width:0;}
    .dnav .nav-dropdown a{color:var(--sand);} .dnav .nav-dropdown .material-symbols-outlined{color:var(--sand);opacity:.85;}
    .dnav .nav-dropdown a:hover{background:rgba(255,255,255,.12);}
    .dnav-sep{display:none;}
  }
  @media(max-width:760px){ .notif-panel{position:fixed;top:auto;bottom:0;left:0;right:0;width:100%;max-height:70vh;border-radius:16px 16px 0 0;} }
</style>
<script>
function toggleNotifPanel() { document.getElementById('notifPanel').classList.toggle('open'); }
document.addEventListener('click', (e) => {
  const wrap = document.querySelector('.notif-bell-wrap');
  if (wrap && !wrap.contains(e.target)) document.getElementById('notifPanel')?.classList.remove('open');
});
</script>
<?php
$SOMAHUB_CHAT_CONTEXT = 'dashboard';
include __DIR__ . '/../_chat_widget.php';
?>