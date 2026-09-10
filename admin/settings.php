<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
require_once __DIR__ . '/../includes/settings.php';
$db = get_db();

// type: 'text' (default), 'password' (masked, shows placeholder instead of
// value), or 'select' (with options)
$fields = [
    'Payments' => [
        'payment_till_number' => ['label' => 'Till Number'],
        'payment_pochi_number' => ['label' => 'Pochi la Biashara Number'],
        'payment_send_money_number' => ['label' => 'Send Money Number'],
        'payment_equity_paybill' => ['label' => 'Equity Paybill Number'],
        'payment_equity_account_number' => ['label' => 'Equity Account Number'],
        'payment_display_name' => ['label' => 'Name Shown on Payment (must match what schools see)'],
    ],
    'Email (SMTP)' => [
        'smtp_host' => ['label' => 'SMTP Host', 'placeholder' => 'e.g. mail.somahub.top — leave blank to use config/mail.php'],
        'smtp_port' => ['label' => 'SMTP Port', 'placeholder' => '465 or 587 — leave blank to use config/mail.php'],
        'smtp_username' => ['label' => 'SMTP Username', 'placeholder' => 'e.g. info@somahub.top — leave blank to use config/mail.php'],
        'smtp_password' => ['label' => 'SMTP Password', 'type' => 'password', 'placeholder' => 'Leave blank to keep using config/mail.php'],
        'smtp_encryption' => ['label' => 'SMTP Encryption', 'type' => 'select', 'options' => ['' => 'Use config/mail.php default', 'ssl' => 'SSL (port 465)', 'tls' => 'STARTTLS (port 587)']],
        'sending_email' => ['label' => 'Sending Email Address (From)', 'placeholder' => 'e.g. info@somahub.top — shown as the From address on every outgoing email'],
    ],
    'Notifications' => [
        'admin_notify_email' => ['label' => 'Admin Notification Email'],
        'whatsapp_outreach_number' => ['label' => 'WhatsApp Number (used in outreach links)'],
    ],
    'System' => [
        'cron_secret' => ['label' => 'Cron Secret Key (only needed if triggering cron via URL, not CLI)'],
    ],
];

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($fields as $group => $groupFields) {
        foreach ($groupFields as $key => $field) {
            // Password field: an empty submission means "don't change it",
            // not "clear it" — otherwise saving the form with the masked
            // blank field would wipe out a working password every time.
            if (($field['type'] ?? '') === 'password' && trim($_POST[$key] ?? '') === '') {
                continue;
            }
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
  input, select{width:100%;padding:9px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box;font-family:inherit;}
  .field-hint{font-size:0.76rem;color:#999;margin-top:4px;}
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
        <?php foreach ($groupFields as $key => $field):
          $type = $field['type'] ?? 'text';
          $value = $current[$key] ?? '';
        ?>
          <label><?= htmlspecialchars($field['label']) ?></label>
          <?php if ($type === 'select'): ?>
            <select name="<?= $key ?>">
              <?php foreach ($field['options'] as $optVal => $optLabel): ?>
                <option value="<?= htmlspecialchars($optVal) ?>" <?= $value === $optVal ? 'selected' : '' ?>><?= htmlspecialchars($optLabel) ?></option>
              <?php endforeach; ?>
            </select>
          <?php elseif ($type === 'password'): ?>
            <input type="password" name="<?= $key ?>" value="" placeholder="<?= htmlspecialchars($value ? '••••••••  (already set — leave blank to keep it)' : ($field['placeholder'] ?? '')) ?>">
          <?php else: ?>
            <input type="text" name="<?= $key ?>" value="<?= htmlspecialchars($value) ?>" placeholder="<?= htmlspecialchars($field['placeholder'] ?? '') ?>">
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
    <button type="submit" class="btn">Save All Settings</button>
  </form>

  <p style="font-size:0.8rem;color:#888;margin-top:20px;">
    Leave any SMTP field blank to keep using the values in <code>config/mail.php</code> on the server. Fill one in here to override it without touching that file.
  </p>
</main>
</body>
</html>
