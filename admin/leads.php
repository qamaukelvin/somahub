<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lead_id'], $_POST['status'])) {
    $db->prepare("UPDATE leads SET status = ? WHERE id = ?")
       ->execute([$_POST['status'], (int)$_POST['lead_id']]);
}

$leads = $db->query("SELECT * FROM leads ORDER BY submitted_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Leads — Somahub Admin</title>
<?php include __DIR__ . '/_styles.php'; ?>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>Leads (<?= count($leads) ?>)</h1>
  <p style="color:#666;margin-bottom:24px;">Submissions from the homepage contact form.</p>

  <table>
    <tr><th>School</th><th>Contact</th><th>Phone</th><th>County</th><th>Message</th><th>Received</th><th>Status</th></tr>
    <?php foreach ($leads as $l): ?>
    <tr>
      <td><?= htmlspecialchars($l['school_name']) ?></td>
      <td><?= htmlspecialchars($l['contact_name']) ?><?= $l['email'] ? '<br><small>'.htmlspecialchars($l['email']).'</small>' : '' ?></td>
      <td><a href="tel:<?= htmlspecialchars($l['phone']) ?>"><?= htmlspecialchars($l['phone']) ?></a></td>
      <td><?= htmlspecialchars($l['county'] ?: '—') ?></td>
      <td style="max-width:220px;"><?= htmlspecialchars($l['message'] ?: '—') ?></td>
      <td><?= date('d M Y', strtotime($l['submitted_at'])) ?></td>
      <td>
        <form method="POST">
          <input type="hidden" name="lead_id" value="<?= $l['id'] ?>">
          <select name="status" onchange="this.form.submit()">
            <?php foreach (['new','contacted','converted','declined'] as $s): ?>
              <option value="<?= $s ?>" <?= $l['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$leads): ?>
    <tr><td colspan="7" style="text-align:center;color:#888;">No leads yet.</td></tr>
    <?php endif; ?>
  </table>
</main>
</body>
</html>
