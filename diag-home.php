<?php
// TEMPORARY: open https://somahub.top/diag-home.php to see why the homepage returns 500. DELETE after use.
ini_set('display_errors', '1'); error_reporting(E_ALL);
header('Content-Type: text/plain');
function step($name, $fn) { try { $r = $fn(); echo "OK   $name" . ($r !== null ? " => $r" : '') . "\n"; } catch (Throwable $e) { echo "FAIL $name: " . get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ")\n"; } }
echo 'PHP ' . PHP_VERSION . "\n";
step('consent.php', function () { require_once __DIR__ . '/includes/consent.php'; });
step('db.php', function () { require_once __DIR__ . '/config/db.php'; });
step('reviews.php', function () { require_once __DIR__ . '/includes/reviews.php'; });
step('connect', function () { global $db; $db = get_db(); return 'connected'; });
step('schools query', function () { global $db; return count($db->query("SELECT name, slug, plan, county, verification_status FROM schools WHERE status IN ('active','trial') AND slug <> 'demo' ORDER BY created_at DESC LIMIT 60")->fetchAll()) . ' rows'; });
step('count query', function () { global $db; return $db->query("SELECT COUNT(*) c FROM schools WHERE status IN ('active','trial') AND slug <> 'demo'")->fetch()['c']; });
step('reviews query', function () { global $db; return count(get_approved_reviews($db, 'platform', null, 12)) . ' rows'; });
step('_public_nav.php', function () { $navRoot = '.'; ob_start(); include __DIR__ . '/_public_nav.php'; return strlen(ob_get_clean()) . ' bytes'; });
step('_chat_widget.php', function () { ob_start(); include __DIR__ . '/_chat_widget.php'; return strlen(ob_get_clean()) . ' bytes'; });
step('_loader.php', function () { ob_start(); include __DIR__ . '/_loader.php'; return strlen(ob_get_clean()) . ' bytes'; });
$log = ini_get('error_log'); echo "\nerror_log: $log\n";
if ($log && is_readable($log)) { $l = file($log); echo "--- last lines ---\n" . implode('', array_slice($l, -15)); }
