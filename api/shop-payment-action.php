<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/payment_helper.php';
require_once dirname(__DIR__) . '/includes/notification_helper.php';

if (!is_logged_in($pdo) || current_role($pdo) !== ROLE_SHOP) {
    redirect(BASE_URL . 'auth/login.php');
}

csrf_require($_POST['csrf_token'] ?? null);

$user = current_user($pdo);
$shopStmt = $pdo->prepare("SELECT id, shop_name FROM shops WHERE user_id = ? LIMIT 1");
$shopStmt->execute([$user['id']]);
$shop = $shopStmt->fetch();
$shopId = (int)$shop['id'];

$action = $_POST['action'] ?? '';

try {
    switch ($action) {

        case 'submit_payment':
            $orderId = (int)($_POST['order_id'] ?? 0);
            $amount  = (float)($_POST['amount'] ?? 0);
            $method  = $_POST['payment_method'] ?? '';
            $ref     = clean($_POST['reference_no'] ?? '');
            $notes   = clean($_POST['notes'] ?? '');

           if ($orderId <= 0) throw new Exception('Order is required.');
if ($amount <= 0) throw new Exception('Amount must be greater than 0.');
if (!array_key_exists($method, PAYMENT_METHODS)) throw new Exception('Invalid payment method.');
if ($ref === '') throw new Exception('Transaction ID is required.');

// Reference ID format validation (security — client-side bypass rokne ke liye)
$refError = validate_reference_no($ref, $method);
if ($refError !== null) throw new Exception($refError);

            $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND shop_id = ? LIMIT 1");
            $stmt->execute([$orderId, $shopId]);
            $order = $stmt->fetch();
            if (!$order) throw new Exception('Order not found.');

            $stmt = $pdo->prepare(
                "SELECT COALESCE(SUM(amount), 0) FROM payments
                 WHERE order_id = ? AND status = 'pending_verification'"
            );
            $stmt->execute([$orderId]);
            $pending = (float)$stmt->fetchColumn();

            $remaining = (float)$order['total_amount'] - (float)$order['paid_amount'];
            $available = $remaining - $pending;

            if ($amount > $available + 0.01) {
                throw new Exception('Amount exceeds available balance (' . money($available) . ').');
            }

            // Screenshot upload
            $proofPath = null;
            if (!empty($_FILES['proof_image']['name'])) {
                $proofPath = upload_file($_FILES['proof_image'], 'payment_proofs', ALLOWED_IMAGE_EXT);
                if ($proofPath === null) {
                    throw new Exception('Invalid image file. Use JPG/PNG under 5 MB.');
                }
            }

            $pdo->beginTransaction();

            $paymentNo = generate_payment_no($pdo);

            $pdo->prepare(
                "INSERT INTO payments (payment_no, order_id, shop_id, amount, payment_method, 
                 reference_no, status, paid_at, notes, submitted_by, proof_image)
                 VALUES (?, ?, ?, ?, ?, ?, 'pending_verification', NOW(), ?, ?, ?)"
            )->execute([
                $paymentNo, $orderId, $shopId, $amount, $method,
                $ref, $notes, $user['id'], $proofPath
            ]);

            $pdo->commit();

            log_activity($pdo, 'payment_submitted', 'payment', $orderId, 
                'Shop submitted ' . $paymentNo . ' — ' . money($amount));

            notify_vendor(
                $pdo, 'payment_verification',
                'New payment submission',
                $shop['shop_name'] . ' submitted ' . money($amount) . ' via ' . 
                    payment_method_label($method) . ' for order ' . $order['order_number'] . '.',
                BASE_URL . 'vendor/verify-payments.php'
            );

            flash_success('Payment submitted! Vendor will verify. Reference: ' . $paymentNo);
            redirect(BASE_URL . 'shop/order-details.php?id=' . $orderId);

        default:
            flash_error('Unknown action.');
            redirect(BASE_URL . 'shop/index.php');
    }
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('shop-payment-action error: ' . $ex->getMessage());
    flash_error($ex->getMessage() ?: 'Payment submission failed.');
    redirect(BASE_URL . 'shop/orders.php');
}