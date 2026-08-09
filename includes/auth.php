<?php
require_once __DIR__ . '/../config/db.php';

session_start();

function current_user() {
    return $_SESSION['user'] ?? null;
}

function login($email, $password) {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        unset($user['password_hash']); // never keep the hash in session
        $_SESSION['user'] = $user;

        $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")
           ->execute([$user['id']]);

        return true;
    }
    return false;
}

function logout() {
    $_SESSION = [];
    session_destroy();
}

// Call at the top of any dashboard page (school staff)
function require_school_login() {
    $user = current_user();

    // Admin impersonation: if a platform admin has chosen to edit a specific
    // school's content, build a synthetic user context matching that school,
    // so every existing dashboard page works completely unchanged.
    if ($user && $user['role'] === 'platform_admin' && !empty($_SESSION['impersonating_school_id'])) {
        return [
            'id' => $user['id'],
            'name' => $user['name'] . ' (Admin)',
            'email' => $user['email'],
            'school_id' => $_SESSION['impersonating_school_id'],
            'role' => 'school_owner',
            'is_admin_impersonating' => true,
        ];
    }

    if (!$user || !in_array($user['role'], ['school_owner', 'school_editor'])) {
        header('Location: login.php');
        exit;
    }
    return $user;
}

// Call at the top of any admin page (you / your staff)
function require_platform_admin() {
    $user = current_user();
    if (!$user || $user['role'] !== 'platform_admin') {
        header('Location: login.php');
        exit;
    }
    return $user;
}