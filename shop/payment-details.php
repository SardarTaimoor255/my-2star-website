<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/payment_helper.php';
require_role($pdo, ROLE_SHOP);

$user = current_user($pdo);
$shopStmt = $pdo->prepare("SELECT id, shop_name, address FROM shops WHERE user_id = ? LIMIT 1");
$shopStmt->execute([$user['id']]);
$shop = $shopStmt->fetch();
$shopId = (int)$shop['id'];

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT p.*, o.order_number, o.total_amount, o.paid_amount, o.payment_status
     FROM payments p
     JOIN orders o ON o.id = p.order_id
     WHERE p.id = ? AND p.shop_id = ? LIMIT 1"
);
$stmt->execute([$id, $shopId]);
$payment = $stmt->fetch();

if (!$payment) { flash_error('Payment not found.'); redirect(BASE_URL . 'shop/payments.php'); }

$pageTitle = 'Payment ' . $payment['payment_no'];
require_once dirname(__DIR__) . '/includes/shop_header.php';
?>

<div class="mb-3 d-flex justify-content-between align-items-center">
    <a href="<?= BASE_URL ?>shop/payments.php" class="text-decoration-none small">
        <i class="bi bi-arrow-left"></i> Back to Payments
    </a>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-printer"></i> Print
    </button>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card-soft p-4" id="receiptArea">
            <!-- Header -->
            <div class="text-center border-bottom pb-3 mb-3">
                <h4 class="fw-bold mb-1"><?= e(SITE_NAME) ?></h4>
                <div class="text-muted small"><?= e(SITE_EMAIL) ?> · <?= e(SITE_PHONE) ?></div>
                <div class="badge text-bg-success mt-2">PAYMENT RECEIPT</div>
            </div>

            <div class="row mb-4">
                <div class="col-6">
                    <div class="small text-muted">Receipt #</div>
                    <div class="fw-bold"><?= e($payment['payment_no']) ?></div>
                </div>
                <div class="col-6 text-end">
                    <div class="small text-muted">Date</div>
                    <div class="fw-bold"><?= fmt_datetime($payment['paid_at']) ?></div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-6">
                    <div class="small text-muted">Shop</div>
                    <div class="fw-semibold"><?= e($shop['shop_name']) ?></div>
                    <div class="small text-muted"><?= e($shop['address'] ?? '') ?></div>
                </div>
                <div class="col-6 text-end">
                    <div class="small text-muted">Order #</div>
                    <div class="fw-semibold"><?= e($payment['order_number']) ?></div>
                </div>
            </div>

            <table class="table table-bordered mb-4">
                <tbody>
                    <tr>
                        <td>Payment Method</td>
                        <td class="text-end fw-semibold">
                            <i class="bi <?= e(payment_method_icon($payment['payment_method'])) ?>"></i>
                            <?= e(payment_method_label($payment['payment_method'])) ?>
                        </td>
                    </tr>
                    <?php if ($payment['reference_no']): ?>
                        <tr>
                            <td>Reference #</td>
                            <td class="text-end"><?= e($payment['reference_no']) ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr class="table-light">
                        <td class="fw-bold">Amount Paid</td>
                        <td class="text-end fw-bold text-success fs-5"><?= money($payment['amount']) ?></td>
                    </tr>
                </tbody>
            </table>

            <div class="border-top pt-3">
                <div class="row small">
                    <div class="col-4">
                        <div class="text-muted">Order Total</div>
                        <div class="fw-semibold"><?= money($payment['total_amount']) ?></div>
                    </div>
                    <div class="col-4">
                        <div class="text-muted">Total Paid</div>
                        <div class="fw-semibold text-success"><?= money($payment['paid_amount']) ?></div>
                    </div>
                    <div class="col-4">
                        <div class="text-muted">Remaining</div>
                        <div class="fw-semibold text-danger">
                            <?= money((float)$payment['total_amount'] - (float)$payment['paid_amount']) ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($payment['notes']): ?>
                <div class="mt-3 small text-muted fst-italic">
                    Note: <?= e($payment['notes']) ?>
                </div>
            <?php endif; ?>

            <div class="text-center text-muted small mt-4 pt-3 border-top">
                Thank you for your business!
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .shop-sidebar, .shop-topbar, .shop-content > .mb-3, footer, .btn { display: none !important; }
    .shop-main { margin-left: 0 !important; }
    .card-soft { box-shadow: none; border: 1px solid #ddd; }
    body { background: #fff !important; }
}
</style>

<?php require_once dirname(__DIR__) . '/includes/shop_footer.php'; ?>