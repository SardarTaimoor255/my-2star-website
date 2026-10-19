<?php
/**
 * shop/index.php — Shop Dashboard
 */
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_SHOP);

$user = current_user($pdo);
$shopStmt = $pdo->prepare("SELECT * FROM shops WHERE user_id = ? LIMIT 1");
$shopStmt->execute([$user['id']]);
$shop = $shopStmt->fetch();
$shopId = (int)$shop['id'];

$stats = [
    'total'      => 0,
    'pending'    => 0,
    'delivered'  => 0,
    'outstanding'=> 0.0,
];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE shop_id = ?");
$stmt->execute([$shopId]);
$stats['total'] = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE shop_id = ? AND status NOT IN ('delivered','cancelled','returned')");
$stmt->execute([$shopId]);
$stats['pending'] = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE shop_id = ? AND status = 'delivered'");
$stmt->execute([$shopId]);
$stats['delivered'] = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount - paid_amount),0) FROM orders WHERE shop_id = ? AND payment_status IN ('pending','partially_paid')");
$stmt->execute([$shopId]);
$stats['outstanding'] = (float)$stmt->fetchColumn();

// Recent orders
$stmt = $pdo->prepare("SELECT * FROM orders WHERE shop_id = ? ORDER BY id DESC LIMIT 5");
$stmt->execute([$shopId]);
$recentOrders = $stmt->fetchAll();

$pageTitle = 'Dashboard';
require_once dirname(__DIR__) . '/includes/shop_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1" style="color: #ffffff;">Welcome, <?= e($shop['shop_name']) ?>! 👋</h2>
        <p class="small mb-0" style="color: var(--text-muted);">Here is your shop overview for today.</p>
    </div>
    <a href="<?= BASE_URL ?>shop/products.php" class="btn btn-primary btn-sm px-4 py-2 rounded-3">
        <i class="bi bi-cart-plus me-1"></i> Browse Products
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="label">Total Orders</div>
            <div class="value" style="color: #38bdf8;"><?= $stats['total'] ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="label">Pending</div>
            <div class="value" style="color: #fbbf24;"><?= $stats['pending'] ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="label">Delivered</div>
            <div class="value" style="color: #34d399;"><?= $stats['delivered'] ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="label">Outstanding</div>
            <div class="value" style="color: #f87171; font-size:1.4rem;"><?= money($stats['outstanding']) ?></div>
        </div>
    </div>
</div>

<div class="card-soft h-100">
    <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
        <strong style="color: #fff;">Recent Orders</strong>
        <a href="<?= BASE_URL ?>shop/orders.php" class="small fw-semibold" style="color: var(--brand);">View All <i class="bi bi-arrow-right"></i></a>
    </div>
    <?php if (empty($recentOrders)): ?>
        <div class="p-5 text-center" style="color: var(--text-muted);">
            <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
            No orders yet. <a href="<?= BASE_URL ?>shop/products.php" style="color: var(--brand);">Start browsing products</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th class="text-end">Total</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentOrders as $o): ?>
                    <tr>
                        <td class="small fw-bold" style="color: #e2e8f0;"><?= e($o['order_number']) ?></td>
                        <td class="small" style="color: var(--text-muted);"><?= fmt_date($o['created_at']) ?></td>
                        <td class="text-end small fw-semibold" style="color: #fff;"><?= money($o['total_amount']) ?></td>
                        <td><?= status_badge($o['status']) ?></td>
                        <td class="text-end">
                            <a href="<?= BASE_URL ?>shop/order-details.php?id=<?= (int)$o['id'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                View
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/includes/shop_footer.php'; ?>