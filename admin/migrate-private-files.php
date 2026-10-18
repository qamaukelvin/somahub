<?php
/**
 * One-time: move EXISTING sensitive files (ID documents, signed agreements,
 * results and attendance CSVs) out of the public uploads/ folder into
 * storage/private/ and update the database paths. Safe to re-run.
 * Public site images are NOT touched.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/private-storage.php';
require_platform_admin();
$db = get_db();
$root = realpath(__DIR__ . '/..');

$targets = [
    ['schools', 'signed_agreement_path', 'id', 'schools/{school}'],
    ['users', 'id_document_path', 'id', 'schools/{school}'],
    ['result_uploads', 'stored_path', 'id', 'results/{school}'],
    ['attendance_uploads', 'stored_path', 'id', 'attendance/{school}'],
];
$apply = $_SERVER['REQUEST_METHOD'] === 'POST';
$log = [];

foreach ($targets as [$table, $col, $pk, $subTpl]) {
    try {
        $schoolCol = $table === 'schools' ? 'id' : 'school_id';
        $rows = $db->query("SELECT `$pk` AS pk, `$col` AS p, `$schoolCol` AS sid FROM `$table` WHERE `$col` IS NOT NULL AND `$col` <> '' AND `$col` NOT LIKE '%storage/private/%'")->fetchAll();
    } catch (\Throwable $e) { $log[] = "$table: skipped (" . $e->getMessage() . ")"; continue; }
    foreach ($rows as $r) {
        $rel = ltrim($r['p'], '/');
        $src = realpath($root . '/' . $rel);
        if (!$src || strpos($src, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($src)) { $log[] = "$table #{$r['pk']}: file missing ($rel)"; continue; }
        $sid = (int)($r['sid'] ?: 0);
        $sub = str_replace('{school}', (string)$sid, $subTpl);
        $name = private_random_name(pathinfo($src, PATHINFO_FILENAME), pathinfo($src, PATHINFO_EXTENSION));
        if ($apply) {
            $dest = private_dir($sub) . $name;
            if (@rename($src, $dest) || (@copy($src, $dest) && @unlink($src))) {
                $new = PRIVATE_BASE . $sub . '/' . $name;
                $db->prepare("UPDATE `$table` SET `$col` = ? WHERE `$pk` = ?")->execute([$new, $r['pk']]);
                $log[] = "$table #{$r['pk']}: moved to $new";
            } else { $log[] = "$table #{$r['pk']}: FAILED to move $rel"; }
        } else {
            $log[] = "$table #{$r['pk']}: would move $rel";
        }
    }
}
?><!DOCTYPE html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Move sensitive files</title>
<body style="font-family:system-ui,sans-serif;max-width:720px;margin:30px auto;padding:0 16px">
<h2>Move sensitive files to private storage</h2>
<p><?= $apply ? 'Done.' : 'Preview only — nothing has been moved yet.' ?></p>
<pre style="background:#f4f4f4;padding:12px;border-radius:8px;white-space:pre-wrap"><?= htmlspecialchars($log ? implode("\n", $log) : 'Nothing to move.') ?></pre>
<?php if (!$apply && $log): ?><form method="post"><button style="padding:10px 18px">Move these files now</button></form><?php endif; ?>
<p><a href="index.php">&larr; Admin home</a></p></body>
