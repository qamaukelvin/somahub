<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
$db = get_db();

$themes = $db->query("SELECT * FROM themes WHERE is_active=1 ORDER BY name")->fetchAll();
$defaultSectionKeys = ['hero','about','academics','admissions','gallery','contact']; // sensible starter set

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $slug = strtolower(preg_replace('/[^a-z0-9]/', '', strtolower($_POST['slug'])));
    $themeId = (int)$_POST['theme_id'];
    $ownerEmail = trim($_POST['owner_email']);
    $ownerName = trim($_POST['owner_name']);
    $ownerPhone = trim($_POST['owner_phone']);
    $tempPassword = bin2hex(random_bytes(4)); // e.g. "a1b2c3d4" — sent to school owner directly

    if (!$name || !$slug || !$ownerEmail) {
        $error = 'Please fill in all required fields.';
    } else {
        $check = $db->prepare("SELECT id FROM schools WHERE slug = ?");
        $check->execute([$slug]);
        if ($check->fetch()) {
            $error = "The subdomain \"$slug\" is already taken. Try another.";
        } else {
            $db->beginTransaction();
            try {
                // 1. Create the school (starts on free plan + promo window, per the free-first-term strategy)
                $promoEnds = date('Y-m-d', strtotime('+1 term', strtotime('+4 months')));
                $insertSchool = $db->prepare("
                    INSERT INTO schools (name, slug, theme_id, plan, promo_ends_at, status, phone, email)
                    VALUES (?, ?, ?, 'promo_paid', ?, 'trial', ?, ?)
                ");
                $insertSchool->execute([$name, $slug, $themeId, $promoEnds, $ownerPhone, $ownerEmail]);
                $schoolId = $db->lastInsertId();

                // 2. Create the owner login
                $insertUser = $db->prepare("
                    INSERT INTO users (school_id, name, email, phone, password_hash, role)
                    VALUES (?, ?, ?, ?, ?, 'school_owner')
                ");
                $insertUser->execute([
                    $schoolId, $ownerName, $ownerEmail, $ownerPhone,
                    password_hash($tempPassword, PASSWORD_DEFAULT),
                ]);

                // 3. Seed default sections
                $typeStmt = $db->prepare("SELECT id, schema_json FROM section_types WHERE key_name = ?");
                $insertSection = $db->prepare("
                    INSERT INTO site_sections (school_id, section_type_id, position, is_visible, content_json)
                    VALUES (?, ?, ?, 1, ?)
                ");
                foreach ($defaultSectionKeys as $i => $key) {
                    $typeStmt->execute([$key]);
                    $type = $typeStmt->fetch();
                    if ($type) {
                        $schema = json_decode($type['schema_json'], true);
                        $emptyContent = array_fill_keys(array_keys($schema), '');
                        $insertSection->execute([$schoolId, $type['id'], $i, json_encode($emptyContent)]);
                    }
                }

                $db->commit();
                header("Location: school-created.php?id=$schoolId&pw=" . urlencode($tempPassword));
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Something went wrong: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add School</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>.error{background:#FBE8E4;color:#8C3B2E;padding:12px 16px;border-radius:6px;margin-bottom:16px;}</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>Add a New School</h1>
  <p style="color:#666;margin-bottom:24px;">Creates the school, its subdomain, an owner login, and a default set of sections — ready for them to edit.</p>

  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <form method="POST" class="stacked" style="max-width:480px;">
    <label>School Name</label>
    <input type="text" name="name" required placeholder="e.g. Kinangop Pride Primary School">

    <label>Subdomain (lowercase, no spaces)</label>
    <input type="text" name="slug" required placeholder="e.g. kinangoppride">
    <p style="font-size:0.78rem;color:#888;margin-top:-10px;margin-bottom:16px;">Will be: [subdomain].somahub.top</p>

    <label>Theme</label>
    <select name="theme_id" required>
      <?php foreach ($themes as $t): ?>
        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
      <?php endforeach; ?>
    </select>

    <label>Owner / Head Teacher Name</label>
    <input type="text" name="owner_name" required>

    <label>Owner Email (used to log in)</label>
    <input type="email" name="owner_email" required>

    <label>Owner Phone</label>
    <input type="text" name="owner_phone">

    <button type="submit" class="btn">Create School</button>
  </form>
</main>
</body>
</html>
