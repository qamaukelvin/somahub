<?php
/**
 * Quattro Homes — Image Compression Tool
 * Run once via CLI: php tools/compress_images.php
 * Or via browser at yourdomain.com/tools/compress_images.php (delete after use!)
 *
 * Converts floor1/floor2 JPEGs to optimised JPEGs + WebP copies.
 * Originals backed up to images/originals/
 */
if (!extension_loaded('gd')) die('GD extension required.');

$dirs        = [__DIR__.'/../images/floor1', __DIR__.'/../images/floor2'];
$jpegQuality = 78;
$webpQuality = 80;
$backupRoot  = __DIR__.'/../images/originals';
if (!is_dir($backupRoot)) mkdir($backupRoot, 0755, true);

$results = [];
foreach ($dirs as $dir) {
  $floor = basename($dir);
  $bup   = $backupRoot.'/'.$floor;
  if (!is_dir($bup)) mkdir($bup, 0755, true);
  foreach (glob($dir.'/*.{jpg,jpeg,JPG,JPEG}', GLOB_BRACE) as $f) {
    $name    = basename($f);
    $noext   = pathinfo($name, PATHINFO_FILENAME);
    $orig    = filesize($f);
    if (!file_exists($bup.'/'.$name)) copy($f, $bup.'/'.$name);
    $img     = @imagecreatefromjpeg($f);
    if (!$img) { $results[] = "SKIP $floor/$name"; continue; }
    imagejpeg($img, $f, $jpegQuality);
    $new     = filesize($f);
    $webpOk  = function_exists('imagewebp') && imagewebp($img, $dir.'/'.$noext.'.webp', $webpQuality);
    imagedestroy($img);
    $saved   = round(($orig-$new)/1024);
    $pct     = $orig>0 ? round((1-$new/$orig)*100) : 0;
    $results[] = "$floor/$name — {$pct}% smaller (~{$saved}KB saved)".($webpOk?' + WebP':'');
  }
}
header('Content-Type: text/plain');
echo "=== Quattro Homes Image Compression ===\n\n".implode("\n",$results)."\n\nDone. Originals in images/originals/\nDelete this file after running!\n";
