<?php
/**
 * api/cart-action.php
 */
require_once dirname(__DIR__) . '/includes/init.php';

if (!is_logged_in($pdo) || current_role($pdo) !== ROLE_SHOP) {
    if (is_ajax()) json_response(['success' => false, 'message' => 'Unauthorized'], 403);
    redirect(BASE_URL . 'auth/login.php');
}

$action    = $_POST['action'] ?? $_GET['action'] ?? '';
$productId = (int)($_POST['product_id'] ?? $_GET['product_id'] ?? 0);
$qty       = max(1, (int)($_POST['qty'] ?? 1));

if (!csrf_verify($_POST['csrf_token'] ?? null) && !is_ajax()) {
    // Allow GET for direct remove link, but require CSRF on POST
}

if ($productId <= 0) {
    if (is_ajax()) json_response(['success' => false, 'message' => 'Invalid product'], 400);
    flash_error('Invalid product.');
    redirect(BASE_URL . 'shop/products.php');
}

if (empty($_SESSION['cart'])) $_SESSION['cart'] = [];

switch ($action) {
    case 'add':
        // Verify product exists, price confirmed
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id=? AND status='active' LIMIT 1");
        $stmt->execute([$productId]);
        $p = $stmt->fetch();
        if (!$p) {
            if (is_ajax()) json_response(['success' => false, 'message' => 'Product not found'], 404);
            flash_error('Product not found.');
            redirect(BASE_URL . 'shop/products.php');
        }
        if ((int)$p['show_price'] !== 1) {
            if (is_ajax()) json_response(['success' => false, 'message' => 'Price pending. Please request price.'], 400);
            flash_error('This product needs a price request first.');
            redirect(BASE_URL . 'shop/products.php');
        }
        $_SESSION['cart'][$productId] = ($_SESSION['cart'][$productId] ?? 0) + $qty;
        if (is_ajax()) json_response(['success' => true, 'count' => array_sum($_SESSION['cart'])]);
        redirect(BASE_URL . 'shop/cart.php');

    case 'update':
        if (isset($_SESSION['cart'][$productId])) {
            $_SESSION['cart'][$productId] = $qty;
        }
        redirect(BASE_URL . 'shop/cart.php');

    case 'remove':
        unset($_SESSION['cart'][$productId]);
        flash_success('Item removed from cart.');
        redirect(BASE_URL . 'shop/cart.php');

    default:
        if (is_ajax()) json_response(['success' => false, 'message' => 'Unknown action'], 400);
        redirect(BASE_URL . 'shop/cart.php');
}