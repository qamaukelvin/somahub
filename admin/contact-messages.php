<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message_id'], $_POST['status'])) {
    $db->prepare("UPDATE contact_messages SET status = ? WHERE id = ?")
       ->execute([$_POST['status'], (int)$_POST['message_id']]);
}

$messages = $db->query("SELECT * FROM contact_messages ORDER BY submitted_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Contact Messages — Somahub Admin</title>
<?php include __DIR__ . '/_styles.php'; ?>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>Contact Messages (<?= count($messages) ?>)</h1>
  <p style="color:#666;margin-bottom:24px;">General enquiries from the homepage contact form.</p>

  <table>
    <tr><th>Email</th><th>Phone</th><th>Subject</th><th>Message</th><th>Received</th><th>Status</th></tr>
    <?php foreach ($messages as $m): ?>
    <tr>
      <td data-label="Email"><a href="mailto:<?= htmlspecialchars($m['email']) ?>"><?= htmlspecialchars($m['email']) ?></a></td>
      <td data-label="Phone"><?= $m['phone'] ? '<a href="tel:'.htmlspecialchars($m['phone']).'">'.htmlspecialchars($m['phone']).'</a>' : '—' ?></td>
      <td data-label="Subject"><?= htmlspecialchars($m['subject']) ?></td>
      <td data-label="Message" style="max-width:220px;"><?= htmlspecialchars($m['message']) ?></td>
      <td data-label="Received"><?= date('d M Y', strtotime($m['submitted_at'])) ?></td>
      <td data-label="Status">
        <form method="POST">
          <input type="hidden" name="message_id" value="<?= $m['id'] ?>">
          <select name="status" onchange="this.form.submit()">
            <option value="new" <?= $m['status'] === 'new' ? 'selected' : '' ?>>New</option>
            <option value="read" <?= $m['status'] === 'read' ? 'selected' : '' ?>>Read</option>
            <option value="replied" <?= $m['status'] === 'replied' ? 'selected' : '' ?>>Replied</option>
          </select>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</main>
</body>
</html>
