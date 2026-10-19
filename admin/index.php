<?php
/**
 * admin/index.php — Dashboard
 */
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_ADMIN);

// Stats
$stats = [
    'users'     => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'shops'     => (int)$pdo->query("SELECT COUNT(*) FROM shops")->fetchColumn(),
    'companies' => (int)$pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn(),
    'pending'   => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='pending'")->fetchColumn(),
    'orders'    => (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'delivered' => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status='delivered'")->fetchColumn(),
    'sales'     => (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status='delivered'")->fetchColumn(),
    'pendingPay' => (float)$pdo->query("SELECT COALESCE(SUM(total_amount - paid_amount),0) FROM orders WHERE payment_status IN ('pending','partially_paid')")->fetchColumn(),
];

// Recent orders
$recentOrders = $pdo->query(
    "SELECT o.*, s.shop_name
     FROM orders o
     JOIN shops s ON s.id = o.shop_id
     ORDER BY o.id DESC LIMIT 5"
)->fetchAll();

// Pending approvals (recent 5)
$pendingUsers = $pdo->query(
    "SELECT u.*, r.display_name AS role_label
     FROM users u
     JOIN roles r ON r.id = u.role_id
     WHERE u.status = 'pending'
     ORDER BY u.id DESC LIMIT 5"
)->fetchAll();

$pageTitle = 'Dashboard';
require_once dirname(__DIR__) . '/includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1" style="color: #ffffff;">Dashboard</h2>
        <p class="small mb-0" style="color: var(--text-muted);">Overview of your entire platform</p>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card position-relative">
            <i class="bi bi-people-fill icon" style="color: #3b82f6;"></i>
            <div class="label">Total Users</div>
            <div class="value" style="color: #3b82f6;"><?= $stats['users'] ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card position-relative">
            <i class="bi bi-shop icon" style="color: #34d399;"></i>
            <div class="label">Total Shops</div>
            <div class="value" style="color: #34d399;"><?= $stats['shops'] ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card position-relative">
            <i class="bi bi-building icon" style="color: #38bdf8;"></i>
            <div class="label">Companies</div>
            <div class="value" style="color: #38bdf8;"><?= $stats['companies'] ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card position-relative">
            <i class="bi bi-hourglass-split icon" style="color: #fbbf24;"></i>
            <div class="label">Pending Approval</div>
            <div class="value" style="color: #fbbf24;"><?= $stats['pending'] ?></div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="stat-card position-relative">
            <i class="bi bi-receipt icon" style="color: #818cf8;"></i>
            <div class="label">Total Orders</div>
            <div class="value" style="color: #818cf8;"><?= $stats['orders'] ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card position-relative">
            <i class="bi bi-check-circle-fill icon" style="color: #34d399;"></i>
            <div class="label">Delivered</div>
            <div class="value" style="color: #34d399;"><?= $stats['delivered'] ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card position-relative">
            <i class="bi bi-cash-stack icon" style="color: #10b981;"></i>
            <div class="label">Delivered Sales</div>
            <div class="value" style="font-size:1.4rem; color: #10b981;"><?= money($stats['sales']) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card position-relative">
            <i class="bi bi-exclamation-triangle icon" style="color: #f43f5e;"></i>
            <div class="label">Pending Payments</div>
            <div class="value" style="font-size:1.4rem; color: #f43f5e;"><?= money($stats['pendingPay']) ?></div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Recent Orders -->
    <div class="col-lg-7">
        <div class="card-soft h-100">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                <strong style="color: #fff;">Recent Orders</strong>
                <a href="<?= BASE_URL ?>admin/orders.php" class="small fw-semibold" style="color: var(--brand);">View All <i class="bi bi-arrow-right"></i></a>
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
                                <td class="small fw-bold" style="color: #e2e8f0;"><?= e($o['order_number']) ?></td>
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

    <!-- Pending Approvals -->
    <div class="col-lg-5">
        <div class="card-soft h-100">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                <strong style="color: #fff;">Pending Approvals</strong>
                <a href="<?= BASE_URL ?>admin/approvals.php" class="small fw-semibold" style="color: var(--brand);">View All <i class="bi bi-arrow-right"></i></a>
            </div>
            <?php if (empty($pendingUsers)): ?>
                <div class="p-5 text-center" style="color: var(--text-muted);">
                    <i class="bi bi-check-circle fs-1 d-block mb-2 opacity-50" style="color: #34d399;"></i>
                    No pending approvals. All caught up! 🎉
                </div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($pendingUsers as $u): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center p-3">
                            <div>
                                <div class="small fw-bold" style="color: #fff;"><?= e($u['full_name']) ?></div>
                                <div style="color: var(--text-muted); font-size:.75rem; margin-top: 3px;">
                                    <i class="bi bi-envelope me-1"></i> <?= e($u['email']) ?> 
                                    <span class="mx-1">·</span> 
                                    <span class="badge text-bg-secondary"><?= e($u['role_label']) ?></span>
                                </div>
                            </div>
                            <span class="badge text-bg-warning rounded-pill px-3 py-2 text-dark">Pending</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/admin_footer.php'; ?>