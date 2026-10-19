<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/notification_helper.php';
require_once dirname(__DIR__) . '/includes/payment_helper.php';
if (!is_logged_in($pdo) || current_role($pdo) !== ROLE_TRANSPORT) {
    if (is_ajax()) json_response(['success' => false, 'message' => 'Unauthorized'], 403);
    redirect(BASE_URL . 'auth/login.php');
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    if (is_ajax()) json_response(['success' => false, 'message' => 'Invalid token'], 419);
    flash_error('Security token expired.');
    redirect(BASE_URL . 'transport/index.php');
}

$action = $_POST['action'] ?? '';
$id     = (int)($_POST['id'] ?? 0);
$user   = current_user($pdo);

try {
    switch ($action) {

        case 'update_status':
            $newStatus = $_POST['status'] ?? '';
            $allowed = ['picked_up','in_transit','arrived_destination','out_for_delivery','delivered','delivery_failed','returned'];
            if (!in_array($newStatus, $allowed, true)) {
                throw new Exception('Invalid status.');
            }

            // Verify shipment belongs to this user
            $stmt = $pdo->prepare("SELECT * FROM shipments WHERE id = ? AND transport_user_id = ? LIMIT 1");
            $stmt->execute([$id, $user['id']]);
            $shipment = $stmt->fetch();
            if (!$shipment) throw new Exception('Shipment not found.');

            $pdo->beginTransaction();

            // Update shipment
            $update = "UPDATE shipments SET status = ?";
            $params = [$newStatus];
            if ($newStatus === 'delivered') {
                $update .= ", delivered_at = NOW()";
            }
            $update .= " WHERE id = ?";
            $params[] = $id;
            $pdo->prepare($update)->execute($params);

            // Add tracking event
            $pdo->prepare(
                "INSERT INTO shipment_tracking (shipment_id, status, note, updated_by)
                 VALUES (?, ?, ?, ?)"
            )->execute([$id, $newStatus, 'Status updated by transport', $user['id']]);

            // Map shipment status to order status
            $orderStatusMap = [
                'picked_up'           => 'picked_up',
                'in_transit'          => 'in_transit',
                'arrived_destination' => 'arrived_destination',
                'out_for_delivery'    => 'out_for_delivery',
                'delivered'           => 'delivered',
                'delivery_failed'     => 'delivery_failed',
                'returned'            => 'returned',
            ];
            $orderStatus = $orderStatusMap[$newStatus] ?? null;

            if ($orderStatus) {
                // Get previous order status
                $prevStmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
                $prevStmt->execute([$shipment['order_id']]);
                $prevOrderStatus = $prevStmt->fetchColumn();

                // Update order
                $orderUpdate = "UPDATE orders SET status = ?";
                $orderParams = [$orderStatus];
                if ($newStatus === 'delivered') {
                    $orderUpdate .= ", delivered_at = NOW()";
                }
                $orderUpdate .= " WHERE id = ?";
                $orderParams[] = $shipment['order_id'];
                $pdo->prepare($orderUpdate)->execute($orderParams);

                // Order status history
                $pdo->prepare(
                    "INSERT INTO order_status_history (order_id, previous_status, new_status, changed_by, changed_by_role, note)
                     VALUES (?, ?, ?, ?, 'transport', ?)"
                )->execute([
                    $shipment['order_id'], $prevOrderStatus, $orderStatus, $user['id'],
                    'Shipment ' . $shipment['shipment_no'] . ': ' . SHIPMENT_STATUSES[$newStatus]
                ]);

                // Notifications
                $orderInfo = $pdo->prepare("SELECT o.*, sh.user_id AS shop_user_id FROM orders o JOIN shops sh ON sh.id = o.shop_id WHERE o.id = ?");
                $orderInfo->execute([$shipment['order_id']]);
                $orderData = $orderInfo->fetch();

                $notifTitle = 'Order ' . $orderData['order_number'] . ' update';
                $notifMsg   = 'Status: ' . (SHIPMENT_STATUSES[$newStatus] ?? $newStatus);

                if ($orderData) {
                    // Notify shop
                    create_notification(
                        $pdo, (int)$orderData['shop_user_id'], 'order_status',
                        $notifTitle, $notifMsg,
                        BASE_URL . 'shop/order-details.php?id=' . $orderData['id']
                    );
                    // Notify vendor
                    notify_vendor(
                        $pdo, 'order_status',
                        $notifTitle, $notifMsg,
                        BASE_URL . 'vendor/order-details.php?id=' . $orderData['id']
                    );

                    // If delivered, also notify the company that supplied
                    if ($newStatus === 'delivered') {
                        $comp = $pdo->prepare(
                            "SELECT DISTINCT oi.company_id FROM order_items oi
                             WHERE oi.order_id = ? AND oi.company_id IS NOT NULL"
                        );
                        $comp->execute([$orderData['id']]);
                        foreach ($comp->fetchAll() as $c) {
                            $cu = $pdo->prepare("SELECT user_id FROM companies WHERE id = ?");
                            $cu->execute([$c['company_id']]);
                            $cuid = $cu->fetchColumn();
                            if ($cuid) {
                                create_notification(
                                    $pdo, (int)$cuid, 'order_delivered',
                                    'Order delivered',
                                    'Order ' . $orderData['order_number'] . ' has been delivered to the shop.',
                                    BASE_URL . 'company/orders.php'
                                );
                            }
                        }
                    }
                }
            }

            $pdo->commit();

            log_activity($pdo, 'shipment_status_updated', 'transport', $id, 'Shipment ' . $shipment['shipment_no'] . ' → ' . $newStatus);

            if (is_ajax()) json_response(['success' => true, 'message' => 'Status updated to ' . (SHIPMENT_STATUSES[$newStatus] ?? $newStatus)]);
            flash_success('Shipment status updated.');
            redirect(BASE_URL . 'transport/shipment-details.php?id=' . $id);
        // ============================================
        // SET VEHICLE & BILTY (transport sets)
        // ============================================
        case 'set_vehicle_bilty':
            $vehicleNo = clean($_POST['vehicle_no'] ?? '');
            $biltyNo   = clean($_POST['bilty_no'] ?? '');
            $driverName  = clean($_POST['driver_name'] ?? '');
            $driverPhone = clean($_POST['driver_phone'] ?? '');

            if ($vehicleNo === '') throw new Exception('Vehicle number is required.');
            if ($biltyNo === '')   throw new Exception('Bilty number is required.');

            // Verify shipment belongs to this transport user
            $stmt = $pdo->prepare(
                "SELECT s.*, o.order_number, o.shop_id, o.vendor_id 
                 FROM shipments s
                 JOIN orders o ON o.id = s.order_id
                 WHERE s.id = ? AND s.transport_user_id = ? LIMIT 1"
            );
            $stmt->execute([$id, $user['id']]);
            $shipment = $stmt->fetch();
            if (!$shipment) throw new Exception('Shipment not found.');

            $pdo->beginTransaction();

            $pdo->prepare(
                "UPDATE shipments SET vehicle_no = ?, bilty_no = ?, driver_name = ?, driver_phone = ? WHERE id = ?"
            )->execute([$vehicleNo, $biltyNo, $driverName ?: null, $driverPhone ?: null, $id]);

            // Add to tracking
            $pdo->prepare(
                "INSERT INTO shipment_tracking (shipment_id, status, note, updated_by)
                 VALUES (?, ?, ?, ?)"
            )->execute([
                $id, $shipment['status'],
                'Vehicle: ' . $vehicleNo . ' · Bilty: ' . $biltyNo . ($driverName ? ' · Driver: ' . $driverName : ''),
                $user['id']
            ]);

            $pdo->commit();

            log_activity($pdo, 'shipment_vehicle_bilty_set', 'transport', $id, 
                'Vehicle: ' . $vehicleNo . ', Bilty: ' . $biltyNo);

            // Notify shop
            try {
                $shopUserStmt = $pdo->prepare("SELECT user_id FROM shops WHERE id = ? LIMIT 1");
                $shopUserStmt->execute([$shipment['shop_id']]);
                $shopUserId = (int)$shopUserStmt->fetchColumn();

                if ($shopUserId > 0) {
                    create_notification(
                        $pdo, $shopUserId, 'shipment_update',
                        'Shipment Details Updated',
                        'Order ' . $shipment['order_number'] . ' — Vehicle: ' . $vehicleNo . ', Bilty: ' . $biltyNo,
                        BASE_URL . 'shop/order-details.php?id=' . $shipment['order_id']
                    );
                }
            } catch (Throwable $ex) {
                error_log('Notify shop failed: ' . $ex->getMessage());
            }

            // Notify vendor
            try {
                notify_vendor(
                    $pdo, 'shipment_update',
                    'Shipment Details Updated',
                    'Order ' . $shipment['order_number'] . ' — Vehicle: ' . $vehicleNo . ', Bilty: ' . $biltyNo,
                    BASE_URL . 'vendor/order-details.php?id=' . $shipment['order_id']
                );
            } catch (Throwable $ex) {
                error_log('Notify vendor failed: ' . $ex->getMessage());
            }

            // Notify companies (if they supplied items)
            try {
                $compStmt = $pdo->prepare(
                    "SELECT DISTINCT c.user_id FROM order_items oi
                     JOIN companies c ON c.id = oi.company_id
                     WHERE oi.order_id = ? AND oi.company_id IS NOT NULL"
                );
                $compStmt->execute([$shipment['order_id']]);
                foreach ($compStmt->fetchAll() as $c) {
                    create_notification(
                        $pdo, (int)$c['user_id'], 'shipment_update',
                        'Shipment Dispatched',
                        'Order ' . $shipment['order_number'] . ' — Vehicle: ' . $vehicleNo . ', Bilty: ' . $biltyNo,
                        BASE_URL . 'company/orders.php'
                    );
                }
            } catch (Throwable $ex) {
                error_log('Notify companies failed: ' . $ex->getMessage());
            }

            flash_success('Vehicle & bilty details saved. Notifications sent.');
            redirect(BASE_URL . 'transport/shipment-details.php?id=' . $id);

        // ============================================
        // SET DELIVERY CHARGES (transport sets, shop pays)
        // ============================================
        case 'set_transport_charges':
            $charges = (float)($_POST['charges'] ?? 0);
            $note    = clean($_POST['note'] ?? '');

            if ($charges <= 0) throw new Exception('Charges must be greater than 0.');

            $stmt = $pdo->prepare(
                "SELECT s.*, o.order_number, o.shop_id FROM shipments s
                 JOIN orders o ON o.id = s.order_id
                 WHERE s.id = ? AND s.transport_user_id = ? LIMIT 1"
            );
            $stmt->execute([$id, $user['id']]);
            $shipment = $stmt->fetch();
            if (!$shipment) throw new Exception('Shipment not found.');

            if ((float)$shipment['transport_charges'] > 0) {
                throw new Exception('Charges already set for this shipment.');
            }

            $pdo->beginTransaction();

            $pdo->prepare(
                "UPDATE shipments SET transport_charges = ?, payment_status = 'pending' WHERE id = ?"
            )->execute([$charges, $id]);

            $pdo->prepare(
                "INSERT INTO shipment_tracking (shipment_id, status, note, updated_by)
                 VALUES (?, ?, ?, ?)"
            )->execute([$id, $shipment['status'], 'Delivery charges: Rs. ' . number_format($charges, 2) . ($note ? ' — ' . $note : ''), $user['id']]);

            $pdo->commit();

            log_activity($pdo, 'transport_charges_set', 'transport', $id, 'Charges: ' . $charges);

            notify_shop(
                $pdo, (int)$shipment['shop_id'], 'transport_charges',
                'Delivery charges set',
                'Transport set Rs. ' . number_format($charges, 2) . ' for order ' . $shipment['order_number'] . '. Please pay.',
                BASE_URL . 'shop/order-details.php?id=' . $shipment['order_id']
            );

            notify_vendor(
                $pdo, 'transport_charges',
                'Transport charges set',
                'Rs. ' . number_format($charges, 2) . ' for order ' . $shipment['order_number'],
                BASE_URL . 'vendor/order-details.php?id=' . $shipment['order_id']
            );

            flash_success('Charges set to Rs. ' . number_format($charges, 2) . '. Shop notified.');
            redirect(BASE_URL . 'transport/shipment-details.php?id=' . $id);

        // ============================================
        // VERIFY TRANSPORT PAYMENT (approve/reject)
        // ============================================
        case 'verify_transport_payment':
            $paymentId = $id;
            $decision  = $_POST['decision'] ?? '';
            $reason    = clean($_POST['rejection_reason'] ?? '');

            if (!in_array($decision, ['approve', 'reject'], true)) {
                throw new Exception('Invalid decision.');
            }

            $stmt = $pdo->prepare(
                "SELECT p.*, sh.transport_user_id, sh.transport_charges
                 FROM payments p
                 JOIN shipments sh ON sh.id = p.shipment_id
                 WHERE p.id = ? AND p.recipient_type = 'transport' 
                   AND p.status = 'pending_verification' 
                   AND sh.transport_user_id = ? LIMIT 1"
            );
            $stmt->execute([$paymentId, $user['id']]);
            $payment = $stmt->fetch();
            if (!$payment) throw new Exception('Payment not found.');

            $pdo->beginTransaction();

            if ($decision === 'approve') {
                $pdo->prepare(
                    "UPDATE payments SET status = 'paid', verified_by = ?, verified_at = NOW() WHERE id = ?"
                )->execute([$user['id'], $paymentId]);

                // Recalculate shipment payment
                $stmt = $pdo->prepare(
                    "SELECT COALESCE(SUM(amount), 0) FROM payments 
                     WHERE shipment_id = ? AND recipient_type = 'transport' AND status = 'paid'"
                );
                $stmt->execute([$payment['shipment_id']]);
                $newPaid = (float)$stmt->fetchColumn();

                $charges = (float)$payment['transport_charges'];
                $payStatus = $newPaid <= 0 ? 'pending' 
                           : ($newPaid + 0.01 < $charges ? 'partially_paid' : 'paid');

                $pdo->prepare(
                    "UPDATE shipments SET paid_amount = ?, payment_status = ? WHERE id = ?"
                )->execute([$newPaid, $payStatus, $payment['shipment_id']]);

                $msg = 'Your delivery payment of ' . money($payment['amount']) . ' has been approved.';
                log_activity($pdo, 'transport_payment_verified', 'payment', $paymentId, 'Approved');
            } else {
                if ($reason === '') $reason = 'Payment could not be verified.';
                $pdo->prepare(
                    "UPDATE payments SET status = 'failed', verified_by = ?, verified_at = NOW(), rejection_reason = ? WHERE id = ?"
                )->execute([$user['id'], $reason, $paymentId]);

                $msg = 'Your delivery payment of ' . money($payment['amount']) . ' was rejected. Reason: ' . $reason;
                log_activity($pdo, 'transport_payment_rejected', 'payment', $paymentId, 'Rejected: ' . $reason);
            }

            $pdo->commit();

            notify_shop(
                $pdo, (int)$payment['shop_id'], 'transport_payment',
                $decision === 'approve' ? 'Payment Approved' : 'Payment Rejected',
                $msg,
                BASE_URL . 'shop/order-details.php?id=' . $payment['order_id']
            );

            flash_success($decision === 'approve' ? 'Payment approved.' : 'Payment rejected.');
            redirect(BASE_URL . 'transport/payments.php');

        

        default:
            if (is_ajax()) json_response(['success' => false, 'message' => 'Unknown action'], 400);
            flash_error('Unknown action.');
            redirect(BASE_URL . 'transport/index.php');
    }
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('transport-action error: ' . $ex->getMessage());
    if (is_ajax()) json_response(['success' => false, 'message' => 'Server error: ' . $ex->getMessage()], 500);
    flash_error('Action failed. Please try again.');
    redirect(BASE_URL . 'transport/index.php');
}