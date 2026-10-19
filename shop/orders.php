<?php
/**
 * shop/orders.php
 */
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_SHOP);
require_once dirname(__DIR__) . '/includes/pagination.php';
$user = current_user($pdo);
$shopStmt = $pdo->prepare("SELECT id FROM shops WHERE user_id = ? LIMIT 1");
$shopStmt->execute([$user['id']]);
$shopId = (int)$shopStmt->fetchColumn();

$statusFilter = $_GET['status'] ?? '';

// Build WHERE clause
$where = " WHERE shop_id = ?";
$params = [$shopId];
if ($statusFilter !== '') {
    $where .= " AND status = ?";
    $params[] = $statusFilter;
}

// Count total for pagination
$countSql = "SELECT COUNT(*) FROM orders" . $where;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalOrders = (int)$countStmt->fetchColumn();

// Build base URL for pagination
$baseUrl = 'orders.php' . ($statusFilter !== '' ? '?status=' . urlencode($statusFilter) : '');

// Pagination
$pg = paginate($totalOrders, current_page_num(), 20, $baseUrl);
$offset = $pg['offset'];

// Main query
$sql = "SELECT * FROM orders" . $where
        . " ORDER BY id DESC LIMIT {$pg['perPage']} OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$pageTitle = 'My Orders';
require_once dirname(__DIR__) . '/includes/shop_header.php';
?>

<h2 class="fw-bold mb-1">My Orders</h2>
<p class="text-muted small mb-4">All your orders in one place</p>

<div class="card-soft p-2 mb-3">
    <form method="get" class="d-flex flex-wrap gap-2 align-items-center">
        <span class="small text-muted ms-2">Filter:</span>
        <a href="?" class="btn btn-sm <?= $statusFilter === '' ? 'btn-primary' : 'btn-outline-secondary' ?>">All</a>
        <a href="?status=order_created" class="btn btn-sm <?= $statusFilter === 'order_created' ? 'btn-primary' : 'btn-outline-secondary' ?>">New</a>
        <a href="?status=in_transit" class="btn btn-sm <?= $statusFilter === 'in_transit' ? 'btn-primary' : 'btn-outline-secondary' ?>">In Transit</a>
        <a href="?status=delivered" class="btn btn-sm <?= $statusFilter === 'delivered' ? 'btn-primary' : 'btn-outline-secondary' ?>">Delivered</a>
    </form>
</div>

<?php if (empty($orders)): ?>
    <div class="card-soft p-5 text-center text-muted">
        <i class="bi bi-receipt fs-1 d-block mb-2 opacity-25"></i>
        No orders found.
    </div>
<?php else: ?>
    <div class="card-soft">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Paid</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td class="small fw-semibold"><?= e($o['order_number']) ?></td>
                        <td class="small text-muted"><?= fmt_date($o['created_at']) ?></td>
                        <td class="text-end small"><?= money($o['total_amount']) ?></td>
                        <td class="text-end small"><?= money($o['paid_amount']) ?></td>
                        <td>
                            <?php
                            $payColors = ['pending'=>'danger','partially_paid'=>'warning','paid'=>'success','failed'=>'danger','refunded'=>'secondary'];
                            $pc = $payColors[$o['payment_status']] ?? 'secondary';
                            ?>
                            <span class="badge text-bg-<?= $pc ?>"><?= e(PAYMENT_STATUSES[$o['payment_status']]) ?></span>
                        </td>
                        <td><?= status_badge($o['status']) ?></td>
                                                <td class="text-end">
                            <a href="<?= BASE_URL ?>shop/order-details.php?id=<?= (int)$o['id'] ?>" class="btn btn-sm btn-outline-primary">
                                View
                            </a>
                            <?php if ($o['status'] === 'order_created'): ?>
                                <button class="btn btn-sm btn-outline-danger ms-1"
                                        onclick="cancelOrder(<?= (int)$o['id'] ?>, '<?= e($o['order_number']) ?>')">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $pg['html'] ?>
    </div>
<?php endif; ?>
<script>
async function cancelOrder(orderId, orderNumber) {
    const reason = prompt(
        'Cancel order ' + orderNumber + '?\n\nReason (optional):\n' +
        'Jaise: "Galti se order ho gaya", "Product nahi chahiye", etc.'
    );
    
    // Agar user ne Cancel click kiya (null) → ruk jayein
    if (reason === null) return;
    
    if (!confirm('Pakka cancel karna hai?\n\nOrder ' + orderNumber + '\nReason: ' + (reason || 'No reason given'))) {
        return;
    }
    
    const fd = new FormData();
    fd.append('action', 'cancel_order');
    fd.append('order_id', orderId);
    fd.append('reason', reason);
    fd.append('csrf_token', '<?= e(csrf_token()) ?>');
    
    try {
        const res = await fetch('<?= BASE_URL ?>api/shop-order-action.php', {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        
        if (data.success) {
            location.reload();
        } else {
            alert('❌ ' + (data.message || 'Failed to cancel order'));
        }
    } catch (e) {
        alert('Network error. Please try again.');
    }
}
</script>
<?php require_once dirname(__DIR__) . '/includes/shop_footer.php'; ?>