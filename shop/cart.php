<?php
/**
 * shop/cart.php
 */
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_SHOP);
require_once dirname(__DIR__) . '/includes/invoice_helper.php';


$user = current_user($pdo);
$shopStmt = $pdo->prepare("SELECT id FROM shops WHERE user_id = ? LIMIT 1");
$shopStmt->execute([$user['id']]);
$shopId = (int)$shopStmt->fetchColumn();

$vendorInfo = $pdo->query("SELECT * FROM vendors LIMIT 1")->fetch();
$defComm    = (float)($vendorInfo['default_commission_value'] ?? 500);
$commType   = $vendorInfo['default_commission_type'] ?? 'fixed';

$cart = $_SESSION['cart'] ?? [];
$items = [];
$subtotal = 0;

if (!empty($cart)) {
    $ids = array_keys($cart);
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($in) AND status='active'");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();

    foreach ($rows as $p) {
        $qty = (int)($cart[$p['id']] ?? 0);
        if ($qty <= 0) continue;

        // Calculate final price
        $stmt = $pdo->prepare("SELECT custom_price FROM shop_specific_prices WHERE product_id=? AND shop_id=? AND is_active=1 LIMIT 1");
        $stmt->execute([$p['id'], $shopId]);
        $custom = $stmt->fetchColumn();

        $final = null;
        if ($custom !== false && $custom !== null) {
            $final = (float)$custom;
        } elseif ((int)$p['show_price'] === 1 && $p['base_price'] !== null) {
            $base = (float)$p['base_price'];
            $comm = $commType === 'percentage' ? $base * $defComm / 100 : $defComm;
            $final = $base + $comm;
        }

        if ($final === null) continue;

        $line = $final * $qty;
        $subtotal += $line;
        $items[] = ['product' => $p, 'qty' => $qty, 'unit_price' => $final, 'line_total' => $line];
    }
}

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    csrf_require($_POST['csrf_token'] ?? null);

    if (empty($items)) {
        flash_error('Cart is empty.');
        redirect(BASE_URL . 'shop/cart.php');
    }

    try {
        $pdo->beginTransaction();

        $orderNumber = generate_number($pdo, 'ORD', 'orders', 'order_number');
        $vendorId = (int)($vendorInfo['id'] ?? 0) ?: null;

        $totalComm = 0;
        foreach ($items as $it) {
            $stmt = $pdo->prepare("SELECT custom_price FROM shop_specific_prices WHERE product_id=? AND shop_id=? AND is_active=1 LIMIT 1");
            $stmt->execute([$it['product']['id'], $shopId]);
            $custom = $stmt->fetchColumn();
            if ($custom !== false && $custom !== null) {
                $comm = (float)$custom - (float)$it['product']['base_price'];
            } elseif ($commType === 'percentage') {
                $comm = (float)$it['product']['base_price'] * $defComm / 100;
            } else {
                $comm = $defComm;
            }
            $totalComm += $comm * $it['qty'];
        }

        $deliveryAddress = trim($_POST['delivery_address'] ?? '');
if ($deliveryAddress === '') {
    throw new Exception('Delivery address is required.');
}

$stmt = $pdo->prepare(
    "INSERT INTO orders (order_number, shop_id, vendor_id, status, subtotal,
      commission_total, total_amount, paid_amount, payment_status, notes, delivery_address)
     VALUES (?, ?, ?, 'order_created', ?, ?, ?, 0, 'pending', ?, ?)"
);
$stmt->execute([
    $orderNumber, $shopId, $vendorId,
    $subtotal - $totalComm, $totalComm, $subtotal,
    clean($_POST['notes'] ?? ''),
    clean($deliveryAddress)
]);
        $orderId = (int)$pdo->lastInsertId();

        foreach ($items as $it) {
            $p = $it['product'];

            $stmt = $pdo->prepare("SELECT custom_price FROM shop_specific_prices WHERE product_id=? AND shop_id=? AND is_active=1 LIMIT 1");
            $stmt->execute([$p['id'], $shopId]);
            $custom = $stmt->fetchColumn();
            if ($custom !== false && $custom !== null) {
                $comm = (float)$custom - (float)$p['base_price'];
            } elseif ($commType === 'percentage') {
                $comm = (float)$p['base_price'] * $defComm / 100;
            } else {
                $comm = $defComm;
            }

            $stmt = $pdo->prepare(
                "INSERT INTO order_items (order_id, product_id, company_id, quantity, unit,
                  company_base_price, vendor_commission, final_price, line_total, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
            );
            $stmt->execute([
                $orderId, $p['id'], $p['company_id'], $it['qty'], $p['unit'],
                (float)$p['base_price'], $comm, $it['unit_price'], $it['line_total']
            ]);
        }

        $stmt = $pdo->prepare(
            "INSERT INTO order_status_history (order_id, previous_status, new_status, changed_by, changed_by_role, note)
             VALUES (?, NULL, 'order_created', ?, 'shop', 'Order created by shop')"
        );
        $stmt->execute([$orderId, $user['id']]);

                $pdo->commit();

                
        // Notify vendor about new order
        try {
            require_once dirname(__DIR__) . '/includes/notification_helper.php';
            
            $vendorUserStmt = $pdo->query("SELECT user_id FROM vendors LIMIT 1");
            $vendorUserId = (int)$vendorUserStmt->fetchColumn();
            
            if ($vendorUserId > 0) {
                create_notification(
                    $pdo,
                    $vendorUserId,
                    'order_created',
                    'New Order Received',
                    'Order ' . $orderNumber . ' — Rs. ' . number_format($subtotal, 2) . ' — Please review',
                    BASE_URL . 'vendor/order-details.php?id=' . $orderId
                );
            }
        } catch (Throwable $ex) {
            error_log('Notify vendor failed: ' . $ex->getMessage());
        }

        // Auto-create invoice
        try {
            get_or_create_invoice($pdo, $orderId);
        } catch (Throwable $ex) {
            error_log('Auto-invoice failed: ' . $ex->getMessage());
        }


        
        // Auto-create order chat
        
        unset($_SESSION['cart']);
        log_activity($pdo, 'order_placed', 'shop', $orderId, 'Order ' . $orderNumber . ' placed');

        flash_success('Order placed successfully! Order #' . $orderNumber);
        redirect(BASE_URL . 'shop/order-details.php?id=' . $orderId);

    } catch (Throwable $ex) {
        $pdo->rollBack();
        error_log('Order place error: ' . $ex->getMessage());
        flash_error('Order could not be placed. Please try again.');
        redirect(BASE_URL . 'shop/cart.php');
    }
}

$pageTitle = 'Cart';
require_once dirname(__DIR__) . '/includes/shop_header.php';
?>

<h2 class="fw-bold mb-1">My Cart</h2>
<p class="text-muted small mb-4">Review your items before placing the order</p>

<?php if (empty($items)): ?>
    <div class="card-soft p-5 text-center">
        <i class="bi bi-cart-x fs-1 text-muted d-block mb-3 opacity-50"></i>
        <h5 class="fw-bold">Your cart is empty</h5>
        <p class="text-muted">Browse products and add items to place an order.</p>
        <a href="<?= BASE_URL ?>shop/products.php" class="btn btn-primary">
            <i class="bi bi-box-seam"></i> Browse Products
        </a>
    </div>
<?php else: ?>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card-soft">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th class="text-end">Unit Price</th>
                                <th style="width:130px;">Qty</th>
                                <th class="text-end">Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $it): $p = $it['product']; ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold small"><?= e($p['name']) ?></div>
                                    <div class="text-muted" style="font-size:.72rem;">SKU: <?= e($p['sku']) ?></div>
                                </td>
                                <td class="text-end small"><?= money($it['unit_price']) ?></td>
                                <td>
                                    <form method="post" action="<?= BASE_URL ?>api/cart-action.php" class="d-flex gap-1">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                                        <input type="number" name="qty" class="form-control form-control-sm" style="width:70px;"
                                               value="<?= (int)$it['qty'] ?>" min="1">
                                        <button class="btn btn-sm btn-outline-secondary" type="submit">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                    </form>
                                </td>
                                <td class="text-end small fw-semibold"><?= money($it['line_total']) ?></td>
                                <td class="text-end">
                                    <a href="<?= BASE_URL ?>api/cart-action.php?action=remove&product_id=<?= (int)$p['id'] ?>"
                                       class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card-soft p-3">
                <h5 class="fw-bold mb-3">Order Summary</h5>
                <div class="d-flex justify-content-between mb-2 small">
                    <span class="text-muted">Items</span>
                    <span><?= count($items) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <strong>Total</strong>
                    <strong class="text-primary fs-5"><?= money($subtotal) ?></strong>
                </div>
                <hr>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
    <label class="form-label small">Delivery Address <span class="text-danger">*</span></label>
    <textarea name="delivery_address" class="form-control form-control-sm" rows="3"
              placeholder="Shop ka poora address jahan delivery chahiye..." required></textarea>
    <div class="form-text" style="font-size:.72rem;">
        <i class="bi bi-info-circle"></i> Ye address vendor aur transport ko dikhega.
    </div>
</div>
                    <div class="mb-3">
                        <label class="form-label small">Order Notes (optional)</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2"
                                  placeholder="Any special instruction..."></textarea>
                    </div>
                    <button type="submit" name="place_order" value="1" class="btn btn-primary w-100">
                        <i class="bi bi-check-circle"></i> Place Order
                    </button>
                    <a href="<?= BASE_URL ?>shop/products.php" class="btn btn-outline-secondary w-100 mt-2">
                        Continue Shopping
                    </a>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/shop_footer.php'; ?>