<?php
/**
 * Undo / redo for the site editor.
 *
 * After every change we store a snapshot of the whole editable site (all
 * sections + colour/font settings). Undo / redo restore a neighbouring
 * snapshot. Sites are small, so full snapshots are simple and reliable.
 *
 * Usage in any editing endpoint (one line, right after auth):
 *     history_track_request($db, $schoolId, 'Edited text', 'text:' . $sectionId . ':' . $field);
 * It saves a baseline if this school has no history yet, then records the
 * new state when the request finishes. Same coalesce key within 15 seconds =
 * one undo step (so typing a sentence is one step, not forty).
 *
 * Fails open: if the tables can't be created, editing carries on without undo.
 */
const HISTORY_KEEP = 40;
const SCHOOL_APPEARANCE_COLS = ['palette_id', 'color_mode', 'primary_override', 'secondary_override', 'accent_override', 'bg_override', 'font_display_override', 'font_body_override'];

function history_ready(PDO $db): bool {
    static $ok = null;
    if ($ok !== null) return $ok;
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS edit_history (
            id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            school_id INT NOT NULL,
            label VARCHAR(120) NOT NULL,
            coalesce_key VARCHAR(160) NULL,
            state_json MEDIUMTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_school (school_id, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS edit_history_ptr (
            school_id INT NOT NULL PRIMARY KEY,
            ptr_id BIGINT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $ok = true;
    } catch (\Throwable $e) { $ok = false; }
    return $ok;
}

function history_capture(PDO $db, int $schoolId): array {
    $q = $db->prepare("SELECT * FROM site_sections WHERE school_id = ? ORDER BY position, id");
    $q->execute([$schoolId]);
    $sections = $q->fetchAll(PDO::FETCH_ASSOC);
    $s = $db->prepare("SELECT * FROM schools WHERE id = ?");
    $s->execute([$schoolId]);
    $row = $s->fetch(PDO::FETCH_ASSOC) ?: [];
    $app = [];
    foreach (SCHOOL_APPEARANCE_COLS as $c) { if (array_key_exists($c, $row)) $app[$c] = $row[$c]; }
    return ['sections' => $sections, 'appearance' => $app];
}

function history_ptr(PDO $db, int $schoolId): ?int {
    $q = $db->prepare("SELECT ptr_id FROM edit_history_ptr WHERE school_id = ?");
    $q->execute([$schoolId]);
    $v = $q->fetchColumn();
    return $v ? (int)$v : null;
}

function history_set_ptr(PDO $db, int $schoolId, ?int $id): void {
    $db->prepare("REPLACE INTO edit_history_ptr (school_id, ptr_id) VALUES (?, ?)")->execute([$schoolId, $id]);
}

function history_ensure_baseline(PDO $db, int $schoolId): void {
    if (!history_ready($db)) return;
    $c = $db->prepare("SELECT COUNT(*) FROM edit_history WHERE school_id = ?");
    $c->execute([$schoolId]);
    if ((int)$c->fetchColumn() > 0) return;
    $db->prepare("INSERT INTO edit_history (school_id, label, state_json, created_at) VALUES (?, 'Start', ?, NOW())")
       ->execute([$schoolId, json_encode(history_capture($db, $schoolId))]);
    history_set_ptr($db, $schoolId, (int)$db->lastInsertId());
}

function history_push(PDO $db, int $schoolId, string $label, ?string $coalesceKey = null): void {
    if (!history_ready($db)) return;
    $ptr = history_ptr($db, $schoolId);
    // Anything after the current position is a redo branch we are abandoning.
    if ($ptr) { $db->prepare("DELETE FROM edit_history WHERE school_id = ? AND id > ?")->execute([$schoolId, $ptr]); }
    $state = json_encode(history_capture($db, $schoolId));

    if ($ptr && $coalesceKey) {
        $q = $db->prepare("SELECT id FROM edit_history WHERE id = ? AND coalesce_key = ? AND created_at > (NOW() - INTERVAL 15 SECOND)");
        $q->execute([$ptr, $coalesceKey]);
        if ($q->fetchColumn()) {
            $db->prepare("UPDATE edit_history SET state_json = ?, created_at = NOW() WHERE id = ?")->execute([$state, $ptr]);
            return;
        }
    }
    $db->prepare("INSERT INTO edit_history (school_id, label, coalesce_key, state_json, created_at) VALUES (?, ?, ?, ?, NOW())")
       ->execute([$schoolId, mb_substr($label, 0, 120), $coalesceKey, $state]);
    history_set_ptr($db, $schoolId, (int)$db->lastInsertId());
    $db->prepare("DELETE FROM edit_history WHERE school_id = ? AND id <= (SELECT m FROM (SELECT id AS m FROM edit_history WHERE school_id = ? ORDER BY id DESC LIMIT 1 OFFSET " . (int)HISTORY_KEEP . ") t)")
       ->execute([$schoolId, $schoolId]);
}

/** One call from an endpoint: baseline now, snapshot when the request ends. */
function history_track_request(PDO $db, int $schoolId, string $label, ?string $coalesceKey = null): void {
    try { history_ensure_baseline($db, $schoolId); } catch (\Throwable $e) { return; }
    register_shutdown_function(function () use ($db, $schoolId, $label, $coalesceKey) {
        if (http_response_code() >= 400) return;
        try { history_push($db, $schoolId, $label, $coalesceKey); } catch (\Throwable $e) { error_log('history push failed: ' . $e->getMessage()); }
    });
}

function history_status(PDO $db, int $schoolId): array {
    if (!history_ready($db)) return ['canUndo' => false, 'canRedo' => false];
    $ptr = history_ptr($db, $schoolId);
    if (!$ptr) return ['canUndo' => false, 'canRedo' => false];
    $u = $db->prepare("SELECT COUNT(*) FROM edit_history WHERE school_id = ? AND id < ?");
    $u->execute([$schoolId, $ptr]);
    $r = $db->prepare("SELECT COUNT(*) FROM edit_history WHERE school_id = ? AND id > ?");
    $r->execute([$schoolId, $ptr]);
    return ['canUndo' => (int)$u->fetchColumn() > 0, 'canRedo' => (int)$r->fetchColumn() > 0];
}

function history_restore(PDO $db, int $schoolId, array $state): void {
    $db->beginTransaction();
    try {
        $db->prepare("DELETE FROM site_sections WHERE school_id = ?")->execute([$schoolId]);
        foreach ($state['sections'] ?? [] as $row) {
            $cols = array_keys($row);
            $sql = "INSERT INTO site_sections (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")";
            $db->prepare($sql)->execute(array_values($row));
        }
        $app = $state['appearance'] ?? [];
        if ($app) {
            $set = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($app)));
            $db->prepare("UPDATE schools SET $set WHERE id = ?")->execute([...array_values($app), $schoolId]);
        }
        $db->commit();
    } catch (\Throwable $e) { $db->rollBack(); throw $e; }
}

/** $dir = 'undo' | 'redo'. Returns the label of the change that was undone/redone, or null. */
function history_step(PDO $db, int $schoolId, string $dir): ?string {
    if (!history_ready($db)) return null;
    $ptr = history_ptr($db, $schoolId);
    if (!$ptr) return null;
    if ($dir === 'undo') {
        $cur = $db->prepare("SELECT label FROM edit_history WHERE id = ?"); $cur->execute([$ptr]);
        $label = $cur->fetchColumn();
        $q = $db->prepare("SELECT id, state_json FROM edit_history WHERE school_id = ? AND id < ? ORDER BY id DESC LIMIT 1");
        $q->execute([$schoolId, $ptr]);
    } else {
        $q = $db->prepare("SELECT id, label, state_json FROM edit_history WHERE school_id = ? AND id > ? ORDER BY id ASC LIMIT 1");
        $q->execute([$schoolId, $ptr]);
    }
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    history_restore($db, $schoolId, json_decode($row['state_json'], true) ?: []);
    history_set_ptr($db, $schoolId, (int)$row['id']);
    return $dir === 'undo' ? (string)$label : (string)$row['label'];
}
