<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/payment_helper.php';
require_role($pdo, ROLE_SHOP);

$user = current_user($pdo);
$shopStmt = $pdo->prepare("SELECT id FROM shops WHERE user_id = ? LIMIT 1");
$shopStmt->execute([$user['id']]);
$shopId = (int)$shopStmt->fetchColumn();

$shipmentId = (int)($_GET['shipment_id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT s.*, o.order_number, o.id AS order_id, 
            u.full_name AS transport_name, u.phone AS transport_phone,
            tc.company_name AS transport_company
     FROM shipments s
     JOIN orders o ON o.id = s.order_id
     LEFT JOIN users u ON u.id = s.transport_user_id
     LEFT JOIN transport_companies tc ON tc.user_id = u.id
     WHERE s.id = ? AND o.shop_id = ? LIMIT 1"
);
$stmt->execute([$shipmentId, $shopId]);
$shipment = $stmt->fetch();

if (!$shipment) { flash_error('Shipment not found.'); redirect(BASE_URL . 'shop/orders.php'); }

$remaining = (float)$shipment['transport_charges'] - (float)$shipment['paid_amount'];
if ($remaining <= 0.01) {
    flash_info('Delivery charges already paid.');
    redirect(BASE_URL . 'shop/order-details.php?id=' . $shipment['order_id']);
}

$pageTitle = 'Pay Transport Charges';
require_once dirname(__DIR__) . '/includes/shop_header.php';
?>

<div class="mb-3">
    <a href="<?= BASE_URL ?>shop/order-details.php?id=<?= (int)$shipment['order_id'] ?>" class="text-decoration-none small">
        <i class="bi bi-arrow-left"></i> Back to Order
    </a>
</div>

<h2 class="fw-bold mb-1">Pay Transport Charges</h2>
<p class="text-muted small mb-4">
    Order <strong><?= e($shipment['order_number']) ?></strong> · 
    Shipment <strong><?= e($shipment['shipment_no']) ?></strong>
</p>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card-soft p-4">
            <div class="alert alert-light border small mb-4">
                <strong>Transport:</strong> <?= e($shipment['transport_company'] ?? $shipment['transport_name'] ?? 'N/A') ?><br>
                <?php if ($shipment['vehicle_no']): ?><strong>Vehicle:</strong> <?= e($shipment['vehicle_no']) ?><br><?php endif; ?>
                <?php if ($shipment['transport_phone']): ?><strong>Contact:</strong> <?= e($shipment['transport_phone']) ?><?php endif; ?>
            </div>

            <div class="row text-center mb-4">
                <div class="col-4">
                    <div class="text-muted small">Charges</div>
                    <div class="fw-bold"><?= money($shipment['transport_charges']) ?></div>
                </div>
                <div class="col-4">
                    <div class="text-muted small">Paid</div>
                    <div class="fw-bold text-success"><?= money($shipment['paid_amount']) ?></div>
                </div>
                <div class="col-4">
                    <div class="text-muted small">Remaining</div>
                    <div class="fw-bold text-danger"><?= money($remaining) ?></div>
                </div>
            </div>

            <hr>

            <form method="post" action="<?= BASE_URL ?>api/transport-payment.php" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="shipment_id" value="<?= $shipmentId ?>">

                <div class="mb-3">
                    <label class="form-label small">Payment Method <span class="text-danger">*</span></label>
                   <select name="payment_method" class="form-select" required>
    <option value="easypaisa">Easypaisa</option>
    <option value="jazzcash">JazzCash</option>
    <option value="sadapay">SadaPay</option>
    <option value="nayapay">NayaPay</option>
    <option value="raast">Raast</option>
    <option value="bank_transfer">Bank Transfer</option>
    <option value="cheque">Cheque</option>
    <option value="cash">Cash</option>
    <option value="other">Other</option>
</select>
                </div>

                <div class="mb-3">
                    <label class="form-label small">Amount (PKR) <span class="text-danger">*</span></label>
                    <input type="number" name="amount" class="form-control" step="0.01" min="0.01"
                           max="<?= number_format($remaining, 2, '.', '') ?>" required
                           value="<?= number_format($remaining, 2, '.', '') ?>">
                </div>

                <div class="mb-3">
    <label class="form-label small">Transaction ID / Reference # <span class="text-danger">*</span></label>
    <input type="text" 
           name="reference_no" 
           id="reference_no" 
           class="form-control" 
           required
           minlength="6"
           maxlength="50"
           pattern="^(?=.*\d{4,})[A-Za-z0-9_-]+$"
           title="6-50 characters. Sirf letters, numbers, dash (-), underscore (_). Kam se kam 4 digits hone chahiye."
           placeholder="e.g. EP-987654321">
    <div class="form-text" id="refHint" style="font-size:.72rem;">
        <i class="bi bi-info-circle"></i>
        Kam se kam 6 characters aur 4 digits hone chahiye.
    </div>
</div>

                <div class="mb-3">
                    <label class="form-label small">Screenshot (optional)</label>
                    <input type="file" name="proof_image" class="form-control" accept="image/*">
                </div>

                <div class="mb-3">
                    <label class="form-label small">Notes (optional)</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>

                <div class="alert alert-info small mb-3">
                    <i class="bi bi-info-circle"></i>
                    Transport verify karegi. Aap ko notification milegi.
                </div>

                <button type="submit" class="btn btn-warning w-100">
                    <i class="bi bi-send"></i> Submit Payment
                </button>
            </form>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card-soft p-3">
            <h6 class="fw-bold mb-3">Previous Payments</h6>
            <?php
            $stmtP = $pdo->prepare(
                "SELECT * FROM payments WHERE shipment_id = ? AND recipient_type = 'transport' ORDER BY id DESC"
            );
            $stmtP->execute([$shipmentId]);
            $payments = $stmtP->fetchAll();
            ?>
            <?php if (empty($payments)): ?>
                <p class="text-muted small mb-0">No payments yet.</p>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($payments as $p): ?>
                        <li class="list-group-item px-0">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <div class="fw-semibold small"><?= money($p['amount']) ?></div>
                                <span class="badge text-bg-<?= payment_status_color($p['status']) ?>">
                                    <?= e(payment_status_label($p['status'])) ?>
                                </span>
                            </div>
                            <div class="text-muted" style="font-size:.72rem;">
                                <i class="bi <?= payment_method_icon($p['payment_method']) ?>"></i>
                                <?= e(payment_method_label($p['payment_method'])) ?>
                                <?php if ($p['reference_no']): ?> · <?= e($p['reference_no']) ?><?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
(function() {
    const methodSelect = document.querySelector('select[name="payment_method"]');
    const refInput = document.getElementById('reference_no');
    const refHint = document.getElementById('refHint');
    
    if (!methodSelect || !refInput) return;
    
    function updateRefRules() {
    const method = methodSelect.value;
    let placeholder = 'e.g. ';
    let hint = '';
    let pattern = '';
    let title = '';

    switch (method) {
        case 'easypaisa':
            placeholder = 'e.g. 41210468367 ya EP-41210468367';
            pattern = '^(EP-?|TID-?)?[0-9]{8,25}$';
            title = 'Easypaisa TID: 8-25 digits (EP- prefix optional)';
            hint = 'Easypaisa TID 8-25 digits ka hota hai (TXN receipt par "ID#" ke baad)';
            break;
        case 'jazzcash':
            placeholder = 'e.g. 41210468367 ya JC-41210468367';
            pattern = '^(JC-?|TID-?)?[0-9]{8,25}$';
            title = 'JazzCash TID: 8-25 digits';
            hint = 'JazzCash TID 8-25 digits (JC- prefix optional)';
            break;
        case 'sadapay':
            placeholder = 'e.g. Raast-467914 ya SDP-123456';
            pattern = '^(SDP-?|Raast-?)?[0-9A-Za-z]{6,25}$';
            title = 'SadaPay: Raast-XXXXXX ya SDP-XXXXXX format';
            hint = 'SadaPay reference (Raast-467914 ya SDP-XXXXXX)';
            break;
        case 'nayapay':
            placeholder = 'e.g. NP-467914 ya NayaPay-123456';
            pattern = '^(NP-?|NayaPay-?)?[0-9A-Za-z]{6,25}$';
            title = 'NayaPay: NP-XXXXXX ya NayaPay-XXXXXX';
            hint = 'NayaPay reference (NP-467914 format)';
            break;
        case 'raast':
            placeholder = 'e.g. Raast-467914';
            pattern = '^Raast-?[0-9]{6,20}$';
            title = 'Raast ID: Raast-XXXXXX (numeric with prefix)';
            hint = 'Raast ID format: Raast-467914 (6-20 digits ke saath)';
            break;
        case 'bank_transfer':
            placeholder = 'e.g. TRF-2026-123456';
            pattern = '^(?=.*\\d{4,})[A-Za-z0-9_-]{6,50}$';
            title = 'Bank reference: 6-50 chars, kam se kam 4 digits';
            hint = 'Bank transfer reference (TRF-2026-123456)';
            break;
        case 'cheque':
            placeholder = 'e.g. CHQ-123456';
            pattern = '^[A-Za-z0-9-]{4,20}$';
            title = 'Cheque number (4-20 characters)';
            hint = 'Cheque number (CHQ-123456 format)';
            break;
        case 'cash':
            placeholder = 'e.g. CASH-2026-001';
            pattern = '^(?=.*\\d{3,})[A-Za-z0-9_-]{6,50}$';
            title = 'Cash reference: 6-50 chars, kam se kam 3 digits';
            hint = 'Cash reference (CASH-2026-001)';
            break;
        default:
            placeholder = 'e.g. REF-2026-001';
            pattern = '^(?=.*\\d{3,})[A-Za-z0-9_-]{6,50}$';
            title = '6-50 chars, kam se kam 3 digits';
            hint = 'Kam se kam 6 characters aur 3 digits';
    }

    refInput.placeholder = placeholder;
    refInput.setAttribute('pattern', pattern);
    refInput.title = title;
    refHint.innerHTML = '<i class="bi bi-info-circle"></i> ' + hint;
}
    
    methodSelect.addEventListener('change', updateRefRules);
    updateRefRules();
    
    refInput.addEventListener('input', function() {
        if (this.value.length === 0) {
            this.setCustomValidity('');
            return;
        }
        if (this.validity.patternMismatch) {
            this.setCustomValidity('Reference ID galat format mein hai.');
        } else {
            this.setCustomValidity('');
        }
    });
})();
</script>
<?php require_once dirname(__DIR__) . '/includes/shop_footer.php'; ?>