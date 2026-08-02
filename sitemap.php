<?php
require_once __DIR__ . '/config/db.php';
$db = get_db();

header('Content-Type: application/xml; charset=utf-8');

$schools = $db->query("
    SELECT slug, updated_at FROM schools
    WHERE status IN ('active','trial')
    ORDER BY created_at DESC
")->fetchAll();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://somahub.top/</loc>
    <priority>1.0</priority>
  </url>
  <url>
    <loc>https://somahub.top/results-portal.php</loc>
    <priority>0.6</priority>
  </url>
  <url>
    <loc>https://somahub.top/terms.php</loc>
    <priority>0.3</priority>
  </url>
  <url>
    <loc>https://somahub.top/privacy.php</loc>
    <priority>0.3</priority>
  </url>
  <?php foreach ($schools as $s): ?>
  <url>
    <loc>https://<?= htmlspecialchars($s['slug']) ?>.somahub.top/</loc>
    <?php if (!empty($s['updated_at'])): ?><lastmod><?= date('Y-m-d', strtotime($s['updated_at'])) ?></lastmod><?php endif; ?>
    <priority>0.8</priority>
  </url>
  <?php endforeach; ?>
</urlset>
