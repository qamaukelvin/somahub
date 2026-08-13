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

// Your payment details — update these once (e.g. after registering the business name)
const PAYMENT_TILL_NUMBER = 'XXXXXX';           // <-- fill in your real Till Number
const PAYMENT_POCHI_NUMBER = '07XXXXXXXX';       // <-- fill in your real Pochi la Biashara number
const PAYMENT_SEND_MONEY_NUMBER = '07XXXXXXXX';  // <-- fill in your real Send Money number
const PAYMENT_EQUITY_PAYBILL = '247247';          // Equity Bank's standard paybill number (same for all Equity account holders)
const PAYMENT_EQUITY_ACCOUNT_NUMBER = 'XXXXXXXXXXX'; // <-- fill in YOUR Equity account number
const PAYMENT_DISPLAY_NAME = 'Kelvin Njehia';    // <-- shown to schools so they can confirm the name matches

function generate_order_reference(PDO $db): string {
    do {
        $ref = 'SH' . strtoupper(bin2hex(random_bytes(3))); // e.g. SH3F9A2C
        $check = $db->prepare("SELECT id FROM orders WHERE reference_code = ?");
        $check->execute([$ref]);
    } while ($check->fetch());
    return $ref;
}

/**
 * Creates a pending order for a school with the given product keys.
 * $productKeys e.g. ['paid_plan'] or ['paid_plan', 'custom_domain']
 */
function create_order(PDO $db, int $schoolId, array $productKeys): int {
    $placeholders = implode(',', array_fill(0, count($productKeys), '?'));
    $stmt = $db->prepare("SELECT * FROM products WHERE product_key IN ($placeholders) AND is_active = 1");
    $stmt->execute($productKeys);
    $products = $stmt->fetchAll();

    if (!$products) {
        throw new Exception('No valid products selected.');
    }

    $total = array_sum(array_column($products, 'price'));
    $reference = generate_order_reference($db);

    $db->beginTransaction();
    try {
        $insertOrder = $db->prepare("INSERT INTO orders (school_id, reference_code, total_amount, status) VALUES (?, ?, ?, 'pending')");
        $insertOrder->execute([$schoolId, $reference, $total]);
        $orderId = (int)$db->lastInsertId();

        $insertItem = $db->prepare("INSERT INTO order_items (order_id, product_key, label, amount) VALUES (?, ?, ?, ?)");
        foreach ($products as $p) {
            $insertItem->execute([$orderId, $p['product_key'], $p['label'], $p['price']]);
        }

        $db->commit();
        return $orderId;
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Called when a school submits their M-Pesa confirmation code after paying.
 * Does NOT mark the order paid — just records the code for admin review.
 */
function submit_mpesa_code(PDO $db, int $orderId, string $method, string $mpesaCode): void {
    $stmt = $db->prepare("
        UPDATE orders SET payment_method = ?, mpesa_code = ?, mpesa_code_submitted_at = NOW()
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->execute([$method, strtoupper(trim($mpesaCode)), $orderId]);
}

/**
 * Called by the admin once they've checked their M-Pesa messages and confirmed
 * the code/amount genuinely match. This is the manual equivalent of a webhook.
 */
function mark_order_paid(PDO $db, int $orderId, string $verifiedBy): void {
    $stmt = $db->prepare("UPDATE orders SET status = 'paid', paid_at = NOW(), verified_by = ? WHERE id = ?");
    $stmt->execute([$verifiedBy, $orderId]);

    // Apply the effect of what was paid for — extend plan / enable domain add-on.
    // Adjust these to match your actual schools table columns.
    $items = $db->prepare("SELECT product_key FROM order_items WHERE order_id = ?");
    $items->execute([$orderId]);
    $productKeys = array_column($items->fetchAll(), 'product_key');

    $order = $db->prepare("SELECT school_id FROM orders WHERE id = ?");
    $order->execute([$orderId]);
    $schoolId = $order->fetchColumn();

    if (in_array('paid_plan', $productKeys, true)) {
        $db->prepare("UPDATE schools SET plan = 'paid', promo_ends_at = DATE_ADD(NOW(), INTERVAL 1 YEAR) WHERE id = ?")
           ->execute([$schoolId]);
    }
    if (in_array('custom_domain', $productKeys, true)) {
        $db->prepare("UPDATE schools SET custom_domain_enabled = 1 WHERE id = ?")
           ->execute([$schoolId]);
    }
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
