<?php
/**
 * Guaranteed-visible error logging. Writes to includes/app.log (blocked from
 * direct web access by includes/.htaccess) instead of relying on PHP's
 * error_log ini setting, which on shared hosting often points somewhere you
 * don't have easy access to, or may have logging disabled entirely.
 *
 * Use this anywhere you'd otherwise call error_log() for something you
 * actually need to be able to find and read.
 */
function app_log(string $message): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    try {
        file_put_contents(__DIR__ . '/app.log', $line, FILE_APPEND | LOCK_EX);
    } catch (\Throwable $e) {
        // Fall back to the regular PHP error log if the file itself can't
        // be written (e.g. permissions) — better than losing the message.
        error_log('app_log() failed to write, original message: ' . $message);
    }
    // Also send to the standard PHP error log as a backup, in case someone
    // does have that configured and monitored.
    error_log($message);
}
