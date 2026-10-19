<?php
if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

/**
 * Get existing invoice for an order, or create a new one.
 * Returns invoice row (assoc array).
 */
function get_or_create_invoice(PDO $pdo, int $orderId): array
{
    // Try fetch existing
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE order_id = ? LIMIT 1");
    $stmt->execute([$orderId]);
    $inv = $stmt->fetch();
    if ($inv) return $inv;

    // Fetch order + shop
    $stmt = $pdo->prepare(
        "SELECT o.*, s.shop_name, s.address AS shop_address, s.user_id AS shop_user_id
         FROM orders o JOIN shops s ON s.id = o.shop_id
         WHERE o.id = ? LIMIT 1"
    );
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        throw new Exception('Order not found.');
    }

    // Fetch items
    $stmt = $pdo->prepare(
        "SELECT oi.*, p.name AS product_name, p.sku, p.unit
         FROM order_items oi
         JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id = ?"
    );
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll();

    $pdo->beginTransaction();

    // Generate invoice number
    $invoiceNo = generate_number($pdo, 'INV', 'invoices', 'invoice_no');

    // Insert invoice
    $pdo->prepare(
        "INSERT INTO invoices (invoice_no, order_id, shop_id, vendor_id, subtotal,
          commission_total, transport_charges, discount, total, payment_status, issued_at, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)"
    )->execute([
        $invoiceNo,
        $orderId,
        $order['shop_id'],
        $order['vendor_id'],
        $order['subtotal'],
        $order['commission_total'],
        $order['transport_charges'],
        $order['discount'],
        $order['total_amount'],
        $order['payment_status'],
        $order['notes']
    ]);
    $invoiceId = (int)$pdo->lastInsertId();

    // Insert items
    foreach ($items as $it) {
        $desc = $it['product_name'] . ' (' . $it['sku'] . ')';
        $pdo->prepare(
            "INSERT INTO invoice_items (invoice_id, product_id, description, quantity, unit_price, line_total)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([
            $invoiceId, $it['product_id'], $desc,
            $it['quantity'], $it['final_price'], $it['line_total']
        ]);
    }

    $pdo->commit();

    log_activity($pdo, 'invoice_created', 'invoice', $invoiceId, 'Invoice ' . $invoiceNo . ' for order #' . $orderId);

    // Fetch and return
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ? LIMIT 1");
    $stmt->execute([$invoiceId]);
    return $stmt->fetch();
}

/**
 * Get invoice items
 */
function get_invoice_items(PDO $pdo, int $invoiceId): array
{
    $stmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id");
    $stmt->execute([$invoiceId]);
    return $stmt->fetchAll();
}

/**
 * Format invoice number with monospace style
 */
function invoice_no_html(string $no): string
{
    return '<code>' . e($no) . '</code>';
}