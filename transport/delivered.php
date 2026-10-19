<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_TRANSPORT);

$user = current_user($pdo);

$stmt = $pdo->prepare(
    "SELECT s.*, o.order_number, sh.shop_name
     FROM shipments s
     JOIN orders o ON o.id = s.order_id
     JOIN shops sh ON sh.id = o.shop_id
     WHERE s.transport_user_id = ? AND s.status = 'delivered'
     ORDER BY s.delivered_at DESC LIMIT 100"
);
$stmt->execute([$user['id']]);
$delivered = $stmt->fetchAll();

$pageTitle = 'Delivered Shipments';
require_once dirname(__DIR__) . '/includes/transport_header.php';
?>

<h2 class="fw-bold mb-1">Delivered Shipments</h2>
<p class="text-muted small mb-4">Successfully delivered shipments</p>

<?php if (empty($delivered)): ?>
    <div class="card-soft p-5 text-center text-muted">
        <i class="bi bi-check-circle fs-1 d-block mb-2 opacity-25"></i>
        No delivered shipments yet.
    </div>
<?php else: ?>
    <div class="card-soft">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Shipment #</th><th>Order #</th><th>Shop</th><th>Vehicle</th><th>Delivered At</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($delivered as $s): ?>
                    <tr>
                        <td class="small fw-semibold"><?= e($s['shipment_no']) ?></td>
                        <td class="small text-muted"><?= e($s['order_number']) ?></td>
                        <td class="small"><?= e($s['shop_name']) ?></td>
                        <td class="small"><?= e($s['vehicle_no'] ?? '—') ?></td>
                        <td class="small">
                            <span class="badge text-bg-success"><i class="bi bi-check2"></i></span>
                            <?= fmt_datetime($s['delivered_at']) ?>
                        </td>
                        <td class="text-end">
                            <a href="<?= BASE_URL ?>transport/shipment-details.php?id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/transport_footer.php'; ?>