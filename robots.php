<?php
// Host-aware robots.txt (served for /robots.txt via .htaccess).
header('Content-Type: text/plain; charset=utf-8');
$host = strtolower($_SERVER['HTTP_HOST'] ?? 'somahub.top');
$isSchoolHost = (bool)preg_match('/^([a-z0-9-]+)\.somahub\.top$/', $host, $m) && $m[1] !== 'www';
echo "User-agent: *\nAllow: /\n";
echo "Disallow: /admin/\nDisallow: /dashboard/\nDisallow: /config/\nDisallow: /includes/\nDisallow: /uploads/\n";
if ($isSchoolHost) {
    // Form/lookup pages are not useful search results.
    echo "Disallow: /results-check.php\nDisallow: /enrollment-apply.php\nDisallow: /report-card.php\n";
    echo "\nSitemap: https://{$m[1]}.somahub.top/sitemap.xml\n";
} else {
    echo "Disallow: /search.php\n";
    echo "\nSitemap: https://somahub.top/sitemap.xml\n";
}
