<?php
// Saves the school's site-wide colours (a preset palette, or custom colours)
// from the "Colours" control in any live editor. Replaces the old design-save.php:
// rules but never touches the template and answers with JSON.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/appearance.php';
$user = require_school_login();
$db = get_db();
require_once __DIR__ . '/../includes/edit-history.php';
history_track_request($db, (int)$user['school_id'], 'Changed colours', 'colors');
header('Content-Type: application/json');

$mode = ($_POST['color_mode'] ?? 'preset') === 'custom' ? 'custom' : 'preset';
$hex = fn($k) => preg_match('/^#[0-9a-fA-F]{6}$/', $_POST[$k] ?? '') ? strtoupper($_POST[$k]) : null;

if ($mode === 'preset') {
    $paletteId = (int)($_POST['palette_id'] ?? 0);
    $ok = false;
    foreach (get_active_palettes($db) as $p) { if ((int)$p['id'] === $paletteId) { $ok = true; break; } }
    if (!$ok) { echo json_encode(['ok' => false, 'error' => 'Unknown palette.']); exit; }
    $db->prepare("UPDATE schools SET palette_id = ?, color_mode = 'preset', primary_override = NULL, secondary_override = NULL, accent_override = NULL, bg_override = NULL WHERE id = ?")
       ->execute([$paletteId, $user['school_id']]);
} else {
    $p = $hex('primary'); $s = $hex('secondary'); $a = $hex('accent'); $b = $hex('bg');
    if (!$p || !$a || !$b) { echo json_encode(['ok' => false, 'error' => 'Please pick all the colours.']); exit; }
    $db->prepare("UPDATE schools SET palette_id = NULL, color_mode = 'custom', primary_override = ?, secondary_override = ?, accent_override = ?, bg_override = ? WHERE id = ?")
       ->execute([$p, $s ?: $p, $a, $b, $user['school_id']]);
}
echo json_encode(['ok' => true]);
