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

if ($filter !== 'all') {
    $sql .= " HAVING computed_status = " . $db->quote($filter);
}
$sql .= " ORDER BY l.submitted_at DESC";

$leads = $db->query($sql)->fetchAll();

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
  .convert-btn{background:#0F5257;color:#fff;border:none;padding:5px 12px;border-radius:5px;font-size:0.78rem;font-weight:700;cursor:pointer;margin-top:4px;text-decoration:none;display:inline-block;}
  .delete-btn{background:none;border:none;color:#8C3B2E;font-size:0.75rem;cursor:pointer;text-decoration:underline;padding:0;margin-top:6px;display:inline-block;}
  .lead-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;}
  .lead-tabs a{padding:7px 14px;border-radius:20px;font-size:0.82rem;font-weight:600;text-decoration:none;background:#F4F1E6;color:#555;}
  .lead-tabs a.active{background:#0F5257;color:#fff;}
  .status-pill{display:inline-block;padding:2px 10px;border-radius:10px;font-size:0.75rem;font-weight:700;}
  .status-new{background:#F4F1E6;color:#555;}
  .status-contacted{background:#EAF1F0;color:#2E5C57;}
  .status-converted{background:#FBF0D1;color:#8C6D1F;}
  .status-onboarded{background:#E4F0E7;color:#1B4D3E;}
  .status-declined{background:#FBE8E4;color:#8C3B2E;}
  .success-msg{background:#E4F0E7;color:#1B4D3E;padding:10px 14px;border-radius:6px;margin-bottom:16px;font-size:0.88rem;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>Leads</h1>
  <p style="color:#666;margin-bottom:16px;">Submissions from the homepage contact form. "Onboarded" means the school was converted AND has actually logged in at least once — not just an account sitting unused.</p>

  <?php if (isset($_GET['deleted'])): ?><div class="success-msg">Lead deleted.</div><?php endif; ?>

  <div class="lead-tabs">
    <?php foreach ($tabs as $key => $label): ?>
      <a href="?filter=<?= $key ?>" class="<?= $filter === $key ? 'active' : '' ?>"><?= htmlspecialchars($label) ?></a>
    <?php endforeach; ?>
  </div>

  <table>
    <thead><tr><th>School</th><th>Contact</th><th>Phone</th><th>County</th><th>Message</th><th>Received</th><th>Status</th></tr></thead>
    <?php foreach ($leads as $l): ?>
    <tr>
      <td data-label="School">
        <?= htmlspecialchars($l['school_name']) ?>
        <?php if ($l['converted_school_id']): ?>
          <br><a href="school-edit.php?id=<?= $l['converted_school_id'] ?>" style="font-size:0.78rem;">Manage school →</a>
        <?php endif; ?>
      </td>
      <td data-label="Contact"><?= htmlspecialchars($l['contact_name']) ?><?= $l['email'] ? '<br><small>'.htmlspecialchars($l['email']).'</small>' : '' ?></td>
      <td data-label="Phone"><a href="tel:<?= htmlspecialchars($l['phone']) ?>"><?= htmlspecialchars($l['phone']) ?></a></td>
      <td data-label="County"><?= htmlspecialchars($l['county'] ?: '—') ?></td>
      <td data-label="Message" style="max-width:220px;"><?= htmlspecialchars($l['message'] ?: '—') ?></td>
      <td data-label="Received"><?= date('d M Y', strtotime($l['submitted_at'])) ?></td>
      <td data-label="Status">
        <span class="status-pill status-<?= $l['computed_status'] ?>"><?= ucfirst($l['computed_status']) ?></span>

        <?php if ($l['computed_status'] !== 'onboarded'): ?>
        <form method="POST" style="margin-top:6px;">
          <input type="hidden" name="lead_id" value="<?= $l['id'] ?>">
          <select name="status" onchange="this.form.submit()">
            <?php foreach (['new','contacted','converted','declined'] as $s): ?>
              <option value="<?= $s ?>" <?= $l['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <?php endif; ?>

        <?php if ($l['status'] !== 'converted'): ?>
          <a href="leads-convert.php?lead_id=<?= $l['id'] ?>" class="convert-btn">Convert to School</a>
        <?php endif; ?>

        <form method="POST" onsubmit="return confirm('Delete this lead? This can\'t be undone.')">
          <input type="hidden" name="action" value="delete_lead">
          <input type="hidden" name="lead_id" value="<?= $l['id'] ?>">
          <button type="submit" class="delete-btn">Delete Lead</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$leads): ?>
    <tr><td colspan="7" style="text-align:center;color:#888;">No leads in this view.</td></tr>
    <?php endif; ?>
  </table>
</main>
</body>
</html>
