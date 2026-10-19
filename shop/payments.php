<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/payment_helper.php';
require_role($pdo, ROLE_SHOP);

$user = current_user($pdo);
$shopStmt = $pdo->prepare("SELECT id FROM shops WHERE user_id = ? LIMIT 1");
$shopStmt->execute([$user['id']]);
$shopId = (int)$shopStmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT p.*, o.order_number
     FROM payments p
     JOIN orders o ON o.id = p.order_id
     WHERE p.shop_id = ?
     ORDER BY p.id DESC LIMIT 200"
);
$stmt->execute([$shopId]);
$payments = $stmt->fetchAll();

// Summary
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(amount),0) FROM payments WHERE shop_id = ? AND status='paid'"
);
$stmt->execute([$shopId]);
$totalPaid = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(total_amount - paid_amount),0) FROM orders
     WHERE shop_id = ? AND payment_status IN ('pending','partially_paid')"
);
$stmt->execute([$shopId]);
$outstanding = (float)$stmt->fetchColumn();

$pageTitle = 'Payments';
require_once dirname(__DIR__) . '/includes/shop_header.php';
?>

<h2 class="fw-bold mb-1">Payments</h2>
<p class="text-muted small mb-4">Your payment history</p>

<div class="row g-3 mb-4">
    <div class="col-6">
        <div class="stat-card">
            <div class="label">Total Paid</div>
            <div class="value text-success" style="font-size:1.3rem;"><?= money($totalPaid) ?></div>
        </div>
    </div>
    <div class="col-6">
        <div class="stat-card">
            <div class="label">Outstanding</div>
            <div class="value text-danger" style="font-size:1.3rem;"><?= money($outstanding) ?></div>
        </div>
    </div>
</div>

<?php if (empty($payments)): ?>
    <div class="card-soft p-5 text-center text-muted">
        <i class="bi bi-cash-stack fs-1 d-block mb-2 opacity-25"></i>
        No payments recorded yet.
    </div>
<?php else: ?>
    <div class="card-soft">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Payment #</th>
                        <th>Order #</th>
                        <th>Method</th>
                        <th class="text-end">Amount</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td class="small fw-semibold"><?= e($p['payment_no']) ?></td>
                        <td class="small"><?= e($p['order_number']) ?></td>
                        <td class="small">
                            <i class="bi <?= e(payment_method_icon($p['payment_method'])) ?>"></i>
                            <?= e(payment_method_label($p['payment_method'])) ?>
                        </td>
                        <td class="text-end small fw-semibold text-success"><?= money($p['amount']) ?></td>
                        <td class="small text-muted"><?= fmt_datetime($p['paid_at']) ?></td>
                        <td class="text-end">
                            <a href="<?= BASE_URL ?>shop/payment-details.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-receipt"></i> Receipt
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/shop_footer.php'; ?>