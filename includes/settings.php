<?php
/**
 * Simple key/value store for operational settings that used to be hardcoded
 * constants (payment numbers, notification email, cron secret, etc) — lets
 * these be changed from admin/settings.php instead of editing PHP files and
 * re-uploading. Cached per-request so calling get_setting() many times on
 * one page load only hits the database once.
 */

function get_setting(PDO $db, string $key, string $default = ''): string {
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $db->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    $cache[$key] = ($value !== false && $value !== null && $value !== '') ? $value : $default;
    return $cache[$key];
}

function set_setting(PDO $db, string $key, string $value): void {
    $db->prepare("
        INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ")->execute([$key, $value]);
}

function get_all_settings(PDO $db): array {
    $rows = $db->query("SELECT setting_key, setting_value FROM platform_settings")->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[$r['setting_key']] = $r['setting_value'];
    }
    return $out;
}
