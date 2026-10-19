<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/product_helper.php';

if (!is_logged_in($pdo) || current_role($pdo) !== ROLE_COMPANY) {
    json_response(['success' => false, 'message' => 'Unauthorized'], 403);
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    json_response(['success' => false, 'message' => 'Invalid token'], 419);
}

$user = current_user($pdo);
$coStmt = $pdo->prepare("SELECT id FROM companies WHERE user_id = ? LIMIT 1");
$coStmt->execute([$user['id']]);
$companyId = (int)$coStmt->fetchColumn();

$action = $_POST['action'] ?? '';
$id     = (int)($_POST['id'] ?? 0);

try {
    switch ($action) {

        case 'delete_image':
            $imgId = (int)($_POST['image_id'] ?? 0);
            if ($imgId <= 0) throw new Exception('Invalid image.');
            if (!delete_product_image($pdo, $imgId, $companyId)) {
                throw new Exception('Image not found or not yours.');
            }
            log_activity($pdo, 'product_image_deleted', 'company', $imgId, 'Image deleted');
            json_response(['success' => true]);

        case 'set_primary_image':
            $imgId = (int)($_POST['image_id'] ?? 0);
            $stmt = $pdo->prepare(
                "SELECT pi.product_id FROM product_images pi
                 JOIN products p ON p.id = pi.product_id
                 WHERE pi.id = ? AND p.company_id = ? LIMIT 1"
            );
            $stmt->execute([$imgId, $companyId]);
            $productId = (int)$stmt->fetchColumn();
            if (!$productId) throw new Exception('Image not found.');

            $pdo->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?")->execute([$productId]);
            $pdo->prepare("UPDATE product_images SET is_primary = 1 WHERE id = ?")->execute([$imgId]);
            json_response(['success' => true]);

                case 'delete_product':
            $stmt = $pdo->prepare("SELECT id, name FROM products WHERE id = ? AND company_id = ? LIMIT 1");
            $stmt->execute([$id, $companyId]);
            $product = $stmt->fetch();
            if (!$product) throw new Exception('Product not found.');

            // Check ALL references
            $refs = [];
            
            $chk = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
            $chk->execute([$id]);
            $orderCount = (int)$chk->fetchColumn();
            if ($orderCount > 0) $refs[] = $orderCount . ' order(s)';
            
            $chk = $pdo->prepare("SELECT COUNT(*) FROM price_request_items WHERE product_id = ?");
            $chk->execute([$id]);
            $prCount = (int)$chk->fetchColumn();
            if ($prCount > 0) $refs[] = $prCount . ' price request(s)';

            if (!empty($refs)) {
                throw new Exception(
                    'Is product ko delete nahi kar sakte kyunke ye use ho raha hai: ' . 
                    implode(', ', $refs) . '. ' .
                    'Bajaye delete karne ke, Power button (⚡) se Deactivate karein.'
                );
            }

            $pdo->beginTransaction();
            // Delete images
            $imgs = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
            $imgs->execute([$id]);
            foreach ($imgs->fetchAll() as $img) {
                $full = UPLOADS_PATH . '/' . $img['image_path'];
                if (file_exists($full)) @unlink($full);
            }
            $pdo->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM product_company_prices WHERE product_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
            $pdo->commit();

            log_activity($pdo, 'product_deleted', 'company', $id, 'Product: ' . $product['name']);
            json_response(['success' => true]);

        case 'toggle_status':
            $stmt = $pdo->prepare("SELECT status FROM products WHERE id = ? AND company_id = ? LIMIT 1");
            $stmt->execute([$id, $companyId]);
            $cur = $stmt->fetchColumn();
            if ($cur === false) throw new Exception('Product not found.');

            $new = $cur === 'active' ? 'inactive' : 'active';
            $pdo->prepare("UPDATE products SET status = ? WHERE id = ?")->execute([$new, $id]);

            log_activity($pdo, 'product_status_toggled', 'company', $id, 'Status: ' . $new);
            json_response(['success' => true, 'new_status' => $new]);

        default:
            json_response(['success' => false, 'message' => 'Unknown action'], 400);
    }
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('company-product-action error: ' . $ex->getMessage());
    json_response(['success' => false, 'message' => $ex->getMessage()], 500);
}