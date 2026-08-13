<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
$db = get_db();

$pending = $db->query("
    SELECT s.*, u.name AS owner_name, u.email AS owner_email, u.id_number, u.id_document_path, u.id_verified_at
    FROM schools s
    LEFT JOIN users u ON u.school_id = s.id AND u.role = 'school_owner'
    WHERE s.verification_status = 'pending'
    ORDER BY s.id DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verifications</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .v-card{background:#fff;border-radius:8px;padding:18px 20px;margin-bottom:14px;box-shadow:0 1px 3px rgba(0,0,0,0.08);}
  .v-row{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;}
  .v-docs{font-size:0.85rem;margin-top:10px;}
  .v-docs a{color:#0F5257;font-weight:700;}
  .missing{color:#8C3B2E;}
  .present{color:#1B4D3E;}
  .v-actions{margin-top:12px;}
  .v-actions a{background:#0F5257;color:#fff;padding:7px 16px;border-radius:5px;font-size:0.82rem;font-weight:700;text-decoration:none;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>Verifications Queue (<?= count($pending) ?>)</h1>
  <p style="color:#666;font-size:0.9rem;">Schools here are self-signed-up and building their sites, but not yet public. Review documents and approve/reject in each school's edit page.</p>

  <?php foreach ($pending as $s): ?>
    <div class="v-card">
      <div class="v-row">
        <div>
          <strong><?= htmlspecialchars($s['name']) ?></strong> — <?= htmlspecialchars($s['slug']) ?>.somahub.top<br>
          <small style="color:#888;"><?= htmlspecialchars($s['owner_name'] ?? '—') ?> · <?= htmlspecialchars($s['owner_email'] ?? '—') ?></small>
        </div>
        <div class="v-actions">
          <a href="school-edit.php?id=<?= $s['id'] ?>">Review & Decide</a>
        </div>
      </div>
      <div class="v-docs">
        Agreement:
        <?php if (!empty($s['signed_agreement_path'])): ?>
          <span class="present">✓ Uploaded</span> — <a href="../<?= htmlspecialchars($s['signed_agreement_path']) ?>" target="_blank">View</a>
        <?php else: ?>
          <span class="missing">Not yet uploaded</span>
        <?php endif; ?>
        &nbsp;·&nbsp;
        ID:
        <?php if (!empty($s['id_document_path'])): ?>
          <span class="present">✓ Uploaded</span> (<?= htmlspecialchars($s['id_number'] ?? '') ?>) — <a href="../<?= htmlspecialchars($s['id_document_path']) ?>" target="_blank">View</a>
        <?php else: ?>
          <span class="missing">Not yet uploaded</span>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$pending): ?><p style="color:#888;">Nothing awaiting verification right now.</p><?php endif; ?>
</main>
</body>
</html>
