<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/plan.php';
require_once __DIR__ . '/../includes/audit.php';
$user = require_school_login();
$db = get_db();

$schoolStmt = $db->prepare("SELECT plan, promo_ends_at FROM schools WHERE id = ?");
$schoolStmt->execute([$user['school_id']]);
if (is_premium_locked($schoolStmt->fetch())) {
    die('Fee publishing is a paid-plan feature, or your paid term has ended. <a href="https://wa.me/254707306888?text=' . urlencode('Hi Somahub, I would like to reactivate my paid plan.') . '" target="_blank">Message us</a> to reactivate.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $deleteId = (int)$_POST['delete_id'];
        $old = $db->prepare("SELECT * FROM fee_structures WHERE id=? AND school_id=?");
        $old->execute([$deleteId, $user['school_id']]);
        $oldRow = $old->fetch();

        $db->prepare("DELETE FROM fee_structures WHERE id=? AND school_id=?")
           ->execute([$deleteId, $user['school_id']]);

        if ($oldRow) {
            log_content_change($db, $user['school_id'], $user['id'], 'fee', $deleteId, 'delete', $oldRow, null);
        }
    } else {
        // Check if a matching fee row already exists, to log this as create vs update, and capture the old value
        $existingStmt = $db->prepare("SELECT * FROM fee_structures WHERE school_id=? AND grade=? AND term_label=?");
        $existingStmt->execute([$user['school_id'], trim($_POST['grade']), trim($_POST['term_label'])]);
        $existingRow = $existingStmt->fetch();

        $stmt = $db->prepare("
            INSERT INTO fee_structures (school_id, grade, term_label, amount, payment_details_json)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE amount = VALUES(amount), payment_details_json = VALUES(payment_details_json)
        ");
        $paymentDetails = json_encode([
            'paybill' => trim($_POST['paybill'] ?? ''),
            'account_note' => trim($_POST['account_note'] ?? ''),
        ]);
        $stmt->execute([
            $user['school_id'],
            trim($_POST['grade']),
            trim($_POST['term_label']),
            (float)$_POST['amount'],
            $paymentDetails,
        ]);

        // Re-fetch to get the actual row ID (needed whether this was an insert or update)
        $newStmt = $db->prepare("SELECT * FROM fee_structures WHERE school_id=? AND grade=? AND term_label=?");
        $newStmt->execute([$user['school_id'], trim($_POST['grade']), trim($_POST['term_label'])]);
        $newRow = $newStmt->fetch();

        if ($newRow) {
            $action = $existingRow ? 'update' : 'create';
            log_content_change($db, $user['school_id'], $user['id'], 'fee', $newRow['id'], $action, $existingRow ?: null, $newRow);
        }
    }
}

$fees = $db->prepare("SELECT * FROM fee_structures WHERE school_id=? ORDER BY grade, term_label");
$fees->execute([$user['school_id']]);
$fees = $fees->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fee Structure</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  input,textarea{padding:9px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box;}
  .form-row{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;align-items:end;}
  .form-row div{display:flex;flex-direction:column;}
  .form-row label{font-size:0.75rem;color:#666;margin-bottom:4px;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>Fee Structure</h1>
  <p class="sub">This shows on your website's Fees section for parents to see.</p>

  <form method="POST" class="form-row">
    <div><label>Grade</label><input type="text" name="grade" placeholder="e.g. Grade 4" required></div>
    <div><label>Term</label><input type="text" name="term_label" placeholder="Term 2, 2026" required></div>
    <div><label>Amount (KSh)</label><input type="text" name="amount" placeholder="15000" required></div>
    <div><label>Paybill No.</label><input type="text" name="paybill" placeholder="Optional"></div>
    <div><label>Account note</label><input type="text" name="account_note" placeholder="Use admission no."></div>
    <button type="submit" class="btn">Save</button>
  </form>

  <table>
    <tr><th>Grade</th><th>Term</th><th>Amount</th><th>Paybill</th><th></th></tr>
    <?php foreach ($fees as $f): $pd = json_decode($f['payment_details_json'], true); ?>
    <tr>
      <td><?= htmlspecialchars($f['grade']) ?></td>
      <td><?= htmlspecialchars($f['term_label']) ?></td>
      <td>KSh <?= number_format($f['amount'], 2) ?></td>
      <td><?= htmlspecialchars($pd['paybill'] ?? '—') ?></td>
      <td>
        <form method="POST" onsubmit="return confirm('Delete this fee entry?')">
          <input type="hidden" name="delete_id" value="<?= $f['id'] ?>">
          <button type="submit" style="border:none;background:none;color:#8C3B2E;cursor:pointer;">Delete</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</main>
</body>
</html>
