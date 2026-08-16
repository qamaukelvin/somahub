<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/reviews.php';
$db = get_db();

$error = '';
$redirectBack = $_POST['redirect_to'] ?? '/';

// Prevent this from being used as an open redirect — only ever send someone
// back to a real somahub.top page, regardless of what redirect_to claims.
// Without this check, anyone could craft a review form pointing redirect_to
// at an external site, and Somahub's own domain would do the redirecting,
// exactly the pattern phishing links rely on to look trustworthy.
$parsedHost = parse_url($redirectBack, PHP_URL_HOST);
if ($parsedHost !== null && !preg_match('/(^|\.)somahub\.top$/', $parsedHost)) {
    $redirectBack = 'https://somahub.top/';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    submit_review($db, $_POST, $error);
}

$separator = strpos($redirectBack, '?') !== false ? '&' : '?';
$flag = $error ? 'review_error=' . urlencode($error) : 'review_submitted=1';
header("Location: {$redirectBack}{$separator}{$flag}#reviews");
exit;
