<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/payment_helper.php';
require_once dirname(__DIR__) . '/includes/notification_helper.php';

if (!is_logged_in($pdo) || current_role($pdo) !== ROLE_VENDOR) {
    redirect(BASE_URL . 'auth/login.php');
}

csrf_require($_POST['csrf_token'] ?? null);

$user = current_user($pdo);

$orderId   = (int)($_POST['order_id'] ?? 0);
$companyId = (int)($_POST['company_id'] ?? 0);
$amount    = (float)($_POST['amount'] ?? 0);
$method    = $_POST['payment_method'] ?? '';
$ref       = clean($_POST['reference_no'] ?? '');
$notes     = clean($_POST['notes'] ?? '');

try {
    if ($orderId <= 0 || $companyId <= 0) throw new Exception('Order and company are required.');
    if ($amount <= 0) throw new Exception('Amount must be greater than 0.');
    if ($ref === '') throw new Exception('Transaction ID is required.');
   
    if (!array_key_exists($method, PAYMENT_METHODS)) throw new Exception('Invalid payment method.');
// Reference ID format validation (security — client-side bypass rokne ke liye)
$refError = validate_reference_no($ref, $method);
if ($refError !== null) throw new Exception($refError);
    // Verify order + company items
    $stmt = $pdo->prepare(
        "SELECT SUM(company_base_price * quantity) AS due
         FROM order_items WHERE order_id = ? AND company_id = ?"
    );
    $stmt->execute([$orderId, $companyId]);
    $totalDue = (float)$stmt->fetchColumn();
    if ($totalDue <= 0) throw new Exception('No items from this company.');

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM payments 
         WHERE order_id = ? AND company_id = ? AND recipient_type='company' AND status='paid'"
    );
    $stmt->execute([$orderId, $companyId]);
    $alreadyPaid = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM payments 
         WHERE order_id = ? AND company_id = ? AND recipient_type='company' AND status='pending_verification'"
    );
    $stmt->execute([$orderId, $companyId]);
    $pending = (float)$stmt->fetchColumn();

    $available = ($totalDue - $alreadyPaid) - $pending;

    if ($amount > $available + 0.01) {
        throw new Exception('Amount exceeds available balance (' . money($available) . ').');
    }

    // Upload
    $proofPath = null;
    if (!empty($_FILES['proof_image']['name'])) {
        $proofPath = upload_file($_FILES['proof_image'], 'payment_proofs', ALLOWED_IMAGE_EXT);
        if ($proofPath === null) throw new Exception('Invalid image.');
    }

    $pdo->beginTransaction();

    $paymentNo = generate_payment_no($pdo);

    // Get vendor id and shop id
    $vStmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ? LIMIT 1");
    $vStmt->execute([$user['id']]);
    $vendorId = (int)$vStmt->fetchColumn();

    $oStmt = $pdo->prepare("SELECT shop_id FROM orders WHERE id = ?");
    $oStmt->execute([$orderId]);
    $shopId = (int)$oStmt->fetchColumn();

    $pdo->prepare(
        "INSERT INTO payments (payment_no, order_id, company_id, recipient_type, shop_id, 
         amount, payment_method, reference_no, status, paid_at, notes, submitted_by, proof_image)
         VALUES (?, ?, ?, 'company', ?, ?, ?, ?, 'pending_verification', NOW(), ?, ?, ?)"
    )->execute([
        $paymentNo, $orderId, $companyId, $shopId,
        $amount, $method, $ref, $notes, $user['id'], $proofPath
    ]);

    $pdo->commit();

    log_activity($pdo, 'company_payment_submitted', 'payment', $orderId, 
        $paymentNo . ' — ' . money($amount));

    // Notify company user
    $cStmt = $pdo->prepare("SELECT user_id FROM companies WHERE id = ? LIMIT 1");
    $cStmt->execute([$companyId]);
    $companyUserId = (int)$cStmt->fetchColumn();

    if ($companyUserId > 0) {
        create_notification(
            $pdo, $companyUserId, 'company_payment',
            'New payment from vendor',
            'Vendor submitted ' . money($amount) . ' for order payment.',
            BASE_URL . 'company/verify-payments.php'
        );
    }

    flash_success('Payment submitted! Reference: ' . $paymentNo);
    redirect(BASE_URL . 'vendor/order-details.php?id=' . $orderId);

} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('company-payment error: ' . $ex->getMessage());
    flash_error($ex->getMessage() ?: 'Payment failed.');
    redirect(BASE_URL . 'vendor/orders.php');
}