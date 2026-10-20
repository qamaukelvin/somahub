<?php
// For accounts that exist but have no school yet (signed up before the signup
// form was merged, or provisioning failed part-way). New signups never see this.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/app_log.php';
require_once __DIR__ . '/../includes/school-provision.php';
require_once __DIR__ . '/../includes/consent.php';
$db = get_db();

$user = current_user();
if (!$user || !in_array($user['role'], ['school_owner', 'school_editor'])) {
    header('Location: ../get-started.php?mode=login');
    exit;
}
if (!empty($user['school_id'])) {
    header('Location: index.php');
    exit;
}

$contentPresets = get_school_content_presets();
$subdomainCheckUrl = '../subdomain-check.php';
$sf = [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sf = $_POST;
    $sf['job_title_raw'] = $_POST['job_title'] ?? '';
    if (!consent_given()) {
        $error = CONSENT_ERROR;
    } else {
        [$schoolData, $error] = validate_school_input($db, $_POST);
        if (!$error) {
            try {
                $res = provision_school($db, $user, $schoolData);
                header('Location: https://' . $schoolData['slug'] . '.somahub.top/?edit=1&welcome=1');
                exit;
            } catch (\Throwable $e) {
                app_log('school-setup.php failed: ' . $e->getMessage());
                $error = 'Something went wrong creating your site. Please try again, or contact us on WhatsApp.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Set Up Your School - Somahub</title>
<link rel="icon" type="image/x-icon" href="../favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
  :root{ --teal:#0F5257; --teal-deep:#0A3A3E; --amber:#F2A65A; --sand:#F7F2E7; --ink:#1C1C16; --muted:#6E6A5C; --line:#E5DFCC; }
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:'Manrope',sans-serif;background:var(--sand);color:var(--ink);line-height:1.6;}
  a{color:inherit;}
  .wrap{max-width:680px;margin:0 auto;padding:40px 20px;}
  h1{font-size:1.7rem;margin-bottom:8px;}
  .sub{color:var(--muted);margin-bottom:24px;}
  .card{background:#fff;border-radius:12px;padding:28px;box-shadow:0 1px 4px rgba(0,0,0,0.06);}
  label{display:block;font-size:0.85rem;font-weight:700;margin:16px 0 6px;}
  input,select{width:100%;padding:11px;border:1px solid var(--line);border-radius:6px;font-family:inherit;font-size:0.95rem;background:#fff;}
  button[type=submit]{width:100%;margin-top:24px;background:var(--teal);color:var(--sand);border:none;padding:14px;border-radius:8px;font-weight:800;font-size:1rem;cursor:pointer;}
  .error{background:#FBE8E4;color:#8C3B2E;padding:10px 14px;border-radius:6px;margin-bottom:16px;font-size:0.88rem;}
</style>
</head>
<body>
<?php $navRoot = '..'; include __DIR__ . '/../_public_nav.php'; ?>

<main class="wrap">
  <h1>Finish setting up your school</h1>
  <p class="sub">A few details and your website goes live, with a free 60-day trial of every feature.</p>
  <div class="card">
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST" class="loader-on-submit">
      <?php include __DIR__ . '/../includes/_school_form_fields.php'; ?>
      <?= consent_checkbox() ?>
      <button type="submit">Create My Website</button>
    </form>
  </div>
</main>
<?php include __DIR__ . '/../_loader.php'; ?>
</body>
</html>
