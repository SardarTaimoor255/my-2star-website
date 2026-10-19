<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_SHOP);

$user = current_user($pdo);
$shopStmt = $pdo->prepare("SELECT id FROM shops WHERE user_id = ? LIMIT 1");
$shopStmt->execute([$user['id']]);
$shopId = (int)$shopStmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT i.*, o.order_number, o.paid_amount
     FROM invoices i
     JOIN orders o ON o.id = i.order_id
     WHERE i.shop_id = ?
     ORDER BY i.id DESC LIMIT 200"
);
$stmt->execute([$shopId]);
$invoices = $stmt->fetchAll();

$pageTitle = 'Invoices';
require_once dirname(__DIR__) . '/includes/shop_header.php';
?>

<h2 class="fw-bold mb-1">Invoices</h2>
<p class="text-muted small mb-4">Your order invoices</p>

<?php if (empty($invoices)): ?>
    <div class="card-soft p-5 text-center text-muted">
        <i class="bi bi-file-earmark-text fs-1 d-block mb-2 opacity-25"></i>
        No invoices yet.
    </div>
<?php else: ?>
    <div class="card-soft">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Invoice #</th>
                        <th>Order #</th>
                        <th>Issued</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Paid</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td class="small fw-semibold"><code><?= e($inv['invoice_no']) ?></code></td>
                        <td class="small text-muted"><?= e($inv['order_number']) ?></td>
                        <td class="small text-muted"><?= fmt_date($inv['issued_at']) ?></td>
                        <td class="text-end small fw-semibold"><?= money($inv['total']) ?></td>
                        <td class="text-end small text-success"><?= money($inv['paid_amount']) ?></td>
                        <td>
                            <?php
                            $payColors = ['pending'=>'danger','partially_paid'=>'warning','paid'=>'success','failed'=>'danger','refunded'=>'secondary'];
                            $pc = $payColors[$inv['payment_status']] ?? 'secondary';
                            ?>
                            <span class="badge text-bg-<?= $pc ?>"><?= e(PAYMENT_STATUSES[$inv['payment_status']]) ?></span>
                        </td>
                        <td class="text-end">
                            <a href="<?= BASE_URL ?>invoices/view.php?order_id=<?= (int)$inv['order_id'] ?>"
                               target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-file-earmark-text"></i> View
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