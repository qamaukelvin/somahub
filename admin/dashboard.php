<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/notify.php';
require_admin_login();

$pdo      = getDB();
$settings = getSettings();
$currency = $settings['currency'] ?? 'KES';

// ── AJAX status update (autosave) ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $id = (int)($_POST['booking_id'] ?? 0);

    if ($_POST['ajax_action'] === 'status') {
        $allowed = ['pending','confirmed','cancelled'];
        $status  = $_POST['new_status'] ?? '';
        if (!in_array($status, $allowed, true) || !$id) {
            echo json_encode(['success'=>false,'message'=>'Invalid.']); exit;
        }
        $pdo->prepare("UPDATE bookings SET status=:s WHERE id=:id")
            ->execute([':s'=>$status,':id'=>$id]);
        $b = $pdo->prepare("SELECT * FROM bookings WHERE id=:id");
        $b->execute([':id'=>$id]);
        $row = $b->fetch(PDO::FETCH_ASSOC);
        if ($row && in_array($status,['confirmed','cancelled'],true)) {
            notify_booking_event($row, $status);
        }
        echo json_encode(['success'=>true,'message'=>'Status updated.']);
        exit;
    }

    if ($_POST['ajax_action'] === 'checkout') {
        $pdo->prepare("UPDATE bookings SET checked_out_at=NOW() WHERE id=:id AND status='confirmed'")
            ->execute([':id'=>$id]);
        $b = $pdo->prepare("SELECT * FROM bookings WHERE id=:id");
        $b->execute([':id'=>$id]);
        $row = $b->fetch(PDO::FETCH_ASSOC);
        if ($row) notify_booking_event($row,'checked_out');
        echo json_encode(['success'=>true,'message'=>'Checked out.']);
        exit;
    }

    if ($_POST['ajax_action'] === 'delete') {
        $pdo->prepare("DELETE FROM bookings WHERE id=:id")->execute([':id'=>$id]);
        echo json_encode(['success'=>true,'message'=>'Deleted.']);
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Unknown action.']); exit;
}

// ── Block external dates ───────────────────────────────────────────────────
$blockError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['block_dates'])) {
    $blockFloor    = in_array($_POST['block_floor']??'',['floor1','floor2','both'],true) ? $_POST['block_floor'] : 'floor2';
    $blockCheckin  = trim($_POST['block_checkin']??'');
    $blockCheckout = trim($_POST['block_checkout']??'');
    $blockSource   = trim($_POST['block_source']??'')?:'Other';
    $blockName     = trim($_POST['block_guest_name']??'');
    $blockLabel    = $blockName ? "$blockName ($blockSource)" : "Booked via $blockSource";
    $ci = DateTime::createFromFormat('Y-m-d',$blockCheckin);
    $co = DateTime::createFromFormat('Y-m-d',$blockCheckout);
    if (!$ci||!$co||$co<=$ci) {
        $blockError = 'Invalid dates.';
    } else {
        $fc  = $blockFloor==='both' ? "floor IN ('floor1','floor2','both')" : "floor IN (:floor,'both')";
        $chk = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE status!='cancelled' AND checkin_date<:co AND checkout_date>:ci AND $fc");
        $cp  = [':co'=>$blockCheckout,':ci'=>$blockCheckin];
        if ($blockFloor!=='both') $cp[':floor']=$blockFloor;
        $chk->execute($cp);
        if ($chk->fetchColumn()>0) {
            $blockError = 'Dates overlap an existing booking.';
        } else {
            $nights=(int)$ci->diff($co)->days;
            $pdo->prepare("INSERT INTO bookings (full_name,email,phone,checkin_date,checkout_date,floor,nights,total_price,guests,purpose,message,status,is_blocked)
                           VALUES (:n,'','', :ci,:co,:f,:nights,NULL,0,:src,:lbl,'confirmed',1)")
                ->execute([':n'=>$blockLabel,':ci'=>$blockCheckin,':co'=>$blockCheckout,':f'=>$blockFloor,':nights'=>$nights,':src'=>$blockSource,':lbl'=>$blockLabel]);
            header('Location: dashboard.php'); exit;
        }
    }
}

$filter = $_GET['status'] ?? 'all';
$sql    = "SELECT * FROM bookings";
$params = [];
if (in_array($filter,['pending','confirmed','cancelled'],true)) {
    $sql   .= " WHERE status=:status";
    $params[':status'] = $filter;
}
$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
$counts   = $pdo->query("SELECT status,COUNT(*) c FROM bookings GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$floorMap = ['floor1'=>'Floor 1','floor2'=>'Floor 2','both'=>'Both'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard | Quattro Homes Admin</title>
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
  <a href="dashboard.php" class="admin-brand">
    <div class="mark">Q</div>
    <div class="name">Quattro Homes<small>Admin</small></div>
  </a>
  <nav class="admin-nav">
    <a href="dashboard.php" class="active">Bookings</a>
    <a href="reviews.php">Reviews</a>
    <a href="issues.php">Issues</a>
    <a href="referrals.php">Referrals</a>
    <a href="blog.php">Blog</a>
    <a href="settings.php">Settings</a>
    <a href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
  </nav>
</header>

<div class="admin-container">

  <!-- Stats bar -->
  <div class="admin-stats">
    <a href="?status=all"       class="stat-card <?php echo $filter==='all'?'active':''; ?>"><span class="stat-num"><?php echo array_sum($counts); ?></span><span>All</span></a>
    <a href="?status=pending"   class="stat-card stat-pending <?php echo $filter==='pending'?'active':''; ?>"><span class="stat-num"><?php echo $counts['pending']??0; ?></span><span>Pending</span></a>
    <a href="?status=confirmed" class="stat-card stat-confirmed <?php echo $filter==='confirmed'?'active':''; ?>"><span class="stat-num"><?php echo $counts['confirmed']??0; ?></span><span>Confirmed</span></a>
    <a href="?status=cancelled" class="stat-card stat-cancelled <?php echo $filter==='cancelled'?'active':''; ?>"><span class="stat-num"><?php echo $counts['cancelled']??0; ?></span><span>Cancelled</span></a>
  </div>

  <!-- Block external dates (collapsed by default) -->
  <details class="admin-card block-section">
    <summary>Mark as Occupied <span class="summary-hint">— block dates for external bookings</span></summary>
    <div class="block-form-body">
      <?php if ($blockError): ?><div class="admin-alert error"><?php echo htmlspecialchars($blockError); ?></div><?php endif; ?>
      <form method="post" class="settings-form">
        <input type="hidden" name="block_dates" value="1">
        <div class="form-row">
          <div class="field">
            <label>Unit</label>
            <select name="block_floor">
              <option value="floor2">Floor 2</option>
              <option value="floor1">Floor 1</option>
              <option value="both">Both floors</option>
            </select>
          </div>
          <div class="field">
            <label>Booked via</label>
            <select name="block_source">
              <option>Airbnb</option><option>Booking.com</option>
              <option>Walk-in</option><option>Phone</option><option>Other</option>
            </select>
          </div>
          <div class="field">
            <label>Guest name <span class="opt">(optional)</span></label>
            <input type="text" name="block_guest_name" placeholder="e.g. Jane Doe">
          </div>
          <div class="field">
            <label>Check-in</label>
            <input type="date" name="block_checkin" required>
          </div>
          <div class="field">
            <label>Check-out</label>
            <input type="date" name="block_checkout" required>
          </div>
          <div class="field field-btn">
            <button type="submit" class="admin-btn">Block Dates</button>
          </div>
        </div>
      </form>
    </div>
  </details>

  <!-- Bookings table -->
  <div class="admin-card table-card">
    <div id="dash-toast" class="dash-toast" style="display:none;"></div>
    <div style="overflow-x:auto;">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Ref</th>
            <th>Guest</th>
            <th>Contact</th>
            <th>Unit</th>
            <th>Dates</th>
            <th>Nights</th>
            <th>Guests</th>
            <th>Purpose</th>
            <th>Total</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$bookings): ?>
          <tr><td colspan="10" class="empty-row">No bookings found.</td></tr>
        <?php endif; ?>
        <?php foreach ($bookings as $b):
          $isBlocked = !empty($b['is_blocked']);
          $bid       = (int)$b['id'];
          $ref       = 'QH' . str_pad($bid, 4, '0', STR_PAD_LEFT);
        ?>
          <tr class="booking-row <?php echo $isBlocked?'row-blocked':''; ?>" data-id="<?php echo $bid; ?>" data-status="<?php echo htmlspecialchars($b['status']); ?>">
            <td data-label="Ref">
              <span class="booking-ref"><?php echo $ref; ?></span>
              <span class="badge badge-<?php echo $b['status']; ?>"><?php echo ucfirst($b['status']); ?></span>
              <?php if ($isBlocked): ?><span class="badge badge-blocked">External</span><?php endif; ?>
              <?php if (!empty($b['checked_out_at'])): ?><span class="badge badge-confirmed" style="font-size:0.7rem;"><i class="fa-solid fa-circle-check"></i> Checked out</span><?php endif; ?>
            </td>
            <td data-label="Guest"><?php echo htmlspecialchars($b['full_name']); ?></td>
            <td data-label="Contact">
              <?php if ($b['phone']): ?><a href="tel:<?php echo htmlspecialchars($b['phone']); ?>" class="contact-link"><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($b['phone']); ?></a><?php endif; ?>
              <?php if ($b['email']): ?><a href="mailto:<?php echo htmlspecialchars($b['email']); ?>" class="contact-link"><i class="fa-regular fa-envelope"></i> <?php echo htmlspecialchars($b['email']); ?></a><?php endif; ?>
            </td>
            <td data-label="Unit"><?php echo $floorMap[$b['floor']] ?? htmlspecialchars($b['floor']); ?></td>
            <td data-label="Dates" style="white-space:nowrap;"><?php echo htmlspecialchars($b['checkin_date']); ?><br><span style="color:#999;font-size:0.8rem;">&rarr; <?php echo htmlspecialchars($b['checkout_date']); ?></span></td>
            <td data-label="Nights"><?php echo (int)$b['nights']; ?></td>
            <td data-label="Guests"><?php echo (int)$b['guests']; ?></td>
            <td data-label="Purpose"><?php echo htmlspecialchars($b['purpose']??''); ?></td>
            <td data-label="Total"><?php echo $b['total_price']!==null ? $currency.' '.number_format($b['total_price']) : '&mdash;'; ?></td>
            <td data-label="Actions">
              <div class="action-group">
                <?php if (!$isBlocked): ?>
                  <?php if ($b['status']==='pending'): ?>
                    <button class="action-btn btn-confirm" data-action="status" data-status="confirmed" data-id="<?php echo $bid; ?>" title="Confirm booking">
                      <i class="fa-solid fa-circle-check"></i> Confirm
                    </button>
                    <button class="action-btn btn-cancel" data-action="status" data-status="cancelled" data-id="<?php echo $bid; ?>" title="Cancel booking">
                      <i class="fa-solid fa-xmark"></i> Cancel
                    </button>
                  <?php elseif ($b['status']==='confirmed'): ?>
                    <?php if (empty($b['checked_out_at'])): ?>
                    <button class="action-btn btn-checkout" data-action="checkout" data-id="<?php echo $bid; ?>" title="Mark as checked out">
                      <i class="fa-solid fa-door-open"></i> Check Out
                    </button>
                    <?php endif; ?>
                    <button class="action-btn btn-cancel" data-action="status" data-status="cancelled" data-id="<?php echo $bid; ?>" title="Cancel booking">
                      <i class="fa-solid fa-xmark"></i> Cancel
                    </button>
                  <?php elseif ($b['status']==='cancelled'): ?>
                    <button class="action-btn btn-confirm" data-action="status" data-status="confirmed" data-id="<?php echo $bid; ?>" title="Restore booking">
                      <i class="fa-solid fa-rotate-left"></i> Restore
                    </button>
                  <?php endif; ?>
                <?php endif; ?>
                <button class="action-btn btn-delete" data-action="delete" data-id="<?php echo $bid; ?>" title="Delete permanently">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script>
(function(){
  const toast = document.getElementById('dash-toast');

  function showToast(msg, ok) {
    toast.textContent = msg;
    toast.className = 'dash-toast ' + (ok ? 'toast-ok' : 'toast-err');
    toast.style.display = 'block';
    clearTimeout(toast._t);
    toast._t = setTimeout(() => { toast.style.display='none'; }, 3000);
  }

  function doAction(btn) {
    const action = btn.dataset.action;
    const id     = btn.dataset.id;
    const status = btn.dataset.status || '';
    const row    = document.querySelector(`.booking-row[data-id="${id}"]`);

    if (action === 'delete') {
      if (!confirm('Delete this booking permanently? This cannot be undone.')) return;
    }

    btn.disabled = true;
    const body = new URLSearchParams({ ajax_action: action, booking_id: id, new_status: status });

    fetch('dashboard.php', { method:'POST', body })
      .then(r => r.json())
      .then(data => {
        if (!data.success) { showToast(data.message, false); btn.disabled=false; return; }
        showToast(data.message, true);

        if (action === 'delete') {
          row.style.transition = 'opacity .3s';
          row.style.opacity = '0';
          setTimeout(() => row.remove(), 300);
          return;
        }
        // Reload row state by refreshing page after short delay
        setTimeout(() => location.reload(), 800);
      })
      .catch(() => { showToast('Request failed.', false); btn.disabled=false; });
  }

  document.querySelectorAll('.action-btn').forEach(btn => {
    btn.addEventListener('click', () => doAction(btn));
  });
})();
</script>
</body>
</html>
