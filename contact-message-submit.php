<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/consent.php';
$db = get_db();
require_once __DIR__ . '/includes/ratelimit.php';
if ($_SERVER['REQUEST_METHOD']==='POST') rate_limit_or_die('cmsg:'.client_ip(), 8, 3600);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

$schoolId = (int)($_POST['school_id'] ?? 0);
$slug = trim($_POST['redirect_slug'] ?? '');

// Never trust school_id alone - re-verify it actually matches a real school
// with this slug, so a crafted request can't attribute a message to the
// wrong school.
$stmt = $db->prepare("SELECT id, slug FROM schools WHERE id = ? AND slug = ?");
$stmt->execute([$schoolId, $slug]);
$school = $stmt->fetch();

if (!$school) {
    http_response_code(404);
    die('School not found.');
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');

if (!consent_given()) {
    header('Location: https://' . $school['slug'] . '.somahub.top/?msg_error=consent#contact');
    exit;
}

if ($name && $email && $message && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $insert = $db->prepare("INSERT INTO school_contact_messages (school_id, name, email, message) VALUES (?, ?, ?, ?)");
    $insert->execute([$school['id'], $name, $email, $message]);
    try {
        require_once __DIR__ . '/includes/notifications.php';
        create_notification($db, (int)$school['id'], 'new_message', 'New message from your website',
            $name . ' sent you a message. Open Messages to read and reply.', 'contact-messages.php');
    } catch (\Throwable $e) { error_log('message notify failed: ' . $e->getMessage()); }
}
// Deliberately no visible error for missing/invalid fields here - the
// browser's own required/type=email validation on the form already
// catches those before submission reaches this far in normal use.

header('Location: https://' . $school['slug'] . '.somahub.top/?msg_sent=1#contact');
exit;
