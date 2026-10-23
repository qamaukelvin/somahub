<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/app_log.php';
require_platform_admin();
require_once __DIR__ . '/../includes/content-presets.php';
$db = get_db();

$leadId = (int)($_GET['lead_id'] ?? $_POST['lead_id'] ?? 0);
$leadStmt = $db->prepare("SELECT * FROM leads WHERE id = ?");
$leadStmt->execute([$leadId]);
$lead = $leadStmt->fetch();

if (!$lead) { die('Lead not found.'); }

require_once __DIR__ . '/../includes/appearance.php';
require_once __DIR__ . '/../includes/blog.php';
$palettes = get_active_palettes($db);
$contentPresets = get_school_content_presets();
$created = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_school') {
    $schoolName = trim($_POST['school_name'] ?: $lead['school_name']);
    $colorMode = ($_POST['color_mode'] ?? 'preset') === 'custom' ? 'custom' : 'preset';
    $paletteId = $colorMode === 'preset' ? (int)$_POST['palette_id'] : null;
    $primaryOverride = $colorMode === 'custom' ? (trim($_POST['primary_override'] ?? '') ?: null) : null;
    $secondaryOverride = $colorMode === 'custom' ? (trim($_POST['secondary_override'] ?? '') ?: null) : null;
    $accentOverride = $colorMode === 'custom' ? (trim($_POST['accent_override'] ?? '') ?: null) : null;
    $bgOverride = $colorMode === 'custom' ? (trim($_POST['bg_override'] ?? '') ?: null) : null;
    $presetKey = $_POST['content_preset'] ?? 'primary_day';

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
            INSERT INTO schools (name, slug, palette_id, color_mode, primary_override, secondary_override, accent_override, bg_override, plan, status, verification_status, county, phone, email, activate_trial_on_login)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'promo_paid', 'trial', 'pending', ?, ?, ?, 1)
        ");
        $insertSchool->execute([$schoolName, $slug, $paletteId, $colorMode, $primaryOverride, $secondaryOverride, $accentOverride, $bgOverride, $lead['county'], $lead['phone'], $realEmail ?: null]);
        $schoolId = $db->lastInsertId();

        $insertUser = $db->prepare("
            INSERT INTO users (school_id, name, email, phone, password_hash, role, password_is_temp)
            VALUES (?, ?, ?, ?, ?, 'school_owner', 1)
        ");
        $insertUser->execute([$schoolId, $lead['contact_name'] ?: $schoolName, $loginUsername, $lead['phone'], password_hash($tempPassword, PASSWORD_DEFAULT)]);
        $userId = $db->lastInsertId();

        require_once __DIR__ . '/../includes/school-provision.php';
        seed_starter_sections($db, (int)$schoolId, $schoolName, $presetKey, [
            'county' => $lead['county'] ?? '', 'phone' => $lead['phone'] ?? '', 'email' => $realEmail,
        ]);

        $db->prepare("UPDATE leads SET status = 'converted', converted_school_id = ? WHERE id = ?")->execute([$schoolId, $leadId]);
        $db->commit();

        // Everything below runs AFTER commit — the school is already safely
        // saved, so nothing here may call $db->rollBack() (there's nothing
        // left to roll back, and calling it anyway throws a fatal "no active
        // transaction" error that would otherwise silently kill this page
        // with the school created but no confirmation screen ever shown).

        try {
            generate_school_joined_post($db, [
                'id' => $schoolId,
                'name' => $schoolName,
                'slug' => $slug,
                'county' => $lead['county'] ?? null,
            ]);
            $totalSchools = (int)$db->query("SELECT COUNT(*) FROM schools")->fetchColumn();
            maybe_generate_milestone_post($db, $totalSchools);
        } catch (\Throwable $e) {
            app_log('Auto blog draft failed for school ' . $schoolId . ': ' . $e->getMessage());
        }

        try {
            require_once __DIR__ . '/../includes/notifications.php';
            create_notification($db, $schoolId, 'welcome',
                'Welcome to Somahub!',
                'Your site is set up. Log in to review and edit your content.',
                'sections.php');
        } catch (\Throwable $e) {
            app_log('Welcome notification failed for school ' . $schoolId . ': ' . $e->getMessage());
        }

        // Magic login link — the school taps this, no credentials to type.
        // Falls back to a real password only if this link expires unused.
        $magicLink = null;
        try {
            $magicToken = create_magic_login_token($db, $userId, 14);
            $magicLink = "https://somahub.top/dashboard/magic-login.php?token={$magicToken}";
        } catch (\Throwable $e) {
            app_log('Magic login link generation failed for school ' . $schoolId . ': ' . $e->getMessage());
        }

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
        <?= $created['magic_link'] ? htmlspecialchars($created['magic_link']) : '⚠️ Link generation failed — use "Forgot password" on dashboard/login.php to get this school in instead.' ?>
      </div>
      <p style="font-size:0.85rem;color:#666;">Now go to Outreach to send them the intro, then this login link.</p>
      <a href="outreach.php?school_name=<?= urlencode($created['school_name']) ?>&phone=<?= urlencode($created['phone']) ?>&magic_link=<?= urlencode($created['magic_link'] ?? '') ?>&slug=<?= urlencode($created['slug']) ?>" class="next-btn">Go to Outreach →</a>
    </div>
  <?php else: ?>
    <div class="box">
      <?php if ($error): ?><p style="color:#8C3B2E;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
      <form method="POST">
        <input type="hidden" name="action" value="create_school">
        <input type="hidden" name="lead_id" value="<?= $leadId ?>">

        <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:6px;">School Name</label>
        <input type="text" name="school_name" value="<?= htmlspecialchars($lead['school_name']) ?>" style="width:100%;padding:9px;border:1px solid #ccc;border-radius:4px;margin-bottom:16px;" required>

        <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:6px;">Colors</label>
        <?php
          $selectedPaletteId = $palettes[0]['id'] ?? 0;
          $selectedColorMode = 'preset';
          $firstVars = json_decode($palettes[0]['css_variables_json'] ?? '{}', true) ?: [];
          $customColors = [
              'primary' => $firstVars['primary'] ?? '#0F5257',
              'secondary' => $firstVars['secondary'] ?? '#1C1C16',
              'accent' => $firstVars['accent'] ?? '#F2A65A',
              'bg' => $firstVars['bg'] ?? '#F7F2E7',
          ];
          include __DIR__ . '/_palette_picker.php';
        ?>

        <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:6px;">Starting Content</label>
        <select name="content_preset" style="width:100%;padding:9px;border:1px solid #ccc;border-radius:4px;margin-bottom:16px;">
          <?php foreach ($contentPresets as $key => $p): ?>
            <option value="<?= $key ?>" <?= $key === 'primary_day' ? 'selected' : '' ?>><?= htmlspecialchars($p['label']) ?></option>
          <?php endforeach; ?>
        </select>

        <p style="font-size:0.82rem;color:#666;margin-bottom:16px;">Starts on Premium. The 60-day clock begins the first time they log in, nothing to switch on.</p>

        <button type="submit" class="btn">Create School</button>
      </form>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
