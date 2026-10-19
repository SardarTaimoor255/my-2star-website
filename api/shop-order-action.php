<?php
/**
 * api/shop-order-action.php
 * Handles shop-side order actions (cancel, etc.)
 */
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/notification_helper.php';

if (!is_logged_in($pdo) || current_role($pdo) !== ROLE_SHOP) {
    if (is_ajax()) json_response(['success' => false, 'message' => 'Unauthorized'], 403);
    redirect(BASE_URL . 'auth/login.php');
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    if (is_ajax()) json_response(['success' => false, 'message' => 'Invalid security token'], 419);
    flash_error('Security token expired. Please refresh and try again.');
    redirect(BASE_URL . 'shop/orders.php');
}

$user = current_user($pdo);
$shopStmt = $pdo->prepare("SELECT id FROM shops WHERE user_id = ? LIMIT 1");
$shopStmt->execute([$user['id']]);
$shopId = (int)$shopStmt->fetchColumn();

$action  = $_POST['action'] ?? '';
$orderId = (int)($_POST['order_id'] ?? 0);

if ($orderId <= 0) {
    if (is_ajax()) json_response(['success' => false, 'message' => 'Invalid order'], 400);
    flash_error('Invalid order.');
    redirect(BASE_URL . 'shop/orders.php');
}

try {
    switch ($action) {

        // ============================================
        // CANCEL ORDER
        // ============================================
        case 'cancel_order':
            // Fetch order + verify ownership
            $stmt = $pdo->prepare(
                "SELECT o.*, s.shop_name FROM orders o
                 JOIN shops s ON s.id = o.shop_id
                 WHERE o.id = ? AND o.shop_id = ? LIMIT 1"
            );
            $stmt->execute([$orderId, $shopId]);
            $order = $stmt->fetch();
            if (!$order) throw new Exception('Order not found.');

            // Only allow cancel if status = order_created
            if ($order['status'] !== 'order_created') {
                throw new Exception(
                    'Ye order cancel nahi ho sakta. Kyunke vendor ne already process kar diya hai. ' .
                    'Vendor se chat karein.'
                );
            }

            $reason = clean($_POST['reason'] ?? '');
            if ($reason === '') $reason = 'Cancelled by shop';

            $pdo->beginTransaction();

            // Update order status
            $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$orderId]);

            // Status history
            $pdo->prepare(
                "INSERT INTO order_status_history (order_id, previous_status, new_status, changed_by, changed_by_role, note)
                 VALUES (?, 'order_created', 'cancelled', ?, 'shop', ?)"
            )->execute([$orderId, $user['id'], 'Cancelled by shop: ' . $reason]);

            $pdo->commit();

            log_activity($pdo, 'order_cancelled_by_shop', 'shop', $orderId, 'Reason: ' . $reason);

            // Notify vendor
            try {
                notify_vendor(
                    $pdo,
                    'order_status',
                    'Order Cancelled by Shop',
                    'Order ' . $order['order_number'] . ' was cancelled by ' . $order['shop_name'] . '. Reason: ' . $reason,
                    BASE_URL . 'vendor/order-details.php?id=' . $orderId
                );
            } catch (Throwable $ex) {
                error_log('Notify vendor failed: ' . $ex->getMessage());
            }

            if (is_ajax()) {
                json_response([
                    'success' => true,
                    'message' => 'Order cancelled successfully.',
                    'redirect' => BASE_URL . 'shop/orders.php'
                ]);
            }

            flash_success('Order ' . $order['order_number'] . ' cancelled successfully.');
            redirect(BASE_URL . 'shop/orders.php');

        default:
            if (is_ajax()) json_response(['success' => false, 'message' => 'Unknown action'], 400);
            flash_error('Unknown action.');
            redirect(BASE_URL . 'shop/orders.php');
    }
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('shop-order-action error: ' . $ex->getMessage());

    if (is_ajax()) {
        json_response(['success' => false, 'message' => $ex->getMessage() ?: 'Action failed.'], 500);
    }
    flash_error($ex->getMessage() ?: 'Action failed. Please try again.');
    redirect(BASE_URL . 'shop/orders.php');
}