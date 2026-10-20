<?php
/**
 * Payments helpers. Since Somahub currently uses a personal Till/Pochi/Send
 * Money (no Daraja API access yet), payments cannot be auto-confirmed —
 * a school submits their M-Pesa code, and an admin manually verifies it
 * against their own M-Pesa messages before marking an order paid.
 *
 * When the startup is registered and Daraja API access is available,
 * only mark_order_paid() needs a new automatic caller (a webhook) —
 * everything else here stays the same.
 */

require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/settings.php';

// Payment details now live in admin/settings.php, not hardcoded here.
// Defined as constants (not variables) so every existing usage elsewhere
// in the codebase — PAYMENT_TILL_NUMBER etc, used as bare words in
// dashboard/checkout.php's template — keeps working unchanged.
if (!defined('PAYMENT_TILL_NUMBER')) {
    $settingsDb = get_db();
    define('PAYMENT_TILL_NUMBER', get_setting($settingsDb, 'payment_till_number', '4567050'));
    define('PAYMENT_POCHI_NUMBER', get_setting($settingsDb, 'payment_pochi_number', '254707306888'));
    define('PAYMENT_SEND_MONEY_NUMBER', get_setting($settingsDb, 'payment_send_money_number', '254707306888'));
    define('PAYMENT_EQUITY_PAYBILL', get_setting($settingsDb, 'payment_equity_paybill', '247247'));
    define('PAYMENT_EQUITY_ACCOUNT_NUMBER', get_setting($settingsDb, 'payment_equity_account_number', ''));
    define('PAYMENT_DISPLAY_NAME', get_setting($settingsDb, 'payment_display_name', 'Kelvin Njehia'));
}

function generate_order_reference(PDO $db): string {
    do {
        $ref = 'SH' . strtoupper(bin2hex(random_bytes(3))); // e.g. SH3F9A2C
        $check = $db->prepare("SELECT id FROM orders WHERE reference_code = ?");
        $check->execute([$ref]);
    } while ($check->fetch());
    return $ref;
}

/** Adds the quantity column to order_items and creates the cart table, once. */
function ensure_cart_schema(PDO $db): void {
    static $done = false; if ($done) return; $done = true;
    try {
        $has = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items' AND COLUMN_NAME = 'quantity'")->fetchColumn();
        if (!$has) $db->exec("ALTER TABLE order_items ADD COLUMN quantity INT NOT NULL DEFAULT 1");
        $db->exec("CREATE TABLE IF NOT EXISTS cart_items (school_id INT NOT NULL, product_key VARCHAR(60) NOT NULL, qty INT NOT NULL DEFAULT 1, added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (school_id, product_key))");
    } catch (Throwable $e) { error_log('cart schema: ' . $e->getMessage()); }
}

/** Max quantity per product: the plan is bought in years, content writing in pieces, one-off setups once. */
function product_max_qty(string $key): int {
    return match ($key) { 'paid_plan' => 3, 'content_writing' => 10, default => 1 };
}
function product_qty_label(string $key): string {
    return match ($key) { 'paid_plan' => 'year', 'content_writing' => 'piece', default => '' };
}

function cart_get(PDO $db, int $schoolId): array {
    ensure_cart_schema($db);
    $st = $db->prepare("SELECT c.product_key, c.qty, p.label, p.description, p.price, p.billing_cycle FROM cart_items c JOIN products p ON p.product_key = c.product_key AND p.is_active = 1 WHERE c.school_id = ? ORDER BY c.added_at");
    $st->execute([$schoolId]);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) { $r['qty'] = max(1, min((int)$r['qty'], product_max_qty($r['product_key']))); $r['line'] = $r['qty'] * (float)$r['price']; }
    return $rows;
}
function cart_total(array $rows): float { return array_sum(array_column($rows, 'line')); }
function cart_set(PDO $db, int $schoolId, string $key, int $qty): void {
    ensure_cart_schema($db);
    if ($qty <= 0) { $db->prepare("DELETE FROM cart_items WHERE school_id = ? AND product_key = ?")->execute([$schoolId, $key]); return; }
    $ok = $db->prepare("SELECT COUNT(*) FROM products WHERE product_key = ? AND is_active = 1"); $ok->execute([$key]);
    if (!$ok->fetchColumn()) return;
    $qty = min($qty, product_max_qty($key));
    $db->prepare("INSERT INTO cart_items (school_id, product_key, qty) VALUES (?,?,?) ON DUPLICATE KEY UPDATE qty = VALUES(qty)")->execute([$schoolId, $key, $qty]);
}
function cart_clear(PDO $db, int $schoolId): void {
    ensure_cart_schema($db);
    $db->prepare("DELETE FROM cart_items WHERE school_id = ?")->execute([$schoolId]);
}
function cart_count(PDO $db, int $schoolId): int {
    try { ensure_cart_schema($db); $st = $db->prepare("SELECT COALESCE(SUM(qty),0) FROM cart_items WHERE school_id = ?"); $st->execute([$schoolId]); return (int)$st->fetchColumn(); } catch (Throwable $e) { return 0; }
}

/**
 * Creates a pending order. $productKeys is either a list of keys (quantity 1
 * each, as the Google Business form uses) or [key => quantity]. Prices always
 * come from the products table, never from the browser.
 */
function create_order(PDO $db, int $schoolId, array $productKeys): int {
    ensure_cart_schema($db);
    $qty = [];
    foreach ($productKeys as $k => $v) { if (is_int($k)) $qty[(string)$v] = 1; else $qty[(string)$k] = max(1, min((int)$v, product_max_qty((string)$k))); }
    $keys = array_keys($qty);
    if (!$keys) throw new Exception('No valid products selected.');
    $stmt = $db->prepare("SELECT * FROM products WHERE product_key IN (" . implode(',', array_fill(0, count($keys), '?')) . ") AND is_active = 1");
    $stmt->execute($keys);
    $products = $stmt->fetchAll();
    if (!$products) throw new Exception('No valid products selected.');

    $total = 0.0;
    foreach ($products as $p) $total += (float)$p['price'] * $qty[$p['product_key']];
    $reference = generate_order_reference($db);

    $db->beginTransaction();
    try {
        $db->prepare("INSERT INTO orders (school_id, reference_code, total_amount, status) VALUES (?, ?, ?, 'pending')")->execute([$schoolId, $reference, $total]);
        $orderId = (int)$db->lastInsertId();
        $insertItem = $db->prepare("INSERT INTO order_items (order_id, product_key, label, amount, quantity) VALUES (?, ?, ?, ?, ?)");
        foreach ($products as $p) {
            $q = $qty[$p['product_key']];
            $insertItem->execute([$orderId, $p['product_key'], $q > 1 ? $p['label'] . ' × ' . $q : $p['label'], (float)$p['price'] * $q, $q]);
        }
        $db->commit();
        return $orderId;
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function create_order_from_cart(PDO $db, int $schoolId): int {
    $rows = cart_get($db, $schoolId);
    if (!$rows) throw new Exception('Your cart is empty.');
    $map = []; foreach ($rows as $r) $map[$r['product_key']] = $r['qty'];
    $id = create_order($db, $schoolId, $map);
    cart_clear($db, $schoolId);
    return $id;
}

/** Schools can drop an unpaid order and start over (admin can still cancel any). */
function cancel_pending_order(PDO $db, int $orderId, int $schoolId): bool {
    $st = $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND school_id = ? AND status = 'pending'");
    $st->execute([$orderId, $schoolId]);
    return $st->rowCount() > 0;
}

/**
 * Called when a school submits their M-Pesa confirmation code after paying.
 * Does NOT mark the order paid — just records the code for admin review.
 * Returns an error message, or '' on success.
 */
function submit_mpesa_code(PDO $db, int $orderId, string $method, string $mpesaCode): string {
    $code = strtoupper(preg_replace('/\s+/', '', $mpesaCode));
    if (!preg_match('/^[A-Z0-9\-]{6,24}$/', $code)) return 'That code does not look right. M-Pesa codes are letters and numbers, like QJH7XXXXXX.';
    $dup = $db->prepare("SELECT COUNT(*) FROM orders WHERE mpesa_code = ? AND id <> ? AND status IN ('pending','paid')");
    $dup->execute([$code, $orderId]);
    if ($dup->fetchColumn() > 0) return 'This code has already been used on another order. Check the code in your M-Pesa message, or contact us.';
    $db->prepare("UPDATE orders SET payment_method = ?, mpesa_code = ?, mpesa_code_submitted_at = NOW() WHERE id = ? AND status = 'pending'")
       ->execute([$method, $code, $orderId]);
    return '';
}

/**
 * Called by the admin once they've checked their M-Pesa messages and confirmed
 * the code/amount genuinely match. This is the manual equivalent of a webhook.
 * Safe to call twice: only a pending order can be marked paid.
 */
function mark_order_paid(PDO $db, int $orderId, string $verifiedBy): bool {
    ensure_cart_schema($db);
    $claim = $db->prepare("UPDATE orders SET status = 'paid', paid_at = NOW(), verified_by = ? WHERE id = ? AND status = 'pending'");
    $claim->execute([$verifiedBy, $orderId]);
    if ($claim->rowCount() === 0) return false; // already paid, cancelled or refunded: do nothing, send nothing

    $items = $db->prepare("SELECT product_key, quantity FROM order_items WHERE order_id = ?");
    $items->execute([$orderId]);
    $lines = $items->fetchAll();
    $productKeys = array_column($lines, 'product_key');
    $qtyOf = []; foreach ($lines as $l) $qtyOf[$l['product_key']] = max(1, (int)$l['quantity']);

    $order = $db->prepare("SELECT school_id FROM orders WHERE id = ?");
    $order->execute([$orderId]);
    $schoolId = $order->fetchColumn();

    if (in_array('paid_plan', $productKeys, true)) {
        // Years are added on top of whatever time is left, so renewing early never wastes days.
        $years = $qtyOf['paid_plan'];
        $db->prepare("
            UPDATE schools SET plan = 'paid',
                promo_ends_at = DATE_ADD(GREATEST(COALESCE(promo_ends_at, NOW()), NOW()), INTERVAL ? YEAR),
                plan_reminder_sent_7d = 0, plan_reminder_sent_1d = 0, plan_reminder_sent_expired = 0
            WHERE id = ?
        ")->execute([$years, $schoolId]);
    }
    if (in_array('custom_domain', $productKeys, true)) {
        $db->prepare("UPDATE schools SET custom_domain_enabled = 1 WHERE id = ?")->execute([$schoolId]);
    }
    if (in_array('custom_templates', $productKeys, true)) {
        $db->prepare("UPDATE schools SET custom_templates_enabled = 1 WHERE id = ?")->execute([$schoolId]);
    }
    // content_writing and google_business_setup are manual services, tracked in their admin queues.

    $schoolStmt = $db->prepare("SELECT name, email FROM schools WHERE id = ?");
    $schoolStmt->execute([$schoolId]);
    $school = $schoolStmt->fetch();
    $itemsList = implode(', ', array_map(fn($k) => str_replace('_', ' ', $k) . ($qtyOf[$k] > 1 ? ' × ' . $qtyOf[$k] : ''), $productKeys));

    if ($school && !empty($school['email'])) {
        $confirmBody = "
            <h2 style='color:#0F5257;margin-top:0;'>Payment Confirmed</h2>
            <p>Your payment for <strong>" . htmlspecialchars(ucwords($itemsList)) . "</strong> has been verified. The changes are live on your account now.</p>
            <p><a href='https://somahub.top/dashboard/invoices.php' style='color:#0F5257;font-weight:700;'>View your receipt &rarr;</a></p>
        ";
        send_somahub_email($school['email'], 'Your Somahub payment is confirmed', $confirmBody);
    }

    require_once __DIR__ . '/notifications.php';
    create_notification($db, $schoolId, 'payment_confirmed',
        'Payment confirmed',
        'Your payment for ' . ucwords($itemsList) . ' is verified and live on your account.',
        'invoices.php');
    return true;
}

/**
 * Starts a 60-day Trial: full Paid-tier feature access (enrollment, results,
 * fees) without payment, using the same promo_paid mechanic as the existing
 * "free first term" flow. Reverts to Free (locked) automatically once
 * promo_ends_at passes — is_premium_locked() in includes/plan.php already
 * handles this, no separate expiry logic needed.
 */
function start_trial(PDO $db, int $schoolId, int $days = 60): void {
    $db->prepare("
        UPDATE schools
        SET plan = 'promo_paid', promo_ends_at = DATE_ADD(NOW(), INTERVAL ? DAY),
            plan_reminder_sent_7d = 0, plan_reminder_sent_1d = 0, plan_reminder_sent_expired = 0
        WHERE id = ?
    ")->execute([$days, $schoolId]);
}

function request_refund(PDO $db, int $orderId, float $amount, string $reason): void {
    $stmt = $db->prepare("INSERT INTO refunds (order_id, amount, reason, status) VALUES (?, ?, ?, 'requested')");
    $stmt->execute([$orderId, $amount, $reason]);
}

function process_refund(PDO $db, int $refundId, string $status, string $adminNote = ''): void {
    $stmt = $db->prepare("UPDATE refunds SET status = ?, admin_note = ?, processed_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $adminNote, $refundId]);

    if ($status === 'sent') {
        $orderStmt = $db->prepare("
            UPDATE orders o JOIN refunds r ON r.order_id = o.id
            SET o.status = 'refunded' WHERE r.id = ?
        ");
        $orderStmt->execute([$refundId]);
    }
}
