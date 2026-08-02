<?php
// Database connection
//
// IMPORTANT: DB_NAME below must exactly match whatever database name you actually
// created in phpMyAdmin — it does NOT need to match the brand name "somahub".
// If you already created a database earlier (e.g. named "soma_platform" or with a
// cPanel prefix like "romfdgrb_soma"), put that EXACT name below, don't guess.
//
// LOCAL (XAMPP) DEFAULTS — these normally work out of the box on XAMPP:
//   host: localhost   user: root   password: (empty)   db name: whatever you created
//
// LIVE HOSTING (cPanel/Truehost) — replace all four with the real values from your hosting's
// MySQL Databases panel. cPanel db/user names are usually prefixed, e.g. "cpaneluser_somahub".
define('DB_HOST', 'localhost');
define('DB_NAME', 'soma_platform'); // Confirmed: this matches the database already created — do not change this to "somahub_platform"
define('DB_USER', 'root');
define('DB_PASS', '');

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
