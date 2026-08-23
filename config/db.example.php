<?php
// Database connection
//
// SETUP: copy this file to config/db.php and fill in real values.
// config/db.php itself is gitignored — never commit real credentials.
//
// IMPORTANT: DB_NAME below must exactly match whatever database name you actually
// created in phpMyAdmin — it does NOT need to match the brand name "somahub".
//
// LOCAL (XAMPP) DEFAULTS — these normally work out of the box on XAMPP:
//   host: localhost   user: root   password: (empty)   db name: whatever you created
//
// LIVE HOSTING (cPanel/Truehost) — replace all four with the real values from your hosting's
// MySQL Databases panel. cPanel db/user names are usually prefixed, e.g. "cpaneluser_somahub".
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name_here');
define('DB_USER', 'your_database_user_here');
define('DB_PASS', 'your_database_password_here');

function get_db() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage() . '<br><br>If you are on XAMPP, make sure Apache and MySQL are both running in the XAMPP control panel, and that a database with the name set in DB_NAME above actually exists in phpMyAdmin.');
        }
    }
    return $pdo;
}
