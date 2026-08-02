<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
$db = get_db();

$schoolFilter = (int)($_GET['school_id'] ?? 0);

$sql = "
    SELECT cal.*, s.name AS school_name, u.name AS user_name, u.email AS user_email
    FROM content_audit_log cal
    JOIN schools s ON s.id = cal.school_id
    LEFT JOIN users u ON u.id = cal.user_id
";
$params = [];
if ($schoolFilter) {
    $sql .= " WHERE cal.school_id = ?";
    $params[] = $schoolFilter;
}
$sql .= " ORDER BY cal.changed_at DESC LIMIT 200";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$schools = $db->query("SELECT id, name FROM schools ORDER BY name")->fetchAll();

function summarize_diff($old, $new): string {
    if ($old === null) return 'Created new entry';
    if ($new === null) return 'Deleted entry';
    $oldArr = json_decode($old, true) ?: [];
    $newArr = json_decode($new, true) ?: [];
    $changes = [];
    $allKeys = array_unique(array_merge(array_keys($oldArr), array_keys($newArr)));
    foreach ($allKeys as $key) {
        $oldVal = $oldArr[$key] ?? '';
        $newVal = $newArr[$key] ?? '';
        if ($oldVal !== $newVal) {
            $oldShort = mb_strlen($oldVal) > 40 ? mb_substr($oldVal, 0, 40) . '…' : $oldVal;
            $newShort = mb_strlen($newVal) > 40 ? mb_substr($newVal, 0, 40) . '…' : $newVal;
            $changes[] = "<strong>$key</strong>: \"" . htmlspecialchars($oldShort) . "\" &rarr; \"" . htmlspecialchars($newShort) . "\"";
        }
    }
    return $changes ? implode('<br>', $changes) : 'No visible field changes';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Audit Log — Somahub Admin</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .log-row{background:#fff;border:1px solid #eee;border-radius:8px;padding:16px 18px;margin-bottom:10px;font-size:0.85rem;}
  .log-row.fee-change{border-left:4px solid #C0392B;background:#FFF8F7;}
  .log-meta{display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:8px;color:#666;font-size:0.78rem;}
  .log-badge{background:#eee;padding:2px 8px;border-radius:10px;font-size:0.7rem;text-transform:uppercase;font-weight:700;}
  .log-badge.fee{background:#FBE0DD;color:#C0392B;}
  .log-diff{color:#333;line-height:1.6;}
  select{padding:8px;border-radius:4px;border:1px solid #ccc;margin-bottom:20px;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>Content Audit Log</h1>
  <p style="color:#666;margin-bottom:20px;">Every content and fee change across all schools, most recent first. Fee changes are highlighted since payment details are the highest-risk field on the platform.</p>

  <form method="GET">
    <select name="school_id" onchange="this.form.submit()">
      <option value="0">All schools</option>
      <?php foreach ($schools as $s): ?>
        <option value="<?= $s['id'] ?>" <?= $schoolFilter == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>

  <?php if (!$logs): ?>
    <p style="color:#888;">No changes logged yet.</p>
  <?php endif; ?>

  <?php foreach ($logs as $log): ?>
    <div class="log-row <?= $log['entity_type'] === 'fee' ? 'fee-change' : '' ?>">
      <div class="log-meta">
        <span>
          <span class="log-badge <?= $log['entity_type'] === 'fee' ? 'fee' : '' ?>"><?= htmlspecialchars($log['entity_type']) ?> · <?= htmlspecialchars($log['action']) ?></span>
          &nbsp; <strong><?= htmlspecialchars($log['school_name']) ?></strong>
          &nbsp; by <?= $log['user_name'] ? htmlspecialchars($log['user_name']) . ' (' . htmlspecialchars($log['user_email']) . ')' : 'Unknown' ?>
        </span>
        <span><?= date('d M Y, g:i A', strtotime($log['changed_at'])) ?></span>
      </div>
      <div class="log-diff"><?= summarize_diff($log['old_value'], $log['new_value']) ?></div>
    </div>
  <?php endforeach; ?>
</main>
</body>
</html>
