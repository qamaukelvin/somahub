<?php
require_once __DIR__ . '/../includes/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (login($email, $password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'Incorrect email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>School Login — Somahub</title>
<link rel="icon" type="image/x-icon" href="../favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{ --teal:#0F5257; --teal-deep:#0A3A3E; --amber:#F2A65A; --sand:#F7F2E7; }
  *{box-sizing:border-box;}
  body{font-family:'Manrope',sans-serif;background:var(--sand);display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;}
  .card{background:#fff;padding:36px 32px;border-radius:16px;box-shadow:0 8px 30px rgba(0,0,0,0.08);width:100%;max-width:380px;}
  .brand{display:flex;align-items:center;justify-content:center;gap:8px;font-weight:800;font-size:1.15rem;color:var(--teal-deep);margin-bottom:6px;}
  .brand .dot{width:9px;height:9px;background:var(--amber);border-radius:50%;}
  .subtitle{text-align:center;color:#6E6A5C;font-size:0.82rem;margin-bottom:28px;}
  label{display:block;font-size:0.85rem;font-weight:700;margin-bottom:6px;color:#1C1C16;}
  input{width:100%;padding:11px 14px;margin-bottom:16px;border:1.5px solid #E5DFCC;border-radius:8px;box-sizing:border-box;font-family:inherit;font-size:0.92rem;}
  input:focus{outline:none;border-color:var(--teal);}
  button{width:100%;padding:12px;background:var(--teal);color:#fff;border:none;border-radius:24px;font-weight:700;cursor:pointer;font-size:0.92rem;}
  button:hover{background:var(--teal-deep);}
  .error{background:#FBE8E4;color:#8C3B2E;font-size:0.85rem;padding:10px 14px;border-radius:8px;margin-bottom:16px;}
  .back{display:block;text-align:center;margin-top:20px;color:#6E6A5C;font-size:0.82rem;text-decoration:none;}
</style>
</head>
<body>
  <form class="card" method="POST">
    <div class="brand"><span class="dot"></span> somahub</div>
    <div class="subtitle">School Dashboard</div>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <label>Email</label>
    <input type="email" name="email" required autofocus>
    <label>Password</label>
    <input type="password" name="password" required>
    <button type="submit">Log In</button>
  </form>
  <a href="../index.php" class="back">&larr; Back to somahub.top</a>
</body>
</html>