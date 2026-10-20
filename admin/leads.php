<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['lead_id'], $_POST['status']) && ($_POST['action'] ?? '') !== 'delete_lead') {
        $db->prepare("UPDATE leads SET status = ? WHERE id = ?")
           ->execute([$_POST['status'], (int)$_POST['lead_id']]);
    } elseif (($_POST['action'] ?? '') === 'delete_lead') {
        $db->prepare("DELETE FROM leads WHERE id = ?")->execute([(int)$_POST['lead_id']]);
        header('Location: leads.php?deleted=1' . (isset($_GET['filter']) ? '&filter=' . urlencode($_GET['filter']) : ''));
        exit;
    }
}

// "Onboarded" isn't a stored status — it's computed: the lead was converted
// AND the resulting school has actually logged in at least once. This keeps
// it honest rather than just another manual label anyone could set.
$filter = $_GET['filter'] ?? 'all';

$sql = "
    SELECT l.*, s.first_login_at, s.slug AS school_slug,
        CASE
            WHEN l.status = 'converted' AND s.first_login_at IS NOT NULL THEN 'onboarded'
            ELSE l.status
        END AS computed_status
    FROM leads l
    LEFT JOIN schools s ON s.id = l.converted_school_id
";
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $like = $db->quote('%' . $q . '%');
    $sql .= " WHERE (l.school_name LIKE $like OR l.contact_name LIKE $like OR l.email LIKE $like OR l.phone LIKE $like OR l.county LIKE $like)";
}

if ($filter !== 'all') {
    $sql .= " HAVING computed_status = " . $db->quote($filter);
}
$sql .= " ORDER BY l.submitted_at DESC";

require_once __DIR__ . '/../includes/paginate.php';
$leads = $db->query($sql)->fetchAll();
[$page, $perPage, $offset] = page_info(40);
$totalLeads = count($leads);
$leads = array_slice($leads, $offset, $perPage);

$counts = $db->query("
    SELECT
        SUM(CASE WHEN l.status = 'new' THEN 1 ELSE 0 END) AS c_new,
        SUM(CASE WHEN l.status = 'contacted' THEN 1 ELSE 0 END) AS c_contacted,
        SUM(CASE WHEN l.status = 'converted' AND s.first_login_at IS NULL THEN 1 ELSE 0 END) AS c_converted,
        SUM(CASE WHEN l.status = 'converted' AND s.first_login_at IS NOT NULL THEN 1 ELSE 0 END) AS c_onboarded,
        SUM(CASE WHEN l.status = 'declined' THEN 1 ELSE 0 END) AS c_declined,
        COUNT(*) AS c_all
    FROM leads l LEFT JOIN schools s ON s.id = l.converted_school_id
")->fetch();

$tabs = [
    'all' => 'All (' . $counts['c_all'] . ')',
    'new' => 'New (' . $counts['c_new'] . ')',
    'contacted' => 'Contacted (' . $counts['c_contacted'] . ')',
    'converted' => 'Converted (' . $counts['c_converted'] . ')',
    'onboarded' => 'Onboarded (' . $counts['c_onboarded'] . ')',
    'declined' => 'Declined (' . $counts['c_declined'] . ')',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Leads — Somahub Admin</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .lead-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 16px;}
  .lead-tabs a{padding:8px 16px;border-radius:99px;font-size:.82rem;font-weight:800;text-decoration:none;background:#fff;color:var(--muted);border:1.5px solid var(--line);}
  .lead-tabs a.active{background:var(--teal);color:#fff;border-color:var(--teal);}
  .searchrow{display:flex;gap:10px;margin-bottom:18px;} .searchrow input{flex:1;padding:11px 14px;border:1.5px solid var(--line);border-radius:99px;font:inherit;background:#fff;}
  .lead{background:#fff;border:1px solid var(--line);border-radius:16px;padding:18px 20px;margin-bottom:14px;box-shadow:0 1px 4px rgba(0,0,0,.04);display:grid;grid-template-columns:48px 1fr auto;gap:16px;align-items:start;}
  .lead .av{width:48px;height:48px;border-radius:14px;background:#E3F1F2;color:var(--teal);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.15rem;}
  .lead h3{margin:0 0 2px;font-size:1.05rem;color:var(--teal-deep);} .lead .meta{font-size:.84rem;color:var(--muted);line-height:1.6;}
  .lead .meta a{color:var(--teal);font-weight:700;text-decoration:none;} .lead .msg{margin-top:8px;background:var(--sand);border-radius:10px;padding:10px 12px;font-size:.86rem;color:#3a3a34;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;}
  .lead .acts{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;align-items:center;}
  .chip{display:inline-block;padding:7px 14px;border-radius:99px;font-size:.78rem;font-weight:800;text-decoration:none;border:1.5px solid var(--teal);color:var(--teal);background:#fff;cursor:pointer;}
  .chip.fill{background:var(--teal);color:#fff;} .chip.wa{border-color:#25D366;color:#128C4A;} .chip.del{border-color:#F0C4BE;color:#C0392B;}
  .status-pill{display:inline-block;padding:4px 12px;border-radius:99px;font-size:.74rem;font-weight:800;}
  .status-new{background:#FFF1DC;color:#8A5A12;} .status-contacted{background:#E3F1F2;color:#2E5C57;} .status-converted{background:#FBF0D1;color:#8C6D1F;} .status-onboarded{background:#DCEFE1;color:#1B4D3E;} .status-declined{background:#FBE8E4;color:#8C3B2E;}
  .success-msg{background:#E4F0E7;color:#1B4D3E;padding:10px 14px;border-radius:8px;margin-bottom:16px;font-size:.88rem;}
  @media(max-width:640px){.lead{grid-template-columns:1fr;} .lead .av{display:none;}}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>
<main class="wrap">
  <div class="header-row"><h1>Leads</h1><a class="btn secondary" href="broadcast.php">Broadcast to leads</a></div>
  <p style="color:var(--muted);margin:-14px 0 18px;font-size:.88rem;">Schools that asked about Somahub. "Onboarded" means converted and logged in at least once.</p>
  <?php if (isset($_GET['deleted'])): ?><div class="success-msg">Lead deleted.</div><?php endif; ?>

  <div class="lead-tabs"><?php foreach ($tabs as $key => $label): ?><a href="?filter=<?= $key ?>&q=<?= urlencode($q) ?>" class="<?= $filter === $key ? 'active' : '' ?>"><?= htmlspecialchars($label) ?></a><?php endforeach; ?></div>
  <form method="GET" class="searchrow"><input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>"><input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search school, contact, email, phone or county"><button class="btn" type="submit">Search</button><?php if ($q): ?><a class="btn secondary" href="?filter=<?= htmlspecialchars($filter) ?>">Clear</a><?php endif; ?></form>

  <?php foreach ($leads as $l):
    $ini = strtoupper(mb_substr(trim($l['school_name']) ?: '?', 0, 1));
    $days = (int)floor((time() - strtotime($l['submitted_at'])) / 86400);
    $ago = $days <= 0 ? 'today' : ($days == 1 ? 'yesterday' : $days . ' days ago');
    $out = 'outreach.php?' . http_build_query(['school_name' => $l['school_name'], 'name' => $l['contact_name'], 'email' => $l['email'], 'phone' => $l['phone']]);
    $digits = preg_replace('/[^0-9]/', '', (string)$l['phone']); if (str_starts_with($digits, '0')) $digits = '254' . substr($digits, 1);
  ?>
  <div class="lead">
    <div class="av"><?= htmlspecialchars($ini) ?></div>
    <div>
      <h3><?= htmlspecialchars($l['school_name']) ?></h3>
      <div class="meta">
        <?= htmlspecialchars($l['contact_name']) ?><?= $l['county'] ? ' · ' . htmlspecialchars($l['county']) : '' ?> · received <?= $ago ?><br>
        <?php if ($l['email']): ?><a href="mailto:<?= htmlspecialchars($l['email']) ?>"><?= htmlspecialchars($l['email']) ?></a><?php endif; ?>
        <?php if ($l['phone']): ?> · <a href="tel:<?= htmlspecialchars($l['phone']) ?>"><?= htmlspecialchars($l['phone']) ?></a><?php endif; ?>
      </div>
      <?php if (!empty($l['message'])): ?><div class="msg"><?= htmlspecialchars($l['message']) ?></div><?php endif; ?>
      <div class="acts">
        <?php if ($l['email']): ?><a class="chip fill" href="<?= htmlspecialchars($out) ?>">Email</a><?php endif; ?>
        <?php if ($digits): ?><a class="chip wa" href="<?= htmlspecialchars($out) ?>&amp;channel=wa">WhatsApp</a><?php endif; ?>
        <?php if ($l['status'] !== 'converted'): ?><a class="chip" href="leads-convert.php?lead_id=<?= (int)$l['id'] ?>">Convert to school</a>
        <?php elseif ($l['converted_school_id']): ?><a class="chip" href="school-edit.php?id=<?= (int)$l['converted_school_id'] ?>">Manage school</a><?php endif; ?>
        <form method="POST" onsubmit="return confirm('Delete this lead? This cannot be undone.')" style="display:inline"><input type="hidden" name="action" value="delete_lead"><input type="hidden" name="lead_id" value="<?= (int)$l['id'] ?>"><button class="chip del" type="submit">Delete</button></form>
      </div>
    </div>
    <div style="text-align:right;">
      <span class="status-pill status-<?= $l['computed_status'] ?>"><?= ucfirst($l['computed_status']) ?></span>
      <?php if ($l['computed_status'] !== 'onboarded'): ?>
      <form method="POST" style="margin-top:8px;"><input type="hidden" name="lead_id" value="<?= (int)$l['id'] ?>">
        <select name="status" onchange="this.form.submit()" style="padding:6px 10px;border:1.5px solid var(--line);font:inherit;font-size:.8rem;">
          <?php foreach (['new','contacted','converted','declined'] as $st): ?><option value="<?= $st ?>" <?= $l['status'] === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option><?php endforeach; ?>
        </select></form>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (!$leads): ?><div class="box" style="text-align:center;color:var(--muted);">No leads in this view.</div><?php endif; ?>
  <?= pager_html($totalLeads, $perPage, $page) ?>
</main>
</body>
</html>
