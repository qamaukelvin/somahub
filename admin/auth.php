<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/db.php';

function admin_logged_in() {
    return !empty($_SESSION['admin_id']);
}

function require_admin_login() {
    if (!admin_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function admin_attempt_login($username, $password) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT id, username, password_hash FROM admin_users WHERE username = :u");
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        return true;
    }
    return false;
}

function admin_logout() {
    unset($_SESSION['admin_id'], $_SESSION['admin_username']);
    session_destroy();
}
