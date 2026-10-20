<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
    if ($ids) {
        $db->prepare("DELETE FROM contact_messages WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")")->execute($ids);
    }
    header('Location: contact-messages.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message_id'], $_POST['status'])) {
    $db->prepare("UPDATE contact_messages SET status = ? WHERE id = ?")
       ->execute([$_POST['status'], (int)$_POST['message_id']]);
}

require_once __DIR__ . '/../includes/paginate.php';
[$page, $perPage, $offset] = page_info(30);
$totalMsgs = (int)$db->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();
$messages = $db->query("SELECT * FROM contact_messages ORDER BY submitted_at DESC LIMIT $perPage OFFSET $offset")->fetchAll();
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

  <form method="POST" id="delForm" onsubmit="return confirm('Delete the selected messages? This cannot be undone.')">
    <input type="hidden" name="action" value="delete">
    <div class="bulkbar"><label><input type="checkbox" onclick="document.querySelectorAll('.pick').forEach(function(c){c.checked=this.checked}.bind(this))"> Select all</label><button class="btn danger" type="submit">Delete selected</button></div>
  </form>
  <table>
    <thead><tr><th></th><th>Email</th><th>Phone</th><th>Subject</th><th>Message</th><th>Received</th><th>Status</th></tr></thead>
    <?php foreach ($messages as $m): ?>
    <tr>
      <td data-label=""><input type="checkbox" class="pick" name="ids[]" value="<?= (int)$m['id'] ?>" form="delForm"></td>
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
    <?= pager_html($totalMsgs, $perPage, $page) ?>
  </table>
</main>
</body>
</html>
