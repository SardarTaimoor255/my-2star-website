<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_TRANSPORT);

$user = current_user($pdo);
$tcStmt = $pdo->prepare("SELECT * FROM transport_companies WHERE user_id = ? LIMIT 1");
$tcStmt->execute([$user['id']]);
$transportCo = $tcStmt->fetch();

// Stats for this transport user
$stats = [
    'awaiting'  => 0, 'assigned' => 0, 'picked_up' => 0,
    'in_transit'=> 0, 'out_delivery' => 0, 'delivered' => 0, 'failed' => 0,
];

$rows = $pdo->prepare(
    "SELECT status, COUNT(*) AS cnt FROM shipments WHERE transport_user_id = ? GROUP BY status"
);
$rows->execute([$user['id']]);
foreach ($rows->fetchAll() as $r) {
    $stats[$r['status']] = (int)$r['cnt'];
}

// Recent active shipments
$recent = $pdo->prepare(
    "SELECT s.*, o.order_number, sh.shop_name, sh.address AS shop_address
     FROM shipments s
     JOIN orders o ON o.id = s.order_id
     JOIN shops sh ON sh.id = o.shop_id
     WHERE s.transport_user_id = ? AND s.status NOT IN ('delivered','returned','delivery_failed')
     ORDER BY s.id DESC LIMIT 5"
);
$recent->execute([$user['id']]);
$recentShipments = $recent->fetchAll();

$pageTitle = 'Transport Dashboard';
require_once dirname(__DIR__) . '/includes/transport_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1" style="color: #ffffff;">Welcome, <?= e($transportCo['company_name'] ?? $user['full_name']) ?>! 🚚</h2>
        <p class="small mb-0" style="color: var(--text-muted);">Here is your delivery operations overview.</p>
    </div>
    <a href="<?= BASE_URL ?>transport/shipments.php" class="btn btn-primary btn-sm px-4 py-2 rounded-3">
        <i class="bi bi-truck me-1"></i> View All Shipments
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="label">Assigned</div>
            <div class="value" style="color: #fbbf24;"><?= $stats['assigned'] ?? 0 ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="label">Picked Up</div>
            <div class="value" style="color: #38bdf8;"><?= $stats['picked_up'] ?? 0 ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="label">In Transit</div>
            <div class="value" style="color: #818cf8;"><?= $stats['in_transit'] ?? 0 ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="label">Out for Delivery</div>
            <div class="value" style="color: #c084fc;"><?= $stats['out_for_delivery'] ?? 0 ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="label">Delivered</div>
            <div class="value" style="color: #34d399;"><?= $stats['delivered'] ?? 0 ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="label">Delivery Failed</div>
            <div class="value" style="color: #f87171;"><?= $stats['delivery_failed'] ?? 0 ?></div>
        </div>
    </div>
</div>

<div class="card-soft">
    <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
        <strong style="color: #fff;">Active Shipments</strong>
        <a href="<?= BASE_URL ?>transport/shipments.php" class="small fw-semibold" style="color: var(--brand);">View All <i class="bi bi-arrow-right"></i></a>
    </div>
    <?php if (empty($recentShipments)): ?>
        <div class="p-5 text-center" style="color: var(--text-muted);">
            <i class="bi bi-truck fs-1 d-block mb-2 opacity-50"></i>
            No active shipments. All caught up! 🎉
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr><th>Shipment #</th><th>Order #</th><th>Shop</th><th>Vehicle</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($recentShipments as $s): ?>
                    <tr>
                        <td class="small fw-bold" style="color: #e2e8f0;"><?= e($s['shipment_no']) ?></td>
                        <td class="small" style="color: var(--text-muted);"><?= e($s['order_number']) ?></td>
                        <td class="small" style="color: #e2e8f0;"><?= e($s['shop_name']) ?></td>
                        <td class="small" style="color: #e2e8f0;"><?= e($s['vehicle_no'] ?? '—') ?></td>
                        <td>
                            <?php
                            $clr = ['assigned'=>'warning','picked_up'=>'info','in_transit'=>'primary','arrived_destination'=>'info','out_for_delivery'=>'info','delivered'=>'success','delivery_failed'=>'danger','returned'=>'dark'][$s['status']] ?? 'secondary';
                            ?>
                            <span class="badge text-bg-<?= $clr ?>"><?= e(SHIPMENT_STATUSES[$s['status']] ?? $s['status']) ?></span>
                        </td>
                        <td class="text-end">
                            <a href="<?= BASE_URL ?>transport/shipment-details.php?id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Open</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/includes/transport_footer.php'; ?>