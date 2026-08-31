<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
require_once __DIR__ . '/../includes/settings.php';
$db = get_db();

$fields = [
    'Payments' => [
        'payment_till_number' => 'Till Number',
        'payment_pochi_number' => 'Pochi la Biashara Number',
        'payment_send_money_number' => 'Send Money Number',
        'payment_equity_paybill' => 'Equity Paybill Number',
        'payment_equity_account_number' => 'Equity Account Number',
        'payment_display_name' => 'Name Shown on Payment (must match what schools see)',
    ],
    'Notifications' => [
        'admin_notify_email' => 'Admin Notification Email',
        'whatsapp_outreach_number' => 'WhatsApp Number (used in outreach links)',
    ],
    'System' => [
        'cron_secret' => 'Cron Secret Key (only needed if triggering cron via URL, not CLI)',
    ],
];

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($fields as $group => $groupFields) {
        foreach ($groupFields as $key => $label) {
            set_setting($db, $key, trim($_POST[$key] ?? ''));
        }
    }
    $message = 'Settings saved.';
}

$current = get_all_settings($db);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .settings-box{background:#fff;border-radius:8px;padding:24px;max-width:560px;margin-bottom:20px;}
  .settings-box h3{margin-bottom:16px;color:#0F5257;}
  label{display:block;font-size:0.85rem;font-weight:600;margin-bottom:6px;margin-top:16px;}
  label:first-of-type{margin-top:0;}
  input{width:100%;padding:9px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box;font-family:inherit;}
  .success{background:#E4F0E7;color:#1B4D3E;padding:10px 14px;border-radius:6px;margin-bottom:16px;font-size:0.88rem;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>Settings</h1>
  <p style="color:#666;margin-bottom:20px;">Credentials and numbers used across the platform — change here instead of editing code.</p>

  <?php if ($message): ?><div class="success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

  <form method="POST">
    <?php foreach ($fields as $group => $groupFields): ?>
      <div class="settings-box">
        <h3><?= htmlspecialchars($group) ?></h3>
        <?php foreach ($groupFields as $key => $label): ?>
          <label><?= htmlspecialchars($label) ?></label>
          <input type="text" name="<?= $key ?>" value="<?= htmlspecialchars($current[$key] ?? '') ?>">
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
    <button type="submit" class="btn">Save All Settings</button>
  </form>

  <p style="font-size:0.8rem;color:#888;margin-top:20px;">
    Note: email/SMTP credentials still live in <code>config/mail.php</code> on the server, not here — that file is loaded before the database connects and holds your mailbox password, which is more sensitive than the values above.
  </p>
</main>
</body>
</html>
