<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
$db = get_db();

if (isset($_GET['download'])) {
    $id = (int)$_GET['download'];
    $stmt = $db->prepare("SELECT school_name, export_json FROM archived_schools WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        $filename = preg_replace('/[^a-z0-9]/i', '-', $row['school_name']) . '-archive.json';
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $row['export_json'];
        exit;
    }
}

$archives = $db->query("SELECT id, school_name, slug, county, email, archived_by, archived_at FROM archived_schools ORDER BY archived_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Archived Schools</title>
<?php include __DIR__ . '/_styles.php'; ?>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>Archived Schools (<?= count($archives) ?>)</h1>
  <p style="color:#666;margin-bottom:20px;">Full data snapshot taken automatically whenever a school is deleted — download for your own records or in case a school asks for their data back.</p>

  <table>
    <thead><tr><th>School</th><th>Subdomain</th><th>County</th><th>Deleted By</th><th>Deleted On</th><th></th></tr></thead>
    <?php foreach ($archives as $a): ?>
    <tr>
      <td data-label="School"><?= htmlspecialchars($a['school_name']) ?></td>
      <td data-label="Subdomain"><?= htmlspecialchars($a['slug']) ?></td>
      <td data-label="County"><?= htmlspecialchars($a['county'] ?: '—') ?></td>
      <td data-label="Deleted By"><?= htmlspecialchars($a['archived_by'] ?: '—') ?></td>
      <td data-label="Deleted On"><?= date('d M Y', strtotime($a['archived_at'])) ?></td>
      <td data-label=""><a href="archived-schools.php?download=<?= $a['id'] ?>">Download JSON</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$archives): ?><tr><td colspan="6" style="text-align:center;color:#888;">No archived schools yet.</td></tr><?php endif; ?>
  </table>
</main>
</body>
</html>
