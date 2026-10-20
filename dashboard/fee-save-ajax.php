<?php
// AJAX endpoint for the Fees live editor. Mirrors fees.php's rules (paid-plan
// only, one row per grade+term, audit-logged) but edits a row by id.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/plan.php';
require_once __DIR__ . '/../includes/audit.php';
$user = require_school_login();
$db = get_db();
header('Content-Type: application/json');

function fee_fail(string $m, int $code = 400): void { http_response_code($code); echo json_encode(['ok' => false, 'error' => $m]); exit; }

$sch = $db->prepare("SELECT plan, promo_ends_at FROM schools WHERE id = ?");
$sch->execute([$user['school_id']]);
// Locked schools may still draft fees in the editor; the public site hides the section until the plan is active.
$sch->fetch();

$action = $_POST['action'] ?? '';
$sid = (int)$user['school_id'];
$getRow = function (int $id) use ($db, $sid) {
    $q = $db->prepare("SELECT * FROM fee_structures WHERE id = ? AND school_id = ?");
    $q->execute([$id, $sid]);
    return $q->fetch();
};
$clean = fn($v) => mb_substr(trim((string)$v), 0, 100);

if ($action === 'delete') {
    $old = $getRow((int)($_POST['id'] ?? 0));
    if (!$old) fee_fail('Fee not found.', 404);
    $db->prepare("DELETE FROM fee_structures WHERE id = ? AND school_id = ?")->execute([$old['id'], $sid]);
    log_content_change($db, $sid, $user['id'], 'fee', $old['id'], 'delete', $old, null);
    echo json_encode(['ok' => true]); exit;
}

if ($action === 'save') {
    $grade = $clean($_POST['grade'] ?? '');
    $term = $clean($_POST['term_label'] ?? '');
    $amountRaw = preg_replace('/[^0-9.]/', '', (string)($_POST['amount'] ?? ''));
    if ($grade === '' || $term === '') fee_fail('Grade and term are both needed.');
    if ($amountRaw === '' || !is_numeric($amountRaw)) fee_fail('Enter the amount as a number.');
    $amount = round((float)$amountRaw, 2);
    if ($amount < 0 || $amount > 99999999) fee_fail('That amount looks wrong.');

    $dup = $db->prepare("SELECT * FROM fee_structures WHERE school_id = ? AND grade = ? AND term_label = ?");
    $dup->execute([$sid, $grade, $term]);
    $dupRow = $dup->fetch();

    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $old = $getRow($id);
        if (!$old) fee_fail('Fee not found.', 404);
        if ($dupRow && (int)$dupRow['id'] !== $id) fee_fail("There's already a fee for $grade, $term.");
        $db->prepare("UPDATE fee_structures SET grade = ?, term_label = ?, amount = ? WHERE id = ? AND school_id = ?")->execute([$grade, $term, $amount, $id, $sid]);
        log_content_change($db, $sid, $user['id'], 'fee', $id, 'update', $old, $getRow($id));
    } else {
        $pay = json_encode(['paybill' => '', 'account_note' => '']);
        $db->prepare("INSERT INTO fee_structures (school_id, grade, term_label, amount, payment_details_json) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE amount = VALUES(amount)")->execute([$sid, $grade, $term, $amount, $pay]);
        $dup->execute([$sid, $grade, $term]);
        $new = $dup->fetch();
        if ($new) log_content_change($db, $sid, $user['id'], 'fee', $new['id'], $dupRow ? 'update' : 'create', $dupRow ?: null, $new);
    }
    echo json_encode(['ok' => true]); exit;
}

// Rename a group heading (a grade or a term) across all its rows.
if ($action === 'rename_group') {
    $col = ($_POST['by'] ?? '') === 'term' ? 'term_label' : (($_POST['by'] ?? '') === 'grade' ? 'grade' : '');
    $other = $col === 'grade' ? 'term_label' : 'grade';
    $from = $clean($_POST['from'] ?? ''); $to = $clean($_POST['to'] ?? '');
    if (!$col || $from === '' || $to === '') fee_fail('Heading cannot be empty.');
    if ($from === $to) { echo json_encode(['ok' => true]); exit; }
    $clash = $db->prepare("SELECT COUNT(*) FROM fee_structures a JOIN fee_structures b ON a.$other = b.$other AND b.$col = ? AND b.school_id = a.school_id WHERE a.school_id = ? AND a.$col = ?");
    $clash->execute([$to, $sid, $from]);
    if ((int)$clash->fetchColumn() > 0) fee_fail("\"$to\" already exists with some of the same entries.");
    $rows = $db->prepare("SELECT * FROM fee_structures WHERE school_id = ? AND $col = ?");
    $rows->execute([$sid, $from]);
    foreach ($rows->fetchAll() as $old) {
        $db->prepare("UPDATE fee_structures SET $col = ? WHERE id = ? AND school_id = ?")->execute([$to, $old['id'], $sid]);
        log_content_change($db, $sid, $user['id'], 'fee', $old['id'], 'update', $old, $getRow((int)$old['id']));
    }
    echo json_encode(['ok' => true]); exit;
}

fee_fail('Unknown action.');
