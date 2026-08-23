<?php
require_once __DIR__ . '/config/db.php';
$db = get_db();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

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

        if (!$name || !$email || !$password) {
            $error = 'Please fill in all required fields.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $check = $db->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                $error = 'An account with that email already exists. Try logging in instead.';
                $mode = 'login';
            } else {
                // No school yet - school_id stays NULL until school-setup.php is completed.
                // role is 'school_owner' from the start so the same account owns whichever
                // school gets attached to it next.
                $insert = $db->prepare("
                    INSERT INTO users (name, email, phone, password_hash, role)
                    VALUES (?, ?, ?, ?, 'school_owner')
                ");
                $insert->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);

                $welcomeBody = "
                    <h2 style='color:#0F5257;margin-top:0;'>Welcome to Somahub, {$name}!</h2>
                    <p>Your account is ready. Next, set up your school's details to get your website started.</p>
                    <p><a href='https://somahub.top/dashboard/school-setup.php' style='color:#0F5257;font-weight:700;'>Continue to school setup &rarr;</a></p>
                    <p style='margin-top:20px;color:#6E6A5C;'>Questions? Just reply to this email or message us on WhatsApp.</p>
                ";
                send_somahub_email($email, 'Welcome to Somahub', $welcomeBody);

                $adminBody = "
                    <h2 style='color:#0F5257;margin-top:0;'>New Account Created</h2>
                    <table style='width:100%;font-size:14px;margin:16px 0;'>
                        <tr><td style='color:#6E6A5C;padding:4px 0;'>Name</td><td><strong>" . htmlspecialchars($name) . "</strong></td></tr>
                        <tr><td style='color:#6E6A5C;padding:4px 0;'>Email</td><td>" . htmlspecialchars($email) . "</td></tr>
                        <tr><td style='color:#6E6A5C;padding:4px 0;'>Phone</td><td>" . htmlspecialchars($phone ?: 'Not provided') . "</td></tr>
                    </table>
                    <p style='color:#6E6A5C;'>No school attached yet - they're mid signup.</p>
                ";
                send_somahub_email('admin@somahub.top', "New signup: {$name}", $adminBody, $email);

                login($email, $password);
                header('Location: dashboard/school-setup.php');
                exit;
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
  .wrap{max-width:440px;margin:0 auto;padding:40px 20px;}
  h1{font-size:1.6rem;margin-bottom:6px;}
  .sub{color:var(--muted);margin-bottom:24px;font-size:0.92rem;}
  .card{background:#fff;border-radius:12px;padding:28px;box-shadow:0 1px 4px rgba(0,0,0,0.06);}
  .mode-toggle{display:flex;background:var(--sand);border-radius:8px;padding:4px;margin-bottom:24px;}
  .mode-toggle a{flex:1;text-align:center;padding:9px;border-radius:6px;font-size:0.85rem;font-weight:700;text-decoration:none;color:var(--muted);}
  .mode-toggle a.active{background:#fff;color:var(--teal);box-shadow:0 1px 3px rgba(0,0,0,0.08);}
  label{display:block;font-size:0.85rem;font-weight:700;margin:16px 0 6px;}
  label:first-of-type{margin-top:0;}
  input{width:100%;padding:11px;border:1px solid var(--line);border-radius:6px;font-family:inherit;font-size:0.95rem;}
  button[type=submit]{width:100%;margin-top:24px;background:var(--teal);color:var(--sand);border:none;padding:14px;border-radius:8px;font-weight:800;font-size:1rem;cursor:pointer;}
  .error{background:#FBE8E4;color:#8C3B2E;padding:10px 14px;border-radius:6px;margin-bottom:16px;font-size:0.88rem;}
  .flow-note{font-size:0.82rem;color:var(--muted);margin-top:20px;text-align:center;}
</style>
</head>
<body>
<?php $navRoot = '.'; include __DIR__ . '/_public_nav.php'; ?>

<main class="wrap">
  <h1><?= $mode === 'login' ? 'Welcome back' : 'Create your account' ?></h1>
  <p class="sub"><?= $mode === 'login' ? 'Log in to manage your school\'s site.' : 'Just your details for now - your school comes next.' ?></p>

  <div class="card">
    <div class="mode-toggle">
      <a href="?mode=signup" class="<?= $mode === 'signup' ? 'active' : '' ?>">Sign Up</a>
      <a href="?mode=login" class="<?= $mode === 'login' ? 'active' : '' ?>">Log In</a>
    </div>

    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST" class="loader-on-submit">
      <input type="hidden" name="mode" value="<?= $mode ?>">

      <?php if ($mode === 'signup'): ?>
        <label>Your Name</label>
        <input type="text" name="name" required autofocus>
        <label>Email (used to log in)</label>
        <input type="email" name="email" required>
        <label>Phone</label>
        <input type="text" name="phone" placeholder="07XXXXXXXX">
        <label>Create a Password</label>
        <input type="password" name="password" required minlength="6" placeholder="At least 6 characters">
        <button type="submit">Continue</button>
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

  <?php if ($mode === 'signup'): ?>
    <p class="flow-note">After this, you'll set up your school's details, pick a plan and theme, then build your site.</p>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/_loader.php'; ?>
</body>
</html>
