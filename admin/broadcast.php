<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../unsubscribe.php'; // unsub_token()
$db = get_db();
// Create the bulk-mail tables on first visit so nothing has to be run by hand.
foreach ([
 "CREATE TABLE IF NOT EXISTS broadcasts (id INT AUTO_INCREMENT PRIMARY KEY, subject VARCHAR(200) NOT NULL, body TEXT NOT NULL, cta_text VARCHAR(80) NOT NULL DEFAULT '', cta_link VARCHAR(300) NOT NULL DEFAULT '', audience VARCHAR(40) NOT NULL, total INT NOT NULL DEFAULT 0, sent INT NOT NULL DEFAULT 0, failed INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
 "CREATE TABLE IF NOT EXISTS broadcast_recipients (id INT AUTO_INCREMENT PRIMARY KEY, broadcast_id INT NOT NULL, email VARCHAR(190) NOT NULL, name VARCHAR(150) NOT NULL DEFAULT '', school VARCHAR(190) NOT NULL DEFAULT '', status ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued', error VARCHAR(255) NULL, sent_at DATETIME NULL, INDEX (broadcast_id, status))",
 "CREATE TABLE IF NOT EXISTS email_unsubscribes (email VARCHAR(190) PRIMARY KEY, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
] as $__ddl) { try { $db->exec($__ddl); } catch (Throwable $e) { error_log('broadcast tables: ' . $e->getMessage()); } }

$audiences = [
    'schools_all'        => 'All schools',
    'schools_trial'      => 'Schools on trial',
    'schools_paid'       => 'Paid schools',
    'schools_free'       => 'Free-plan schools',
    'schools_unverified' => 'Schools not yet verified',
    'leads_all'          => 'All leads',
    'leads_new'          => 'New leads (not contacted)',
];
$presets = [
    'holiday' => ['Happy holiday from Somahub', "Hi {name},\n\nWishing you and everyone at {school} a happy and restful holiday. Thank you for being part of Somahub.\n\nWarm regards,\nKelvin, Somahub", '', ''],
    'update'  => ['What is new on Somahub', "Hi {name},\n\nWe have made editing your website simpler: open your site, click anything and change it right there. Colours, fonts, photos and sections are all on the page.\n\nTake a look and tell us what you think.", 'Edit my site', 'https://somahub.top/dashboard/login.php'],
    'verify'  => ['Please verify {school}', "Hi {name},\n\nOne last step to keep {school}'s website online is verification. It only takes a few minutes.", 'Verify now', 'https://somahub.top/dashboard/verify.php'],
    'custom'  => ['', '', '', ''],
];

function bc_recipients(PDO $db, string $aud): array {
    $rows = [];
    if (str_starts_with($aud, 'schools_')) {
        $w = ['s.email IS NOT NULL', "s.email <> ''", "s.slug <> 'demo'"];
        if ($aud === 'schools_trial') $w[] = "s.status = 'trial'";
        if ($aud === 'schools_paid') $w[] = "s.plan = 'paid'";
        if ($aud === 'schools_free') $w[] = "s.plan = 'free'";
        if ($aud === 'schools_unverified') $w[] = "s.verification_status <> 'verified'";
        $where = implode(' AND ', $w);
        try {
            $rows = $db->query("SELECT s.email, s.name AS school, COALESCE(u.name, '') AS name FROM schools s LEFT JOIN users u ON u.school_id = s.id AND u.email = s.email WHERE $where")->fetchAll();
        } catch (Throwable $e) {
            $rows = $db->query("SELECT s.email, s.name AS school, '' AS name FROM schools s WHERE $where")->fetchAll();
        }
    } else {
        $w = ["email IS NOT NULL", "email <> ''"];
        if ($aud === 'leads_new') $w[] = "status = 'new'";
        $rows = $db->query("SELECT email, school_name AS school, contact_name AS name FROM leads WHERE " . implode(' AND ', $w))->fetchAll();
    }
    $unsub = array_flip($db->query("SELECT email FROM email_unsubscribes")->fetchAll(PDO::FETCH_COLUMN));
    $seen = []; $out = [];
    foreach ($rows as $r) {
        $e = strtolower(trim($r['email']));
        if (!filter_var($e, FILTER_VALIDATE_EMAIL) || isset($seen[$e]) || isset($unsub[$e])) continue;
        $seen[$e] = 1; $r['email'] = $e; $r['name'] = trim((string)$r['name']); $out[] = $r;
    }
    return $out;
}
function bc_fill(string $t, array $r): string {
    $first = $r['name'] !== '' ? explode(' ', $r['name'])[0] : 'there';
    return str_replace(['{name}', '{school}'], [$first, $r['school'] ?: 'your school'], $t);
}
function bc_html(string $msg, string $cta, string $link): string {
    $h = '<div>' . nl2br(htmlspecialchars($msg)) . '</div>';
    if ($cta && $link) $h .= '<div style="text-align:center;margin-top:26px;"><a href="' . htmlspecialchars($link) . '" style="display:inline-block;background:#F2A65A;color:#0A3A3E;font-weight:800;padding:13px 28px;border-radius:24px;text-decoration:none;font-size:15px;">' . htmlspecialchars($cta) . '</a></div>';
    return $h;
}
function bc_send(PDO $db, array $b, array $r): array {
    $url = 'https://somahub.top/unsubscribe.php?e=' . rawurlencode($r['email']) . '&t=' . unsub_token($db, $r['email']);
    $ok = send_somahub_email($r['email'], bc_fill($b['subject'], $r), bc_html(bc_fill($b['body'], $r), $b['cta_text'], bc_fill($b['cta_link'], $r)), 'hello@somahub.top', ['unsubscribe' => $url]);
    return [$ok, $ok ? null : 'Send failed (see error log)'];
}
$cap = max(1, (int)get_setting($db, 'broadcast_daily_cap', '250'));

// ---- AJAX: send the next small batch ---------------------------------------
if (isset($_GET['tick'])) {
    header('Content-Type: application/json');
    $id = (int)$_GET['tick'];
    $b = $db->prepare("SELECT * FROM broadcasts WHERE id = ?"); $b->execute([$id]); $b = $b->fetch();
    if (!$b) { echo json_encode(['error' => 'Not found']); exit; }
    $today = (int)$db->query("SELECT COUNT(*) FROM broadcast_recipients WHERE status='sent' AND sent_at >= CURDATE()")->fetchColumn();
    $room = max(0, $cap - $today);
    $take = min(8, $room);
    $q = $db->prepare("SELECT * FROM broadcast_recipients WHERE broadcast_id = ? AND status = 'queued' ORDER BY id LIMIT " . (int)$take);
    $q->execute([$id]);
    foreach ($take ? $q->fetchAll() : [] as $r) {
        [$ok, $err] = bc_send($db, $b, $r);
        $db->prepare("UPDATE broadcast_recipients SET status = ?, error = ?, sent_at = NOW() WHERE id = ?")->execute([$ok ? 'sent' : 'failed', $err, $r['id']]);
        usleep(150000);
    }
    $db->prepare("UPDATE broadcasts SET sent = (SELECT COUNT(*) FROM broadcast_recipients WHERE broadcast_id = ? AND status='sent'), failed = (SELECT COUNT(*) FROM broadcast_recipients WHERE broadcast_id = ? AND status='failed') WHERE id = ?")->execute([$id, $id, $id]);
    $left = (int)$db->query("SELECT COUNT(*) FROM broadcast_recipients WHERE broadcast_id = $id AND status='queued'")->fetchColumn();
    $b = $db->query("SELECT sent, failed, total FROM broadcasts WHERE id = $id")->fetch();
    echo json_encode(['sent' => (int)$b['sent'], 'failed' => (int)$b['failed'], 'total' => (int)$b['total'], 'left' => $left, 'capped' => $left > 0 && $room - $take <= 0]);
    exit;
}

$notice = ''; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    $f = ['subject' => trim($_POST['subject'] ?? ''), 'body' => trim($_POST['body'] ?? ''), 'cta_text' => trim($_POST['cta_text'] ?? ''), 'cta_link' => trim($_POST['cta_link'] ?? '')];
    $aud = isset($audiences[$_POST['audience'] ?? '']) ? $_POST['audience'] : 'schools_all';
    if ($act === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare("DELETE FROM broadcast_recipients WHERE broadcast_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM broadcasts WHERE id = ?")->execute([$id]);
        $notice = 'Broadcast deleted.';
    } elseif ($act === 'test') {
        $to = trim($_POST['test_to'] ?? '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) $error = 'Enter a valid test address.';
        elseif ($f['subject'] === '' || $f['body'] === '') $error = 'Write a subject and message first.';
        else {
            // Preview with a real recipient's details (first person in the chosen audience), sent only to you.
            $pool = bc_recipients($db, $aud);
            $sample = $pool[0] ?? ['email' => $to, 'name' => 'Mary Wanjiru', 'school' => 'Sample Academy'];
            $sample['email'] = $to;
            [$ok] = bc_send($db, $f, $sample);
            $notice = $ok ? "Test sent to $to, filled in as: " . ($sample['name'] ?: 'there') . ' / ' . $sample['school'] . '.' : 'Test failed. Check your SMTP/Brevo settings.';
        }
    } elseif ($act === 'start') {
        if ($f['subject'] === '' || $f['body'] === '') $error = 'Write a subject and message first.';
        else {
            $rec = bc_recipients($db, $aud);
            if (!$rec) $error = 'No recipients in that audience (or everyone has unsubscribed).';
            else {
                $db->prepare("INSERT INTO broadcasts (subject, body, cta_text, cta_link, audience, total) VALUES (?,?,?,?,?,?)")->execute([$f['subject'], $f['body'], $f['cta_text'], $f['cta_link'], $aud, count($rec)]);
                $id = (int)$db->lastInsertId();
                $ins = $db->prepare("INSERT INTO broadcast_recipients (broadcast_id, email, name, school) VALUES (?,?,?,?)");
                foreach ($rec as $r) $ins->execute([$id, $r['email'], $r['name'], $r['school']]);
                header('Location: broadcast.php?run=' . $id); exit;
            }
        }
    }
}
$cur = ['subject' => $_POST['subject'] ?? '', 'body' => $_POST['body'] ?? '', 'cta_text' => $_POST['cta_text'] ?? '', 'cta_link' => $_POST['cta_link'] ?? '', 'audience' => $_POST['audience'] ?? 'schools_all'];
$runId = (int)($_GET['run'] ?? 0);
$history = $db->query("SELECT * FROM broadcasts ORDER BY id DESC LIMIT 15")->fetchAll();
$counts = [];
foreach ($audiences as $k => $_) { try { $counts[$k] = count(bc_recipients($db, $k)); } catch (Throwable $e) { $counts[$k] = '?'; } }
$todaySent = (int)$db->query("SELECT COUNT(*) FROM broadcast_recipients WHERE status='sent' AND sent_at >= CURDATE()")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Broadcast — Somahub Admin</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .bc-grid{display:grid;grid-template-columns:1.4fr 1fr;gap:20px;align-items:start;} @media(max-width:860px){.bc-grid{grid-template-columns:1fr;}}
  textarea,input[type=text],input[type=email],select{width:100%;padding:10px 12px;border:1.5px solid var(--line);border-radius:8px;font-family:inherit;font-size:.9rem;box-sizing:border-box;margin-bottom:14px;}
  label{display:block;font-size:.8rem;font-weight:800;margin-bottom:6px;} textarea{min-height:190px;}
  .pre{background:#F7F2E7;border-radius:10px;padding:16px;font-size:.9rem;line-height:1.55;white-space:pre-wrap;}
  .bar{height:12px;border-radius:99px;background:#e6e1d0;overflow:hidden;margin:12px 0;} .bar i{display:block;height:100%;width:0;background:var(--teal);transition:width .3s;}
  .presets{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;} .presets button{border:1.5px solid var(--line);background:#fff;border-radius:99px;padding:6px 14px;font-weight:700;cursor:pointer;font-size:.8rem;}
  .ok{background:#E4F5EA;color:#1B4D3E;padding:10px 14px;border-radius:8px;margin-bottom:16px;} .err{background:#FBE8E4;color:#8C3B2E;padding:10px 14px;border-radius:8px;margin-bottom:16px;}
</style></head><body>
<?php include __DIR__ . '/_nav.php'; ?>
<main class="wrap">
  <div class="header-row"><h1>Broadcast</h1><span style="color:var(--muted);font-size:.85rem;">Sent today: <?= $todaySent ?> / <?= $cap ?> (daily cap, change with setting <code>broadcast_daily_cap</code>)</span></div>
  <?php if ($notice): ?><div class="ok"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($runId): $b = $db->prepare("SELECT * FROM broadcasts WHERE id = ?"); $b->execute([$runId]); $b = $b->fetch(); if ($b): ?>
  <div class="box">
    <h2 style="margin-top:0;">Sending: <?= htmlspecialchars($b['subject']) ?></h2>
    <div class="bar"><i id="bar"></i></div>
    <p id="st" style="margin:0;color:var(--muted);">Starting…</p>
    <p style="font-size:.8rem;color:var(--muted);">Keep this page open. It sends in small batches. If the daily cap is reached it pauses; open this page again tomorrow to continue.</p>
    <a class="btn secondary" href="broadcast.php">Back</a>
  </div>
  <script>
  (function(){ var id=<?= (int)$runId ?>, bar=document.getElementById('bar'), st=document.getElementById('st');
    function tick(){ fetch('broadcast.php?tick='+id,{method:'POST',credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
      if(d.error){ st.textContent=d.error; return; }
      var done=d.sent+d.failed; bar.style.width=(d.total?Math.round(done/d.total*100):100)+'%';
      st.textContent=d.sent+' sent, '+d.failed+' failed, '+d.left+' waiting of '+d.total;
      if(d.left===0){ st.textContent+=' . Done.'; return; }
      if(d.capped){ st.textContent+=' . Daily cap reached, paused. Continue tomorrow.'; return; }
      setTimeout(tick,300);
    }).catch(function(){ st.textContent='Connection problem. Reload this page to continue.'; }); }
    tick(); })();
  </script>
<?php endif; else: ?>
  <div class="bc-grid">
    <form method="POST" class="box" id="bcForm">
      <div class="presets"><?php foreach (['holiday' => 'Holiday greeting', 'update' => 'Product update', 'verify' => 'Verify reminder', 'custom' => 'Blank'] as $k => $l): ?><button type="button" onclick="preset('<?= $k ?>')"><?= $l ?></button><?php endforeach; ?></div>
      <label>Send to</label>
      <select name="audience"><?php foreach ($audiences as $k => $l): ?><option value="<?= $k ?>" <?= $cur['audience'] === $k ? 'selected' : '' ?>><?= $l ?> (<?= $counts[$k] ?>)</option><?php endforeach; ?></select>
      <label>Subject</label><input type="text" name="subject" id="f_subject" value="<?= htmlspecialchars($cur['subject']) ?>" maxlength="180">
      <label>Message <small style="font-weight:500;color:var(--muted)">use {name} and {school}</small></label><textarea name="body" id="f_body"><?= htmlspecialchars($cur['body']) ?></textarea>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;"><div><label>Button text (optional)</label><input type="text" name="cta_text" id="f_cta" value="<?= htmlspecialchars($cur['cta_text']) ?>"></div><div><label>Button link</label><input type="text" name="cta_link" id="f_link" value="<?= htmlspecialchars($cur['cta_link']) ?>"></div></div>
      <label>Send a test to</label><input type="email" name="test_to" placeholder="you@example.com">
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <button class="btn secondary" name="action" value="test" type="submit">Send test</button>
        <button class="btn" name="action" value="start" type="submit" onclick="return confirm('Send this to the selected audience now?')">Send broadcast</button>
      </div>
    </form>
    <div>
      <div class="box"><strong>Preview</strong><div class="pre" id="pv" style="margin-top:10px;">Your message appears here.</div><p style="font-size:.78rem;color:var(--muted);margin-bottom:0;">Every email carries an unsubscribe link, and anyone who unsubscribes is skipped next time. This protects your sender reputation with Brevo.</p></div>
    </div>
  </div>
  <h2 style="margin-top:30px;">Recent broadcasts</h2>
  <table><thead><tr><th>When</th><th>Subject</th><th>Audience</th><th>Sent</th><th>Failed</th><th></th></tr></thead>
  <?php foreach ($history as $h): ?><tr>
    <td data-label="When"><?= date('d M Y H:i', strtotime($h['created_at'])) ?></td>
    <td data-label="Subject"><?= htmlspecialchars($h['subject']) ?></td>
    <td data-label="Audience"><?= htmlspecialchars($audiences[$h['audience']] ?? $h['audience']) ?></td>
    <td data-label="Sent"><?= (int)$h['sent'] ?> / <?= (int)$h['total'] ?></td><td data-label="Failed"><?= (int)$h['failed'] ?></td>
    <td data-label=""><?php if ($h['sent'] + $h['failed'] < $h['total']): ?><a href="?run=<?= (int)$h['id'] ?>">Resume</a> · <?php endif; ?>
      <form method="POST" style="display:inline" onsubmit="return confirm('Delete this broadcast record?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$h['id'] ?>"><button style="border:none;background:none;color:#C0392B;cursor:pointer;font-weight:700;">Delete</button></form></td>
  </tr><?php endforeach; if (!$history): ?><tr><td colspan="6" style="text-align:center;color:var(--muted);">No broadcasts yet.</td></tr><?php endif; ?></table>
  <script>
  var P=<?= json_encode($presets) ?>;
  function preset(k){ var p=P[k]; document.getElementById('f_subject').value=p[0]; document.getElementById('f_body').value=p[1]; document.getElementById('f_cta').value=p[2]; document.getElementById('f_link').value=p[3]; pv(); }
  function pv(){ var b=document.getElementById('f_body').value.replace(/\{name\}/g,'Mary').replace(/\{school\}/g,'Sample Academy'); var c=document.getElementById('f_cta').value; document.getElementById('pv').textContent=(b||'Your message appears here.')+(c?'\n\n['+c+']':''); }
  ['f_body','f_cta'].forEach(function(i){ document.getElementById(i).addEventListener('input',pv); }); pv();
  </script>
<?php endif; ?>
</main></body></html>
