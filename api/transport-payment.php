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

$shipmentId = (int)($_POST['shipment_id'] ?? 0);
$amount     = (float)($_POST['amount'] ?? 0);
$method     = $_POST['payment_method'] ?? '';
$ref        = clean($_POST['reference_no'] ?? '');
$notes      = clean($_POST['notes'] ?? '');

try {
  if ($shipmentId <= 0) throw new Exception('Shipment is required.');
if ($amount <= 0) throw new Exception('Amount must be greater than 0.');
if (!array_key_exists($method, PAYMENT_METHODS)) throw new Exception('Invalid payment method.');
if ($ref === '') throw new Exception('Transaction ID is required.');

// Reference ID format validation (security — client-side bypass rokne ke liye)
$refError = validate_reference_no($ref, $method);
if ($refError !== null) throw new Exception($refError);

    $stmt = $pdo->prepare(
        "SELECT s.*, o.order_number
         FROM shipments s JOIN orders o ON o.id = s.order_id
         WHERE s.id = ? AND o.shop_id = ? LIMIT 1"
    );
    $stmt->execute([$shipmentId, $shopId]);
    $shipment = $stmt->fetch();
    if (!$shipment) throw new Exception('Shipment not found.');
    if (!$shipment['transport_user_id']) throw new Exception('No transport assigned.');

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM payments
         WHERE shipment_id = ? AND recipient_type = 'transport' AND status = 'pending_verification'"
    );
    $stmt->execute([$shipmentId]);
    $pending = (float)$stmt->fetchColumn();

    $remaining = (float)$shipment['transport_charges'] - (float)$shipment['paid_amount'];
    $available = $remaining - $pending;

    if ($amount > $available + 0.01) {
        throw new Exception('Amount exceeds available balance (' . money($available) . ').');
    }

    $proofPath = null;
    if (!empty($_FILES['proof_image']['name'])) {
        $proofPath = upload_file($_FILES['proof_image'], 'payment_proofs', ALLOWED_IMAGE_EXT);
        if ($proofPath === null) throw new Exception('Invalid image file.');
    }

    $pdo->beginTransaction();

    $paymentNo = generate_payment_no($pdo);

    $pdo->prepare(
        "INSERT INTO payments (payment_no, order_id, shipment_id, recipient_type, shop_id, 
         amount, payment_method, reference_no, status, paid_at, notes, submitted_by, proof_image)
         VALUES (?, ?, ?, 'transport', ?, ?, ?, ?, 'pending_verification', NOW(), ?, ?, ?)"
    )->execute([
        $paymentNo, $shipment['order_id'], $shipmentId, $shopId,
        $amount, $method, $ref, $notes, $user['id'], $proofPath
    ]);

    $pdo->commit();

    log_activity($pdo, 'transport_payment_submitted', 'payment', $shipmentId, 
        $paymentNo . ' — ' . money($amount));

    create_notification(
        $pdo, (int)$shipment['transport_user_id'], 'transport_payment',
        'New delivery payment received',
        $shop['shop_name'] . ' submitted ' . money($amount) . ' for order ' . $shipment['order_number'],
        BASE_URL . 'transport/payments.php'
    );

    flash_success('Transport payment submitted! Reference: ' . $paymentNo);
    redirect(BASE_URL . 'shop/order-details.php?id=' . $shipment['order_id']);

} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('transport-payment error: ' . $ex->getMessage());
    flash_error($ex->getMessage() ?: 'Payment failed.');
    redirect(BASE_URL . 'shop/orders.php');
}