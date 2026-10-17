<?php
// Host-aware sitemap. Google only accepts URLs on the same host as the
// sitemap, so somahub.top lists its own pages and each school's subdomain
// serves its own (https://school.somahub.top/sitemap.xml).
require_once __DIR__ . '/config/db.php';
$db = get_db();

$host = strtolower($_SERVER['HTTP_HOST'] ?? 'somahub.top');
$isSchoolHost = (bool)preg_match('/^([a-z0-9-]+)\.somahub\.top$/', $host, $m) && $m[1] !== 'www';

$urls = [];
if ($isSchoolHost) {
    $q = $db->prepare("SELECT slug, updated_at, verification_status, first_login_at, created_at FROM schools WHERE slug = ? AND status IN ('active','trial')");
    $q->execute([$m[1]]);
    $s = $q->fetch();
    if (!$s) { http_response_code(404); exit; }
    $urls[] = ['https://' . $s['slug'] . '.somahub.top/', $s['updated_at'] ?? null, '1.0', 'weekly'];
} else {
    $pages = [
        ['/', '1.0', 'weekly'], ['/pricing.php', '0.8', 'monthly'], ['/docs.php', '0.8', 'monthly'],
        ['/get-started.php', '0.7', 'monthly'], ['/blog/', '0.7', 'weekly'], ['/results-portal.php', '0.6', 'monthly'],
        ['/terms.php', '0.3', 'yearly'], ['/privacy.php', '0.3', 'yearly'],
    ];
    foreach ($pages as $p) $urls[] = ['https://somahub.top' . $p[0], null, $p[1], $p[2]];
    try {
        foreach ($db->query("SELECT slug, published_at FROM blog_posts WHERE status = 'published' ORDER BY published_at DESC")->fetchAll() as $b) {
            $urls[] = ['https://somahub.top/blog/' . $b['slug'], $b['published_at'], '0.6', 'monthly'];
        }
    } catch (\Throwable $e) {}
}

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $u): ?>
  <url>
    <loc><?= htmlspecialchars($u[0]) ?></loc>
<?php if (!empty($u[1])): ?>    <lastmod><?= date('Y-m-d', strtotime($u[1])) ?></lastmod>
<?php endif; ?>    <changefreq><?= $u[3] ?></changefreq>
    <priority><?= $u[2] ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
