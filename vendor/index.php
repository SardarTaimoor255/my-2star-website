<?php

require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_VENDOR);

$user = current_user($pdo);
$vendorStmt = $pdo->prepare("SELECT * FROM vendors WHERE user_id = ? LIMIT 1");
$vendorStmt->execute([$user['id']]);
$vendor = $vendorStmt->fetch();
$vendorId = (int)$vendor['id'];

$stats = [
    'pending_pr'  => (int)$pdo->query("SELECT COUNT(*) FROM price_requests WHERE status = 'pending'")->fetchColumn(),
    'sent_pr'     => (int)$pdo->query("SELECT COUNT(*) FROM price_requests WHERE status = 'sent_to_company'")->fetchColumn(),
    'new_orders'  => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'order_created'")->fetchColumn(),
    'active'      => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status NOT IN ('delivered','cancelled','returned')")->fetchColumn(),
    'delivered'   => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered'")->fetchColumn(),
    'sales'       => (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status = 'delivered'")->fetchColumn(),
    'commission'  => (float)$pdo->query("SELECT COALESCE(SUM(commission_total),0) FROM orders WHERE status = 'delivered'")->fetchColumn(),
    'outstanding' => (float)$pdo->query("SELECT COALESCE(SUM(total_amount - paid_amount),0) FROM orders WHERE payment_status IN ('pending','partially_paid')")->fetchColumn(),
];

$recentOrders = $pdo->query(
    "SELECT o.*, s.shop_name FROM orders o JOIN shops s ON s.id = o.shop_id
     ORDER BY o.id DESC LIMIT 5"
)->fetchAll();

$recentPR = $pdo->query(
    "SELECT pr.*, s.shop_name FROM price_requests pr JOIN shops s ON s.id = pr.shop_id
     WHERE pr.status IN ('pending','sent_to_company','quoted','countered')
     ORDER BY pr.id DESC LIMIT 5"
)->fetchAll();

$pageTitle = 'Vendor Dashboard';
require_once dirname(__DIR__) . '/includes/vendor_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1" style="color: #ffffff;">Welcome back, <?= e(explode(' ', $user['full_name'])[0]) ?>! 👋</h2>
        <p class="small mb-0" style="color: var(--text-muted);">Here is your business snapshot for today.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Stat Cards -->
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="label">New Orders</div>
            <div class="value" style="color: #f87171;"><?= $stats['new_orders'] ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="label">Active Orders</div>
            <div class="value" style="color: #fbbf24;"><?= $stats['active'] ?></div>
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
            <div class="label">Pending Requests</div>
            <div class="value" style="color: #38bdf8;"><?= $stats['pending_pr'] + $stats['sent_pr'] ?></div>
        </div>
    </div>

    <div class="col-6 col-md-4 mt-4">
        <div class="stat-card" style="border-left: 4px solid #38bdf8;">
            <div class="label">Total Sales (Delivered)</div>
            <div class="value" style="font-size:1.3rem; color: #fff;"><?= money($stats['sales']) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4 mt-4">
        <div class="stat-card" style="border-left: 4px solid #34d399;">
            <div class="label">Commission Earned</div>
            <div class="value" style="font-size:1.3rem; color: #34d399;"><?= money($stats['commission']) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4 mt-4">
        <div class="stat-card" style="border-left: 4px solid #f87171;">
            <div class="label">Outstanding Amount</div>
            <div class="value" style="font-size:1.3rem; color: #f87171;"><?= money($stats['outstanding']) ?></div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Recent Orders -->
    <div class="col-lg-7">
        <div class="card-soft h-100">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                <strong style="color: #fff;">Recent Orders</strong>
                <a href="<?= BASE_URL ?>vendor/orders.php" class="small fw-semibold" style="color: var(--brand);">View All <i class="bi bi-arrow-right"></i></a>
            </div>
            <?php if (empty($recentOrders)): ?>
                <div class="p-5 text-center" style="color: var(--text-muted);">
                    <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                    No orders yet.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Order #</th>
                                <th>Shop</th>
                                <th class="text-end">Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentOrders as $o): ?>
                            <tr>
                                <td class="small">
                                    <a href="<?= BASE_URL ?>vendor/order-details.php?id=<?= (int)$o['id'] ?>" class="fw-bold" style="color: var(--brand);">
                                        <?= e($o['order_number']) ?>
                                    </a>
                                </td>
                                <td class="small" style="color: #e2e8f0;"><?= e($o['shop_name']) ?></td>
                                <td class="text-end small fw-semibold" style="color: #fff;"><?= money($o['total_amount']) ?></td>
                                <td><?= status_badge($o['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Price Requests -->
    <div class="col-lg-5">
        <div class="card-soft h-100">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                <strong style="color: #fff;">Open Price Requests</strong>
                <a href="<?= BASE_URL ?>vendor/price-requests.php" class="small fw-semibold" style="color: var(--brand);">View All <i class="bi bi-arrow-right"></i></a>
            </div>
            <?php if (empty($recentPR)): ?>
                <div class="p-5 text-center" style="color: var(--text-muted);">
                    <i class="bi bi-check-circle fs-1 d-block mb-2 opacity-50" style="color: #34d399;"></i>
                    No pending requests. All caught up!
                </div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($recentPR as $pr): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center p-3">
                            <div>
                                <a href="<?= BASE_URL ?>vendor/price-request-details.php?id=<?= (int)$pr['id'] ?>" class="small fw-bold text-decoration-none" style="color: var(--brand);">
                                    <?= e($pr['request_no']) ?>
                                </a>
                                <div style="color: var(--text-muted); font-size:.75rem; margin-top: 3px;">
                                    <i class="bi bi-shop me-1"></i> <?= e($pr['shop_name']) ?>
                                </div>
                            </div>
                            <?php
                            $clr = ['pending'=>'secondary','sent_to_company'=>'info','quoted'=>'primary','countered'=>'warning'][$pr['status']] ?? 'secondary';
                            ?>
                            <span class="badge text-bg-<?= $clr ?> rounded-pill px-3 py-2"><?= e(PRICE_REQUEST_STATUSES[$pr['status']] ?? $pr['status']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/vendor_footer.php'; ?>