<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
require_once __DIR__ . '/../includes/content-presets.php';
$db = get_db();

$converted = null; // holds newly created school info to show a WhatsApp claim-message link

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lead_id'], $_POST['status'])) {
    $db->prepare("UPDATE leads SET status = ? WHERE id = ?")
       ->execute([$_POST['status'], (int)$_POST['lead_id']]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'convert_to_school') {
    $leadId = (int)$_POST['lead_id'];
    $leadStmt = $db->prepare("SELECT * FROM leads WHERE id = ?");
    $leadStmt->execute([$leadId]);
    $lead = $leadStmt->fetch();

    if ($lead) {
        // Build a unique subdomain slug from the school name
        $baseSlug = strtolower(preg_replace('/[^a-z0-9]/', '', strtolower($lead['school_name'])));
        $baseSlug = $baseSlug ?: 'school';
        $slug = $baseSlug;
        $i = 2;
        $slugCheck = $db->prepare("SELECT id FROM schools WHERE slug = ?");
        while (true) {
            $slugCheck->execute([$slug]);
            if (!$slugCheck->fetch()) break;
            $slug = $baseSlug . $i++;
        }

        // Most leads only have a phone — logins require an email, so we
        // generate a clearly-fake placeholder the school replaces with
        // their real one in Account Settings once they claim the site.
        // (dashboard/account.php allows editing when the email matches
        // this @leads.somahub.top pattern — see the note there.)
        $realEmail = trim($lead['email'] ?? '');
        $ownerEmail = $realEmail ?: "{$slug}@leads.somahub.top";
        $tempPassword = bin2hex(random_bytes(4));

        $defaultThemeId = $db->query("SELECT id FROM themes WHERE is_active=1 AND is_premium=0 ORDER BY name LIMIT 1")->fetchColumn();

        $db->beginTransaction();
        try {
            $insertSchool = $db->prepare("
                INSERT INTO schools (name, slug, theme_id, plan, status, verification_status, county, phone, email)
                VALUES (?, ?, ?, 'free', 'trial', 'pending', ?, ?, ?)
            ");
            $insertSchool->execute([$lead['school_name'], $slug, $defaultThemeId, $lead['county'], $lead['phone'], $realEmail ?: null]);
            $schoolId = $db->lastInsertId();

            $insertUser = $db->prepare("
                INSERT INTO users (school_id, name, email, phone, password_hash, role)
                VALUES (?, ?, ?, ?, ?, 'school_owner')
            ");
            $insertUser->execute([$schoolId, $lead['contact_name'] ?: $lead['school_name'], $ownerEmail, $lead['phone'], password_hash($tempPassword, PASSWORD_DEFAULT)]);

            // Seed generic starter content — same presets used at self-serve signup
            $presetContent = get_school_content_presets()['primary_day']['content'] ?? [];
            $defaultSectionKeys = ['hero','about','academics','admissions','gallery','contact'];
            $typeStmt = $db->prepare("SELECT id, schema_json FROM section_types WHERE key_name = ?");
            $insertSection = $db->prepare("
                INSERT INTO site_sections (school_id, section_type_id, position, is_visible, content_json)
                VALUES (?, ?, ?, 1, ?)
            ");
            foreach ($defaultSectionKeys as $idx => $key) {
                $typeStmt->execute([$key]);
                $type = $typeStmt->fetch();
                if ($type) {
                    $schema = json_decode($type['schema_json'], true);
                    $content = array_fill_keys(array_keys($schema), '');
                    if (!empty($presetContent[$key])) {
                        foreach ($presetContent[$key] as $field => $text) {
                            if (array_key_exists($field, $content)) {
                                $content[$field] = str_replace('{school}', $lead['school_name'], $text);
                            }
                        }
                    }
                    $insertSection->execute([$schoolId, $type['id'], $idx, json_encode($content)]);
                }
            }

            $db->prepare("UPDATE leads SET status = 'converted' WHERE id = ?")->execute([$leadId]);
            $db->commit();

            $converted = [
                'school_name' => $lead['school_name'],
                'slug' => $slug,
                'phone' => $lead['phone'],
                'owner_email' => $ownerEmail,
                'temp_password' => $tempPassword,
                'placeholder_email' => !$realEmail,
            ];
        } catch (Exception $e) {
            $db->rollBack();
        }
    }
}

$leads = $db->query("SELECT * FROM leads ORDER BY submitted_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Leads — Somahub Admin</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .convert-result{background:#E4F0E7;border:1px solid #1B4D3E;border-radius:8px;padding:18px 20px;margin-bottom:20px;}
  .convert-result h3{color:#1B4D3E;margin-bottom:10px;}
  .creds{background:#fff;padding:12px 14px;border-radius:6px;font-family:monospace;font-size:0.85rem;margin:10px 0;}
  .wa-link{display:inline-block;background:#25D366;color:#fff;padding:9px 18px;border-radius:6px;font-weight:700;text-decoration:none;font-size:0.85rem;margin-top:6px;}
  .convert-btn{background:#0F5257;color:#fff;border:none;padding:5px 12px;border-radius:5px;font-size:0.78rem;font-weight:700;cursor:pointer;margin-top:4px;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>Leads (<?= count($leads) ?>)</h1>
  <p style="color:#666;margin-bottom:24px;">Submissions from the homepage contact form.</p>

  <?php if ($converted): ?>
    <div class="convert-result">
      <h3>✓ <?= htmlspecialchars($converted['school_name']) ?> is set up</h3>
      <p>Site: <strong><?= htmlspecialchars($converted['slug']) ?>.somahub.top</strong></p>
      <div class="creds">
        Login: <?= htmlspecialchars($converted['owner_email']) ?><br>
        Temp password: <?= htmlspecialchars($converted['temp_password']) ?>
      </div>
      <?php if ($converted['placeholder_email']): ?>
        <p style="font-size:0.82rem;color:#8C6D1F;">⚠️ No real email on file — used a placeholder. They'll need to update it in Account Settings once logged in.</p>
      <?php endif; ?>
      <?php
        $digits = preg_replace('/[^0-9]/', '', $converted['phone']);
        if (str_starts_with($digits, '0')) $digits = '254' . substr($digits, 1);
        $waMsg = "Hi! We've built a free website for {$converted['school_name']} — take a look: https://{$converted['slug']}.somahub.top\n\nLog in to edit it and make it yours:\nLogin: {$converted['owner_email']}\nPassword: {$converted['temp_password']}\n\nOnce you're in, please add your real email and any missing details under Account Settings. Let us know if you have any questions!";
      ?>
      <a href="https://wa.me/<?= $digits ?>?text=<?= rawurlencode($waMsg) ?>" target="_blank" class="wa-link">Send Claim Message on WhatsApp →</a>
    </div>
  <?php endif; ?>

  <table>
    <tr><th>School</th><th>Contact</th><th>Phone</th><th>County</th><th>Message</th><th>Received</th><th>Status</th></tr>
    <?php foreach ($leads as $l): ?>
    <tr>
      <td data-label="School"><?= htmlspecialchars($l['school_name']) ?></td>
      <td data-label="Contact"><?= htmlspecialchars($l['contact_name']) ?><?= $l['email'] ? '<br><small>'.htmlspecialchars($l['email']).'</small>' : '' ?></td>
      <td data-label="Phone"><a href="tel:<?= htmlspecialchars($l['phone']) ?>"><?= htmlspecialchars($l['phone']) ?></a></td>
      <td data-label="County"><?= htmlspecialchars($l['county'] ?: '—') ?></td>
      <td data-label="Message" style="max-width:220px;"><?= htmlspecialchars($l['message'] ?: '—') ?></td>
      <td data-label="Received"><?= date('d M Y', strtotime($l['submitted_at'])) ?></td>
      <td data-label="Status">
        <form method="POST">
          <input type="hidden" name="lead_id" value="<?= $l['id'] ?>">
          <select name="status" onchange="this.form.submit()">
            <?php foreach (['new','contacted','converted','declined'] as $s): ?>
              <option value="<?= $s ?>" <?= $l['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <?php if ($l['status'] !== 'converted'): ?>
          <form method="POST" onsubmit="return confirm('Create a real school account + website for <?= htmlspecialchars(addslashes($l['school_name'])) ?> now?')">
            <input type="hidden" name="action" value="convert_to_school">
            <input type="hidden" name="lead_id" value="<?= $l['id'] ?>">
            <button type="submit" class="convert-btn">Convert to School</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$leads): ?>
    <tr><td colspan="7" style="text-align:center;color:#888;">No leads yet.</td></tr>
    <?php endif; ?>
  </table>
</main>
</body>
</html>
