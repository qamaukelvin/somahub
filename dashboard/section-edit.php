<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
$user = require_school_login();
$db = get_db();

$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT ss.*, st.label, st.schema_json
    FROM site_sections ss
    JOIN section_types st ON st.id = ss.section_type_id
    WHERE ss.id = ? AND ss.school_id = ?
");
$stmt->execute([$id, $user['school_id']]);
$section = $stmt->fetch();

if (!$section) {
    die('Section not found.');
}

$schema = json_decode($section['schema_json'], true);   // {"headline":"text","body":"textarea","photo":"image", ...}
$content = json_decode($section['content_json'], true);

$saved = false;
$uploadErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newContent = [];
    foreach ($schema as $field => $fieldType) {
        if ($fieldType === 'image') continue; // handled separately below
        $newContent[$field] = trim($_POST[$field] ?? '');
    }

    // Handle image upload if a file was submitted for an 'image' field
    foreach ($schema as $field => $fieldType) {
        if ($fieldType === 'image' && !empty($_FILES[$field]['tmp_name'])) {
            $tmpPath = $_FILES[$field]['tmp_name'];
            $originalName = $_FILES[$field]['name'];
            $fileSize = $_FILES[$field]['size'];

            // 1. Size limit — 5MB
            if ($fileSize > 5 * 1024 * 1024) {
                $uploadErrors[$field] = 'File is too large. Maximum size is 5MB.';
                continue;
            }

            // 2. Verify this is actually an image, not just a file with an image-like extension.
            //    getimagesize() reads real file content — a renamed .php file will fail this check.
            $imageInfo = @getimagesize($tmpPath);
            if ($imageInfo === false) {
                $uploadErrors[$field] = 'That file is not a valid image.';
                continue;
            }

            // 3. Whitelist allowed image types by their real detected MIME type
            $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $mime = $imageInfo['mime'];
            if (!isset($allowedMimes[$mime])) {
                $uploadErrors[$field] = 'Only JPG, PNG, or WEBP images are allowed.';
                continue;
            }

            $ext = $allowedMimes[$mime]; // use the extension matching the REAL file type, ignore the uploaded filename's extension
            $safeName = 'sec_' . $section['id'] . '_' . $field . '_' . time() . '.' . $ext;
            $destDir = __DIR__ . '/../uploads/schools/' . $user['school_id'] . '/';
            if (!is_dir($destDir)) mkdir($destDir, 0755, true);
            move_uploaded_file($tmpPath, $destDir . $safeName);

            // Relative path (no leading slash) so this resolves correctly regardless of
            // what subfolder the app is deployed in — see the earlier absolute-path bug.
            $relPath = 'uploads/schools/' . $user['school_id'] . '/' . $safeName;
            $newContent[$field] = $relPath;

            $db->prepare("INSERT INTO media (school_id, uploaded_by_user_id, file_path, file_type, file_size_bytes) VALUES (?,?,?,?,?)")
               ->execute([$user['school_id'], $user['id'], $relPath, $mime, $fileSize]);
        } elseif ($fieldType === 'image') {
            $newContent[$field] = $content[$field] ?? ''; // keep existing if no new upload
        }
    }

    $update = $db->prepare("UPDATE site_sections SET content_json = ? WHERE id = ? AND school_id = ?");
    $update->execute([json_encode($newContent), $section['id'], $user['school_id']]);

    if (empty($uploadErrors)) {
        log_content_change($db, $user['school_id'], $user['id'], 'section', $section['id'], 'update', $content, $newContent);
    }

    $content = $newContent;
    $saved = empty($uploadErrors);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit <?= htmlspecialchars($section['label']) ?></title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .field{margin-bottom:20px;}
  label{display:block;font-size:0.85rem;font-weight:600;margin-bottom:6px;color:#333;}
  input[type=text], textarea{width:100%;padding:10px;border:1px solid #ccc;border-radius:4px;font-family:inherit;box-sizing:border-box;}
  textarea{min-height:110px;}
  .current-img{max-width:200px;border-radius:6px;margin-bottom:8px;display:block;}
  .saved-msg{background:#E4F0E7;color:#1B4D3E;padding:10px 14px;border-radius:6px;margin-bottom:20px;font-size:0.88rem;}
  .error-msg{background:#FBE8E4;color:#8C3B2E;padding:10px 14px;border-radius:6px;margin-bottom:12px;font-size:0.88rem;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>Edit: <?= htmlspecialchars($section['label']) ?></h1>
  <p class="sub"><a href="sections.php">&larr; Back to all sections</a></p>

  <?php if ($saved): ?><div class="saved-msg">Saved successfully.</div><?php endif; ?>
  <?php foreach ($uploadErrors as $field => $err): ?>
    <div class="error-msg"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $field))) ?>: <?= htmlspecialchars($err) ?></div>
  <?php endforeach; ?>
  <?php if ($uploadErrors && !$saved): ?><div class="error-msg">Other changes were saved, but the file(s) above were not uploaded. Please fix and try again.</div><?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <?php foreach ($schema as $field => $fieldType): ?>
      <div class="field">
        <label><?= htmlspecialchars(ucwords(str_replace('_', ' ', $field))) ?></label>

        <?php if ($fieldType === 'textarea'): ?>
          <textarea name="<?= $field ?>"><?= htmlspecialchars($content[$field] ?? '') ?></textarea>

        <?php elseif ($fieldType === 'image'): ?>
          <?php if (!empty($content[$field])): ?>
            <img class="current-img" src="../<?= htmlspecialchars($content[$field]) ?>" alt="">
          <?php endif; ?>
          <input type="file" name="<?= $field ?>" accept="image/*">

        <?php else: ?>
          <input type="text" name="<?= $field ?>" value="<?= htmlspecialchars($content[$field] ?? '') ?>">
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <button type="submit" class="btn">Save Changes</button>
  </form>
</main>
</body>
</html>
