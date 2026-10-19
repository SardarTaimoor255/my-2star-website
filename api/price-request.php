<?php
/**
 * api/price-request.php
 */
require_once dirname(__DIR__) . '/includes/init.php';

if (!is_logged_in($pdo) || current_role($pdo) !== ROLE_SHOP) {
    redirect(BASE_URL . 'auth/login.php');
}

csrf_require($_POST['csrf_token'] ?? null);

$user = current_user($pdo);
$shopStmt = $pdo->prepare("SELECT id FROM shops WHERE user_id = ? LIMIT 1");
$shopStmt->execute([$user['id']]);
$shopId = (int)$shopStmt->fetchColumn();

$productId = (int)($_POST['product_id'] ?? 0);
$quantity  = max(1, (int)($_POST['quantity'] ?? 1));
$desired   = $_POST['desired_price'] !== '' ? (float)$_POST['desired_price'] : null;
$notes     = clean($_POST['notes'] ?? '');

if ($productId <= 0) {
    flash_error('Please select a product.');
    redirect(BASE_URL . 'shop/request-price.php');
}

try {
    $pdo->beginTransaction();

    $requestNo = generate_number($pdo, 'PR', 'price_requests', 'request_no');
    $vendorId = (int)$pdo->query("SELECT id FROM vendors LIMIT 1")->fetchColumn() ?: null;
    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));

    $stmt = $pdo->prepare(
        "INSERT INTO price_requests (request_no, shop_id, vendor_id, status, notes, expires_at)
         VALUES (?, ?, ?, 'pending', ?, ?)"
    );
    $stmt->execute([$requestNo, $shopId, $vendorId, $notes, $expiresAt]);
    $requestId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare(
        "INSERT INTO price_request_items (request_id, product_id, quantity, shop_desired_price, status)
         VALUES (?, ?, ?, ?, 'pending')"
    );
    $stmt->execute([$requestId, $productId, $quantity, $desired]);

    $stmt = $pdo->prepare(
        "INSERT INTO price_request_history (request_id, changed_by, changed_by_role, previous_status, new_status, note)
         VALUES (?, ?, 'shop', NULL, 'pending', 'Request submitted')"
    );
    $stmt->execute([$requestId, $user['id']]);

    $pdo->commit();

    log_activity($pdo, 'price_request_created', 'shop', $requestId, 'Price request ' . $requestNo);
    flash_success('Price request submitted! Request #' . $requestNo);
    redirect(BASE_URL . 'shop/price-requests.php');

} catch (Throwable $ex) {
    $pdo->rollBack();
    error_log('Price request error: ' . $ex->getMessage());
    flash_error('Could not submit request. Please try again.');
    redirect(BASE_URL . 'shop/request-price.php');
}