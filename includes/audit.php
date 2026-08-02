<?php
/**
 * Records a content change to the audit log. Call this whenever a section's
 * content or a fee entry is created, updated, or deleted.
 *
 * @param PDO    $db
 * @param int    $schoolId
 * @param int|null $userId    Who made the change (null if system-generated)
 * @param string $entityType  'section' or 'fee'
 * @param int    $entityId    site_sections.id or fee_structures.id
 * @param string $action      'create', 'update', or 'delete'
 * @param mixed  $oldValue    Previous value (array/object — will be JSON encoded), or null
 * @param mixed  $newValue    New value (array/object — will be JSON encoded), or null
 */
function log_content_change(PDO $db, int $schoolId, ?int $userId, string $entityType, int $entityId, string $action, $oldValue, $newValue): void {
    $stmt = $db->prepare("
        INSERT INTO content_audit_log (school_id, user_id, entity_type, entity_id, action, old_value, new_value)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $schoolId,
        $userId,
        $entityType,
        $entityId,
        $action,
        $oldValue === null ? null : json_encode($oldValue),
        $newValue === null ? null : json_encode($newValue),
    ]);
}
