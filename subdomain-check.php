<?php
require_once __DIR__ . '/config/db.php';
$db = get_db();
header('Content-Type: application/json');

$slug = strtolower(preg_replace('/[^a-z0-9]/', '', $_GET['slug'] ?? ''));

// A short list of reserved words that shouldn't be claimable as subdomains
$reserved = ['www', 'admin', 'dashboard', 'blog', 'api', 'mail', 'ftp', 'somahub', 'app', 'test', 'staging'];

if (strlen($slug) < 3 || in_array($slug, $reserved, true)) {
    echo json_encode(['available' => false]);
    exit;
}

$stmt = $db->prepare("SELECT id FROM schools WHERE slug = ?");
$stmt->execute([$slug]);
echo json_encode(['available' => !$stmt->fetch()]);
