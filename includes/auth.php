<?php
require_once __DIR__ . '/../config/db.php';

// Scope the session cookie to the whole *.somahub.top domain (not just the
// exact host that served the page). Without this, a session started on
// somahub.top (e.g. the admin panel) is invisible on a school's subdomain
// (slug.somahub.top) — which is why "Preview Site" from admin used to open
// a logged-out view and show the "coming soon" gate even for admins.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '.somahub.top',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

function login($email, $password) {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        establish_session($db, $user);
        return true;
    }
    return false;
}

/**
 * Shared by password login and magic-link login: sets the session, updates
 * last_login_at, and handles first-login side effects (verification
 * countdown start, auto-starting a lead-converted school's trial).
 */
function establish_session(PDO $db, array $user): void {
    unset($user['password_hash']); // never keep the hash in session
    $_SESSION['user'] = $user;

    $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")
       ->execute([$user['id']]);

    if (!empty($user['school_id'])) {
        $db->prepare("UPDATE schools SET first_login_at = NOW() WHERE id = ? AND first_login_at IS NULL")
           ->execute([$user['school_id']]);

        $flagStmt = $db->prepare("SELECT activate_trial_on_login FROM schools WHERE id = ?");
        $flagStmt->execute([$user['school_id']]);
        if ($flagStmt->fetchColumn()) {
            require_once __DIR__ . '/payments.php';
            start_trial($db, $user['school_id'], 60);
            $db->prepare("UPDATE schools SET activate_trial_on_login = 0 WHERE id = ?")->execute([$user['school_id']]);
        }
    }
}

/**
 * Creates a one-click login link for a user — no password needed. Used for
 * outreach so a school can log in by tapping a link instead of typing
 * credentials. Single-use, expires after $days. Returns the raw token to
 * build the URL with; only its hash is stored.
 */
function create_magic_login_token(PDO $db, int $userId, int $days = 7): string {
    $token = bin2hex(random_bytes(24));
    $tokenHash = hash('sha256', $token); // fast lookup hash, not a password — no bcrypt needed
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$days} days"));

    $db->prepare("INSERT INTO magic_login_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)")
       ->execute([$userId, $tokenHash, $expiresAt]);

    return $token;
}

/**
 * Verifies and consumes a magic login token. Returns the logged-in user
 * array on success (and establishes the session), or null if invalid,
 * expired, or already used.
 */
function verify_magic_login_token(PDO $db, string $token): ?array {
    $tokenHash = hash('sha256', $token);
    $stmt = $db->prepare("
        SELECT * FROM magic_login_tokens
        WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$tokenHash]);
    $record = $stmt->fetch();
    if (!$record) {
        return null;
    }

    $db->prepare("UPDATE magic_login_tokens SET used_at = NOW() WHERE id = ?")->execute([$record['id']]);

    $userStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $userStmt->execute([$record['user_id']]);
    $user = $userStmt->fetch();
    if (!$user) {
        return null;
    }

    establish_session($db, $user);
    return $_SESSION['user'];
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

    // Logged in, but hasn't completed school setup yet (personal-details-first
    // signup flow). Send them to finish that instead of letting every other
    // dashboard page break on a missing school_id. school-setup.php itself is
    // exempt from this check since it's the destination, not a caller.
    $callingScript = basename($_SERVER['SCRIPT_NAME']);
    if (empty($user['school_id']) && $callingScript !== 'school-setup.php') {
        header('Location: school-setup.php');
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

    if (!$reset) {
        return false;
    }

    // Rate limiting — a 6-digit code has only 900,000 combinations, brute-forceable
    // within the 15-minute window without a cap. 5 attempts per code is generous for
    // a genuine typo but stops any real guessing attempt cold.
    if ((int)$reset['attempts'] >= 5) {
        $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?")->execute([$reset['id']]);
        return false;
    }

    if (!password_verify($submittedCode, $reset['code_hash'])) {
        $db->prepare("UPDATE password_resets SET attempts = attempts + 1 WHERE id = ?")->execute([$reset['id']]);
        return false;
    }

    $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?")->execute([$reset['id']]);
    return true;
}