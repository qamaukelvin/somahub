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

/**
 * Generates a 6-digit email code for password reset, stores its hash
 * (never the raw code) with a 15-minute expiry, and returns the raw code
 * so the caller can email it. Invalidates any previous unused codes for
 * this user first, so only the most recent code ever works.
 */
function create_password_reset_code(PDO $db, int $userId): string {
    $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")
       ->execute([$userId]);

    $code = (string)random_int(100000, 999999); // 6-digit code, easy to type from an email on a phone
    $codeHash = password_hash($code, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    $db->prepare("INSERT INTO password_resets (user_id, code_hash, expires_at) VALUES (?, ?, ?)")
       ->execute([$userId, $codeHash, $expiresAt]);

    return $code;
}

/**
 * Verifies a submitted code against the most recent unused, unexpired code
 * for this user. Returns true and marks it used if valid; false otherwise.
 */
function verify_password_reset_code(PDO $db, int $userId, string $submittedCode): bool {
    $stmt = $db->prepare("
        SELECT * FROM password_resets
        WHERE user_id = ? AND used_at IS NULL AND expires_at > NOW()
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$userId]);
    $reset = $stmt->fetch();

    if (!$reset || !password_verify($submittedCode, $reset['code_hash'])) {
        return false;
    }

    $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?")->execute([$reset['id']]);
    return true;
}