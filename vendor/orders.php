<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_VENDOR);
require_once dirname(__DIR__) . '/includes/pagination.php';
$statusFilter = $_GET['status'] ?? '';

// Build WHERE clause
$where = " WHERE 1=1";
$params = [];
if ($statusFilter !== '') { $where .= " AND o.status = ?"; $params[] = $statusFilter; }

// Count total for pagination
$countSql = "SELECT COUNT(*) FROM orders o JOIN shops s ON s.id = o.shop_id" . $where;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalOrders = (int)$countStmt->fetchColumn();

// Build base URL for pagination
$baseUrl = 'orders.php' . ($statusFilter !== '' ? '?status=' . urlencode($statusFilter) : '');

// Pagination
$pg = paginate($totalOrders, current_page_num(), 20, $baseUrl);
$offset = $pg['offset'];

// Main query
$sql = "SELECT o.*, s.shop_name 
        FROM orders o 
        JOIN shops s ON s.id = o.shop_id" 
        . $where
        . " ORDER BY o.id DESC LIMIT {$pg['perPage']} OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();
$pageTitle = 'Orders';
require_once dirname(__DIR__) . '/includes/vendor_header.php';
?>

<h2 class="fw-bold mb-1">Orders</h2>
<p class="text-muted small mb-4">All shop orders</p>

<div class="card-soft p-2 mb-3">
    <div class="d-flex flex-wrap gap-1 align-items-center">
        <span class="small text-muted ms-2">Filter:</span>
        <a href="?" class="btn btn-sm <?= $statusFilter===''?'btn-primary':'btn-outline-secondary' ?>">All</a>
        <a href="?status=order_created" class="btn btn-sm <?= $statusFilter==='order_created'?'btn-primary':'btn-outline-secondary' ?>">New</a>
        <a href="?status=sent_to_company" class="btn btn-sm <?= $statusFilter==='sent_to_company'?'btn-primary':'btn-outline-secondary' ?>">Sent</a>
        <a href="?status=ready_for_transport" class="btn btn-sm <?= $statusFilter==='ready_for_transport'?'btn-primary':'btn-outline-secondary' ?>">Ready</a>
        <a href="?status=in_transit" class="btn btn-sm <?= $statusFilter==='in_transit'?'btn-primary':'btn-outline-secondary' ?>">In Transit</a>
        <a href="?status=delivered" class="btn btn-sm <?= $statusFilter==='delivered'?'btn-primary':'btn-outline-secondary' ?>">Delivered</a>
    </div>
</div>

<?php if (empty($orders)): ?>
    <div class="card-soft p-5 text-center text-muted">
        <i class="bi bi-receipt fs-1 d-block mb-2 opacity-25"></i> No orders found.
    </div>
<?php else: ?>
    <div class="card-soft">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Order #</th><th>Shop</th><th>Date</th><th class="text-end">Total</th><th class="text-end">Commission</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td class="small fw-semibold"><?= e($o['order_number']) ?></td>
                        <td class="small"><?= e($o['shop_name']) ?></td>
                        <td class="small text-muted"><?= fmt_date($o['created_at']) ?></td>
                        <td class="text-end small"><?= money($o['total_amount']) ?></td>
                        <td class="text-end small text-success"><?= money($o['commission_total']) ?></td>
                        <td><?= status_badge($o['status']) ?></td>
                        <td class="text-end">
                            <a href="<?= BASE_URL ?>vendor/order-details.php?id=<?= (int)$o['id'] ?>" class="btn btn-sm btn-outline-primary">Open</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
         <?= $pg['html'] ?>
    </div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/vendor_footer.php'; ?>