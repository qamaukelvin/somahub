<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify($token) {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

/**
 * Very lightweight rate limiter: allows 1 submission per $seconds per session.
 * Not a substitute for a real captcha under heavy abuse, but stops casual spam/bots.
 */
function rate_limit_ok($key = 'booking_submit', $seconds = 30) {
    $now = time();
    $last = $_SESSION['last_' . $key] ?? 0;
    if ($now - $last < $seconds) {
        return false;
    }
    $_SESSION['last_' . $key] = $now;
    return true;
}
