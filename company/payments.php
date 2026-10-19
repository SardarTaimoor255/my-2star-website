<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/payment_helper.php';
require_role($pdo, ROLE_COMPANY);

$user = current_user($pdo);
$coStmt = $pdo->prepare("SELECT id FROM companies WHERE user_id = ? LIMIT 1");
$coStmt->execute([$user['id']]);
$companyId = (int)$coStmt->fetchColumn();

// History
$stmt = $pdo->prepare(
    "SELECT p.*, o.order_number, v.business_name AS vendor_name
     FROM payments p
     JOIN orders o ON o.id = p.order_id
     LEFT JOIN vendors v ON v.user_id = p.submitted_by
     WHERE p.recipient_type = 'company' 
       AND p.company_id = ?
       AND p.status = 'paid'
     ORDER BY p.id DESC LIMIT 100"
);
$stmt->execute([$companyId]);
$history = $stmt->fetchAll();

// Summary
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(amount), 0) FROM payments 
     WHERE recipient_type='company' AND company_id = ? AND status='paid'"
);
$stmt->execute([$companyId]);
$totalReceived = (float)$stmt->fetchColumn();

// Total due from all vendor orders
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(company_base_price * quantity), 0)
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE oi.company_id = ? AND o.status NOT IN ('cancelled','returned')"
);
$stmt->execute([$companyId]);
$totalDue = (float)$stmt->fetchColumn();

$outstanding = max(0, $totalDue - $totalReceived);

$pageTitle = 'Payments Received';
require_once dirname(__DIR__) . '/includes/company_header.php';
?>

<h2 class="fw-bold mb-1">Payments Received</h2>
<p class="text-muted small mb-4">Vendor payments for supplied products</p>

<div class="row g-3 mb-4">
    <div class="col-6">
        <div class="stat-card">
            <div class="label">Total Received</div>
            <div class="value text-success" style="font-size:1.3rem;"><?= money($totalReceived) ?></div>
        </div>
    </div>
    <div class="col-6">
        <div class="stat-card">
            <div class="label">Outstanding</div>
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
                        <th>Payment #</th><th>Date</th><th>Order</th>
                        <th>Vendor</th><th>Method</th><th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($history as $p): ?>
                    <tr>
                        <td class="small fw-semibold"><?= e($p['payment_no']) ?></td>
                        <td class="small text-muted"><?= fmt_date($p['paid_at']) ?></td>
                        <td class="small"><?= e($p['order_number']) ?></td>
                        <td class="small"><?= e($p['vendor_name'] ?? '—') ?></td>
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

<?php require_once dirname(__DIR__) . '/includes/company_footer.php'; ?>