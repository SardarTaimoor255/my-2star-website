<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_VENDOR);
require_once dirname(__DIR__) . '/includes/payment_helper.php';
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT o.*, s.shop_name, s.address AS shop_address FROM orders o JOIN shops s ON s.id = o.shop_id WHERE o.id = ? LIMIT 1");
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) { flash_error('Order not found.'); redirect(BASE_URL . 'vendor/orders.php'); }

$stmt = $pdo->prepare(
    "SELECT oi.*, p.name AS product_name, p.sku, co.company_name
     FROM order_items oi
     JOIN products p ON p.id = oi.product_id
     LEFT JOIN companies co ON co.id = oi.company_id
     WHERE oi.order_id = ?"
);
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM order_status_history WHERE order_id = ? ORDER BY id ASC");
$stmt->execute([$id]);
$history = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT s.*, u.full_name AS driver_name, u.phone AS driver_phone FROM shipments s LEFT JOIN users u ON u.id = s.transport_user_id WHERE s.order_id = ? ORDER BY s.id DESC LIMIT 1");
$stmt->execute([$id]);
$shipment = $stmt->fetch();

$transportUsers = $pdo->query(
    "SELECT u.id, u.full_name, tc.company_name
     FROM users u
     LEFT JOIN transport_companies tc ON tc.user_id = u.id
     WHERE u.role_id = (SELECT id FROM roles WHERE name='transport') AND u.status='active'"
)->fetchAll();

$pageTitle = 'Order ' . $order['order_number'];
require_once dirname(__DIR__) . '/includes/vendor_header.php';
?>

<div class="mb-3">
    <a href="<?= BASE_URL ?>vendor/orders.php" class="text-decoration-none small"><i class="bi bi-arrow-left"></i> Back to Orders</a>
</div>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1"><?= e($order['order_number']) ?></h2>
        <div class="text-muted small">From <strong><?= e($order['shop_name']) ?></strong> · <?= fmt_datetime($order['created_at']) ?></div>
    </div>
    <?php if (!empty($order['delivery_address'])): ?>
    <div class="alert alert-info small mt-2 mb-0 py-2">
        <i class="bi bi-geo-alt-fill"></i>
        <strong>Delivery Address:</strong> <?= nl2br(e($order['delivery_address'])) ?>
    </div>
<?php endif; ?>
        <div class="d-flex gap-2 align-items-center">
        <?= status_badge($order['status']) ?>
        <a href="<?= BASE_URL ?>vendor/track-order.php?id=<?= (int)$order['id'] ?>" class="btn btn-sm btn-primary">
            <i class="bi bi-geo-alt"></i> Track Order
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <!-- Items -->
        <div class="card-soft mb-3">
            <div class="p-3 border-bottom"><strong>Order Items</strong></div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>Product</th><th>Company</th><th class="text-end">Qty</th><th class="text-end">Company Price</th><th class="text-end">Commission</th><th class="text-end">Final</th><th class="text-end">Total</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td class="small">
                                <div class="fw-semibold"><?= e($it['product_name']) ?></div>
                                <div class="text-muted" style="font-size:.72rem;">SKU: <?= e($it['sku']) ?></div>
                            </td>
                            <td class="small text-muted"><?= e($it['company_name'] ?? '—') ?></td>
                            <td class="text-end small"><?= (int)$it['quantity'] ?></td>
                            <td class="text-end small"><?= money($it['company_base_price']) ?></td>
                            <td class="text-end small text-success"><?= money($it['vendor_commission']) ?></td>
                            <td class="text-end small"><?= money($it['final_price']) ?></td>
                            <td class="text-end small fw-semibold"><?= money($it['line_total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr><td colspan="5" class="text-end fw-bold">Commission Total</td><td colspan="2" class="text-end fw-bold text-success"><?= money($order['commission_total']) ?></td></tr>
                        <tr><td colspan="5" class="text-end fw-bold">Order Total</td><td colspan="2" class="text-end fw-bold text-primary"><?= money($order['total_amount']) ?></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Actions -->
        <?php if ($order['status'] === 'order_created'): ?>
            <div class="card-soft p-3 mb-3">
                <h6 class="fw-bold mb-3">Actions</h6>
                <div class="d-flex gap-2">
                    <button class="btn btn-success btn-sm" onclick="doVendorAction(this,'accept_order',<?= (int)$order['id'] ?>,{},'Accept this order?')">
                        <i class="bi bi-check-circle"></i> Accept Order
                    </button>
                    <button class="btn btn-outline-danger btn-sm" onclick="doVendorAction(this,'reject_order',<?= (int)$order['id'] ?>,{},'Reject this order?')">
                        <i class="bi bi-x-circle"></i> Reject
                    </button>
                </div>
            </div>
        <?php endif; ?>
        <!-- Send to Company -->
        <?php if (in_array($order['status'], ['order_created','order_accepted'], true)): 
            // Get companies in this order
            $stmtCo = $pdo->prepare(
                "SELECT DISTINCT c.id, c.company_name,
                    (SELECT COUNT(*) FROM order_items WHERE order_id = ? AND company_id = c.id) AS item_count,
                    (SELECT SUM(company_base_price * quantity) FROM order_items WHERE order_id = ? AND company_id = c.id) AS total_value
                 FROM companies c
                 WHERE c.id IN (SELECT DISTINCT company_id FROM order_items WHERE order_id = ? AND company_id IS NOT NULL)"
            );
            $stmtCo->execute([$order['id'], $order['id'], $order['id']]);
            $companiesInOrder = $stmtCo->fetchAll();
        ?>
            <?php if (!empty($companiesInOrder)): ?>
                <div class="card-soft p-3 mb-3" style="border-left: 4px solid #3b82f6;">
                    <h6 class="fw-bold mb-3"><i class="bi bi-building text-primary"></i> Send to Company</h6>
                    <p class="small text-muted mb-3">
                        Order ko relevant company ko bhejein. Company confirm karegi aur products prepare karegi.
                    </p>
                    <?php foreach ($companiesInOrder as $co): ?>
                        <div class="border rounded p-3 mb-2">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <div class="fw-semibold small"><?= e($co['company_name']) ?></div>
                                    <div class="text-muted" style="font-size:.72rem;">
                                        <?= (int)$co['item_count'] ?> item(s) · Rs. <?= number_format($co['total_value'], 2) ?>
                                    </div>
                                </div>
                                <form method="post" action="<?= BASE_URL ?>api/vendor-action.php" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="send_to_company_order">
                                    <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
                                    <input type="hidden" name="company_id" value="<?= (int)$co['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-primary"
                                            onclick="return confirm('Send this order to <?= e($co['company_name']) ?>?')">
                                        <i class="bi bi-send"></i> Send to Company
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
       
               <!-- Assign Transport / Reassign -->
<?php 
$canAssign = false;
$isReassign = false;

if (!$shipment && !in_array($order['status'], ['delivered','cancelled','returned'], true)) {
    $canAssign = true;
} elseif ($shipment && in_array($shipment['status'], ['delivery_failed','returned'], true)) {
    $canAssign = true;
    $isReassign = true;
}
?>
<?php if ($canAssign): ?>
    <div class="card-soft p-3 mb-3" style="<?= $isReassign ? 'border-left: 4px solid #f5a623;' : '' ?>">
        <h6 class="fw-bold mb-2">
            <i class="bi bi-truck"></i> 
            <?= $isReassign ? 'Reassign Transport' : 'Assign Transport' ?>
        </h6>
        
        <?php if ($isReassign): ?>
            <div class="alert alert-warning small mb-3 py-2">
                <i class="bi bi-exclamation-triangle"></i>
                <strong>Previous delivery failed.</strong> Nayi transport company assign karein.
            </div>
        <?php else: ?>
            <p class="small text-muted mb-3">Transport company select karein — wo apni vehicle aur bilty details set karegi.</p>
        <?php endif; ?>
        
        <form method="post" action="<?= BASE_URL ?>api/vendor-action.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="assign_transport">
            <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
            <div class="row g-2">
                <div class="col-md-9">
                    <label class="form-label small">Transport Company</label>
                    <select name="transport_user_id" class="form-select form-select-sm" required>
                        <option value="">— Select Transport Company —</option>
                        <?php foreach ($transportUsers as $t): ?>
                            <option value="<?= (int)$t['id'] ?>">
                                <?= e($t['company_name'] ?? $t['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn <?= $isReassign ? 'btn-warning' : 'btn-primary' ?> btn-sm w-100">
                        <i class="bi bi-truck"></i> 
                        <?= $isReassign ? 'Reassign' : 'Assign' ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
<?php endif; ?>

                <!-- Company Payments Section -->
        <?php
        // Get companies that supplied items in this order
        $stmtCo = $pdo->prepare(
            "SELECT DISTINCT c.id, c.company_name,
                (SELECT SUM(company_base_price * quantity) FROM order_items WHERE order_id = ? AND company_id = c.id) AS total_due
             FROM companies c
             WHERE c.id IN (SELECT DISTINCT company_id FROM order_items WHERE order_id = ? AND company_id IS NOT NULL)"
        );
        $stmtCo->execute([$order['id'], $order['id']]);
        $companiesInOrder = $stmtCo->fetchAll();
        ?>
        <?php if (!empty($companiesInOrder)): ?>
            <div class="card-soft p-3 mb-3">
                <h6 class="fw-bold mb-3"><i class="bi bi-building"></i> Pay Companies</h6>
                <?php foreach ($companiesInOrder as $co):
                    // Already paid
                    $stmtP = $pdo->prepare(
                        "SELECT COALESCE(SUM(amount),0) FROM payments 
                         WHERE order_id=? AND company_id=? AND recipient_type='company' AND status='paid'"
                    );
                    $stmtP->execute([$order['id'], $co['id']]);
                    $paid = (float)$stmtP->fetchColumn();

                    // Pending
                    $stmtPending = $pdo->prepare(
                        "SELECT COALESCE(SUM(amount),0) FROM payments 
                         WHERE order_id=? AND company_id=? AND recipient_type='company' AND status='pending_verification'"
                    );
                    $stmtPending->execute([$order['id'], $co['id']]);
                    $pend = (float)$stmtPending->fetchColumn();

                    $due = (float)$co['total_due'];
                    $remaining = $due - $paid;
                ?>
                    <div class="border rounded p-2 mb-2">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <div class="small fw-semibold"><?= e($co['company_name']) ?></div>
                            <?php if ($remaining <= 0.01): ?>
                                <span class="badge text-bg-success">Paid</span>
                            <?php elseif ($pend > 0): ?>
                                <span class="badge text-bg-warning">Awaiting Verify</span>
                            <?php else: ?>
                                <span class="badge text-bg-danger">Due</span>
                            <?php endif; ?>
                        </div>
                        <div class="small text-muted mb-2">
                            Due: <strong><?= money($due) ?></strong> · 
                            Paid: <strong class="text-success"><?= money($paid) ?></strong> · 
                            Remaining: <strong class="text-danger"><?= money($remaining) ?></strong>
                        </div>
                        <?php if ($remaining > 0.01 && $pend <= 0.01): ?>
                            <a href="<?= BASE_URL ?>vendor/pay-company.php?order_id=<?= (int)$order['id'] ?>&company_id=<?= (int)$co['id'] ?>"
                               class="btn btn-primary btn-sm w-100">
                                <i class="bi bi-send"></i> Pay Company
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

      
        <!-- Payment Section -->
        <div class="card-soft p-3 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-cash-stack"></i> Payments</h6>
                <?php if ($order['payment_status'] !== 'paid'): ?>
                    <a href="<?= BASE_URL ?>vendor/record-payment.php?order_id=<?= (int)$order['id'] ?>" class="btn btn-sm btn-success">
                        <i class="bi bi-plus-circle"></i> Record Payment
                    </a>
                <?php endif; ?>
            </div>

            <?php
            $stmtPay = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC");
            $stmtPay->execute([$order['id']]);
            $paymentsList = $stmtPay->fetchAll();
            ?>
            <?php if (empty($paymentsList)): ?>
                <p class="text-muted small mb-0">No payments recorded yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Payment #</th>
                                <th>Method</th>
                                <th class="text-end">Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($paymentsList as $p): ?>
                            <tr>
                                <td class="small"><?= e($p['payment_no']) ?></td>
                                <td class="small">
                                    <i class="bi <?= e(payment_method_icon($p['payment_method'])) ?>"></i>
                                    <?= e(payment_method_label($p['payment_method'])) ?>
                                </td>
                                <td class="text-end small fw-semibold text-success"><?= money($p['amount']) ?></td>
                                <td class="small text-muted"><?= fmt_date($p['paid_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <hr class="my-2">
            <div class="d-flex justify-content-between small">
                <span class="text-muted">Remaining</span>
                <strong class="text-danger">
                    <?= money($order['total_amount'] - $order['paid_amount']) ?>
                </strong>
            </div>
        </div>
        <!-- Timeline -->
        <div class="card-soft">
            <div class="p-3 border-bottom"><strong>Timeline</strong></div>
            <div class="p-3">
                <?php foreach ($history as $h): ?>
                    <div class="mb-3 ps-3 position-relative">
                        <span class="position-absolute bg-primary rounded-circle" style="width:8px;height:8px;left:0;top:6px;"></span>
                        <div class="small fw-semibold"><?= e(ORDER_STATUSES[$h['new_status']] ?? $h['new_status']) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            <?= fmt_datetime($h['created_at']) ?>
                            <?php if ($h['changed_by_role']): ?> · <?= e(ucfirst($h['changed_by_role'])) ?><?php endif; ?>
                        </div>
                        <?php if ($h['note']): ?><div class="small fst-italic text-muted">"<?= e($h['note']) ?>"</div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card-soft p-3 mb-3">
            <h6 class="fw-bold mb-3">Payment</h6>
            <div class="d-flex justify-content-between small mb-1"><span class="text-muted">Total</span><span><?= money($order['total_amount']) ?></span></div>
            <div class="d-flex justify-content-between small mb-1"><span class="text-muted">Paid</span><span class="text-success"><?= money($order['paid_amount']) ?></span></div>
            <hr class="my-2">
            <div class="d-flex justify-content-between"><strong>Remaining</strong><strong class="text-danger"><?= money($order['total_amount'] - $order['paid_amount']) ?></strong></div>
        </div>

        <?php if ($shipment): ?>
            <div class="card-soft p-3">
                <h6 class="fw-bold mb-3">Transport</h6>
                <div class="small mb-1"><strong><?= e($shipment['bilty_no'] ?? 'N/A') ?></strong></div>
                <div class="small text-muted mb-1"><i class="bi bi-truck"></i> <?= e($shipment['vehicle_no'] ?? '—') ?></div>
                <div class="small text-muted mb-1"><i class="bi bi-person"></i> <?= e($shipment['driver_name'] ?? '—') ?></div>
                <div class="mt-2">
                    <span class="badge text-bg-info"><?= e(SHIPMENT_STATUSES[$shipment['status']] ?? $shipment['status']) ?></span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/vendor_footer.php'; ?>