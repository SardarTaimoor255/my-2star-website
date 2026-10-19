<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/payment_helper.php';
require_role($pdo, ROLE_VENDOR);

$user = current_user($pdo);
$orderId    = (int)($_GET['order_id'] ?? 0);
$companyId  = (int)($_GET['company_id'] ?? 0);

// Verify order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
$stmt->execute([$orderId]);
$order = $stmt->fetch();
if (!$order) { flash_error('Order not found.'); redirect(BASE_URL . 'vendor/orders.php'); }

// Company info
$stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ? LIMIT 1");
$stmt->execute([$companyId]);
$company = $stmt->fetch();
if (!$company) { flash_error('Company not found.'); redirect(BASE_URL . 'vendor/orders.php'); }

// Calculate amount due to this company for this order
$stmt = $pdo->prepare(
    "SELECT SUM(company_base_price * quantity) AS due, SUM(quantity) AS qty
     FROM order_items WHERE order_id = ? AND company_id = ?"
);
$stmt->execute([$orderId, $companyId]);
$itemData = $stmt->fetch();
$totalDue = (float)($itemData['due'] ?? 0);

if ($totalDue <= 0) {
    flash_error('No items from this company in this order.');
    redirect(BASE_URL . 'vendor/order-details.php?id=' . $orderId);
}

// Already paid (approved)
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(amount), 0) FROM payments 
     WHERE order_id = ? AND company_id = ? AND recipient_type = 'company' AND status = 'paid'"
);
$stmt->execute([$orderId, $companyId]);
$alreadyPaid = (float)$stmt->fetchColumn();

// Pending verification
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(amount), 0) FROM payments 
     WHERE order_id = ? AND company_id = ? AND recipient_type = 'company' AND status = 'pending_verification'"
);
$stmt->execute([$orderId, $companyId]);
$pending = (float)$stmt->fetchColumn();

$remaining = $totalDue - $alreadyPaid;
$available = $remaining - $pending;

if ($available <= 0.01) {
    flash_info('No balance available. ' . ($pending > 0 ? 'Payment awaiting company verification.' : ''));
    redirect(BASE_URL . 'vendor/order-details.php?id=' . $orderId);
}

// Previous submissions
$stmt = $pdo->prepare(
    "SELECT * FROM payments 
     WHERE order_id = ? AND company_id = ? AND recipient_type = 'company' 
     ORDER BY id DESC"
);
$stmt->execute([$orderId, $companyId]);
$payments = $stmt->fetchAll();

$pageTitle = 'Pay Company — ' . $company['company_name'];
require_once dirname(__DIR__) . '/includes/vendor_header.php';
?>

<div class="mb-3">
    <a href="<?= BASE_URL ?>vendor/order-details.php?id=<?= $orderId ?>" class="text-decoration-none small">
        <i class="bi bi-arrow-left"></i> Back to Order
    </a>
</div>

<h2 class="fw-bold mb-1">Pay Company</h2>
<p class="text-muted small mb-4">
    <strong><?= e($company['company_name']) ?></strong> · Order <strong><?= e($order['order_number']) ?></strong>
</p>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card-soft p-4">
            <div class="row text-center mb-4">
                <div class="col-4">
                    <div class="text-muted small">Total Due</div>
                    <div class="fw-bold"><?= money($totalDue) ?></div>
                </div>
                <div class="col-4">
                    <div class="text-muted small">Paid</div>
                    <div class="fw-bold text-success"><?= money($alreadyPaid) ?></div>
                </div>
                <div class="col-4">
                    <div class="text-muted small">Remaining</div>
                    <div class="fw-bold text-danger"><?= money($remaining) ?></div>
                </div>
            </div>

            <?php if ($pending > 0): ?>
                <div class="alert alert-warning small">
                    <i class="bi bi-hourglass-split"></i>
                    <strong><?= money($pending) ?></strong> awaiting company verification.
                </div>
            <?php endif; ?>

            <hr>

            <form method="post" action="<?= BASE_URL ?>api/company-payment.php" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="order_id" value="<?= $orderId ?>">
                <input type="hidden" name="company_id" value="<?= $companyId ?>">

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
                           max="<?= number_format($available, 2, '.', '') ?>" required
                           value="<?= number_format($available, 2, '.', '') ?>">
                    <div class="form-text">Maximum: <?= money($available) ?></div>
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
                    <label class="form-label small">Screenshot / Receipt (optional)</label>
                    <input type="file" name="proof_image" class="form-control" accept="image/*">
                </div>

                <div class="mb-3">
                    <label class="form-label small">Notes (optional)</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>

                <div class="alert alert-info small mb-3">
                    <i class="bi bi-info-circle"></i>
                    Company verify karegi. Aap ko notification milegi.
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-send"></i> Submit Payment to Company
                </button>
            </form>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card-soft p-3">
            <h6 class="fw-bold mb-3">Previous Payments</h6>
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
                            <?php if ($p['status'] === 'failed' && $p['rejection_reason']): ?>
                                <div class="alert alert-danger small mt-2 mb-0 py-1">
                                    <strong>Rejected:</strong> <?= e($p['rejection_reason']) ?>
                                </div>
                            <?php endif; ?>
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
<?php require_once dirname(__DIR__) . '/includes/vendor_footer.php'; ?>