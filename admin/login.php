<?php
require_once __DIR__ . '/../includes/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (login($email, $password) && current_user()['role'] === 'platform_admin') {
        header('Location: index.php');
        exit;
    } else {
        logout();
        $error = 'Incorrect email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — Somahub</title>
<style>
  body{font-family:Arial,sans-serif;background:#1B1B18;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;}
  .card{background:#fff;padding:32px;border-radius:8px;width:100%;max-width:340px;}
  h1{font-size:1.2rem;margin-bottom:20px;}
  label{display:block;font-size:0.85rem;margin-bottom:6px;}
  input{width:100%;padding:10px;margin-bottom:16px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;}
  button{width:100%;padding:12px;background:#1B1B18;color:#fff;border:none;border-radius:4px;font-weight:600;cursor:pointer;}
  .error{color:#8C3B2E;font-size:0.85rem;margin-bottom:12px;}
</style>
</head>
<body>
  <form class="card" method="POST">
    <h1>Platform Admin Login</h1>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <label>Email</label>
    <input type="email" name="email" required>
    <label>Password</label>
    <input type="password" name="password" required>
    <button type="submit">Log In</button>
  </form>
  <p style="text-align:center;margin-top:16px;"><a href="../index.php" style="color:#999;font-size:0.82rem;">&larr; Back to somahub.top</a></p>
</body>
</html>
