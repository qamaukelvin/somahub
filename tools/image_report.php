<?php
/**
 * Image Size Report — compares current images vs originals
 * Visit: yourdomain.com/tools/image_report.php (delete after use)
 */
$dirs = [
    'floor1' => [__DIR__.'/../images/floor1', __DIR__.'/../images/originals/floor1'],
    'floor2' => [__DIR__.'/../images/floor2', __DIR__.'/../images/originals/floor2'],
];

$totalCurrent  = 0;
$totalOriginal = 0;
$rows = [];

foreach ($dirs as $floor => [$current, $originals]) {
    foreach (glob($current.'/*.{jpg,jpeg,JPG,JPEG}', GLOB_BRACE) as $file) {
        $name     = basename($file);
        $curSize  = filesize($file);
        $origFile = $originals.'/'.$name;
        $origSize = file_exists($origFile) ? filesize($origFile) : null;
        $saved    = $origSize ? $origSize - $curSize : null;
        $pct      = ($origSize && $origSize > 0) ? round((1 - $curSize/$origSize)*100) : null;
        $webp     = file_exists($current.'/'.pathinfo($name,PATHINFO_FILENAME).'.webp');
        $totalCurrent  += $curSize;
        $totalOriginal += $origSize ?? $curSize;
        $rows[] = [$floor, $name, $curSize, $origSize, $saved, $pct, $webp];
    }
}

$totalSaved = $totalOriginal - $totalCurrent;
$totalPct   = $totalOriginal > 0 ? round((1 - $totalCurrent/$totalOriginal)*100) : 0;

function fmt($bytes) {
    if ($bytes === null) return '—';
    if ($bytes >= 1048576) return round($bytes/1048576,1).' MB';
    return round($bytes/1024).' KB';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Image Report | Quattro Homes</title>
<style>
  body { font-family: system-ui, sans-serif; padding: 28px; color: #1a1a1a; background: #f5f5f5; }
  h1 { font-size: 1.4rem; margin-bottom: 6px; }
  .summary { display: flex; gap: 20px; margin: 18px 0 28px; flex-wrap: wrap; }
  .stat { background: #fff; border: 1px solid #e0e0e0; border-radius: 6px; padding: 16px 22px; min-width: 160px; }
  .stat strong { display: block; font-size: 1.6rem; color: #1c3626; }
  .stat span { font-size: 0.8rem; color: #777; text-transform: uppercase; letter-spacing: 0.05em; }
  table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 6px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
  th { background: #1c3626; color: rgba(245,237,224,0.85); padding: 10px 14px; text-align: left; font-size: 0.78rem; letter-spacing: 0.06em; text-transform: uppercase; }
  td { padding: 9px 14px; border-bottom: 1px solid #eee; font-size: 0.87rem; }
  tr:last-child td { border-bottom: none; }
  tr:hover td { background: #fafafa; }
  .good  { color: #1a6b2a; font-weight: 600; }
  .none  { color: #999; font-style: italic; }
  .badge { display: inline-block; padding: 2px 7px; border-radius: 10px; font-size: 0.72rem; font-weight: 600; }
  .webp-yes { background: #e6f4ea; color: #1a6b2a; border: 1px solid #a8d5b0; }
  .webp-no  { background: #f5f5f5; color: #999; border: 1px solid #ddd; }
  .warn { background: #fff8e1; border: 1px solid #f5d88a; padding: 12px 18px; border-radius: 6px; margin-bottom: 24px; font-size: 0.88rem; color: #7a5c0a; }
</style>
</head>
<body>
<h1>Image Size Report</h1>
<p style="color:#777;font-size:0.88rem;">Compares current images to originals in <code>images/originals/</code>. Delete this file after reviewing.</p>

<?php if (!is_dir(__DIR__.'/../images/originals')): ?>
<div class="warn">No <code>images/originals/</code> folder found. Run <code>compress_images.php</code> first to compress images and create backups.</div>
<?php endif; ?>

<div class="summary">
  <div class="stat"><strong><?php echo fmt($totalOriginal); ?></strong><span>Original total</span></div>
  <div class="stat"><strong><?php echo fmt($totalCurrent); ?></strong><span>Current total</span></div>
  <div class="stat"><strong class="good"><?php echo fmt($totalSaved); ?> saved</strong><span>Total reduction</span></div>
  <div class="stat"><strong class="good"><?php echo $totalPct; ?>%</strong><span>Smaller overall</span></div>
</div>

<table>
  <thead>
    <tr>
      <th>Floor</th>
      <th>File</th>
      <th>Current size</th>
      <th>Original size</th>
      <th>Saved</th>
      <th>Reduction</th>
      <th>WebP</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($rows as [$floor, $name, $cur, $orig, $saved, $pct, $webp]): ?>
    <tr>
      <td><?php echo htmlspecialchars($floor); ?></td>
      <td><?php echo htmlspecialchars($name); ?></td>
      <td><?php echo fmt($cur); ?></td>
      <td><?php echo fmt($orig); ?></td>
      <td class="<?php echo $saved > 0 ? 'good' : 'none'; ?>"><?php echo $saved !== null ? fmt($saved) : 'No original'; ?></td>
      <td class="<?php echo $pct !== null && $pct > 0 ? 'good' : 'none'; ?>"><?php echo $pct !== null ? $pct.'%' : '—'; ?></td>
      <td><span class="badge <?php echo $webp ? 'webp-yes' : 'webp-no'; ?>"><?php echo $webp ? 'Yes' : 'No'; ?></span></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</body>
</html>
