<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_ADMIN);
require_once dirname(__DIR__) . '/includes/pagination.php';
// Filters
$statusFilter = $_GET['status'] ?? '';
$shopFilter = (int)($_GET['shop'] ?? 0);
$search = trim($_GET['q'] ?? '');

// Build WHERE clause once
$where = " WHERE 1=1";
$params = [];

if ($statusFilter !== '') {
    $where .= " AND o.status = ?";
    $params[] = $statusFilter;
}
if ($shopFilter > 0) {
    $where .= " AND o.shop_id = ?";
    $params[] = $shopFilter;
}
if ($search !== '') {
    $where .= " AND o.order_number LIKE ?";
    $params[] = "%$search%";
}

// Count total for pagination
$countSql = "SELECT COUNT(*) FROM orders o JOIN shops s ON s.id = o.shop_id" . $where;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalOrders = (int)$countStmt->fetchColumn();

// Build base URL for pagination (preserve filters)
$filterParams = [];
if ($statusFilter !== '') $filterParams[] = 'status=' . urlencode($statusFilter);
if ($shopFilter > 0)      $filterParams[] = 'shop=' . $shopFilter;
if ($search !== '')       $filterParams[] = 'q=' . urlencode($search);
$baseUrl = 'orders.php' . (empty($filterParams) ? '' : '?' . implode('&', $filterParams));

// Pagination
$pg = paginate($totalOrders, current_page_num(), 20, $baseUrl);
$offset = $pg['offset'];

// Main query with pagination
$sql = "SELECT o.*, s.shop_name, u.full_name AS vendor_name
        FROM orders o
        JOIN shops s ON s.id = o.shop_id
        LEFT JOIN vendors v ON v.id = o.vendor_id
        LEFT JOIN users u ON u.id = v.user_id"
        . $where
        . " ORDER BY o.id DESC LIMIT {$pg['perPage']} OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Summary
$summary = $pdo->query(
    "SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
        COALESCE(SUM(total_amount), 0) AS total_value,
        COALESCE(SUM(paid_amount), 0) AS total_paid
     FROM orders"
)->fetch();

// Shops for filter
$shops = $pdo->query("SELECT id, shop_name FROM shops ORDER BY shop_name")->fetchAll();

$pageTitle = 'All Orders';
require_once dirname(__DIR__) . '/includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1">All Orders</h2>
        <p class="text-muted small mb-0"><?= (int)$summary['total'] ?> total orders</p>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="label">Total Orders</div>
            <div class="value"><?= (int)$summary['total'] ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="label">Delivered</div>
            <div class="value text-success"><?= (int)$summary['delivered'] ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="label">Total Value</div>
            <div class="value" style="font-size:1.1rem;"><?= money($summary['total_value']) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="label">Total Paid</div>
            <div class="value text-success" style="font-size:1.1rem;"><?= money($summary['total_paid']) ?></div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card-soft p-3 mb-3">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small mb-1">Search</label>
            <input type="text" name="q" class="form-control form-control-sm" 
                   placeholder="Order #..." value="<?= e($search) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1">Shop</label>
            <select name="shop" class="form-select form-select-sm">
                <option value="0">All Shops</option>
                <?php foreach ($shops as $s): ?>
                    <option value="<?= (int)$s['id'] ?>" <?= $shopFilter == $s['id'] ? 'selected' : '' ?>>
                        <?= e($s['shop_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All Status</option>
                <?php foreach (ORDER_STATUSES as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>>
                        <?= e($v) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary btn-sm w-100">
                <i class="bi bi-funnel"></i> Filter
            </button>
        </div>
    </form>
</div>

<!-- Orders Table -->
<div class="card-soft">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Order #</th>
                    <th>Shop</th>
                    <th>Vendor</th>
                    <th>Date</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Paid</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($orders)): ?>
                <tr><td colspan="9" class="text-center text-muted py-5">
                    <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
                    No orders found.
                </td></tr>
            <?php else: foreach ($orders as $o): 
                $payColors = ['pending'=>'danger','partially_paid'=>'warning','paid'=>'success'];
                $pc = $payColors[$o['payment_status']] ?? 'secondary';
            ?>
                <tr>
                    <td class="small fw-semibold"><?= e($o['order_number']) ?></td>
                    <td class="small"><?= e($o['shop_name']) ?></td>
                    <td class="small text-muted"><?= e($o['vendor_name'] ?? '—') ?></td>
                    <td class="small text-muted"><?= fmt_date($o['created_at']) ?></td>
                    <td class="text-end small"><?= money($o['total_amount']) ?></td>
                    <td class="text-end small text-success"><?= money($o['paid_amount']) ?></td>
                    <td>
                        <span class="badge text-bg-<?= $pc ?>">
                            <?= e(PAYMENT_STATUSES[$o['payment_status']] ?? $o['payment_status']) ?>
                        </span>
                    </td>
                    <td><?= status_badge($o['status']) ?></td>
                    <td class="text-end">
                        <a href="<?= BASE_URL ?>vendor/order-details.php?id=<?= (int)$o['id'] ?>" 
                           class="btn btn-sm btn-outline-primary" target="_blank">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
     <?= $pg['html'] ?>
</div>

<?php require_once dirname(__DIR__) . '/includes/admin_footer.php'; ?>