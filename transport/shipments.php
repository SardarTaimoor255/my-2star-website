<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_TRANSPORT);
require_once dirname(__DIR__) . '/includes/pagination.php';
$user = current_user($pdo);
$statusFilter = $_GET['status'] ?? '';

// Build WHERE clause
$where = " WHERE s.transport_user_id = ?";
$params = [$user['id']];
if ($statusFilter !== '') { 
    $where .= " AND s.status = ?"; 
    $params[] = $statusFilter; 
}

// Count total for pagination
$countSql = "SELECT COUNT(*) FROM shipments s 
             JOIN orders o ON o.id = s.order_id
             JOIN shops sh ON sh.id = o.shop_id" . $where;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalShipments = (int)$countStmt->fetchColumn();

// Build base URL
$baseUrl = 'shipments.php' . ($statusFilter !== '' ? '?status=' . urlencode($statusFilter) : '');

// Pagination
$pg = paginate($totalShipments, current_page_num(), 20, $baseUrl);
$offset = $pg['offset'];

// Main query
$sql = "SELECT s.*, o.order_number, sh.shop_name, sh.address AS shop_address
        FROM shipments s
        JOIN orders o ON o.id = s.order_id
        JOIN shops sh ON sh.id = o.shop_id"
        . $where
        . " ORDER BY s.id DESC LIMIT {$pg['perPage']} OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$shipments = $stmt->fetchAll();

$pageTitle = 'Shipments';
require_once dirname(__DIR__) . '/includes/transport_header.php';
?>

<h2 class="fw-bold mb-1">My Shipments</h2>
<p class="text-muted small mb-4">All shipments assigned to you</p>

<div class="card-soft p-2 mb-3">
    <div class="d-flex flex-wrap gap-1 align-items-center">
        <span class="small text-muted ms-2">Filter:</span>
        <a href="?" class="btn btn-sm <?= $statusFilter===''?'btn-primary':'btn-outline-secondary' ?>">All</a>
        <a href="?status=assigned" class="btn btn-sm <?= $statusFilter==='assigned'?'btn-primary':'btn-outline-secondary' ?>">Assigned</a>
        <a href="?status=picked_up" class="btn btn-sm <?= $statusFilter==='picked_up'?'btn-primary':'btn-outline-secondary' ?>">Picked Up</a>
        <a href="?status=in_transit" class="btn btn-sm <?= $statusFilter==='in_transit'?'btn-primary':'btn-outline-secondary' ?>">In Transit</a>
        <a href="?status=out_for_delivery" class="btn btn-sm <?= $statusFilter==='out_for_delivery'?'btn-primary':'btn-outline-secondary' ?>">Out for Delivery</a>
        <a href="?status=delivered" class="btn btn-sm <?= $statusFilter==='delivered'?'btn-primary':'btn-outline-secondary' ?>">Delivered</a>
    </div>
</div>

<?php if (empty($shipments)): ?>
    <div class="card-soft p-5 text-center text-muted">
        <i class="bi bi-truck fs-1 d-block mb-2 opacity-25"></i>
        No shipments found.
    </div>
<?php else: ?>
    <div class="card-soft">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Shipment #</th><th>Order</th><th>Shop</th><th>Vehicle</th><th>Bilty #</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($shipments as $s): ?>
                    <tr>
                        <td class="small fw-semibold"><?= e($s['shipment_no']) ?></td>
                        <td class="small text-muted"><?= e($s['order_number']) ?></td>
                        <td class="small">
                            <div><?= e($s['shop_name']) ?></div>
                            <div class="text-muted" style="font-size:.72rem;"><?= e(mb_strimwidth($s['shop_address'] ?? '', 0, 40, '...')) ?></div>
                        </td>
                        <td class="small"><?= e($s['vehicle_no'] ?? '—') ?></td>
                        <td class="small"><?= e($s['bilty_no'] ?? '—') ?></td>
                        <td>
                            <?php
                            $clr = ['assigned'=>'warning','picked_up'=>'info','in_transit'=>'primary','arrived_destination'=>'info','out_for_delivery'=>'info','delivered'=>'success','delivery_failed'=>'danger','returned'=>'dark'][$s['status']] ?? 'secondary';
                            ?>
                            <span class="badge text-bg-<?= $clr ?>"><?= e(SHIPMENT_STATUSES[$s['status']] ?? $s['status']) ?></span>
                        </td>
                        <td class="text-end">
                            <a href="<?= BASE_URL ?>transport/shipment-details.php?id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-primary">Open</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
         <?= $pg['html'] ?>
    </div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/transport_footer.php'; ?>