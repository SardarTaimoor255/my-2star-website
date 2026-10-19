<?php
/**
 * shop/order-details.php
 */
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_SHOP);
require_once dirname(__DIR__) . '/includes/review_helper.php';
require_once dirname(__DIR__) . '/includes/payment_helper.php';
$user = current_user($pdo);
$shopStmt = $pdo->prepare("SELECT id FROM shops WHERE user_id = ? LIMIT 1");
$shopStmt->execute([$user['id']]);
$shopId = (int)$shopStmt->fetchColumn();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND shop_id = ? LIMIT 1");
$stmt->execute([$id, $shopId]);
$order = $stmt->fetch();

if (!$order) { flash_error('Order not found.'); redirect(BASE_URL . 'shop/orders.php'); }

// Items
$stmt = $pdo->prepare(
    "SELECT oi.*, p.name AS product_name, p.sku
     FROM order_items oi
     JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = ?"
);
$stmt->execute([$id]);
$items = $stmt->fetchAll();

// Timeline
$stmt = $pdo->prepare("SELECT * FROM order_status_history WHERE order_id = ? ORDER BY id ASC");
$stmt->execute([$id]);
$timeline = $stmt->fetchAll();

// Shipment
$stmt = $pdo->prepare(
    "SELECT s.*, u.full_name AS driver_user
     FROM shipments s
     LEFT JOIN users u ON u.id = s.transport_user_id
     WHERE s.order_id = ? LIMIT 1"
);
$stmt->execute([$id]);
$shipment = $stmt->fetch();


// Fetch vendor info
$vendorInfo = null;
if ($order['vendor_id']) {
    $stmt = $pdo->prepare(
        "SELECT v.business_name, u.full_name, u.phone, u.email
         FROM vendors v
         JOIN users u ON u.id = v.user_id
         WHERE v.id = ? LIMIT 1"
    );
    $stmt->execute([$order['vendor_id']]);
    $vendorInfo = $stmt->fetch();
}

$pageTitle = 'Order ' . $order['order_number'];

require_once dirname(__DIR__) . '/includes/shop_header.php';
?>

<div class="mb-3">
    <a href="<?= BASE_URL ?>shop/orders.php" class="text-decoration-none small">
        <i class="bi bi-arrow-left"></i> Back to Orders
    </a>
</div>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1"><?= e($order['order_number']) ?></h2>
        <div class="text-muted small">
            Placed on <?= fmt_datetime($order['created_at']) ?>
            <?php if ($vendorInfo): ?>
                · Vendor: <strong><?= e($vendorInfo['business_name'] ?: $vendorInfo['full_name']) ?></strong>
            <?php endif; ?>
        </div>
    </div>
        <div class="d-flex gap-2 align-items-center">
        <?= status_badge($order['status']) ?>
        <a href="<?= BASE_URL ?>shop/track-order.php?id=<?= (int)$order['id'] ?>" class="btn btn-sm btn-primary">
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
                        <tr>
                            <th>Product</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Total</th>
                            <?php if ($order['status'] === 'delivered'): ?>
                                <th class="text-center">Review</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $it): 
                        $canReview = ($order['status'] === 'delivered') && can_review_order_item($pdo, (int)$it['id'], $shopId);
                        $existingReview = get_order_item_review($pdo, (int)$it['id']);
                    ?>
                        <tr>
                            <td class="small">
                                <div class="fw-semibold"><?= e($it['product_name']) ?></div>
                                <div class="text-muted" style="font-size:.72rem;">SKU: <?= e($it['sku']) ?></div>
                            </td>
                            <td class="text-end small"><?= (int)$it['quantity'] ?></td>
                            <td class="text-end small"><?= money($it['final_price']) ?></td>
                            <td class="text-end small fw-semibold"><?= money($it['line_total']) ?></td>
                            <?php if ($order['status'] === 'delivered'): ?>
                                <td class="text-center">
                                    <?php if ($existingReview): ?>
                                        <div><?= render_stars((float)$existingReview['rating']) ?></div>
                                        <small class="text-success">Reviewed</small>
                                    <?php elseif ($canReview): ?>
                                        <button class="btn btn-sm btn-warning" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#reviewModal<?= (int)$it['id'] ?>">
                                            <i class="bi bi-star"></i> Review
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="3" class="text-end fw-bold">Total</td>
                            <td class="text-end fw-bold text-primary"><?= money($order['total_amount']) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Timeline -->
        <div class="card-soft">
            <div class="p-3 border-bottom"><strong>Tracking Timeline</strong></div>
            <div class="p-3">
                <?php if (empty($timeline)): ?>
                    <p class="text-muted mb-0 small">No status updates yet.</p>
                <?php else: ?>
                    <div class="position-relative ps-4">
                        <?php foreach ($timeline as $i => $h): ?>
                            <div class="position-relative mb-3">
                                <span class="position-absolute top-0 start-0 translate-middle-x bg-primary rounded-circle"
                                      style="width:10px;height:10px;left:-16px !important;"></span>
                                <div class="small fw-semibold">
                                    <?= e(ORDER_STATUSES[$h['new_status']] ?? $h['new_status']) ?>
                                </div>
                                <div class="text-muted" style="font-size:.72rem;">
                                    <?= fmt_datetime($h['created_at']) ?>
                                    <?php if ($h['changed_by_role']): ?>
                                        · by <?= e(ucfirst($h['changed_by_role'])) ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($h['note']): ?>
                                    <div class="small text-muted fst-italic">"<?= e($h['note']) ?>"</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">

        <!-- Vendor Info -->
        <?php if ($vendorInfo): ?>
            <div class="card-soft p-3 mb-3">
                <h6 class="fw-bold mb-3"><i class="bi bi-person-badge"></i> Vendor</h6>
                <div class="small mb-1"><strong><?= e($vendorInfo['business_name'] ?: $vendorInfo['full_name']) ?></strong></div>
                <?php if ($vendorInfo['phone']): ?>
                    <div class="small text-muted mb-1">
                        <i class="bi bi-telephone"></i> <?= e($vendorInfo['phone']) ?>
                    </div>
                <?php endif; ?>
                <?php if ($vendorInfo['email']): ?>
                    <div class="small text-muted">
                        <i class="bi bi-envelope"></i> <?= e($vendorInfo['email']) ?>
                    </div>
                <?php endif; ?>
                
            </div>
        <?php endif; ?>

      
        <!-- Cancel Order (agar possible ho) -->
        <?php if ($order['status'] === 'order_created'): ?>
            <div class="card-soft p-3 mb-3" style="border-left: 4px solid #dc3545;">
                <h6 class="fw-bold mb-2"><i class="bi bi-x-circle text-danger"></i> Cancel Order</h6>
                <p class="small text-muted mb-3">
                    Abhi ye order cancel ho sakta hai kyunke vendor ne accept nahi kiya. 
                    Cancel karne ke baad vendor ko notification jayegi.
                </p>
                <button class="btn btn-outline-danger btn-sm w-100"
                        onclick="cancelOrder(<?= (int)$order['id'] ?>, '<?= e($order['order_number']) ?>')">
                    <i class="bi bi-x-circle"></i> Cancel This Order
                </button>
            </div>
        <?php endif; ?>

        <!-- Payment Summary -->
        <!-- Payment Summary -->
        <div class="card-soft p-3 mb-3">
            <h6 class="fw-bold mb-3">Payment</h6>
            <div class="d-flex justify-content-between small mb-1">
                <span class="text-muted">Total</span>
                <span><?= money($order['total_amount']) ?></span>
            </div>
            <div class="d-flex justify-content-between small mb-1">
                <span class="text-muted">Paid</span>
                <span class="text-success"><?= money($order['paid_amount']) ?></span>
            </div>
            <hr class="my-2">
                        <div class="d-flex justify-content-between">
                <strong>Remaining</strong>
                <strong class="text-danger"><?= money($order['total_amount'] - $order['paid_amount']) ?></strong>
            </div>

           <?php if (($order['total_amount'] - $order['paid_amount']) > 0.01 && !in_array($order['status'], ['cancelled', 'returned', 'rejected'])): ?>
    <a href="<?= BASE_URL ?>shop/make-payment.php?order_id=<?= (int)$order['id'] ?>" class="btn btn-success w-100">
        <i class="bi bi-send"></i> Make Payment
    </a>
<?php endif; ?>
        </div>

        <!-- Payments List -->
        <div class="card-soft p-3 mb-3">
            <h6 class="fw-bold mb-3">Payments Made</h6>
            <?php
            $stmtP = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC");
            $stmtP->execute([$order['id']]);
            $paymentsList = $stmtP->fetchAll();
            ?>
            <?php if (empty($paymentsList)): ?>
                <p class="text-muted small mb-0">No payments recorded yet.</p>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($paymentsList as $p): ?>
                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="small fw-semibold">
                                    <i class="bi <?= e(payment_method_icon($p['payment_method'])) ?>"></i>
                                    <?= money($p['amount']) ?>
                                </div>
                                <div class="text-muted" style="font-size:.72rem;">
                                    <?= e($p['payment_no']) ?> · <?= fmt_date($p['paid_at']) ?>
                                </div>
                            </div>
                            <a href="<?= BASE_URL ?>shop/payment-details.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-receipt"></i>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

                <!-- Shipment Info -->
        <?php if ($shipment): ?>
            <div class="card-soft p-3 mb-3">
                <h6 class="fw-bold mb-3"><i class="bi bi-truck"></i> Transport</h6>
                <div class="small mb-1"><strong><?= e($shipment['bilty_no'] ?? 'N/A') ?></strong></div>
                <div class="small text-muted mb-1"><i class="bi bi-truck"></i> <?= e($shipment['vehicle_no'] ?? 'N/A') ?></div>
                <div class="small text-muted mb-1"><i class="bi bi-person"></i> <?= e($shipment['driver_name'] ?? '—') ?></div>
                <?php if ($shipment['driver_phone']): ?>
                    <div class="small text-muted mb-2"><i class="bi bi-telephone"></i> <?= e($shipment['driver_phone']) ?></div>
                <?php endif; ?>
                <div class="small mb-0">
                    <span class="text-muted">Status:</span>
                    <span class="badge text-bg-info"><?= e(SHIPMENT_STATUSES[$shipment['status']] ?? $shipment['status']) ?></span>
                </div>
            </div>

            <!-- Transport Charges Card -->
            <?php if ((float)$shipment['transport_charges'] > 0): ?>
                <div class="card-soft p-3 mb-3" style="border-left: 4px solid #f5a623;">
                    <h6 class="fw-bold mb-3"><i class="bi bi-cash-coin text-warning"></i> Delivery Charges</h6>
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">Total Charges</span>
                        <span class="fw-semibold"><?= money($shipment['transport_charges']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">Paid</span>
                        <span class="text-success"><?= money($shipment['paid_amount']) ?></span>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between">
                        <strong>Remaining</strong>
                        <strong class="text-danger">
                            <?= money((float)$shipment['transport_charges'] - (float)$shipment['paid_amount']) ?>
                        </strong>
                    </div>
                    <?php if ($shipment['payment_status'] !== 'paid'): ?>
                        <a href="<?= BASE_URL ?>shop/pay-transport.php?shipment_id=<?= (int)$shipment['id'] ?>"
                           class="btn btn-warning btn-sm w-100 mt-3">
                            <i class="bi bi-send"></i> Pay Transport Company
                        </a>
                    <?php else: ?>
                        <div class="alert alert-success small mt-3 mb-0 py-2 text-center">
                            <i class="bi bi-check-circle"></i> Delivery charges fully paid
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="card-soft p-3 mb-3 text-center text-muted small">
                    <i class="bi bi-hourglass-split"></i>
                    Waiting for transport to set delivery charges.
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="card-soft p-3 text-center text-muted small">
                Transport not assigned yet.
            </div>
        <?php endif; ?>
    </div>
</div>
<script>
async function cancelOrder(orderId, orderNumber) {
    const reason = prompt(
        'Cancel order ' + orderNumber + '?\n\nReason (optional):\n' +
        'Jaise: "Galti se order ho gaya", "Product nahi chahiye", etc.'
    );
    
    if (reason === null) return;
    
    if (!confirm('Pakka cancel karna hai?\n\nOrder ' + orderNumber + '\nReason: ' + (reason || 'No reason given'))) {
        return;
    }
    
    const fd = new FormData();
    fd.append('action', 'cancel_order');
    fd.append('order_id', orderId);
    fd.append('reason', reason);
    fd.append('csrf_token', '<?= e(csrf_token()) ?>');
    
    try {
        const res = await fetch('<?= BASE_URL ?>api/shop-order-action.php', {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        
        if (data.success) {
            if (data.redirect) location.href = data.redirect;
            else location.reload();
        } else {
            alert('❌ ' + (data.message || 'Failed to cancel order'));
        }
    } catch (e) {
        alert('Network error. Please try again.');
    }
}
</script>
<?php if ($order['status'] === 'delivered'): ?>
    <?php foreach ($items as $it): 
        if (!can_review_order_item($pdo, (int)$it['id'], $shopId)) continue;
    ?>
        <div class="modal fade" id="reviewModal<?= (int)$it['id'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <form method="post" action="<?= BASE_URL ?>api/review-action.php" class="modal-content">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="submit_review">
                    <input type="hidden" name="order_item_id" value="<?= (int)$it['id'] ?>">

                    <div class="modal-header">
                        <h5 class="modal-title">Review: <?= e($it['product_name']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Rating <span class="text-danger">*</span></label>
                            <div class="d-flex gap-1" id="starPicker<?= (int)$it['id'] ?>">
                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                    <i class="bi bi-star fs-2 text-warning" 
                                       style="cursor:pointer;" 
                                       data-value="<?= $s ?>"></i>
                                <?php endfor; ?>
                                <input type="hidden" name="rating" id="ratingInput<?= (int)$it['id'] ?>" value="5" required>
                            </div>
                            <div class="form-text">Click karke rating select karein.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Title <small class="text-muted">(optional)</small></label>
                            <input type="text" name="title" class="form-control" maxlength="150" 
                                   placeholder="e.g. Acha product hai">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Your Review <span class="text-danger">*</span></label>
                            <textarea name="comment" class="form-control" rows="4" required minlength="3" maxlength="1000"
                                      placeholder="Product ke baray mein apni raye likhein..."></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-send"></i> Submit Review
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
document.querySelectorAll('[id^="starPicker"]').forEach(function(picker) {
    const pickerId = picker.id.replace('starPicker', '');
    const hiddenInput = document.getElementById('ratingInput' + pickerId);
    const stars = picker.querySelectorAll('i');
    
    function setRating(val) {
        hiddenInput.value = val;
        stars.forEach(function(s, idx) {
            if (idx + 1 <= val) {
                s.classList.remove('bi-star');
                s.classList.add('bi-star-fill');
            } else {
                s.classList.remove('bi-star-fill');
                s.classList.add('bi-star');
            }
        });
    }
    
    stars.forEach(function(s) {
        s.addEventListener('click', function() {
            setRating(parseInt(s.dataset.value));
        });
        s.addEventListener('mouseenter', function() {
            const hoverVal = parseInt(s.dataset.value);
            stars.forEach(function(st, idx) {
                if (idx + 1 <= hoverVal) {
                    st.classList.remove('bi-star');
                    st.classList.add('bi-star-fill');
                } else {
                    st.classList.remove('bi-star-fill');
                    st.classList.add('bi-star');
                }
            });
        });
    });
    
    picker.addEventListener('mouseleave', function() {
        setRating(parseInt(hiddenInput.value) || 5);
    });
    
    setRating(5);
});
</script>
<?php require_once dirname(__DIR__) . '/includes/shop_footer.php'; ?>