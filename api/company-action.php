<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/payment_helper.php';
require_once dirname(__DIR__) . '/includes/notification_helper.php';

if (!is_logged_in($pdo) || current_role($pdo) !== ROLE_COMPANY) {
    if (is_ajax()) json_response(['success' => false, 'message' => 'Unauthorized'], 403);
    redirect(BASE_URL . 'auth/login.php');
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    if (is_ajax()) json_response(['success' => false, 'message' => 'Invalid token'], 419);
    flash_error('Security token expired.');
    redirect(BASE_URL . 'company/index.php');
}

$action = $_POST['action'] ?? '';
$id     = (int)($_POST['id'] ?? 0);
$user   = current_user($pdo);

$coStmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ? LIMIT 1");
$coStmt->execute([$user['id']]);
$company = $coStmt->fetch();
$companyId = (int)$company['id'];

try {
    switch ($action) {

        // ============================================
        // 1. RESPOND TO PRICE REQUEST
        // ============================================
        case 'respond_price':
            $basePrices   = $_POST['base_price'] ?? [];
            $availability = $_POST['availability'] ?? [];

            if (empty($basePrices)) throw new Exception('No prices submitted.');

            $pdo->beginTransaction();

            foreach ($basePrices as $itemId => $basePrice) {
                $itemId = (int)$itemId;
                $basePrice = (float)$basePrice;
                $avail = $availability[$itemId] ?? 'available';

                $chk = $pdo->prepare(
                    "SELECT pri.id FROM price_request_items pri
                     JOIN price_requests pr ON pr.id = pri.request_id
                     WHERE pri.id = ? AND pri.company_id = ? AND pr.id = ? LIMIT 1"
                );
                $chk->execute([$itemId, $companyId, $id]);
                if (!$chk->fetch()) continue;

                $status = $avail === 'unavailable' ? 'unavailable' : 'quoted';

                $pdo->prepare(
                    "UPDATE price_request_items SET company_base_price = ?, status = ? WHERE id = ?"
                )->execute([$basePrice, $status, $itemId]);
            }

            $pdo->prepare(
                "INSERT INTO price_request_history (request_id, changed_by, changed_by_role, previous_status, new_status, note)
                 VALUES (?, ?, 'company', 'sent_to_company', 'quoted', 'Company provided base prices')"
            )->execute([$id, $user['id']]);

            $pdo->commit();

            log_activity($pdo, 'company_responded_price', 'company', $id, 'Company submitted base prices');
            if (is_ajax()) json_response(['success' => true]);
            flash_success('Response sent to vendor.');
            redirect(BASE_URL . 'company/price-request-details.php?id=' . $id);

        // ============================================
        // 2. CONFIRM ORDER AVAILABILITY
        // ============================================
                case 'confirm_order':
            // Verify order has items from this company
            $chk = $pdo->prepare(
                "SELECT COUNT(*) FROM order_items WHERE order_id = ? AND company_id = ?"
            );
            $chk->execute([$id, $companyId]);
            if ((int)$chk->fetchColumn() === 0) {
                throw new Exception('Order not assigned to your company.');
            }

            $prev = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
            $prev->execute([$id]);
            $prevStatus = $prev->fetchColumn();

            $note = clean($_POST['note'] ?? 'Company confirmed availability');

            $pdo->beginTransaction();

            $pdo->prepare("UPDATE orders SET status = 'company_confirmed' WHERE id = ?")->execute([$id]);

            $pdo->prepare(
                "INSERT INTO order_status_history (order_id, previous_status, new_status, changed_by, changed_by_role, note)
                 VALUES (?, ?, 'company_confirmed', ?, 'company', ?)"
            )->execute([$id, $prevStatus, $user['id'], $note]);

            $pdo->commit();

            log_activity($pdo, 'company_confirmed_order', 'company', $id, 'Company confirmed order');

            // Notify vendor
            try {
                $ordInfo = $pdo->prepare("SELECT order_number FROM orders WHERE id = ? LIMIT 1");
                $ordInfo->execute([$id]);
                $ordNo = $ordInfo->fetchColumn();

                $coName = $pdo->prepare("SELECT company_name FROM companies WHERE id = ? LIMIT 1");
                $coName->execute([$companyId]);
                $companyName = $coName->fetchColumn();

                notify_vendor(
                    $pdo,
                    'order_status',
                    'Order Confirmed by Company',
                    'Company ' . $companyName . ' confirmed order ' . $ordNo . '. Ready to prepare products.',
                    BASE_URL . 'vendor/order-details.php?id=' . $id
                );
            } catch (Throwable $ex) {
                error_log('Notify vendor failed: ' . $ex->getMessage());
            }

            if (is_ajax()) json_response(['success' => true]);
            flash_success('Order confirmed. Ready to prepare products.');
            redirect(BASE_URL . 'company/order-details.php?id=' . $id);

        // ============================================
        // 3. MARK READY FOR TRANSPORT
        // ============================================
        case 'ready_for_transport':
            $chk = $pdo->prepare(
                "SELECT COUNT(*) FROM order_items WHERE order_id = ? AND company_id = ?"
            );
            $chk->execute([$id, $companyId]);
            if ((int)$chk->fetchColumn() === 0) {
                throw new Exception('Order not assigned to your company.');
            }

            $prev = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
            $prev->execute([$id]);
            $prevStatus = $prev->fetchColumn();

            $note = clean($_POST['note'] ?? 'Products packed and ready for pickup');

            $pdo->beginTransaction();

            $pdo->prepare("UPDATE orders SET status = 'ready_for_transport' WHERE id = ?")->execute([$id]);

            $pdo->prepare(
                "INSERT INTO order_status_history (order_id, previous_status, new_status, changed_by, changed_by_role, note)
                 VALUES (?, ?, 'ready_for_transport', ?, 'company', ?)"
            )->execute([$id, $prevStatus, $user['id'], $note]);

            $pdo->commit();

            log_activity($pdo, 'company_ready_transport', 'company', $id, 'Order ready for transport');
                        // Notify vendor
            try {
                $ordInfo = $pdo->prepare("SELECT order_number FROM orders WHERE id = ? LIMIT 1");
                $ordInfo->execute([$id]);
                $ordNo = $ordInfo->fetchColumn();

                $coName = $pdo->prepare("SELECT company_name FROM companies WHERE id = ? LIMIT 1");
                $coName->execute([$companyId]);
                $companyName = $coName->fetchColumn();

                notify_vendor(
                    $pdo,
                    'order_status',
                    'Order Ready for Transport',
                    'Company ' . $companyName . ' packed order ' . $ordNo . '. Please assign transport.',
                    BASE_URL . 'vendor/order-details.php?id=' . $id
                );
            } catch (Throwable $ex) {
                error_log('Notify vendor failed: ' . $ex->getMessage());
            }
            if (is_ajax()) json_response(['success' => true]);
            flash_success('Marked as ready for transport. Vendor will assign transport now.');
            redirect(BASE_URL . 'company/order-details.php?id=' . $id);

        // ============================================
        // 4. VERIFY COMPANY PAYMENT (NEW)
        // ============================================
        case 'verify_company_payment':
            $paymentId = $id;
            $decision  = $_POST['decision'] ?? '';
            $reason    = clean($_POST['rejection_reason'] ?? '');

            if (!in_array($decision, ['approve', 'reject'], true)) {
                throw new Exception('Invalid decision.');
            }

            $stmt = $pdo->prepare(
                "SELECT * FROM payments 
                 WHERE id = ? AND recipient_type = 'company' 
                   AND company_id = ? AND status = 'pending_verification' LIMIT 1"
            );
            $stmt->execute([$paymentId, $companyId]);
            $payment = $stmt->fetch();
            if (!$payment) throw new Exception('Payment not found.');

            $pdo->beginTransaction();

            if ($decision === 'approve') {
                $pdo->prepare(
                    "UPDATE payments SET status='paid', verified_by=?, verified_at=NOW() WHERE id=?"
                )->execute([$user['id'], $paymentId]);
                $msg = 'Your payment of ' . money($payment['amount']) . ' has been approved.';
                log_activity($pdo, 'company_payment_verified', 'payment', $paymentId, 'Approved');
            } else {
                if ($reason === '') $reason = 'Payment could not be verified.';
                $pdo->prepare(
                    "UPDATE payments SET status='failed', verified_by=?, verified_at=NOW(), rejection_reason=? WHERE id=?"
                )->execute([$user['id'], $reason, $paymentId]);
                $msg = 'Your payment of ' . money($payment['amount']) . ' was rejected. Reason: ' . $reason;
                log_activity($pdo, 'company_payment_rejected', 'payment', $paymentId, 'Rejected: ' . $reason);
            }

            $pdo->commit();

            notify_vendor(
                $pdo, 'company_payment',
                $decision === 'approve' ? 'Payment Approved' : 'Payment Rejected',
                $msg,
                BASE_URL . 'vendor/order-details.php?id=' . $payment['order_id']
            );

            flash_success($decision === 'approve' ? 'Payment approved.' : 'Payment rejected.');
            redirect(BASE_URL . 'company/verify-payments.php');

        // ============================================
        // DEFAULT
        // ============================================
        default:
            if (is_ajax()) json_response(['success' => false, 'message' => 'Unknown action'], 400);
            flash_error('Unknown action.');
            redirect(BASE_URL . 'company/index.php');
    }
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('company-action error: ' . $ex->getMessage());
    if (is_ajax()) json_response(['success' => false, 'message' => $ex->getMessage()], 500);
    flash_error($ex->getMessage() ?: 'Action failed. Please try again.');
    redirect(BASE_URL . 'company/index.php');
}