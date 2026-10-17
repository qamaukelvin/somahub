<?php
// One-off: shrinks images uploaded before automatic optimisation existed.
// Keeps each file's name and format so no page needs updating. Processes a
// batch per click, so it is safe on shared hosting. Platform admin only.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/image-upload.php';
require_platform_admin();
set_time_limit(120);
$root = realpath(__DIR__ . '/../uploads/schools');
$msg = '';
$dry = $root ? [] : null;
$todo = []; $saved = 0; $done = 0;
if ($root) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $ext = strtolower($f->getExtension());
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) continue;
        $info = @getimagesize($f->getPathname());
        if (!$info) continue;
        if (max($info[0], $info[1]) > IMG_MAX_DIM || $f->getSize() > 600 * 1024) $todo[] = $f->getPathname();
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $todo) {
    foreach (array_slice($todo, 0, 40) as $path) {
        $mime = @getimagesize($path)['mime'] ?? '';
        $before = filesize($path);
        $im = img_load($path, $mime);
        if (!$im) continue;
        $im = img_fit($im);
        $tmp = $path . '.opt';
        $ok = false;
        if ($mime === 'image/png') { imagealphablending($im, false); imagesavealpha($im, true); $ok = @imagepng($im, $tmp, 7); }
        elseif ($mime === 'image/webp') $ok = @imagewebp($im, $tmp, IMG_QUALITY);
        else $ok = @imagejpeg($im, $tmp, IMG_QUALITY);
        imagedestroy($im);
        if ($ok && filesize($tmp) < $before) { rename($tmp, $path); $saved += $before - filesize($path); $done++; }
        else @unlink($tmp);
    }
    $msg = "Optimised $done image(s), saved " . round($saved / 1048576, 1) . ' MB.';
    header('Location: optimize-images.php?m=' . urlencode($msg)); exit;
}
if (!empty($_GET['m'])) $msg = $_GET['m'];
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Optimise images</title>
<?php include __DIR__ . '/_styles.php'; ?></head><body>
<?php include __DIR__ . '/_nav.php'; ?>
<main class="wrap" style="max-width:640px;">
  <h1>Optimise existing images</h1>
  <p style="color:#666;margin:6px 0 18px;">New uploads are already resized and converted automatically. This shrinks older, oversized photos in place (max <?= IMG_MAX_DIM ?>px, same file names).</p>
  <?php if ($msg): ?><p style="background:#DCEFE1;color:#1B4D3E;padding:10px 14px;border-radius:8px;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
  <p><strong><?= count($todo) ?></strong> image(s) still need optimising.</p>
  <?php if ($todo): ?><form method="post" style="margin-top:14px;"><button class="btn" type="submit">Optimise next 40</button></form><?php endif; ?>
</main></body></html>
