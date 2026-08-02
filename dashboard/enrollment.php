<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_school_login();
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['app_id'], $_POST['status'])) {
    $stmt = $db->prepare("UPDATE enrollment_applications SET status = ? WHERE id = ? AND school_id = ?");
    $stmt->execute([$_POST['status'], (int)$_POST['app_id'], $user['school_id']]);
}

$apps = $db->prepare("
    SELECT * FROM enrollment_applications
    WHERE school_id = ?
    ORDER BY submitted_at DESC
");
$apps->execute([$user['school_id']]);
$apps = $apps->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Enrollment Enquiries</title>
<?php include __DIR__ . '/_styles.php'; ?>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>Enrollment Enquiries</h1>
  <p class="sub">Applications submitted through your website's enrollment form.</p>

  <table>
    <tr>
      <th>Child</th><th>Grade</th><th>Parent</th><th>Phone</th><th>Submitted</th><th>Status</th>
    </tr>
    <?php foreach ($apps as $a): ?>
    <tr>
      <td><?= htmlspecialchars($a['child_name']) ?></td>
      <td><?= htmlspecialchars($a['grade_applying_for']) ?></td>
      <td><?= htmlspecialchars($a['parent_name']) ?></td>
      <td><a href="tel:<?= htmlspecialchars($a['parent_phone']) ?>"><?= htmlspecialchars($a['parent_phone']) ?></a></td>
      <td><?= date('d M Y', strtotime($a['submitted_at'])) ?></td>
      <td>
        <form method="POST" style="display:inline">
          <input type="hidden" name="app_id" value="<?= $a['id'] ?>">
          <select name="status" onchange="this.form.submit()" class="<?= $a['status'] === 'new' ? 'status-new' : '' ?>">
            <?php foreach (['new','contacted','enrolled','declined'] as $s): ?>
              <option value="<?= $s ?>" <?= $a['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$apps): ?>
    <tr><td colspan="6" style="text-align:center;color:#888;">No applications yet.</td></tr>
    <?php endif; ?>
  </table>
</main>
</body>
</html>
