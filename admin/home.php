<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
$db = get_db();
function n(PDO $db, string $sql): int { try { return (int)$db->query($sql)->fetchColumn(); } catch (Throwable $e) { return 0; } }

$attention = [
  ['verified',        'Verifications waiting',   n($db, "SELECT COUNT(*) FROM schools WHERE verification_status='pending'"), 'verifications.php'],
  ['payments',        'M-Pesa payments to confirm', n($db, "SELECT COUNT(*) FROM orders WHERE status='pending' AND mpesa_code IS NOT NULL"), 'payments.php'],
  ['currency_exchange','Refund requests',        n($db, "SELECT COUNT(*) FROM refunds WHERE status='requested'"), 'refunds.php'],
  ['person_remove',   'Account removal requests', n($db, "SELECT COUNT(*) FROM account_removal_requests WHERE status='pending'"), 'account-requests.php'],
  ['edit_note',       'Content writing to deliver', n($db, "SELECT COUNT(*) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.product_key='content_writing' AND o.status='paid' AND oi.delivered_at IS NULL"), 'content-writing-queue.php'],
  ['storefront',      'Google Business setups',  n($db, "SELECT COUNT(*) FROM service_requests sr JOIN orders o ON o.id=sr.order_id WHERE sr.service_key='google_business_setup' AND o.status='paid' AND sr.status<>'completed'"), 'google-business-queue.php'],
  ['reviews',         'Reviews to moderate',     n($db, "SELECT COUNT(*) FROM reviews WHERE status='pending'"), 'reviews.php'],
  ['mail',            'New contact messages',    n($db, "SELECT COUNT(*) FROM contact_messages WHERE status='new'"), 'contact-messages.php'],
  ['person_search',   'New leads',               n($db, "SELECT COUNT(*) FROM leads WHERE status='new'"), 'leads.php'],
];
$total = array_sum(array_column($attention, 2));
$stats = [
  ['Schools', n($db, "SELECT COUNT(*) FROM schools WHERE slug <> 'demo'")],
  ['On trial', n($db, "SELECT COUNT(*) FROM schools WHERE status='trial' AND slug <> 'demo'")],
  ['Paid', n($db, "SELECT COUNT(*) FROM schools WHERE plan='paid'")],
  ['Verified', n($db, "SELECT COUNT(*) FROM schools WHERE verification_status='verified'")],
  ['Signed up (7 days)', n($db, "SELECT COUNT(*) FROM schools WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND slug <> 'demo'")],
];
$recent = []; try { $recent = $db->query("SELECT name, slug, plan, verification_status, created_at FROM schools WHERE slug <> 'demo' ORDER BY created_at DESC LIMIT 6")->fetchAll(); } catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Admin Home — Somahub</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:28px;}
  .stat{background:#fff;border-radius:14px;padding:18px 20px;box-shadow:0 1px 4px rgba(0,0,0,.05);} .stat b{display:block;font-size:1.9rem;color:var(--teal);line-height:1.1;} .stat span{font-size:.78rem;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.04em;}
  .att{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px;margin-bottom:30px;}
  .att a{display:flex;align-items:center;gap:12px;background:#fff;border-radius:14px;padding:14px 16px;text-decoration:none;color:var(--ink);box-shadow:0 1px 4px rgba(0,0,0,.05);transition:transform .12s,box-shadow .12s;border:1.5px solid transparent;}
  .att a:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(0,0,0,.08);}
  .att a.hot{border-color:var(--amber);background:#FFF8EC;}
  .att .ic{font-family:'Material Symbols Outlined';font-size:22px;background:#E3F1F2;color:var(--teal);border-radius:10px;padding:8px;} .att a.hot .ic{background:var(--amber);color:#3b2500;}
  .att .ct{margin-left:auto;font-weight:800;font-size:1.2rem;color:var(--teal);} .att a.hot .ct{color:#8A5A12;} .att .lb{font-size:.88rem;font-weight:700;}
  .quick{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:26px;}
</style>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"></head><body>
<?php include __DIR__ . '/_nav.php'; ?>
<main class="wrap">
  <div class="header-row"><h1>Home</h1></div>
  <div class="quick"><a class="btn" href="school-new.php">Add school</a><a class="btn secondary" href="broadcast.php">Send a broadcast</a><a class="btn secondary" href="outreach.php">Outreach</a><a class="btn secondary" href="index.php">All schools</a></div>
  <div class="stats"><?php foreach ($stats as $s): ?><div class="stat"><b><?= $s[1] ?></b><span><?= htmlspecialchars($s[0]) ?></span></div><?php endforeach; ?></div>
  <h2 style="margin:0 0 12px;"><?= $total ? "Needs attention ($total)" : 'All clear' ?></h2>
  <div class="att"><?php foreach ($attention as $a): ?>
    <a href="<?= $a[3] ?>" class="<?= $a[2] > 0 ? 'hot' : '' ?>"><span class="ic"><?= $a[0] ?></span><span class="lb"><?= htmlspecialchars($a[1]) ?></span><span class="ct"><?= $a[2] ?></span></a>
  <?php endforeach; ?></div>
  <h2 style="margin:0 0 12px;">Newest schools</h2>
  <table><thead><tr><th>School</th><th>Plan</th><th>Verification</th><th>Joined</th><th></th></tr></thead>
  <?php foreach ($recent as $r): ?><tr>
    <td data-label="School"><strong><?= htmlspecialchars($r['name']) ?></strong></td>
    <td data-label="Plan"><span class="plan-badge plan-<?= htmlspecialchars($r['plan']) ?>"><?= htmlspecialchars($r['plan']) ?></span></td>
    <td data-label="Verification"><?= htmlspecialchars($r['verification_status']) ?></td>
    <td data-label="Joined"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
    <td data-label=""><a href="https://<?= htmlspecialchars($r['slug']) ?>.somahub.top/" target="_blank">View site</a></td>
  </tr><?php endforeach; if (!$recent): ?><tr><td colspan="5" style="text-align:center;color:var(--muted);">No schools yet.</td></tr><?php endif; ?></table>
</main></body></html>
