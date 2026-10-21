<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
$db = get_db();
$db->exec("CREATE TABLE IF NOT EXISTS content_sql_runs (id INT AUTO_INCREMENT PRIMARY KEY, school_id INT NOT NULL, sql_text MEDIUMTEXT NOT NULL, snapshot_json LONGTEXT NULL, statements INT NOT NULL DEFAULT 0, rows_changed INT NOT NULL DEFAULT 0, restored TINYINT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (school_id))");

$schools = $db->query("SELECT id, name, slug, county, phone FROM schools ORDER BY created_at DESC LIMIT 500")->fetchAll();
$schoolId = (int)($_POST['school_id'] ?? $_GET['school_id'] ?? 0);
$school = null; foreach ($schools as $s) if ((int)$s['id'] === $schoolId) $school = $s;
$sqlText = (string)($_POST['sql'] ?? '');
$mode = $_POST['mode'] ?? '';
$report = []; $error = ''; $notice = '';

const CSQL_WRITE_TABLES = ['site_sections'];
const CSQL_READ_TABLES  = ['site_sections', 'section_types', 'schools'];

/** Strip comments and split on ; outside quotes. */
function csql_split(string $sql): array {
    $out = []; $cur = ''; $q = ''; $n = strlen($sql);
    for ($i = 0; $i < $n; $i++) {
        $c = $sql[$i]; $d = $sql[$i+1] ?? '';
        if ($q === '') {
            if ($c === '-' && $d === '-' && (($sql[$i+2] ?? ' ') === ' ' || ($sql[$i+2] ?? '') === "\n")) { while ($i < $n && $sql[$i] !== "\n") $i++; continue; }
            if ($c === '#') { while ($i < $n && $sql[$i] !== "\n") $i++; continue; }
            if ($c === '/' && $d === '*') { $e = strpos($sql, '*/', $i + 2); $i = $e === false ? $n : $e + 1; continue; }
            if ($c === "'" || $c === '"' || $c === '`') { $q = $c; $cur .= $c; continue; }
            if ($c === ';') { if (trim($cur) !== '') $out[] = trim($cur); $cur = ''; continue; }
            $cur .= $c;
        } else {
            $cur .= $c;
            if ($c === '\\' && $q !== '`') { $cur .= $d; $i++; continue; }
            if ($c === $q) { if ($d === $q) { $cur .= $d; $i++; } else $q = ''; }
        }
    }
    if (trim($cur) !== '') $out[] = trim($cur);
    return $out;
}
/** Statement with quoted strings blanked, for keyword/table checks. */
function csql_bare(string $st): string {
    return preg_replace(['/\'(?:[^\'\\\\]|\\\\.|\'\')*\'/s', '/"(?:[^"\\\\]|\\\\.|"")*"/s'], ["''", '""'], $st);
}
function csql_validate(string $st, int $schoolId): ?string {
    $bare = strtolower(csql_bare($st));
    if (!preg_match('/^\s*(update|insert|select)\b/', $bare, $m)) return 'Only UPDATE, INSERT and SELECT are allowed.';
    $verb = $m[1];
    if (preg_match('/\b(drop|alter|truncate|create|grant|revoke|rename|load_file|outfile|dumpfile|infile|sleep|benchmark|call|handler|delete|replace|set\s+global|information_schema)\b/', $bare, $b)) return 'Not allowed: ' . strtoupper($b[1]) . '.';
    if (strpos($bare, '`') !== false) {/* allow backticks around known names only */}
    preg_match_all('/\b(?:from|join|update|into)\s+`?([a-z_][a-z0-9_]*)`?/', $bare, $t);
    $tables = array_unique($t[1]);
    if (!$tables) return 'Could not find a table name.';
    foreach ($tables as $tb) {
        $ok = $verb === 'select' ? in_array($tb, CSQL_READ_TABLES, true) : in_array($tb, array_merge(CSQL_WRITE_TABLES, ['section_types']), true);
        if (!$ok) return "Table '$tb' is not allowed here.";
    }
    if ($verb !== 'select') {
        if (preg_match('/\bor\b/', $bare)) return 'OR is not allowed in a write, it could touch other schools.';
        if (!in_array('site_sections', $tables, true)) return 'Writes must target site_sections.';
        if ($verb === 'update' && !preg_match('/^\s*update\s+`?site_sections`?/', $bare)) return 'UPDATE must start with site_sections (you can JOIN section_types after it).';
        if (!preg_match('/school_id\s*=\s*' . $schoolId . '\b/', $bare) && !($verb === 'insert' && preg_match('/\b' . $schoolId . '\b/', $bare))) return 'Statement must be limited to this school (school_id = ' . $schoolId . ').';
    }
    return null;
}

if ($mode === 'restore' && $school) {
    $run = $db->prepare("SELECT * FROM content_sql_runs WHERE id = ? AND school_id = ? AND restored = 0");
    $run->execute([(int)($_POST['run_id'] ?? 0), $schoolId]); $run = $run->fetch();
    if (!$run || !$run['snapshot_json']) { $error = 'Nothing to restore for that run.'; }
    else {
        try {
            $snap = json_decode($run['snapshot_json'], true) ?: [];
            $db->beginTransaction();
            $ids = [];
            $hasVar = db_has_column($db, 'site_sections', 'layout_variant');
            foreach ($snap as $r) {
                $ids[] = (int)$r['id'];
                $db->prepare("UPDATE site_sections SET content_json = ?, position = ?, is_visible = ?" . ($hasVar ? ", layout_variant = ?" : "") . " WHERE id = ? AND school_id = ?")
                   ->execute(array_merge([$r['content_json'], $r['position'], $r['is_visible']], $hasVar ? [$r['layout_variant']] : [], [$r['id'], $schoolId]));
            }
            if ($ids) $db->prepare("DELETE FROM site_sections WHERE school_id = ? AND id NOT IN (" . implode(',', $ids) . ")")->execute([$schoolId]);
            $db->prepare("UPDATE content_sql_runs SET restored = 1 WHERE id = ?")->execute([$run['id']]);
            $db->commit();
            $notice = 'Restored this school to how it was before that run.';
        } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); $error = 'Restore failed: ' . $e->getMessage(); }
    }
} elseif (($mode === 'dry' || $mode === 'apply') && $school) {
    require_once __DIR__ . '/../includes/school-provision.php';
    $subs = ['{school_id}' => (string)$schoolId, '{school_name}' => str_replace("'", "''", $school['name']), '{county}' => str_replace("'", "''", (string)$school['county']), '{phone}' => str_replace("'", "''", (string)$school['phone'])];
    $prepared = strtr($sqlText, $subs);
    $stmts = csql_split($prepared);
    if (!$stmts) $error = 'Paste some SQL first.';
    elseif (count($stmts) > 60) $error = 'Too many statements (max 60 at a time).';
    else {
        foreach ($stmts as $i => $st) { $v = csql_validate($st, $schoolId); if ($v) { $error = 'Statement ' . ($i + 1) . ': ' . $v; break; } }
    }
    if (!$error) {
        $snapRows = $db->prepare("SELECT * FROM site_sections WHERE school_id = ?"); $snapRows->execute([$schoolId]); $snapRows = $snapRows->fetchAll(PDO::FETCH_ASSOC);
        $total = 0;
        try {
            $db->beginTransaction();
            foreach ($stmts as $i => $st) {
                $isSel = stripos(ltrim($st), 'select') === 0;
                $q = $db->query($st);
                if ($isSel) { $rows = $q->fetchAll(PDO::FETCH_ASSOC); $report[] = ['n' => $i + 1, 'sql' => $st, 'rows' => array_slice($rows, 0, 30), 'count' => count($rows), 'select' => true]; }
                else { $c = $q->rowCount(); if ($c > 60) throw new RuntimeException('Statement ' . ($i + 1) . " would change $c rows, more than expected for one school. Nothing was saved."); $total += $c; $report[] = ['n' => $i + 1, 'sql' => $st, 'count' => $c]; }
            }
            // Every section must still hold valid JSON.
            $bad = $db->prepare("SELECT id FROM site_sections WHERE school_id = ? AND (content_json IS NULL OR JSON_VALID(content_json) = 0) LIMIT 1");
            $bad->execute([$schoolId]);
            if ($bad->fetchColumn()) throw new RuntimeException('That would leave a section with invalid JSON. Nothing was saved. Check quotes and JSON_SET paths.');
            if ($mode === 'apply') {
                $db->prepare("INSERT INTO content_sql_runs (school_id, sql_text, snapshot_json, statements, rows_changed) VALUES (?,?,?,?,?)")->execute([$schoolId, $sqlText, json_encode($snapRows), count($stmts), $total]);
                $db->commit();
                $notice = "Applied: $total row(s) changed across " . count($stmts) . " statement(s). View the site to check it. You can undo below.";
            } else { $db->rollBack(); $notice = "Dry run only, nothing was saved. $total row(s) would change."; }
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $error = $e->getMessage(); $report = [];
        }
    }
} elseif ($mode && !$school) { $error = 'Choose a school first.'; }

$fieldsRef = [];
if ($school) {
    $q = $db->prepare("SELECT st.key_name, st.schema_json, ss.position, ss.is_visible FROM site_sections ss JOIN section_types st ON st.id = ss.section_type_id WHERE ss.school_id = ? ORDER BY ss.position");
    $q->execute([$schoolId]);
    foreach ($q->fetchAll() as $r) { $f = json_decode($r['schema_json'], true) ?: []; $fieldsRef[] = ['key' => $r['key_name'], 'fields' => $f, 'visible' => (int)$r['is_visible']]; }
    $runs = $db->prepare("SELECT id, statements, rows_changed, restored, created_at FROM content_sql_runs WHERE school_id = ? ORDER BY id DESC LIMIT 8");
    $runs->execute([$schoolId]); $runs = $runs->fetchAll();
} else $runs = [];
$ctx = '';
foreach ($fieldsRef as $r) { $ctx .= $r['key'] . ': ' . implode(', ', array_map(fn($k, $t) => $k . ($t === 'image' ? ' (image, leave alone)' : ''), array_keys($r['fields']), $r['fields'])) . "\n"; }
$example = "-- Example: rewrite the hero and about text for this school\nUPDATE site_sections ss JOIN section_types st ON st.id = ss.section_type_id\nSET ss.content_json = JSON_SET(ss.content_json,\n  '\$.headline', 'Welcome to {school_name}',\n  '\$.subheading', 'Your text here')\nWHERE ss.school_id = {school_id} AND st.key_name = 'hero';";
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Content SQL - Admin</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
 .cs{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(0,1fr);gap:20px;align-items:start;} @media(max-width:900px){.cs{grid-template-columns:1fr;}}
 .panel{background:#fff;border:1px solid #E5DFCC;border-radius:12px;padding:18px;margin-bottom:16px;}
 .panel h3{margin:0 0 10px;font-size:1rem;} select,textarea{width:100%;box-sizing:border-box;border:1px solid #ccc;border-radius:8px;padding:10px;font-family:inherit;}
 textarea.sql{font-family:'Space Mono',monospace;font-size:.82rem;min-height:300px;line-height:1.5;}
 .ok{background:#E4F0E7;color:#1B4D3E;padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:.9rem;} .er{background:#FBE8E4;color:#8C3B2E;padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:.9rem;}
 .btns{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;} .b2{background:#F4F1E6;color:#0F5257;border:1px solid #D9D2BA;border-radius:8px;padding:10px 18px;font-weight:700;cursor:pointer;}
 pre{background:#0F2A2C;color:#CFE3E3;border-radius:8px;padding:12px;overflow:auto;font-size:.76rem;max-height:340px;margin:0;white-space:pre-wrap;}
 table.t{width:100%;border-collapse:collapse;font-size:.82rem;} table.t td,table.t th{padding:6px 8px;border-bottom:1px solid #eee;text-align:left;vertical-align:top;}
 .rep{font-size:.8rem;border-left:3px solid #0F5257;padding:4px 10px;margin:6px 0;background:#faf8f0;} .rep code{display:block;white-space:pre-wrap;color:#555;margin-top:3px;}
</style></head><body>
<?php include __DIR__ . '/_nav.php'; ?>
<main class="wrap">
<h1>Content SQL</h1>
<p style="color:#6E6A5C;max-width:760px;margin-bottom:18px;">Paste SQL that rewrites one school's page content, so each lead's site gets its own unique text. Run a <b>dry run</b> first. Every apply saves a snapshot you can undo.</p>
<?php if ($error): ?><div class="er"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($notice): ?><div class="ok"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
<div class="cs"><div>
<form method="POST" class="panel">
  <h3>1. School</h3>
  <select name="school_id" onchange="if(!document.getElementById('sqlBox').value.trim()||confirm('Switch school?'))location='content-sql.php?school_id='+this.value;">
    <option value="">Choose a school</option>
    <?php foreach ($schools as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)$s['id'] === $schoolId ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['slug']) ?>)</option><?php endforeach; ?>
  </select>
  <h3 style="margin-top:16px;">2. SQL</h3>
  <textarea class="sql" id="sqlBox" name="sql" spellcheck="false" placeholder="Paste SQL from your content chat here"><?= htmlspecialchars($sqlText) ?></textarea>
  <p style="font-size:.8rem;color:#777;margin:8px 0 0;">Placeholders: <code>{school_id}</code> <code>{school_name}</code> <code>{county}</code> <code>{phone}</code>. Allowed: UPDATE and INSERT on <code>site_sections</code>, SELECT to look. Each write must include <code>school_id = {school_id}</code>.</p>
  <div class="btns">
    <button class="b2" name="mode" value="dry">Dry run (saves nothing)</button>
    <button class="btn" name="mode" value="apply" onclick="return confirm('Apply this SQL to the school now? A snapshot is saved so you can undo.')">Apply</button>
    <button type="button" class="b2" onclick="document.getElementById('sqlBox').value=<?= htmlspecialchars(json_encode($example), ENT_QUOTES) ?>">Insert example</button>
  </div>
</form>
<?php if ($report): ?><div class="panel"><h3>Result</h3>
<?php foreach ($report as $r): ?><div class="rep"><b>Statement <?= $r['n'] ?></b>: <?= !empty($r['select']) ? $r['count'] . ' row(s) returned' : $r['count'] . ' row(s) affected' ?><code><?= htmlspecialchars(mb_strimwidth($r['sql'], 0, 220, '...')) ?></code>
<?php if (!empty($r['select']) && $r['rows']): ?><div style="overflow:auto;margin-top:6px;"><table class="t"><tr><?php foreach (array_keys($r['rows'][0]) as $h): ?><th><?= htmlspecialchars($h) ?></th><?php endforeach; ?></tr><?php foreach ($r['rows'] as $row): ?><tr><?php foreach ($row as $v): ?><td><?= htmlspecialchars(mb_strimwidth((string)$v, 0, 160, '...')) ?></td><?php endforeach; ?></tr><?php endforeach; ?></table></div><?php endif; ?></div><?php endforeach; ?></div><?php endif; ?>
<?php if ($school && $runs): ?><div class="panel"><h3>History and undo</h3><table class="t">
<?php foreach ($runs as $r): ?><tr><td><?= htmlspecialchars($r['created_at']) ?></td><td><?= (int)$r['statements'] ?> stmt, <?= (int)$r['rows_changed'] ?> row(s)</td><td><?php if ($r['restored']): ?>Undone<?php else: ?><form method="POST" style="display:inline"><input type="hidden" name="school_id" value="<?= $schoolId ?>"><input type="hidden" name="run_id" value="<?= (int)$r['id'] ?>"><button class="b2" name="mode" value="restore" onclick="return confirm('Put the school back to how it was before this run? Later runs will be overwritten too.')">Undo this run</button></form><?php endif; ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
</div><div>
<div class="panel"><h3>This school's fields</h3>
<?php if (!$school): ?><p style="font-size:.88rem;color:#777;">Choose a school to see its sections and fields.</p><?php else: ?>
<p style="font-size:.82rem;color:#777;margin-bottom:8px;">Paste this into your content chat so it uses the exact field names. <?= htmlspecialchars($school['name']) ?> (id <?= $schoolId ?>)</p>
<pre id="ctx"><?= htmlspecialchars($ctx) ?></pre>
<div class="btns"><button type="button" class="b2" onclick="navigator.clipboard.writeText(document.getElementById('ctx').innerText).then(()=>this.textContent='Copied')">Copy fields</button><a class="b2" style="text-decoration:none;" target="_blank" href="https://<?= htmlspecialchars($school['slug']) ?>.somahub.top/">View site</a></div>
<?php endif; ?></div>
</div></div>
</main></body></html>
