<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/settings.php';
$db = get_db();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/app_log.php';
require_once __DIR__ . '/includes/school-provision.php';
require_once __DIR__ . '/includes/consent.php';
$contentPresets = get_school_content_presets();
$sf = [];

$error = '';
$mode = ($_GET['mode'] ?? 'signup') === 'login' ? 'login' : 'signup';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = ($_POST['mode'] ?? 'signup') === 'login' ? 'login' : 'signup';

    if ($mode === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (login($email, $password)) {
            $user = current_user();
            header('Location: ' . ($user['school_id'] ? 'dashboard/index.php' : 'dashboard/school-setup.php'));
            exit;
        }
        $error = 'Incorrect email or password.';

    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $sf = $_POST;
        $sf['job_title_raw'] = $_POST['job_title'] ?? '';

        if (!$name || !$email || !$password || !$phone) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif (!consent_given()) {
            $error = CONSENT_ERROR;
        } else {
            try {
            $check = $db->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                $error = 'An account with that email already exists. Try logging in instead.';
                $mode = 'login';
            } else {
                // Validate the school half BEFORE creating the account, so a bad
                // address never leaves a half-finished signup behind.
                [$schoolData, $schoolError] = validate_school_input($db, $_POST);
                if ($schoolError) {
                    $error = $schoolError;
                } else {
                    $uCols = ['name' => $name, 'email' => $email, 'phone' => $phone, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'role' => 'school_owner'];
                    if (db_has_column($db, 'users', 'job_title')) $uCols['job_title'] = $schoolData['job_title'];
                    if (db_has_column($db, 'users', 'terms_accepted_at')) $uCols['terms_accepted_at'] = date('Y-m-d H:i:s');
                    $db->prepare('INSERT INTO users (' . implode(',', array_keys($uCols)) . ') VALUES (' . implode(',', array_fill(0, count($uCols), '?')) . ')')->execute(array_values($uCols));
                    $newUserId = (int)$db->lastInsertId();
                    login($email, $password);
                    $newUser = current_user();

                    try {
                        $res = provision_school($db, $newUser, $schoolData);
                    } catch (\Throwable $e) {
                        // Account exists and is logged in; they can finish on the setup page.
                        app_log('get-started provisioning failed for user ' . $newUserId . ': ' . $e->getMessage());
                        header('Location: dashboard/school-setup.php?retry=1');
                        exit;
                    }

                    $siteUrl = 'https://' . $schoolData['slug'] . '.somahub.top';
                    $welcomeBody = "
                        <h2 style='color:#0F5257;margin-top:0;'>Welcome to Somahub, " . htmlspecialchars($name) . "!</h2>
                        <p>Your website for <strong>" . htmlspecialchars($schoolData['school_name']) . "</strong> is live at <a href='{$siteUrl}' style='color:#0F5257;font-weight:700;'>{$siteUrl}</a>, and your 60-day trial with every feature unlocked has started.</p>
                        <p><a href='https://somahub.top/dashboard/sections.php' style='color:#0F5257;font-weight:700;'>Keep editing your site &rarr;</a></p>
                        <p style='margin-top:20px;color:#6E6A5C;'>Questions? Just reply to this email or message us on WhatsApp.</p>
                    ";
                    send_somahub_email($email, 'Your school website is live', $welcomeBody);

                    $adminBody = "
                        <h2 style='color:#0F5257;margin-top:0;'>New School Signup</h2>
                        <table style='width:100%;font-size:14px;margin:16px 0;'>
                            <tr><td style='color:#6E6A5C;padding:4px 0;'>School</td><td><strong>" . htmlspecialchars($schoolData['school_name']) . "</strong> (" . htmlspecialchars($schoolData['slug']) . ")</td></tr>
                            <tr><td style='color:#6E6A5C;padding:4px 0;'>Name</td><td>" . htmlspecialchars($name) . "</td></tr>
                            <tr><td style='color:#6E6A5C;padding:4px 0;'>Role</td><td>" . htmlspecialchars($schoolData['job_title']) . (role_is_authority($schoolData['job_title']) ? '' : ' (not head/owner, authorised: ' . ($schoolData['authorized'] ? 'yes' : 'no') . ')') . "</td></tr>
                            " . (role_is_authority($schoolData['job_title']) ? '' : "<tr><td style='color:#6E6A5C;padding:4px 0;'>Head teacher</td><td>" . htmlspecialchars($schoolData['head_name']) . ' / ' . htmlspecialchars($schoolData['head_phone']) . "</td></tr>") . "
                            <tr><td style='color:#6E6A5C;padding:4px 0;'>Email</td><td>" . htmlspecialchars($email) . "</td></tr>
                            <tr><td style='color:#6E6A5C;padding:4px 0;'>Phone</td><td>" . htmlspecialchars($phone) . "</td></tr>
                            <tr><td style='color:#6E6A5C;padding:4px 0;'>County</td><td>" . htmlspecialchars($schoolData['county'] ?: 'Not given') . "</td></tr>
                        </table>
                    ";
                    send_somahub_email(get_setting($db, 'admin_notify_email', 'admin@somahub.top'), "New school: " . $schoolData['school_name'], $adminBody, $email);

                    // Straight into the Hero live editor with the first-run welcome.
                    header('Location: dashboard/sections.php?welcome=1');
                    exit;
                }
            }
            } catch (\Throwable $e) {
                app_log('get-started signup failed: ' . $e->getMessage());
                $error = 'Something went wrong creating your site: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Get Started - Somahub</title>
<meta name="description" content="Create your Somahub account to get your school's free website.">
<link rel="canonical" href="https://somahub.top/get-started.php">
<link rel="icon" type="image/x-icon" href="favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{ --teal:#0F5257; --teal-deep:#0A3A3E; --amber:#F2A65A; --sand:#F7F2E7; --ink:#1C1C16; --muted:#6E6A5C; --line:#E5DFCC; }
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:'Manrope',sans-serif;background:var(--sand);color:var(--ink);line-height:1.6;}
  a{color:inherit;}
  .wrap{max-width:<?= $mode === 'signup' ? '680px' : '440px' ?>;margin:0 auto;padding:40px 20px;}
  h1{font-size:1.6rem;margin-bottom:6px;}
  .sub{color:var(--muted);margin-bottom:24px;font-size:0.92rem;}
  .card{background:#fff;border-radius:12px;padding:28px;box-shadow:0 1px 4px rgba(0,0,0,0.06);}
  .mode-toggle{display:flex;background:var(--sand);border-radius:8px;padding:4px;margin-bottom:24px;}
  .mode-toggle a{flex:1;text-align:center;padding:9px;border-radius:6px;font-size:0.85rem;font-weight:700;text-decoration:none;color:var(--muted);}
  .mode-toggle a.active{background:#fff;color:var(--teal);box-shadow:0 1px 3px rgba(0,0,0,0.08);}
  label{display:block;font-size:0.85rem;font-weight:700;margin:16px 0 6px;}
  label:first-of-type{margin-top:0;}
  input,select{width:100%;padding:11px;border:1px solid var(--line);border-radius:6px;font-family:inherit;font-size:0.95rem;background:#fff;}
  .pw-row{position:relative;} .pw-row input{padding-right:64px;} .pw-toggle{position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--teal);font-weight:700;font-size:.78rem;cursor:pointer;width:auto;margin:0;padding:4px 6px;}
  button[type=submit]{width:100%;margin-top:24px;background:var(--teal);color:var(--sand);border:none;padding:14px;border-radius:8px;font-weight:800;font-size:1rem;cursor:pointer;}
  .error{background:#FBE8E4;color:#8C3B2E;padding:10px 14px;border-radius:6px;margin-bottom:16px;font-size:0.88rem;}
  .flow-note{font-size:0.82rem;color:var(--muted);margin-top:20px;text-align:center;}
</style>
</head>
<body>
<?php $navRoot = '.'; include __DIR__ . '/_public_nav.php'; ?>

<main class="wrap">
  <h1><?= $mode === 'login' ? 'Welcome back' : 'Create your school website' ?></h1>
  <p class="sub"><?= $mode === 'login' ? 'Log in to manage your school\'s site.' : 'One short form and your site is live. Fine-tune everything afterwards.' ?></p>

  <div class="card">
    <div class="mode-toggle">
      <a href="?mode=signup" class="<?= $mode === 'signup' ? 'active' : '' ?>">Sign Up</a>
      <a href="?mode=login" class="<?= $mode === 'login' ? 'active' : '' ?>">Log In</a>
    </div>

    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST" class="loader-on-submit">
      <input type="hidden" name="mode" value="<?= $mode ?>">

      <?php if ($mode === 'signup'): $subdomainCheckUrl = 'subdomain-check.php'; ?>
        <div class="sf-grid">
          <div class="sf-h">About you</div>
          <div><label>Your Name</label><input type="text" name="name" required autofocus value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"></div>
          <div><label>Phone</label><input type="text" name="phone" required placeholder="07XXXXXXXX" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"></div>
          <div><label>Email (used to log in)</label><input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"></div>
          <div><label>Create a Password</label><div class="pw-row"><input type="password" name="password" id="pwField" required minlength="8" placeholder="At least 8 characters"><button type="button" class="pw-toggle" onclick="var f=document.getElementById('pwField');f.type=f.type==='password'?'text':'password';this.textContent=f.type==='password'?'Show':'Hide';">Show</button></div></div>
        </div>
        <?php include __DIR__ . '/includes/_school_form_fields.php'; ?>
        <?= consent_checkbox() ?>
        <p class="sub" style="margin:10px 0 0;font-size:.82rem;">Your site goes live straight away with a free 60-day trial of every feature. Nothing is charged unless you choose to upgrade.</p>
        <button type="submit">Create My Website</button>
      <?php else: ?>
        <label>Email</label>
        <input type="email" name="email" required autofocus>
        <label>Password</label>
        <input type="password" name="password" required>
        <button type="submit">Log In</button>
        <p style="text-align:center;margin-top:14px;"><a href="dashboard/forgot-password.php" style="color:var(--teal);font-size:0.82rem;font-weight:700;">Forgot password?</a></p>
      <?php endif; ?>
    </form>
  </div>

</main>

<?php include __DIR__ . '/_loader.php'; ?>
</body>
</html>
