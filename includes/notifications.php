<?php
function create_notification(PDO $db, int $schoolId, string $type, string $title, string $message, ?string $link = null): void {
    // One unread notification of the same type per school at a time —
    // re-triggering (e.g. daily cron) updates it instead of piling up dupes.
    $existing = $db->prepare("SELECT id FROM notifications WHERE school_id = ? AND type = ? AND is_read = 0 LIMIT 1");
    $existing->execute([$schoolId, $type]);
    $id = $existing->fetchColumn();

    if ($id) {
        $db->prepare("UPDATE notifications SET title = ?, message = ?, link = ?, created_at = NOW() WHERE id = ?")
           ->execute([$title, $message, $link, $id]);
    } else {
        $db->prepare("INSERT INTO notifications (school_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)")
           ->execute([$schoolId, $type, $title, $message, $link]);
    }
}

function get_unread_notification_count(PDO $db, int $schoolId): int {
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE school_id = ? AND is_read = 0");
    $stmt->execute([$schoolId]);
    return (int)$stmt->fetchColumn();
}

function get_recent_notifications(PDO $db, int $schoolId, int $limit = 12): array {
    $stmt = $db->prepare("SELECT * FROM notifications WHERE school_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bindValue(1, $schoolId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function mark_notification_read(PDO $db, int $notificationId, int $schoolId): void {
    $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND school_id = ?")->execute([$notificationId, $schoolId]);
}

function mark_all_notifications_read(PDO $db, int $schoolId): void {
    $db->prepare("UPDATE notifications SET is_read = 1 WHERE school_id = ?")->execute([$schoolId]);
}

function dismiss_notifications_by_type(PDO $db, int $schoolId, string $type): void {
    $db->prepare("UPDATE notifications SET is_read = 1 WHERE school_id = ? AND type = ?")->execute([$schoolId, $type]);
}
