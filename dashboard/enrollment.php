<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_school_login();
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['app_id'], $_POST['status'])) {
    $stmt = $db->prepare("UPDATE enrollment_applications SET status = ? WHERE id = ? AND school_id = ?");
    $stmt->execute([$_POST['status'], (int)$_POST['app_id'], $user['school_id']]);
}

require_once __DIR__ . '/../includes/paginate.php';

// CSV export of every application (for Excel / printing)
if (($_GET['export'] ?? '') === 'csv') {
    $all = $db->prepare("SELECT * FROM enrollment_applications WHERE school_id = ? ORDER BY submitted_at DESC");
    $all->execute([$user['school_id']]);
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="enrollment-applications-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Submitted', 'Child', 'Date of birth', 'Grade', 'Previous school', 'Parent', 'Phone', 'Email', 'Status']);
    foreach ($all as $a) {
        fputcsv($out, [$a['submitted_at'], $a['child_name'], $a['date_of_birth'], $a['grade_applying_for'], $a['previous_school'], $a['parent_name'], $a['parent_phone'], $a['parent_email'], $a['status'] ?? '']);
    }
    exit;
}

[$page, $perPage, $offset] = page_info(25);
$totalApps = $db->prepare("SELECT COUNT(*) FROM enrollment_applications WHERE school_id = ?");
$totalApps->execute([$user['school_id']]);
$totalApps = (int)$totalApps->fetchColumn();

$apps = $db->prepare("
    SELECT * FROM enrollment_applications
    WHERE school_id = ?
    ORDER BY submitted_at DESC
    LIMIT $perPage OFFSET $offset
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
    <thead><tr>
      <th>Child</th><th>Grade</th><th>Parent</th><th>Phone</th><th>Submitted</th><th>Status</th>
    </tr></thead>
    <?php foreach ($apps as $a): ?>
    <tr>
      <td data-label="Child"><?= htmlspecialchars($a['child_name']) ?></td>
      <td data-label="Grade"><?= htmlspecialchars($a['grade_applying_for']) ?></td>
      <td data-label="Parent"><?= htmlspecialchars($a['parent_name']) ?></td>
      <td data-label="Phone"><a href="tel:<?= htmlspecialchars($a['parent_phone']) ?>"><?= htmlspecialchars($a['parent_phone']) ?></a></td>
      <td data-label="Submitted"><?= date('d M Y', strtotime($a['submitted_at'])) ?></td>
      <td data-label="Status">
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
  <?= pager_html($totalApps, $perPage, $page) ?>
  <?php if ($totalApps): ?><p style="text-align:center;"><a href="?export=csv" style="color:#0F5257;font-weight:700;">&darr; Download all as CSV</a></p><?php endif; ?>
</main>
</body>
</html>
