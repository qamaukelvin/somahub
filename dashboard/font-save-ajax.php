<?php
// Saves a curated font pairing as an override on the school - site-wide,
// not per-section (fonts aren't a per-section thing), so it's reachable
// from any live editor page's "Aa" control.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/appearance.php';
$user = require_school_login();
$db = get_db();
require_once __DIR__ . '/../includes/edit-history.php';
history_track_request($db, (int)$user['school_id'], 'Changed fonts', 'fonts');

header('Content-Type: application/json');

$pairKey = trim($_POST['font_pair'] ?? '');
$pairs = get_font_pairs();

if ($pairKey === 'default') {
    // Clears both overrides - back to whatever the palette/template says.
    $update = $db->prepare("UPDATE schools SET font_display_override = NULL, font_body_override = NULL WHERE id = ?");
    $update->execute([$user['school_id']]);
    echo json_encode(['ok' => true]);
    exit;
}

if (!isset($pairs[$pairKey])) {
    echo json_encode(['ok' => false, 'errors' => ['_' => 'Unknown font pairing.']]);
    exit;
}

$pair = $pairs[$pairKey];
$update = $db->prepare("UPDATE schools SET font_display_override = ?, font_body_override = ? WHERE id = ?");
$update->execute([$pair['display'], $pair['body'], $user['school_id']]);

echo json_encode(['ok' => true]);
