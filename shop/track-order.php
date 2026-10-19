<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/tracking_helper.php';
require_role($pdo, ROLE_SHOP);

$user = current_user($pdo);
$shopStmt = $pdo->prepare("SELECT id FROM shops WHERE user_id = ? LIMIT 1");
$shopStmt->execute([$user['id']]);
$shopId = (int)$shopStmt->fetchColumn();

$orderId = (int)($_GET['id'] ?? 0);

// Verify ownership
$stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND shop_id = ? LIMIT 1");
$stmt->execute([$orderId, $shopId]);
if (!$stmt->fetch()) { flash_error('Order not found.'); redirect(BASE_URL . 'shop/orders.php'); }

$data = get_order_tracking($pdo, $orderId);
if (empty($data)) { flash_error('Order not found.'); redirect(BASE_URL . 'shop/orders.php'); }

$order = $data['order'];

$pageTitle = 'Track ' . $order['order_number'];
require_once dirname(__DIR__) . '/includes/shop_header.php';
?>

<style>
.tracking-progress { padding: 24px 12px; overflow-x: auto; }
.tracking-stages { display: flex; align-items: flex-start; min-width: 760px; }
.track-stage { display: flex; flex-direction: column; align-items: center; flex: 0 0 70px; text-align: center; }
.track-dot { width: 40px; height: 40px; border-radius: 50%; background: #e2e8f0; color: #94a3b8;
    display: flex; align-items: center; justify-content: center; font-size: 1rem; border: 2px solid #e2e8f0; }
.track-stage.done .track-dot { background: #0b3d91; color: #fff; border-color: #0b3d91; }
.track-stage.current .track-dot { background: #f5a623; color: #fff; border-color: #f5a623;
    box-shadow: 0 0 0 4px rgba(245,166,35,.25); animation: pulse 1.8s infinite; }
@keyframes pulse { 0%{box-shadow:0 0 0 4px rgba(245,166,35,.25);} 50%{box-shadow:0 0 0 8px rgba(245,166,35,.10);} 100%{box-shadow:0 0 0 4px rgba(245,166,35,.25);} }
.track-stage.cancelled .track-dot { background: #dc3545; color: #fff; border-color: #dc3545; }
.track-label { font-size: .72rem; margin-top: 6px; color: #64748b; font-weight: 500; line-height: 1.2; }
.track-stage.done .track-label { color: #0b3d91; font-weight: 600; }
.track-stage.current .track-label { color: #b45309; font-weight: 700; }
.track-line { flex: 1; height: 3px; background: #e2e8f0; margin-top: 19px; border-radius: 2px; min-width: 20px; }
.track-line.done { background: #0b3d91; }

.timeline-item { padding: 14px 0; border-left: 2px solid #e2e8f0; padding-left: 20px; position: relative; }
.timeline-item:last-child { border-left-color: transparent; }
.timeline-item .dot { position: absolute; left: -7px; top: 16px; width: 12px; height: 12px;
    border-radius: 50%; background: #0b3d91; border: 2px solid #fff; box-shadow: 0 0 0 2px #e2e8f0; }
.timeline-item.current .dot { background: #f5a623; box-shadow: 0 0 0 3px rgba(245,166,35,.25); }
</style>

<div class="mb-3 d-flex justify-content-between align-items-center">
    <a href="<?= BASE_URL ?>shop/order-details.php?id=<?= $orderId ?>" class="text-decoration-none small">
        <i class="bi bi-arrow-left"></i> Back to Order
    </a>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-printer"></i> Print
    </button>
</div>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1">Track Order <?= e($order['order_number']) ?></h2>
        <div class="text-muted small">Placed on <?= fmt_datetime($order['created_at']) ?></div>
    </div>
    <?= status_badge($order['status']) ?>
</div>

<!-- Progress Bar -->
<div class="card-soft mb-3">
    <div class="p-3 border-bottom"><strong>Order Progress</strong></div>
    <?= render_progress_bar($order['status']) ?>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <!-- Timeline -->
        <div class="card-soft">
            <div class="p-3 border-bottom"><strong>Timeline</strong></div>
            <div class="p-3">
                <?php if (empty($data['timeline'])): ?>
                    <p class="text-muted small mb-0">No updates yet.</p>
                <?php else: foreach ($data['timeline'] as $h): ?>
                    <div class="timeline-item">
                        <span class="dot"></span>
                        <div class="small fw-semibold">
                            <?= e(ORDER_STATUSES[$h['new_status']] ?? $h['new_status']) ?>
                        </div>
                        <div class="text-muted" style="font-size:.72rem;">
                            <?= fmt_datetime($h['created_at']) ?>
                            <?php if ($h['changed_by_role']): ?> · <?= e(ucfirst($h['changed_by_role'])) ?><?php endif; ?>
                        </div>
                        <?php if ($h['note']): ?>
                            <div class="small text-muted fst-italic">"<?= e($h['note']) ?>"</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <!-- Transport Info -->
        <?php if ($data['shipment']): $sh = $data['shipment']; ?>
            <div class="card-soft p-3 mb-3">
                <h6 class="fw-bold mb-3"><i class="bi bi-truck"></i> Transport</h6>
                <div class="small mb-1"><strong><?= e($sh['transport_company'] ?? 'N/A') ?></strong></div>
                <?php if ($sh['vehicle_no']): ?>
                    <div class="small text-muted mb-1"><i class="bi bi-truck"></i> <?= e($sh['vehicle_no']) ?></div>
                <?php endif; ?>
                <?php if ($sh['driver_name']): ?>
                    <div class="small text-muted mb-1"><i class="bi bi-person"></i> <?= e($sh['driver_name']) ?></div>
                <?php endif; ?>
                <?php if ($sh['driver_phone']): ?>
                    <div class="small text-muted mb-1"><i class="bi bi-telephone"></i> <?= e($sh['driver_phone']) ?></div>
                <?php endif; ?>
                <?php if ($sh['bilty_no']): ?>
                    <div class="small text-muted mb-1"><i class="bi bi-file-text"></i> Bilty: <?= e($sh['bilty_no']) ?></div>
                <?php endif; ?>
                <hr class="my-2">
                <div class="small">
                    <span class="text-muted">Shipment Status:</span>
                    <?php
                    $clr = ['assigned'=>'warning','picked_up'=>'info','in_transit'=>'primary','out_for_delivery'=>'info','delivered'=>'success','delivery_failed'=>'danger'][$sh['status']] ?? 'secondary';
                    ?>
                    <span class="badge text-bg-<?= $clr ?>"><?= e(SHIPMENT_STATUSES[$sh['status']] ?? $sh['status']) ?></span>
                </div>
                <?php if ($sh['expected_delivery']): ?>
                    <div class="small mt-2">
                        <span class="text-muted">Expected:</span>
                        <strong><?= fmt_date($sh['expected_delivery']) ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card-soft p-3 text-center text-muted small">
                <i class="bi bi-truck fs-3 d-block mb-2 opacity-25"></i>
                Transport not assigned yet.
            </div>
        <?php endif; ?>

        <!-- Payment Info -->
        <div class="card-soft p-3">
            <h6 class="fw-bold mb-3"><i class="bi bi-cash-stack"></i> Payment</h6>
            <div class="d-flex justify-content-between small mb-1">
                <span class="text-muted">Total</span>
                <span><?= money($order['total_amount']) ?></span>
            </div>
            <div class="d-flex justify-content-between small mb-1">
                <span class="text-muted">Paid</span>
                <span class="text-success"><?= money($order['paid_amount']) ?></span>
            </div>
            <div class="d-flex justify-content-between">
                <strong>Remaining</strong>
                <strong class="text-danger"><?= money($order['total_amount'] - $order['paid_amount']) ?></strong>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh every 30 seconds
setTimeout(() => location.reload(), 30000);
</script>

<?php require_once dirname(__DIR__) . '/includes/shop_footer.php'; ?>