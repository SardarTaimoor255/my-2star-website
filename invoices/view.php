<?php
/**
 * invoices/view.php
 * Printable invoice — accessible by admin, vendor (owner), shop (owner)
 */
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/invoice_helper.php';

if (!is_logged_in($pdo)) {
    flash_error('Please log in.');
    redirect(BASE_URL . 'auth/login.php');
}

$user = current_user($pdo);
$orderId = (int)($_GET['order_id'] ?? 0);
if ($orderId <= 0) { flash_error('Invalid request.'); redirect(BASE_URL); }

// Fetch order
$stmt = $pdo->prepare(
    "SELECT o.*, s.shop_name, s.address AS shop_address, s.owner_name AS shop_owner,
            s.user_id AS shop_user_id
     FROM orders o JOIN shops s ON s.id = o.shop_id
     WHERE o.id = ? LIMIT 1"
);
$stmt->execute([$orderId]);
$order = $stmt->fetch();
if (!$order) { flash_error('Order not found.'); redirect(BASE_URL); }

// Authorization
$allowed = false;
switch ($user['role_name']) {
    case ROLE_ADMIN:
        $allowed = true;
        break;
    case ROLE_SHOP:
        $allowed = ((int)$order['shop_user_id'] === (int)$user['id']);
        break;
    case ROLE_VENDOR:
        // Vendor can see any order (there is one vendor)
        $allowed = true;
        break;
}

if (!$allowed) {
    http_response_code(403);
    exit('Access denied.');
}

// Get or create invoice
try {
    $invoice = get_or_create_invoice($pdo, $orderId);
} catch (Throwable $ex) {
    error_log('Invoice creation failed: ' . $ex->getMessage());
    exit('Could not generate invoice.');
}

$items = get_invoice_items($pdo, (int)$invoice['id']);

// Payment info for display
$stmtP = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC");
$stmtP->execute([$orderId]);
$payments = $stmtP->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice <?= e($invoice['invoice_no']) ?> — <?= e(SITE_SHORT) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
    body { background: #eef1f7; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
    .invoice-box { max-width: 860px; margin: 24px auto; background: #fff; padding: 40px;
        border-radius: 12px; box-shadow: 0 6px 24px rgba(15,23,42,.08); }
    .invoice-header { border-bottom: 3px solid #0b3d91; padding-bottom: 20px; margin-bottom: 24px; }
    .brand-name { font-size: 1.6rem; font-weight: 800; color: #0b3d91; letter-spacing: -.5px; }
    .brand-sub { color: #64748b; font-size: .85rem; }
    .invoice-title { font-size: 2rem; font-weight: 800; color: #0b3d91; letter-spacing: 2px; }
    .info-box { background: #f8fafc; border-radius: 8px; padding: 14px 16px; }
    .info-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .5px; color: #64748b; font-weight: 600; }
    .info-value { font-weight: 600; color: #0f172a; }
    .items-table th { background: #0b3d91 !important; color: #fff; font-size: .82rem;
        text-transform: uppercase; letter-spacing: .5px; padding: 12px; }
    .items-table td { padding: 12px; vertical-align: middle; }
    .total-row td { font-weight: 700; }
    .grand-total { font-size: 1.3rem; color: #0b3d91; font-weight: 800; }
    .status-badge { font-size: .75rem; padding: 5px 12px; }
    @media print {
        body { background: #fff !important; margin: 0; }
        .no-print { display: none !important; }
        .invoice-box { box-shadow: none; margin: 0; border-radius: 0; padding: 20px; max-width: 100%; }
    }
</style>
</head>
<body>

<!-- Print button bar -->
<div class="container no-print pt-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <a href="javascript:history.back()" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-sm btn-primary">
                <i class="bi bi-printer"></i> Print / Save as PDF
            </button>
        </div>
    </div>
</div>

<!-- Invoice -->
<div class="invoice-box" id="invoiceArea">

    <!-- HEADER -->
    <div class="invoice-header d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <div class="brand-name">
                <i class="bi bi-lightning-charge-fill text-warning"></i>
                <?= e(SITE_NAME) ?>
            </div>
            <div class="brand-sub mt-1">
                <?= e(SITE_EMAIL) ?><br>
                <?= e(SITE_PHONE) ?><br>
                Pakistan &amp; Azad Jammu &amp; Kashmir
            </div>
        </div>
        <div class="text-end">
            <div class="invoice-title">INVOICE</div>
            <div class="info-label mt-2">Invoice #</div>
            <div class="info-value"><?= e($invoice['invoice_no']) ?></div>
            <div class="info-label mt-1">Date</div>
            <div class="info-value"><?= fmt_datetime($invoice['issued_at']) ?></div>
        </div>
    </div>

    <!-- BILL FROM / TO -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="info-box h-100">
                <div class="info-label mb-1">Bill To (Shop)</div>
                <div class="info-value"><?= e($order['shop_name']) ?></div>
                <div class="small text-muted mt-1">
                    <?= e($order['shop_owner']) ?><br>
                    <?= nl2br(e($order['shop_address'])) ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="info-box h-100">
                <div class="info-label mb-1">Order Reference</div>
                <div class="info-value"><?= e($order['order_number']) ?></div>
                <div class="info-label mt-2 mb-1">Order Date</div>
                <div class="info-value"><?= fmt_datetime($order['created_at']) ?></div>
            </div>
        </div>
    </div>

    <!-- ITEMS TABLE -->
    <table class="table items-table mb-4">
        <thead>
            <tr>
                <th style="width:50px;">#</th>
                <th>Product Description</th>
                <th class="text-center" style="width:80px;">Qty</th>
                <th class="text-end" style="width:130px;">Unit Price</th>
                <th class="text-end" style="width:140px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $i => $it): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <div class="fw-semibold"><?= e($it['description']) ?></div>
                    </td>
                    <td class="text-center"><?= (int)$it['quantity'] ?></td>
                    <td class="text-end"><?= money($it['unit_price']) ?></td>
                    <td class="text-end fw-semibold"><?= money($it['line_total']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <?php if ((float)$invoice['subtotal'] > 0): ?>
                <tr class="total-row">
                    <td colspan="4" class="text-end">Subtotal</td>
                    <td class="text-end"><?= money($invoice['subtotal']) ?></td>
                </tr>
            <?php endif; ?>

            <?php if ((float)$invoice['transport_charges'] > 0): ?>
                <tr>
                    <td colspan="4" class="text-end">Transport Charges</td>
                    <td class="text-end"><?= money($invoice['transport_charges']) ?></td>
                </tr>
            <?php endif; ?>

            <?php if ((float)$invoice['discount'] > 0): ?>
                <tr>
                    <td colspan="4" class="text-end text-success">Discount</td>
                    <td class="text-end text-success">- <?= money($invoice['discount']) ?></td>
                </tr>
            <?php endif; ?>

            <tr class="total-row border-top">
                <td colspan="4" class="text-end">TOTAL</td>
                <td class="text-end grand-total"><?= money($invoice['total']) ?></td>
            </tr>
            <tr>
                <td colspan="4" class="text-end text-success">Paid</td>
                <td class="text-end text-success fw-semibold"><?= money($order['paid_amount']) ?></td>
            </tr>
            <tr>
                <td colspan="4" class="text-end text-danger"><strong>Balance Due</strong></td>
                <td class="text-end text-danger fw-bold">
                    <?= money((float)$invoice['total'] - (float)$order['paid_amount']) ?>
                </td>
            </tr>
        </tfoot>
    </table>

    <!-- PAYMENT STATUS + METHOD -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="info-box">
                <div class="info-label mb-1">Payment Status</div>
                <div>
                    <?php
                    $payColors = ['pending'=>'danger','partially_paid'=>'warning','paid'=>'success','failed'=>'danger','refunded'=>'secondary'];
                    $pc = $payColors[$invoice['payment_status']] ?? 'secondary';
                    ?>
                    <span class="badge text-bg-<?= $pc ?> status-badge">
                        <?= e(PAYMENT_STATUSES[$invoice['payment_status']] ?? $invoice['payment_status']) ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="info-box">
                <div class="info-label mb-1">Payment Method(s)</div>
                <?php if (empty($payments)): ?>
                    <span class="text-muted small">Not yet paid</span>
                <?php else:
                    $methods = array_unique(array_map(fn($p) => PAYMENT_METHODS[$p['payment_method']] ?? $p['payment_method'], $payments));
                ?>
                    <div class="small"><?= e(implode(', ', $methods)) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- PAYMENT HISTORY -->
    <?php if (!empty($payments)): ?>
        <div class="mb-4">
            <div class="info-label mb-2">Payment History</div>
            <table class="table table-sm table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Payment #</th>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td class="small"><?= e($p['payment_no']) ?></td>
                        <td class="small"><?= fmt_date($p['paid_at']) ?></td>
                        <td class="small"><?= e(PAYMENT_METHODS[$p['payment_method']] ?? $p['payment_method']) ?></td>
                        <td class="small"><?= e($p['reference_no'] ?: '—') ?></td>
                        <td class="small text-end"><?= money($p['amount']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- NOTES -->
    <?php if ($invoice['notes']): ?>
        <div class="mb-3">
            <div class="info-label mb-1">Notes</div>
            <div class="small text-muted"><?= nl2br(e($invoice['notes'])) ?></div>
        </div>
    <?php endif; ?>

    <!-- FOOTER -->
    <div class="text-center text-muted small mt-4 pt-3 border-top">
        <div class="fw-semibold text-dark">Thank you for your business!</div>
        <div class="mt-1">
            This is a computer-generated invoice. No signature required.
        </div>
        <div class="mt-1">
            For queries: <?= e(SITE_EMAIL) ?> · <?= e(SITE_PHONE) ?>
        </div>
    </div>

</div>

<script>
// Optional: auto-focus print button
</script>
</body>
</html>