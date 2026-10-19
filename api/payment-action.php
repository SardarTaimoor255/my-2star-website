<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/payment_helper.php';
require_once dirname(__DIR__) . '/includes/notification_helper.php';

$user = current_user($pdo);
if (!$user) {
    if (is_ajax()) json_response(['success' => false, 'message' => 'Unauthorized'], 403);
    redirect(BASE_URL . 'auth/login.php');
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    if (is_ajax()) json_response(['success' => false, 'message' => 'Invalid token'], 419);
    flash_error('Security token expired.');
    redirect(BASE_URL . 'vendor/index.php');
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {

        case 'record':
            // Only vendor or admin can record payments
            if (!in_array($user['role_name'], [ROLE_VENDOR, ROLE_ADMIN], true)) {
                throw new Exception('Unauthorized to record payments.');
            }

            $orderId = (int)($_POST['order_id'] ?? 0);
            $amount  = (float)($_POST['amount'] ?? 0);
            $method  = $_POST['payment_method'] ?? '';
            $ref     = clean($_POST['reference_no'] ?? '');
            $notes   = clean($_POST['notes'] ?? '');

            if ($orderId <= 0) throw new Exception('Order is required.');
            if ($amount <= 0) throw new Exception('Amount must be greater than 0.');
            if (!array_key_exists($method, PAYMENT_METHODS)) throw new Exception('Invalid payment method.');

            // Fetch order
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            if (!$order) throw new Exception('Order not found.');

            $remaining = (float)$order['total_amount'] - (float)$order['paid_amount'];

            // Allow over-payment? No — cap at remaining
            if ($amount > $remaining + 0.01) {
                throw new Exception('Amount exceeds remaining balance (' . money($remaining) . ').');
            }

            $pdo->beginTransaction();

            $paymentNo = generate_payment_no($pdo);

            $pdo->prepare(
                "INSERT INTO payments (payment_no, order_id, shop_id, amount, payment_method, reference_no, status, paid_at, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, 'paid', NOW(), ?, ?)"
            )->execute([$paymentNo, $orderId, $order['shop_id'], $amount, $method, $ref, $notes, $user['id']]);

            // Recalculate order payment status
            recalc_order_payment($pdo, $orderId);

            $pdo->commit();

            log_activity($pdo, 'payment_recorded', 'payment', $orderId, 'Payment ' . $paymentNo . ' — ' . money($amount));

            // Notify shop
            notify_shop(
                $pdo, (int)$order['shop_id'], 'payment_recorded',
                'Payment recorded',
                'Payment of ' . money($amount) . ' received on order ' . $order['order_number'],
                BASE_URL . 'shop/order-details.php?id=' . $orderId
            );

            if (is_ajax()) json_response(['success' => true, 'message' => 'Payment recorded', 'payment_no' => $paymentNo]);
            flash_success('Payment ' . $paymentNo . ' recorded successfully.');
            redirect(BASE_URL . 'vendor/order-details.php?id=' . $orderId);

        default:
            if (is_ajax()) json_response(['success' => false, 'message' => 'Unknown action'], 400);
            flash_error('Unknown action.');
            redirect(BASE_URL . 'vendor/index.php');
    }
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('payment-action error: ' . $ex->getMessage());
    if (is_ajax()) json_response(['success' => false, 'message' => $ex->getMessage()], 500);
    flash_error($ex->getMessage() ?: 'Action failed. Please try again.');
    redirect(BASE_URL . 'vendor/index.php');
}