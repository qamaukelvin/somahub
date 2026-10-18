<?php
/**
 * Tiny DB-backed rate limiter. Fails OPEN: if the table can't be created or
 * queried, requests are allowed (a limiter must never take the site down).
 *
 *   if (!rate_limit('login:' . client_ip(), 8, 900)) { ...too many... }
 *
 * Counts hits per key within a sliding fixed window (seconds).
 */
function client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function rate_limit(string $key, int $max, int $windowSeconds, bool $consume = true): bool {
    try {
        $db = get_db();
        static $ready = false;
        if (!$ready) {
            $db->exec("CREATE TABLE IF NOT EXISTS rate_limits (
                k VARCHAR(190) NOT NULL PRIMARY KEY,
                hits INT NOT NULL DEFAULT 0,
                window_start DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $ready = true;
            if (mt_rand(1, 50) === 1) {
                $db->exec("DELETE FROM rate_limits WHERE window_start < (NOW() - INTERVAL 1 DAY)");
            }
        }
        $k = substr($key, 0, 190);
        $row = $db->prepare("SELECT hits, TIMESTAMPDIFF(SECOND, window_start, NOW()) AS age FROM rate_limits WHERE k = ?");
        $row->execute([$k]);
        $r = $row->fetch();
        if (!$r || (int)$r['age'] >= $windowSeconds) {
            if ($consume) {
                $db->prepare("REPLACE INTO rate_limits (k, hits, window_start) VALUES (?, 1, NOW())")->execute([$k]);
            }
            return true;
        }
        if ((int)$r['hits'] >= $max) return false;
        if ($consume) {
            $db->prepare("UPDATE rate_limits SET hits = hits + 1 WHERE k = ?")->execute([$k]);
        }
        return true;
    } catch (\Throwable $e) {
        return true;
    }
}

/** Clear a key (e.g. after a successful login). */
function rate_limit_reset(string $key): void {
    try { get_db()->prepare("DELETE FROM rate_limits WHERE k = ?")->execute([substr($key, 0, 190)]); } catch (\Throwable $e) {}
}

/** Stop a request outright with a 429 (HTML or JSON). */
function rate_limit_or_die(string $key, int $max, int $windowSeconds, string $msg = 'Too many requests. Please wait a few minutes and try again.'): void {
    if (rate_limit($key, $max, $windowSeconds)) return;
    http_response_code(429);
    header('Retry-After: ' . $windowSeconds);
    $json = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'json') !== false;
    if ($json) { header('Content-Type: application/json'); echo json_encode(['ok' => false, 'error' => $msg]); }
    else { echo '<!DOCTYPE html><meta name="viewport" content="width=device-width,initial-scale=1"><title>Slow down</title><body style="font-family:system-ui,sans-serif;max-width:420px;margin:15vh auto;padding:0 20px"><h2>Too many requests</h2><p>' . htmlspecialchars($msg) . '</p></body>'; }
    exit;
}
