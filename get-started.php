<?php
require_once __DIR__ . '/config/db.php';
$db = get_db();
require_once __DIR__ . '/includes/auth.php'; // for password_hash consistency, session
require_once __DIR__ . '/includes/content-presets.php';

$themes = $db->query("SELECT * FROM themes WHERE is_active=1 ORDER BY name")->fetchAll();
$contentPresets = get_school_content_presets();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schoolName = trim($_POST['school_name'] ?? '');
    $slug = strtolower(preg_replace('/[^a-z0-9]/', '', strtolower($_POST['slug'] ?? '')));
    $ownerName = trim($_POST['owner_name'] ?? '');
    $ownerEmail = trim($_POST['owner_email'] ?? '');
    $ownerPhone = trim($_POST['owner_phone'] ?? '');
    $county = trim($_POST['county'] ?? '');
    $themeId = (int)($_POST['theme_id'] ?? 0);
    $presetKey = $_POST['content_preset'] ?? 'blank';
    $password = $_POST['password'] ?? '';

    if (!$schoolName || !$slug || !$ownerName || !$ownerEmail || !$password) {
        $error = 'Please fill in all required fields.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $check = $db->prepare("SELECT id FROM schools WHERE slug = ?");
        $check->execute([$slug]);
        if ($check->fetch()) {
            $error = "The subdomain \"$slug\" was just taken by someone else — please pick another.";
        } else {
            $db->beginTransaction();
            try {
                // New schools start UNPUBLISHED and UNVERIFIED — site.php will show a
                // "coming soon" placeholder to the public until an admin approves
                // verification (uploaded in dashboard/verify.php).
                $insertSchool = $db->prepare("
                    INSERT INTO schools (name, slug, theme_id, plan, status, verification_status, county, phone, email)
                    VALUES (?, ?, ?, 'free', 'trial', 'pending', ?, ?, ?)
                ");
                $insertSchool->execute([$schoolName, $slug, $themeId, $county, $ownerPhone, $ownerEmail]);
                $schoolId = $db->lastInsertId();

                $insertUser = $db->prepare("
                    INSERT INTO users (school_id, name, email, phone, password_hash, role)
                    VALUES (?, ?, ?, ?, ?, 'school_owner')
                ");
                $insertUser->execute([$schoolId, $ownerName, $ownerEmail, $ownerPhone, password_hash($password, PASSWORD_DEFAULT)]);

                // Seed the standard starter sections. If a content preset was chosen,
                // fill them with real starter copy; otherwise leave blank for the
                // school to write themselves before requesting verification.
                $presetContent = $contentPresets[$presetKey]['content'] ?? [];
                $defaultSectionKeys = ['hero','about','academics','admissions','gallery','contact'];
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

                        if (!empty($presetContent[$key])) {
                            foreach ($presetContent[$key] as $field => $text) {
                                if (array_key_exists($field, $emptyContent)) {
                                    $emptyContent[$field] = str_replace('{school}', $schoolName, $text);
                                }
                            }
                        }

                        $insertSection->execute([$schoolId, $type['id'], $i, json_encode($emptyContent)]);
                    }
                }

                $db->commit();

                // Log the new owner straight in and send them to the dashboard —
                // no waiting on you to create anything.
                login($ownerEmail, $password);
                header("Location: dashboard/index.php?welcome=1");
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Something went wrong. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Get Started — Somahub</title>
<meta name="description" content="Create your school's free website with Somahub. Pick your web address, build your site, and go live once verified.">
<link rel="canonical" href="https://somahub.top/get-started.php">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
  :root{ --teal:#0F5257; --teal-deep:#0A3A3E; --amber:#F2A65A; --sand:#F7F2E7; --ink:#1C1C16; --muted:#6E6A5C; --line:#E5DFCC; }
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:'Manrope',sans-serif;background:var(--sand);color:var(--ink);line-height:1.6;}
  a{color:inherit;}
  .wrap{max-width:520px;margin:0 auto;padding:40px 20px;}
  header{padding:20px 24px;}
  .brand{display:flex;align-items:center;gap:8px;font-weight:800;font-size:1.15rem;}
  .brand .dot{width:9px;height:9px;background:var(--amber);border-radius:50%;}
  h1{font-size:1.8rem;margin-bottom:8px;}
  .sub{color:var(--muted);margin-bottom:28px;}
  .card{background:#fff;border-radius:12px;padding:28px;box-shadow:0 1px 4px rgba(0,0,0,0.06);}
  label{display:block;font-size:0.85rem;font-weight:700;margin:16px 0 6px;}
  label:first-of-type{margin-top:0;}
  input,select{width:100%;padding:11px;border:1px solid var(--line);border-radius:6px;font-family:inherit;font-size:0.95rem;}
  .subdomain-row{display:flex;align-items:center;gap:8px;}
  .subdomain-row input{flex:1;}
  .subdomain-suffix{color:var(--muted);font-size:0.85rem;white-space:nowrap;}
  .avail-msg{font-size:0.82rem;margin-top:6px;min-height:18px;}
  .avail-ok{color:#1B4D3E;}
  .avail-bad{color:#8C3B2E;}
  .avail-checking{color:var(--muted);}
  button[type=submit]{width:100%;margin-top:24px;background:var(--teal);color:var(--sand);border:none;padding:14px;border-radius:8px;font-weight:800;font-size:1rem;cursor:pointer;}
  button[type=submit]:disabled{opacity:0.5;cursor:not-allowed;}
  .error{background:#FBE8E4;color:#8C3B2E;padding:10px 14px;border-radius:6px;margin-bottom:16px;font-size:0.88rem;}
  .flow-note{font-size:0.82rem;color:var(--muted);margin-top:20px;text-align:center;}
</style>
</head>
<body>
<header><a href="/" class="brand"><span class="dot"></span>somahub</a></header>

<main class="wrap">
  <h1>Get Your School Online</h1>
  <p class="sub">Pick your web address, build your site, then request verification to go live.</p>

  <div class="card">
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST" id="signupForm">
      <label>School Name</label>
      <input type="text" name="school_name" required placeholder="e.g. Kinangop Pride Primary School">

      <label>Choose Your Web Address</label>
      <div class="subdomain-row">
        <input type="text" name="slug" id="slugInput" required placeholder="kinangoppride" pattern="[a-z0-9]+" autocomplete="off">
        <span class="subdomain-suffix">.somahub.top</span>
      </div>
      <div class="avail-msg" id="availMsg"></div>

      <label>County</label>
      <input type="text" name="county" placeholder="e.g. Nyandarua">

      <label>Theme</label>
      <select name="theme_id" required>
        <?php foreach ($themes as $t): ?>
          <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <label>Starting Content</label>
      <select name="content_preset">
        <?php foreach ($contentPresets as $key => $p): ?>
          <option value="<?= $key ?>" <?= $key === 'primary_day' ? 'selected' : '' ?>><?= htmlspecialchars($p['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <p style="font-size:0.78rem;color:var(--muted);margin-top:-4px;">Fills your About, Academics, and Admissions sections with a starting point matched to your school type — fully editable afterward.</p>

      <label>Your Name (school representative)</label>
      <input type="text" name="owner_name" required>

      <label>Your Email (used to log in)</label>
      <input type="email" name="owner_email" required>

      <label>Your Phone</label>
      <input type="text" name="owner_phone" placeholder="07XXXXXXXX">

      <label>Create a Password</label>
      <input type="password" name="password" required minlength="6" placeholder="At least 6 characters">

      <button type="submit" id="submitBtn">Create My Site</button>
    </form>
  </div>

  <p class="flow-note">After signing up, you'll edit your site content and upload verification documents in your dashboard. Your site goes live once verified — usually within a day or two.</p>
</main>

<script>
const slugInput = document.getElementById('slugInput');
const availMsg = document.getElementById('availMsg');
const submitBtn = document.getElementById('submitBtn');
let debounceTimer, isAvailable = false;

slugInput.addEventListener('input', () => {
    slugInput.value = slugInput.value.toLowerCase().replace(/[^a-z0-9]/g, '');
    clearTimeout(debounceTimer);
    const val = slugInput.value.trim();
    isAvailable = false;

    if (val.length < 3) {
        availMsg.textContent = val.length ? 'At least 3 characters' : '';
        availMsg.className = 'avail-msg avail-bad';
        return;
    }

    availMsg.textContent = 'Checking availability...';
    availMsg.className = 'avail-msg avail-checking';

    debounceTimer = setTimeout(() => {
        fetch('subdomain-check.php?slug=' + encodeURIComponent(val))
            .then(r => r.json())
            .then(data => {
                if (data.available) {
                    availMsg.textContent = '✓ ' + val + '.somahub.top is available';
                    availMsg.className = 'avail-msg avail-ok';
                    isAvailable = true;
                } else {
                    availMsg.textContent = '✗ That address is already taken';
                    availMsg.className = 'avail-msg avail-bad';
                }
            })
            .catch(() => {
                availMsg.textContent = '';
            });
    }, 400);
});

document.getElementById('signupForm').addEventListener('submit', (e) => {
    if (!isAvailable) {
        e.preventDefault();
        availMsg.textContent = 'Please choose an available web address first';
        availMsg.className = 'avail-msg avail-bad';
        slugInput.focus();
    }
});
</script>
</body>
</html>
