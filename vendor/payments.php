<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/payment_helper.php';
require_role($pdo, ROLE_VENDOR);

$user = current_user($pdo);

// Filters
$methodFilter = $_GET['method'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$sql = "SELECT p.*, o.order_number, s.shop_name, u.full_name AS recorded_by_name
        FROM payments p
        JOIN orders o ON o.id = p.order_id
        JOIN shops s ON s.id = p.shop_id
        LEFT JOIN users u ON u.id = p.created_by
        WHERE p.recipient_type = 'vendor'";
$params = [];
if ($methodFilter !== '') { $sql .= " AND p.payment_method = ?"; $params[] = $methodFilter; }
if ($statusFilter !== '') { $sql .= " AND p.status = ?"; $params[] = $statusFilter; }
$sql .= " ORDER BY p.id DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Summary
$sumStmt = $pdo->query(
    "SELECT payment_method, SUM(amount) AS total, COUNT(*) AS cnt
     FROM payments WHERE status='paid' AND recipient_type='vendor' GROUP BY payment_method"
);
$byMethod = $sumStmt->fetchAll();
$grandTotal = 0;
foreach ($byMethod as $m) { $grandTotal += (float)$m['total']; }

$pageTitle = 'Payments Received';
require_once dirname(__DIR__) . '/includes/vendor_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1">Payments Received</h2>
        <p class="text-muted small mb-0">All payments recorded from shops</p>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="stat-card">
            <div class="label">Total Received</div>
            <div class="value text-success" style="font-size:1.3rem;"><?= money($grandTotal) ?></div>
        </div>
    </div>
    <?php foreach ($byMethod as $m): ?>
        <div class="col-6 col-md-2">
            <div class="stat-card">
                <div class="label"><?= e(payment_method_label($m['payment_method'])) ?></div>
                <div class="value" style="font-size:1rem;"><?= money($m['total']) ?></div>
                <div class="text-muted" style="font-size:.72rem;"><?= (int)$m['cnt'] ?> payment(s)</div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Filter -->
<div class="card-soft p-2 mb-3">
    <div class="d-flex flex-wrap gap-1 align-items-center">
        <span class="small text-muted ms-2">Method:</span>
        <a href="?" class="btn btn-sm <?= $methodFilter===''?'btn-primary':'btn-outline-secondary' ?>">All</a>
        <?php foreach (PAYMENT_METHODS as $k => $v): ?>
            <a href="?method=<?= e($k) ?>" class="btn btn-sm <?= $methodFilter===$k?'btn-primary':'btn-outline-secondary' ?>"><?= e($v) ?></a>
        <?php endforeach; ?>
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
                        <th>Shop</th>
                        <th>Method</th>
                        <th class="text-end">Amount</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td class="small fw-semibold"><?= e($p['payment_no']) ?></td>
                        <td class="small"><a href="<?= BASE_URL ?>vendor/order-details.php?id=<?= (int)$p['order_id'] ?>" class="text-decoration-none"><?= e($p['order_number']) ?></a></td>
                        <td class="small"><?= e($p['shop_name']) ?></td>
                        <td class="small">
                            <i class="bi <?= e(payment_method_icon($p['payment_method'])) ?>"></i>
                            <?= e(payment_method_label($p['payment_method'])) ?>
                        </td>
                        <td class="text-end small fw-semibold"><?= money($p['amount']) ?></td>
                        <td class="small text-muted"><?= fmt_datetime($p['paid_at']) ?></td>
                        <td>
                            <?php $c = payment_status_color($p['status']); ?>
                            <span class="badge text-bg-<?= $c ?>"><?= e(PAYMENT_STATUSES[$p['status']] ?? $p['status']) ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/vendor_footer.php'; ?>