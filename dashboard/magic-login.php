<?php
require_once __DIR__ . '/../includes/auth.php';
$db = get_db();

$token = $_GET['token'] ?? '';
$user = $token ? verify_magic_login_token($db, $token) : null;

if (!$user) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Expired</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@600;700&display=swap" rel="stylesheet">
    <style>
      body{font-family:'Manrope',sans-serif;background:#F7F2E7;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;text-align:center;}
      .box{background:#fff;padding:32px;border-radius:16px;max-width:360px;}
      h1{color:#0F5257;font-size:1.2rem;margin-bottom:10px;}
      p{color:#6E6A5C;font-size:0.9rem;line-height:1.6;margin-bottom:20px;}
      a{background:#0F5257;color:#fff;padding:11px 22px;border-radius:24px;text-decoration:none;font-weight:700;font-size:0.88rem;}
    </style>
    </head>
    <body>
      <div class="box">
        <h1>This link has expired</h1>
        <p>Login links only work once, or for a limited time. Ask us for a new one, or log in with your password instead.</p>
        <a href="login.php">Go to Login</a>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// First time in via a magic link and no real password set yet — send them
// straight to set one, so they're not stuck if this link is ever reused
// or expires before they come back.
if (!empty($user['password_is_temp'])) {
    header('Location: account.php?set_password=1');
} else {
    header('Location: index.php');
}
exit;
