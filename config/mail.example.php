<?php
// SMTP mail configuration
//
// SETUP: copy this file to config/mail.php and fill in real values.
// config/mail.php itself is gitignored — never commit real credentials.
//
// Get these exact values from: cPanel → Email → Email Accounts →
// "Connect Devices" (or "Set Up Mail Client") next to your info@ mailbox.

use PHPMailer\PHPMailer\PHPMailer;

define('SMTP_HOST', 'mail.yourdomain.com');
define('SMTP_PORT', 465); // 465 for SSL, or 587 for TLS
define('SMTP_USERNAME', 'info@yourdomain.com');
define('SMTP_PASSWORD', 'your_real_mailbox_password_here');
define('SMTP_ENCRYPTION', PHPMailer::ENCRYPTION_SMTPS); // matches port 465; use STARTTLS for port 587
