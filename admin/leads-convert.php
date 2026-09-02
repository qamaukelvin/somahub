<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
require_once __DIR__ . '/../includes/content-presets.php';
$db = get_db();

$leadId = (int)($_GET['lead_id'] ?? $_POST['lead_id'] ?? 0);
$leadStmt = $db->prepare("SELECT * FROM leads WHERE id = ?");
$leadStmt->execute([$leadId]);
$lead = $leadStmt->fetch();

if (!$lead) { die('Lead not found.'); }

$themes = $db->query("SELECT * FROM themes WHERE is_active = 1 ORDER BY is_premium ASC, name ASC")->fetchAll();
$contentPresets = get_school_content_presets();
$created = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_school') {
    $schoolName = trim($_POST['school_name'] ?: $lead['school_name']);
    $themeId = (int)$_POST['theme_id'];
    $presetKey = $_POST['content_preset'] ?? 'blank';

    $baseSlug = strtolower(preg_replace('/[^a-z0-9]/', '', strtolower($schoolName)));
    $baseSlug = $baseSlug ?: 'school';
    $slug = $baseSlug;
    $i = 2;
    $slugCheck = $db->prepare("SELECT id FROM schools WHERE slug = ?");
    while (true) {
        $slugCheck->execute([$slug]);
        if (!$slugCheck->fetch()) break;
        $slug = $baseSlug . $i++;
    }

    // Login username: real email if the lead has one, otherwise the phone
    // number itself. Login already looks up by the `email` column, so a
    // phone number stored there works as a login identifier unchanged.
    $realEmail = trim($lead['email'] ?? '');
    $phoneDigits = preg_replace('/[^0-9]/', '', $lead['phone'] ?? '');
    $loginUsername = $realEmail ?: $phoneDigits;
    $tempPassword = bin2hex(random_bytes(4));

    $db->beginTransaction();
    try {
        $insertSchool = $db->prepare("
            INSERT INTO schools (name, slug, theme_id, plan, status, verification_status, county, phone, email, activate_trial_on_login)
            VALUES (?, ?, ?, 'free', 'trial', 'pending', ?, ?, ?, 1)
        ");
        $insertSchool->execute([$schoolName, $slug, $themeId, $lead['county'], $lead['phone'], $realEmail ?: null]);
        $schoolId = $db->lastInsertId();

        $insertUser = $db->prepare("
            INSERT INTO users (school_id, name, email, phone, password_hash, role, password_is_temp)
            VALUES (?, ?, ?, ?, ?, 'school_owner', 1)
        ");
        $insertUser->execute([$schoolId, $lead['contact_name'] ?: $schoolName, $loginUsername, $lead['phone'], password_hash($tempPassword, PASSWORD_DEFAULT)]);
        $userId = $db->lastInsertId();

        $presetContent = $contentPresets[$presetKey]['content'] ?? [];
        $defaultSectionKeys = ['hero','about','academics','admissions','faq','gallery','contact'];
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
                            $content[$field] = str_replace('{school}', $schoolName, $text);
                        }
                    }
                }

                // Contact section: prefill whatever we already know from the
                // lead itself, so the school doesn't have to re-type it.
                if ($key === 'contact') {
                    if (array_key_exists('phone', $content) && !empty($lead['phone'])) {
                        $content['phone'] = $lead['phone'];
                    }
                    if (array_key_exists('email', $content) && !empty($realEmail)) {
                        $content['email'] = $realEmail;
                    }
                    if (array_key_exists('address', $content) && !empty($lead['county'])) {
                        $content['address'] = $lead['county'] . ' County';
                    }
                }

                $insertSection->execute([$schoolId, $type['id'], $idx, json_encode($content)]);
            }
        }

        $db->prepare("UPDATE leads SET status = 'converted', converted_school_id = ? WHERE id = ?")->execute([$schoolId, $leadId]);
        $db->commit();

        require_once __DIR__ . '/../includes/notifications.php';
        create_notification($db, $schoolId, 'welcome',
            'Welcome to Somahub!',
            'Your site is set up. Log in to review and edit your content.',
            'sections.php');

        // Magic login link — the school taps this, no credentials to type.
        // Falls back to a real password only if this link expires unused.
        $magicToken = create_magic_login_token($db, $userId, 14);
        $magicLink = "https://somahub.top/dashboard/magic-login.php?token={$magicToken}";

        $created = [
            'school_name' => $schoolName,
            'slug' => $slug,
            'phone' => $lead['phone'],
            'magic_link' => $magicLink,
        ];
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Something went wrong: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Convert Lead</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .box{background:#fff;border-radius:8px;padding:24px;max-width:560px;}
  .creds{background:#F4F1E6;padding:14px 16px;border-radius:6px;font-family:monospace;font-size:0.85rem;margin:14px 0;}
  .next-btn{background:#0F5257;color:#fff;padding:10px 20px;border-radius:6px;font-weight:700;text-decoration:none;display:inline-block;margin-top:10px;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>Convert Lead: <?= htmlspecialchars($lead['school_name']) ?></h1>

  <?php if ($created): ?>
    <div class="box">
      <h3>✓ <?= htmlspecialchars($created['school_name']) ?> is set up</h3>
      <p>Site: <strong><?= htmlspecialchars($created['slug']) ?>.somahub.top</strong></p>
      <div class="creds">
        One-tap login link (valid 14 days, single use):<br>
        <?= htmlspecialchars($created['magic_link']) ?>
      </div>
      <p style="font-size:0.85rem;color:#666;">Now go to Outreach to send them the intro, then this login link.</p>
      <a href="outreach.php?school_name=<?= urlencode($created['school_name']) ?>&phone=<?= urlencode($created['phone']) ?>&magic_link=<?= urlencode($created['magic_link']) ?>&slug=<?= urlencode($created['slug']) ?>" class="next-btn">Go to Outreach →</a>
    </div>
  <?php else: ?>
    <div class="box">
      <?php if ($error): ?><p style="color:#8C3B2E;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
      <form method="POST">
        <input type="hidden" name="action" value="create_school">
        <input type="hidden" name="lead_id" value="<?= $leadId ?>">

        <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:6px;">School Name</label>
        <input type="text" name="school_name" value="<?= htmlspecialchars($lead['school_name']) ?>" style="width:100%;padding:9px;border:1px solid #ccc;border-radius:4px;margin-bottom:16px;" required>

        <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:6px;">Theme</label>
        <?php $selectedThemeId = $themes[0]['id'] ?? 0; include __DIR__ . '/_theme_picker.php'; ?>

        <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:6px;">Starting Content</label>
        <select name="content_preset" style="width:100%;padding:9px;border:1px solid #ccc;border-radius:4px;margin-bottom:16px;">
          <?php foreach ($contentPresets as $key => $p): ?>
            <option value="<?= $key ?>" <?= $key === 'primary_day' ? 'selected' : '' ?>><?= htmlspecialchars($p['label']) ?></option>
          <?php endforeach; ?>
        </select>

        <p style="font-size:0.82rem;color:#666;margin-bottom:16px;">Trial starts automatically the first time they log in — 60 days of full access, no action needed now.</p>

        <button type="submit" class="btn">Create School</button>
      </form>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
