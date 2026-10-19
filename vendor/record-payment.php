<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/payment_helper.php';
require_role($pdo, ROLE_VENDOR);

$user = current_user($pdo);
$orderId = (int)($_GET['order_id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT o.*, s.shop_name FROM orders o
     JOIN shops s ON s.id = o.shop_id
     WHERE o.id = ? LIMIT 1"
);
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) { flash_error('Order not found.'); redirect(BASE_URL . 'vendor/orders.php'); }

$remaining = (float)$order['total_amount'] - (float)$order['paid_amount'];

// Existing payments
$stmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC");
$stmt->execute([$orderId]);
$payments = $stmt->fetchAll();

$pageTitle = 'Record Payment — ' . $order['order_number'];
require_once dirname(__DIR__) . '/includes/vendor_header.php';
?>

<div class="mb-3">
    <a href="<?= BASE_URL ?>vendor/order-details.php?id=<?= $orderId ?>" class="text-decoration-none small">
        <i class="bi bi-arrow-left"></i> Back to Order
    </a>
</div>

<h2 class="fw-bold mb-1">Record Payment</h2>
<p class="text-muted small mb-4">
    Order <strong><?= e($order['order_number']) ?></strong> from <strong><?= e($order['shop_name']) ?></strong>
</p>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card-soft p-4">
            <!-- Order summary -->
            <div class="row text-center mb-4">
                <div class="col-4">
                    <div class="text-muted small">Total</div>
                    <div class="fw-bold"><?= money($order['total_amount']) ?></div>
                </div>
                <div class="col-4">
                    <div class="text-muted small">Paid</div>
                    <div class="fw-bold text-success"><?= money($order['paid_amount']) ?></div>
                </div>
                <div class="col-4">
                    <div class="text-muted small">Remaining</div>
                    <div class="fw-bold text-danger"><?= money($remaining) ?></div>
                </div>
            </div>

            <hr>

            <h6 class="fw-bold mb-3">New Payment</h6>
            <form method="post" action="<?= BASE_URL ?>api/payment-action.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="record">
                <input type="hidden" name="order_id" value="<?= $orderId ?>">

                <div class="mb-3">
                    <label class="form-label small">Amount (PKR) <span class="text-danger">*</span></label>
                    <input type="number" name="amount" class="form-control" step="0.01" min="0.01"
                           max="<?= $remaining > 0 ? $remaining : 999999 ?>" required
                           value="<?= $remaining > 0 ? number_format($remaining, 2, '.', '') : '' ?>">
                    <div class="form-text">Maximum: <?= money($remaining > 0 ? $remaining : 0) ?></div>
                </div>

                <div class="mb-3">
                    <label class="form-label small">Payment Method <span class="text-danger">*</span></label>
                    <select name="payment_method" class="form-select" required>
                        <?php foreach (PAYMENT_METHODS as $k => $v): ?>
                            <option value="<?= e($k) ?>"><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small">Reference # (optional)</label>
                    <input type="text" name="reference_no" class="form-control"
                           placeholder="e.g. TXN-123456, cheque #">
                </div>

                <div class="mb-3">
                    <label class="form-label small">Notes (optional)</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-cash-coin"></i> Record Payment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card-soft">
            <div class="p-3 border-bottom"><strong>Payment History</strong></div>
            <?php if (empty($payments)): ?>
                <div class="p-4 text-center text-muted small">No payments recorded yet.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Payment #</th>
                                <th>Method</th>
                                <th class="text-end">Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td class="small fw-semibold"><?= e($p['payment_no']) ?></td>
                                <td class="small">
                                    <i class="bi <?= e(payment_method_icon($p['payment_method'])) ?>"></i>
                                    <?= e(payment_method_label($p['payment_method'])) ?>
                                </td>
                                <td class="text-end small fw-semibold"><?= money($p['amount']) ?></td>
                                <td class="small text-muted"><?= fmt_datetime($p['paid_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/vendor_footer.php'; ?>