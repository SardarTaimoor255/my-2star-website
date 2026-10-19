<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/payment_helper.php';
require_role($pdo, ROLE_TRANSPORT);

$user = current_user($pdo);

// History only
$stmt = $pdo->prepare(
    "SELECT p.*, s.shop_name, o.order_number, sh.shipment_no
     FROM payments p
     JOIN orders o ON o.id = p.order_id
     JOIN shops s ON s.id = p.shop_id
     LEFT JOIN shipments sh ON sh.id = p.shipment_id
     WHERE p.recipient_type = 'transport' 
       AND sh.transport_user_id = ?
       AND p.status = 'paid'
     ORDER BY p.id DESC LIMIT 100"
);
$stmt->execute([$user['id']]);
$history = $stmt->fetchAll();

// Summary
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(amount), 0) FROM payments 
     WHERE recipient_type = 'transport' AND status = 'paid'
       AND shipment_id IN (SELECT id FROM shipments WHERE transport_user_id = ?)"
);
$stmt->execute([$user['id']]);
$totalReceived = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(transport_charges - paid_amount), 0) FROM shipments
     WHERE transport_user_id = ? AND payment_status IN ('pending','partially_paid')"
);
$stmt->execute([$user['id']]);
$outstanding = (float)$stmt->fetchColumn();

$pageTitle = 'Payments Received';
require_once dirname(__DIR__) . '/includes/transport_header.php';
?>

<h2 class="fw-bold mb-1">Payments Received</h2>
<p class="text-muted small mb-4">Delivery charges paid by shops</p>

<div class="row g-3 mb-4">
    <div class="col-6">
        <div class="stat-card">
            <div class="label">Total Received</div>
            <div class="value text-success" style="font-size:1.3rem;"><?= money($totalReceived) ?></div>
        </div>
    </div>
    <div class="col-6">
        <div class="stat-card">
            <div class="label">Pending</div>
            <div class="value text-danger" style="font-size:1.3rem;"><?= money($outstanding) ?></div>
        </div>
    </div>
</div>

<div class="card-soft">
    <div class="p-3 border-bottom"><strong>Payment History</strong></div>
    <?php if (empty($history)): ?>
        <div class="p-5 text-center text-muted small">
            <i class="bi bi-cash-stack fs-1 d-block mb-2 opacity-25"></i>
            No payments received yet.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Payment #</th><th>Date</th><th>Shop</th>
                        <th>Shipment</th><th>Method</th><th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($history as $p): ?>
                    <tr>
                        <td class="small fw-semibold"><?= e($p['payment_no']) ?></td>
                        <td class="small text-muted"><?= fmt_date($p['paid_at']) ?></td>
                        <td class="small"><?= e($p['shop_name']) ?></td>
                        <td class="small text-muted"><?= e($p['shipment_no'] ?? '—') ?></td>
                        <td class="small">
                            <i class="bi <?= payment_method_icon($p['payment_method']) ?>"></i>
                            <?= e(payment_method_label($p['payment_method'])) ?>
                        </td>
                        <td class="text-end small fw-semibold text-success"><?= money($p['amount']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/includes/transport_footer.php'; ?>