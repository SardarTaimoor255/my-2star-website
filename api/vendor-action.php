<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/notification_helper.php';
require_once dirname(__DIR__) . '/includes/payment_helper.php';

if (!is_logged_in($pdo) || current_role($pdo) !== ROLE_VENDOR) {
    if (is_ajax()) json_response(['success' => false, 'message' => 'Unauthorized'], 403);
    redirect(BASE_URL . 'auth/login.php');
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    if (is_ajax()) json_response(['success' => false, 'message' => 'Invalid token'], 419);
    flash_error('Security token expired.');
    redirect(BASE_URL . 'vendor/index.php');
}

$action = $_POST['action'] ?? '';
$id     = (int)($_POST['id'] ?? 0);
$user   = current_user($pdo);

$vendorStmt = $pdo->prepare("SELECT * FROM vendors WHERE user_id = ? LIMIT 1");
$vendorStmt->execute([$user['id']]);
$vendor = $vendorStmt->fetch();
$vendorId = (int)$vendor['id'];

try {
    switch ($action) {

        case 'send_to_company':
            $companyId = (int)($_POST['company_id'] ?? 0);
            if ($companyId <= 0) throw new Exception('Company required.');

            $pdo->prepare("UPDATE price_requests SET status='sent_to_company' WHERE id=?")->execute([$id]);
            $pdo->prepare("UPDATE price_request_items SET company_id=? WHERE request_id=?")->execute([$companyId, $id]);
            $pdo->prepare("INSERT INTO price_request_history (request_id, changed_by, changed_by_role, previous_status, new_status, note)
                           VALUES (?, ?, 'vendor', 'pending', 'sent_to_company', 'Sent to company for pricing')")
                ->execute([$id, $user['id']]);

            log_activity($pdo, 'pr_sent_to_company', 'vendor', $id, 'Request sent to company #' . $companyId);
            if (is_ajax()) json_response(['success' => true, 'message' => 'Sent to company']);
            flash_success('Request sent to company.');
            redirect(BASE_URL . 'vendor/price-request-details.php?id=' . $id);

        case 'quote':
            $companyPrices = $_POST['company_price'] ?? [];
            $commissions   = $_POST['commission'] ?? [];

            if (empty($companyPrices)) throw new Exception('No prices submitted.');

            $pdo->beginTransaction();
            $totalFinal = 0;

            foreach ($companyPrices as $itemId => $companyPrice) {
                $itemId = (int)$itemId;
                $companyPrice = (float)$companyPrice;
                $comm = (float)($commissions[$itemId] ?? 0);
                $final = $companyPrice + $comm;

                $q = $pdo->prepare("SELECT quantity FROM price_request_items WHERE id=? AND request_id=?");
                $q->execute([$itemId, $id]);
                $qty = (int)$q->fetchColumn();
                $lineTotal = $final * $qty;
                $totalFinal += $lineTotal;

                $pdo->prepare("UPDATE price_request_items SET company_base_price=?, vendor_commission=?, final_price=?, line_total=?, status='quoted' WHERE id=?")
                    ->execute([$companyPrice, $comm, $final, $lineTotal, $itemId]);
                                    // Save as shop-specific price for this product
                $stmtShop = $pdo->prepare("SELECT shop_id FROM price_requests WHERE id = ?");
                $stmtShop->execute([$id]);
                $reqShopId = (int)$stmtShop->fetchColumn();

                $stmtProd = $pdo->prepare("SELECT product_id FROM price_request_items WHERE id = ?");
                $stmtProd->execute([$itemId]);
                $reqProductId = (int)$stmtProd->fetchColumn();

                if ($reqShopId > 0 && $reqProductId > 0) {
                    // Upsert shop_specific_prices
                    $pdo->prepare(
                        "INSERT INTO shop_specific_prices (product_id, shop_id, custom_price, is_active)
                         VALUES (?, ?, ?, 1)
                         ON DUPLICATE KEY UPDATE custom_price = VALUES(custom_price), is_active = 1"
                    )->execute([$reqProductId, $reqShopId, $final]);
                }
            }

            $pdo->prepare("UPDATE price_requests SET status='quoted', total_amount=? WHERE id=?")->execute([$totalFinal, $id]);
            $pdo->prepare("INSERT INTO price_request_history (request_id, changed_by, changed_by_role, previous_status, new_status, note)
                           VALUES (?, ?, 'vendor', 'sent_to_company', 'quoted', 'Final quote sent to shop')")
                ->execute([$id, $user['id']]);

            $pdo->commit();

            log_activity($pdo, 'pr_quoted', 'vendor', $id, 'Quote sent: ' . $totalFinal);
            if (is_ajax()) json_response(['success' => true, 'message' => 'Quote sent', 'total' => $totalFinal]);
            flash_success('Quote sent to shop.');
            redirect(BASE_URL . 'vendor/price-request-details.php?id=' . $id);

        case 'accept_order':
            $pdo->prepare("UPDATE orders SET status='order_accepted' WHERE id=?")->execute([$id]);
            $pdo->prepare("INSERT INTO order_status_history (order_id, previous_status, new_status, changed_by, changed_by_role, note)
                           VALUES (?, 'order_created', 'order_accepted', ?, 'vendor', 'Accepted by vendor')")
                ->execute([$id, $user['id']]);
            log_activity($pdo, 'order_accepted', 'vendor', $id, 'Order accepted');
            if (is_ajax()) json_response(['success' => true]);
            redirect(BASE_URL . 'vendor/order-details.php?id=' . $id);

               case 'reject_order':
            // Get order info BEFORE updating
            $ordInfo = $pdo->prepare("SELECT o.order_number, s.user_id AS shop_user_id FROM orders o JOIN shops s ON s.id = o.shop_id WHERE o.id = ?");
            $ordInfo->execute([$id]);
            $oi = $ordInfo->fetch();
            
            $prev = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
            $prev->execute([$id]);
            $prevStatus = $prev->fetchColumn();

            $pdo->prepare("UPDATE orders SET status='cancelled' WHERE id=?")->execute([$id]);
            $pdo->prepare("INSERT INTO order_status_history (order_id, previous_status, new_status, changed_by, changed_by_role, note)
                           VALUES (?, ?, 'cancelled', ?, 'vendor', 'Rejected by vendor')")
                ->execute([$id, $prevStatus, $user['id']]);
            log_activity($pdo, 'order_rejected', 'vendor', $id, 'Order rejected');

            // Notify shop
            if ($oi && !empty($oi['shop_user_id'])) {
                try {
                    create_notification(
                        $pdo,
                        (int)$oi['shop_user_id'],
                        'order_status',
                        'Order Rejected',
                        'Your order ' . $oi['order_number'] . ' has been cancelled by vendor.',
                        BASE_URL . 'shop/order-details.php?id=' . $id
                    );
                } catch (Throwable $ex) {
                    error_log('Notify shop failed: ' . $ex->getMessage());
                }
            }

            if (is_ajax()) json_response(['success' => true]);
            redirect(BASE_URL . 'vendor/order-details.php?id=' . $id);
        case 'send_to_company_order':
            $companyId = (int)($_POST['company_id'] ?? 0);
            if ($companyId <= 0) throw new Exception('Company required.');

            // Verify order has items from this company
            $chk = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ? AND company_id = ?");
            $chk->execute([$id, $companyId]);
            if ((int)$chk->fetchColumn() === 0) {
                throw new Exception('Order has no items from this company.');
            }

            // Get current status
            $prev = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
            $prev->execute([$id]);
            $prevStatus = $prev->fetchColumn();

            $pdo->beginTransaction();

            // Update order status
            $pdo->prepare("UPDATE orders SET status = 'sent_to_company' WHERE id = ?")->execute([$id]);

            // History
            $pdo->prepare(
                "INSERT INTO order_status_history (order_id, previous_status, new_status, changed_by, changed_by_role, note)
                 VALUES (?, ?, 'sent_to_company', ?, 'vendor', ?)"
            )->execute([$id, $prevStatus, $user['id'], 'Sent to company for confirmation']);

            $pdo->commit();

            log_activity($pdo, 'order_sent_to_company', 'vendor', $id, 'Order sent to company #' . $companyId);

            // Notify company
            try {
                $coUserStmt = $pdo->prepare("SELECT user_id FROM companies WHERE id = ? LIMIT 1");
                $coUserStmt->execute([$companyId]);
                $coUserId = (int)$coUserStmt->fetchColumn();

                $ordInfo = $pdo->prepare("SELECT order_number FROM orders WHERE id = ?");
                $ordInfo->execute([$id]);
                $ordNo = $ordInfo->fetchColumn();

                if ($coUserId > 0) {
                    create_notification(
                        $pdo, $coUserId, 'order',
                        'New Order Received',
                        'Order ' . $ordNo . ' sent to you. Please confirm availability and prepare products.',
                        BASE_URL . 'company/orders.php'
                    );
                }
            } catch (Throwable $ex) {
                error_log('Notify company failed: ' . $ex->getMessage());
            }

            flash_success('Order sent to company.');
            redirect(BASE_URL . 'vendor/order-details.php?id=' . $id);

                             case 'assign_transport':
            $transportUserId = (int)($_POST['transport_user_id'] ?? 0);
            if ($transportUserId <= 0) throw new Exception('Transport required.');

            $order = $pdo->prepare("SELECT * FROM orders WHERE id=?");
            $order->execute([$id]);
            $ord = $order->fetch();
            if (!$ord) throw new Exception('Order not found.');

            $shop = $pdo->prepare("SELECT s.* FROM shops s WHERE s.id = ?");
            $shop->execute([$ord['shop_id']]);
            $shopInfo = $shop->fetch();
// Delivery address — order se lo, warna shop ke profile se
$deliveryAddress = trim($ord['delivery_address'] ?? '') ?: ($shopInfo['address'] ?? '');
            // Check existing shipment
            $existingStmt = $pdo->prepare(
                "SELECT id, shipment_no, status, transport_user_id 
                 FROM shipments WHERE order_id = ? ORDER BY id DESC LIMIT 1"
            );
            $existingStmt->execute([$id]);
            $existingShipment = $existingStmt->fetch();

            if ($existingShipment && !in_array($existingShipment['status'], ['delivery_failed', 'returned'], true)) {
                throw new Exception('Shipment already active. Status: ' . $existingShipment['status']);
            }

            $pdo->beginTransaction();

            if ($existingShipment) {
                // ============================================
                // REASSIGNMENT
                // ============================================
                $oldTransportId = (int)$existingShipment['transport_user_id'];

                // Cancel old transport ki IS ORDER ki pending payments
                $pdo->prepare(
                    "UPDATE payments 
                     SET status = 'cancelled', 
                         rejection_reason = 'Transport reassigned — delivery not completed'
                     WHERE shipment_id = ? 
                       AND recipient_type = 'transport'
                       AND status IN ('pending', 'pending_verification')"
                )->execute([$existingShipment['id']]);

                // Update shipment with new transport + reset
                $pdo->prepare(
                    "UPDATE shipments SET 
                        transport_user_id = ?,
                        delivery_address = ?,
                        status = 'assigned',
                        transport_charges = 0,
                        paid_amount = 0,
                        payment_status = 'pending',
                        vehicle_no = NULL,
                        vehicle_type = NULL,
                        driver_name = NULL,
                        driver_phone = NULL,
                        bilty_no = NULL,
                        delivered_at = NULL,
                        expected_delivery = NULL
                     WHERE id = ?"
                )->execute([$transportUserId, $deliveryAddress, $existingShipment['id']]);

                // Tracking log
                $pdo->prepare(
                    "INSERT INTO shipment_tracking (shipment_id, status, note, updated_by)
                     VALUES (?, 'assigned', 'Reassigned by vendor — new transport assigned', ?)"
                )->execute([$existingShipment['id'], $user['id']]);

                // Notify OLD transport
                if ($oldTransportId > 0 && $oldTransportId !== $transportUserId) {
                    try {
                        create_notification(
                            $pdo, $oldTransportId, 'shipment_removed',
                            'Shipment removed from you',
                            'Order ' . $ord['order_number'] . ' has been reassigned to a different transport company. Any pending payment for this order has been cancelled.',
                            BASE_URL . 'transport/shipments.php'
                        );
                    } catch (Throwable $ex) { error_log('Notify old transport: ' . $ex->getMessage()); }
                }

                $shipmentNo = $existingShipment['shipment_no'];

            } else {
                // ============================================
                // FIRST TIME ASSIGN
                // ============================================
                $shipmentNo = generate_number($pdo, 'SHP', 'shipments', 'shipment_no');

                $pdo->prepare(
                    "INSERT INTO shipments (shipment_no, order_id, transport_user_id, delivery_address, 
                     status, transport_charges, paid_amount, payment_status)
                     VALUES (?, ?, ?, ?, 'assigned', 0, 0, 'pending')"
                )->execute([$shipmentNo, $id, $transportUserId, $deliveryAddress]);
            }

            // Update order status
            $pdo->prepare("UPDATE orders SET status='transport_assigned' WHERE id=?")->execute([$id]);

            // Order history
            $pdo->prepare(
                "INSERT INTO order_status_history (order_id, previous_status, new_status, changed_by, changed_by_role, note)
                 VALUES (?, ?, 'transport_assigned', ?, 'vendor', ?)"
            )->execute([$id, $ord['status'], $user['id'], 
                $existingShipment ? 'Transport reassigned by vendor' : 'Transport assigned']);

            $pdo->commit();

            log_activity($pdo, 'transport_assigned', 'vendor', $id, 'Shipment: ' . $shipmentNo);

            // Add new transport to order chat
           

            // Notify NEW transport
            create_notification(
                $pdo, $transportUserId, 'shipment_assigned',
                $existingShipment ? 'Shipment assigned to you' : 'New shipment assigned',
                'Order ' . $ord['order_number'] . ' — Please set delivery charges, vehicle, and bilty details.',
                BASE_URL . 'transport/shipments.php'
            );

            if (is_ajax()) json_response(['success' => true]);
            flash_success($existingShipment 
                ? 'Transport reassigned. New transport notified.' 
                : 'Transport assigned. Waiting for them to set details.');
            redirect(BASE_URL . 'vendor/order-details.php?id=' . $id);

        // ============================================
        // NEW: Verify a shop-submitted payment
        // ============================================
        case 'verify_payment':
            $paymentId = $id;
            $decision  = $_POST['decision'] ?? '';
            $reason    = clean($_POST['rejection_reason'] ?? '');

            if (!in_array($decision, ['approve', 'reject'], true)) {
                throw new Exception('Invalid decision.');
            }

            $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? AND status = 'pending_verification' LIMIT 1");
            $stmt->execute([$paymentId]);
            $payment = $stmt->fetch();
            if (!$payment) throw new Exception('Payment not found or already processed.');

            $pdo->beginTransaction();

            if ($decision === 'approve') {
                $pdo->prepare(
                    "UPDATE payments SET status = 'paid', verified_by = ?, verified_at = NOW() WHERE id = ?"
                )->execute([$user['id'], $paymentId]);

                recalc_order_payment($pdo, (int)$payment['order_id']);

                $msg = 'Your payment of ' . money($payment['amount']) . ' has been approved.';
                log_activity($pdo, 'payment_verified', 'payment', $paymentId, 'Approved ' . $payment['payment_no']);
            } else {
                if ($reason === '') $reason = 'Payment could not be verified.';
                $pdo->prepare(
                    "UPDATE payments SET status = 'failed', verified_by = ?, verified_at = NOW(), rejection_reason = ? WHERE id = ?"
                )->execute([$user['id'], $reason, $paymentId]);

                $msg = 'Your payment of ' . money($payment['amount']) . ' was rejected. Reason: ' . $reason;
                log_activity($pdo, 'payment_rejected', 'payment', $paymentId, 'Rejected: ' . $reason);
            }

            $pdo->commit();

            notify_shop(
                $pdo, (int)$payment['shop_id'], 'payment_verification',
                $decision === 'approve' ? 'Payment Approved' : 'Payment Rejected',
                $msg,
                BASE_URL . 'shop/order-details.php?id=' . $payment['order_id']
            );

            flash_success($decision === 'approve' ? 'Payment approved.' : 'Payment rejected.');
            redirect(BASE_URL . 'vendor/verify-payments.php');

        default:
            if (is_ajax()) json_response(['success' => false, 'message' => 'Unknown action'], 400);
            flash_error('Unknown action.');
            redirect(BASE_URL . 'vendor/index.php');
    }
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('vendor-action error: ' . $ex->getMessage());
    if (is_ajax()) json_response(['success' => false, 'message' => $ex->getMessage()], 500);
    flash_error($ex->getMessage() ?: 'Action failed. Please try again.');
    redirect(BASE_URL . 'vendor/index.php');
}