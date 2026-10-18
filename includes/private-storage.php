<?php
/**
 * Private file storage for sensitive uploads (ID documents, signed
 * agreements, results/attendance CSVs). Files live in storage/private/, which
 * is denied to the web by storage/.htaccess, and are only reachable through
 * serve_private_file() after a permission check.
 */
const PRIVATE_BASE = 'storage/private/';

function private_dir(string $sub): string {
    $dir = __DIR__ . '/../' . PRIVATE_BASE . trim($sub, '/') . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return $dir;
}

function private_random_name(string $prefix, string $ext): string {
    return preg_replace('/[^a-z0-9_]/i', '', $prefix) . '_' . bin2hex(random_bytes(10)) . '.' . preg_replace('/[^a-z0-9]/i', '', $ext);
}

/** URL that streams a stored private file (checked by the target script). */
function private_file_url(string $rel, string $base = ''): string {
    return $base . 'private-file.php?f=' . rawurlencode(ltrim($rel, '/'));
}

/**
 * Stream $rel if it is a known private/legacy sensitive location.
 * $schoolId = restrict to that school's files (null = any, for platform admins).
 */
function serve_private_file(string $rel, ?int $schoolId): void {
    $rel = ltrim(str_replace('\\', '/', $rel), '/');
    $ok = false;
    foreach ([PRIVATE_BASE, 'uploads/schools/', 'uploads/results/', 'uploads/attendance/'] as $prefix) {
        if (strpos($rel, $prefix) === 0) { $ok = true; break; }
    }
    $root = realpath(__DIR__ . '/..');
    $full = realpath($root . '/' . $rel);
    if (!$ok || !$full || strpos($full, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($full)) {
        http_response_code(404); exit('Not found');
    }
    if ($schoolId !== null && !preg_match('#/(schools|results|attendance)/' . $schoolId . '/#', '/' . $rel)) {
        http_response_code(403); exit('Forbidden');
    }
    $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
    $types = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'csv' => 'text/csv'];
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($full));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('Content-Disposition: inline; filename="' . basename($full) . '"');
    readfile($full);
    exit;
}
