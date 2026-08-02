<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
$user = require_school_login();
$db = get_db();

header('Content-Type: application/json');

$id = (int)($_POST['section_id'] ?? 0);

$stmt = $db->prepare("
    SELECT ss.*, st.schema_json
    FROM site_sections ss
    JOIN section_types st ON st.id = ss.section_type_id
    WHERE ss.id = ? AND ss.school_id = ?
");
$stmt->execute([$id, $user['school_id']]);
$section = $stmt->fetch();

if (!$section) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'errors' => ['_general' => 'Section not found.']]);
    exit;
}

$schema = json_decode($section['schema_json'], true);
$content = json_decode($section['content_json'], true);

$newContent = [];
$errors = [];

foreach ($schema as $field => $fieldType) {
    if ($fieldType === 'image') continue;
    $newContent[$field] = trim($_POST[$field] ?? '');
}

foreach ($schema as $field => $fieldType) {
    if ($fieldType !== 'image') continue;

    if (!empty($_FILES[$field]['tmp_name'])) {
        $tmpPath = $_FILES[$field]['tmp_name'];
        $fileSize = $_FILES[$field]['size'];

        if ($fileSize > 5 * 1024 * 1024) {
            $errors[$field] = 'File is too large. Maximum size is 5MB.';
            $newContent[$field] = $content[$field] ?? '';
            continue;
        }

        $imageInfo = @getimagesize($tmpPath);
        if ($imageInfo === false) {
            $errors[$field] = 'That file is not a valid image.';
            $newContent[$field] = $content[$field] ?? '';
            continue;
        }

        $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = $imageInfo['mime'];
        if (!isset($allowedMimes[$mime])) {
            $errors[$field] = 'Only JPG, PNG, or WEBP images are allowed.';
            $newContent[$field] = $content[$field] ?? '';
            continue;
        }

        $ext = $allowedMimes[$mime];
        $safeName = 'sec_' . $section['id'] . '_' . $field . '_' . time() . '.' . $ext;
        $destDir = __DIR__ . '/../uploads/schools/' . $user['school_id'] . '/';
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);
        move_uploaded_file($tmpPath, $destDir . $safeName);

        $relPath = 'uploads/schools/' . $user['school_id'] . '/' . $safeName;
        $newContent[$field] = $relPath;

        $db->prepare("INSERT INTO media (school_id, uploaded_by_user_id, file_path, file_type, file_size_bytes) VALUES (?,?,?,?,?)")
           ->execute([$user['school_id'], $user['id'], $relPath, $mime, $fileSize]);
    } else {
        $newContent[$field] = $content[$field] ?? '';
    }
}

if ($errors) {
    echo json_encode(['ok' => false, 'errors' => $errors]);
    exit;
}

$update = $db->prepare("UPDATE site_sections SET content_json = ? WHERE id = ? AND school_id = ?");
$update->execute([json_encode($newContent), $section['id'], $user['school_id']]);

log_content_change($db, $user['school_id'], $user['id'], 'section', $section['id'], 'update', $content, $newContent);

echo json_encode(['ok' => true, 'content' => $newContent]);
