<?php
/**
 * api/review-action.php
 * Handles: submit review (shop), company reply
 */
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/review_helper.php';
require_once dirname(__DIR__) . '/includes/notification_helper.php';

if (!is_logged_in($pdo)) {
    if (is_ajax()) json_response(['success' => false, 'message' => 'Unauthorized'], 403);
    redirect(BASE_URL . 'auth/login.php');
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    if (is_ajax()) json_response(['success' => false, 'message' => 'Invalid security token'], 419);
    flash_error('Security token expired. Please refresh and try again.');
    redirect(BASE_URL . 'index.php');
}

$user   = current_user($pdo);
$role   = $user['role_name'];
$action = $_POST['action'] ?? '';

try {
    switch ($action) {

        // ==========================================
        // SHOP: SUBMIT REVIEW
        // ==========================================
        case 'submit_review':
            if ($role !== ROLE_SHOP) throw new Exception('Only shops can submit reviews.');

            $orderItemId = (int)($_POST['order_item_id'] ?? 0);
            $rating      = (int)($_POST['rating'] ?? 0);
            $title       = clean($_POST['title'] ?? '');
            $comment     = clean($_POST['comment'] ?? '');

            if ($orderItemId <= 0) throw new Exception('Invalid order item.');
            if ($rating < 1 || $rating > 5) throw new Exception('Rating must be between 1 and 5.');
            if (mb_strlen($comment) < 3)  throw new Exception('Comment must be at least 3 characters.');
            if (mb_strlen($comment) > 1000) throw new Exception('Comment too long (max 1000 chars).');
            if (mb_strlen($title) > 150)  throw new Exception('Title too long.');

            $shopStmt = $pdo->prepare("SELECT id FROM shops WHERE user_id = ? LIMIT 1");
            $shopStmt->execute([$user['id']]);
            $shopId = (int)$shopStmt->fetchColumn();
            if ($shopId <= 0) throw new Exception('Shop not found.');

            if (!can_review_order_item($pdo, $orderItemId, $shopId)) {
                throw new Exception('Aap ye product review nahi kar sakte. Sirf delivered orders ke liye review allowed hai, aur ek hi baar.');
            }

            $stmt = $pdo->prepare(
                "SELECT oi.product_id, oi.order_id, o.vendor_id, o.order_number, p.name AS product_name
                 FROM order_items oi
                 JOIN orders o ON o.id = oi.order_id
                 JOIN products p ON p.id = oi.product_id
                 WHERE oi.id = ? LIMIT 1"
            );
            $stmt->execute([$orderItemId]);
            $item = $stmt->fetch();
            if (!$item) throw new Exception('Order item not found.');

            $pdo->beginTransaction();

            $pdo->prepare(
                "INSERT INTO product_reviews 
                 (product_id, shop_id, order_id, order_item_id, rating, title, comment)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                (int)$item['product_id'],
                $shopId,
                (int)$item['order_id'],
                $orderItemId,
                $rating,
                $title ?: null,
                $comment
            ]);

            $pdo->commit();

            log_activity($pdo, 'review_submitted', 'shop', (int)$item['product_id'], 
                'Review: ' . $rating . ' stars for ' . $item['product_name']);

            // Notify company
            try {
                $comp = $pdo->prepare(
                    "SELECT c.user_id FROM products p 
                     JOIN companies c ON c.id = p.company_id 
                     WHERE p.id = ? LIMIT 1"
                );
                $comp->execute([(int)$item['product_id']]);
                $compUserId = (int)$comp->fetchColumn();

                if ($compUserId > 0) {
                    create_notification(
                        $pdo, $compUserId, 'review',
                        'New Review Received',
                        $rating . '-star review for ' . $item['product_name'] . ': ' . mb_substr($comment, 0, 60) . '...',
                        BASE_URL . 'company/reviews.php'
                    );
                }
            } catch (Throwable $ex) {
                error_log('Notify company failed: ' . $ex->getMessage());
            }

            flash_success('Review submitted! Thank you for your feedback.');
            redirect(BASE_URL . 'shop/order-details.php?id=' . (int)$item['order_id']);

        // ==========================================
        // COMPANY: REPLY TO REVIEW
        // ==========================================
        case 'company_reply':
            if ($role !== ROLE_COMPANY) throw new Exception('Only companies can reply.');

            $reviewId = (int)($_POST['review_id'] ?? 0);
            $reply    = clean($_POST['reply'] ?? '');

            if ($reviewId <= 0) throw new Exception('Invalid review.');
            if (mb_strlen($reply) < 3) throw new Exception('Reply must be at least 3 characters.');
            if (mb_strlen($reply) > 1000) throw new Exception('Reply too long.');

            $coStmt = $pdo->prepare("SELECT id FROM companies WHERE user_id = ? LIMIT 1");
            $coStmt->execute([$user['id']]);
            $companyId = (int)$coStmt->fetchColumn();
            if ($companyId <= 0) throw new Exception('Company not found.');

            $chk = $pdo->prepare(
                "SELECT r.id FROM product_reviews r
                 JOIN products p ON p.id = r.product_id
                 WHERE r.id = ? AND p.company_id = ? LIMIT 1"
            );
            $chk->execute([$reviewId, $companyId]);
            if (!$chk->fetch()) throw new Exception('Review not found or not yours.');

            $pdo->prepare(
                "UPDATE product_reviews SET company_reply = ?, company_replied_at = NOW() WHERE id = ?"
            )->execute([$reply, $reviewId]);

            log_activity($pdo, 'review_replied', 'company', $reviewId, 'Company reply added');

            flash_success('Reply posted.');
            redirect(BASE_URL . 'company/reviews.php');

        default:
            if (is_ajax()) json_response(['success' => false, 'message' => 'Unknown action'], 400);
            throw new Exception('Unknown action.');
    }
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('review-action error: ' . $ex->getMessage());
    if (is_ajax()) json_response(['success' => false, 'message' => $ex->getMessage()], 500);
    flash_error($ex->getMessage() ?: 'Action failed.');
    redirect(BASE_URL . 'index.php');
}