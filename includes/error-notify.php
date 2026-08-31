<?php
/**
 * Catches fatal errors and uncaught exceptions anywhere on the site and
 * emails admin@somahub.top immediately, instead of the error only sitting
 * silently in error_log until someone happens to check it.
 *
 * Include this ONCE, as early as possible — the very first line of the
 * real config/db.php, before the DB connection code — so it's active for
 * every page on the site.
 *
 * De-duplicates: the same error (same file+line+message) only emails once
 * per 6 hours, so a repeated error doesn't flood your inbox. Tracked in a
 * small JSON file, not the database, since a DB-connection error is
 * exactly the kind of thing this needs to survive reporting on.
 */

function somahub_notify_fatal_error(string $message, string $file, int $line): void {
    $cacheFile = __DIR__ . '/../uploads/.error-notify-cache.json';
    $signature = md5($message . $file . $line);
    $cooldownSeconds = 6 * 3600;

    $cache = [];
    if (file_exists($cacheFile)) {
        $cache = json_decode(@file_get_contents($cacheFile), true) ?: [];
    }

    $lastSent = $cache[$signature] ?? 0;
    if (time() - $lastSent < $cooldownSeconds) {
        return; // already alerted on this exact error recently
    }

    $cache[$signature] = time();
    // Trim old entries so this file doesn't grow forever
    foreach ($cache as $sig => $ts) {
        if (time() - $ts > 30 * 86400) unset($cache[$sig]);
    }
    @file_put_contents($cacheFile, json_encode($cache));

    $url = ($_SERVER['HTTPS'] ?? '') === 'on' ? 'https://' : 'http://';
    $url .= ($_SERVER['HTTP_HOST'] ?? 'unknown-host') . ($_SERVER['REQUEST_URI'] ?? '');

    $body = "A fatal error just occurred on somahub.top.\n\n"
          . "Message: {$message}\n"
          . "File: {$file}\n"
          . "Line: {$line}\n"
          . "URL: {$url}\n"
          . "Time: " . date('Y-m-d H:i:s') . " UTC\n";

    // Admin email defaults to the hardcoded address below — this file must
    // survive even if the database itself is the thing that's broken, so
    // it only overrides from settings if that lookup succeeds quietly.
    $adminEmail = 'admin@somahub.top';
    try {
        $settingsPath = __DIR__ . '/settings.php';
        if (function_exists('get_db') && file_exists($settingsPath)) {
            require_once $settingsPath;
            $adminEmail = get_setting(get_db(), 'admin_notify_email', $adminEmail);
        }
    } catch (\Throwable $e) {
        // ignore — keep the hardcoded fallback
    }

    // Try the site's own mailer if it's loadable; fall back to PHP's raw
    // mail() so a broken mailer.php doesn't also silence this alert.
    try {
        $mailerPath = __DIR__ . '/mailer.php';
        if (file_exists($mailerPath)) {
            require_once $mailerPath;
            if (function_exists('send_somahub_email')) {
                send_somahub_email($adminEmail, '⚠️ Somahub error: ' . mb_strimwidth($message, 0, 60, '…'), nl2br(htmlspecialchars($body)));
                return;
            }
        }
    } catch (\Throwable $e) {
        // fall through to raw mail()
    }

    @mail($adminEmail, 'Somahub error (fallback alert)', $body);
}

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        somahub_notify_fatal_error($error['message'], $error['file'], $error['line']);
    }
});

set_exception_handler(function (\Throwable $e) {
    somahub_notify_fatal_error($e->getMessage(), $e->getFile(), $e->getLine());
    http_response_code(500);
    echo 'Something went wrong on our end. We\'ve been notified and are looking into it.';
});
