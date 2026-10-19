<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/tracking_helper.php';
require_role($pdo, ROLE_VENDOR);

$orderId = (int)($_GET['id'] ?? 0);
$data = get_order_tracking($pdo, $orderId);
if (empty($data)) { flash_error('Order not found.'); redirect(BASE_URL . 'vendor/orders.php'); }

$order = $data['order'];
$pageTitle = 'Track ' . $order['order_number'];
require_once dirname(__DIR__) . '/includes/vendor_header.php';
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
.timeline-item .dot { position: absolute; left: -7px; top: 16px; width: 12px; height: 12px; border-radius: 50%; background: #0b3d91; border: 2px solid #fff; box-shadow: 0 0 0 2px #e2e8f0; }
</style>

<div class="mb-3 d-flex justify-content-between">
    <a href="<?= BASE_URL ?>vendor/order-details.php?id=<?= $orderId ?>" class="text-decoration-none small">
        <i class="bi bi-arrow-left"></i> Back to Order
    </a>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-printer"></i> Print
    </button>
</div>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1">Track <?= e($order['order_number']) ?></h2>
        <div class="text-muted small">Shop: <?= e($order['shop_name']) ?> · <?= fmt_datetime($order['created_at']) ?></div>
    </div>
    <?= status_badge($order['status']) ?>
</div>

<div class="card-soft mb-3">
    <div class="p-3 border-bottom"><strong>Progress</strong></div>
    <?= render_progress_bar($order['status']) ?>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card-soft">
            <div class="p-3 border-bottom"><strong>Timeline</strong></div>
            <div class="p-3">
                <?php foreach ($data['timeline'] as $h): ?>
                    <div class="timeline-item">
                        <span class="dot"></span>
                        <div class="small fw-semibold"><?= e(ORDER_STATUSES[$h['new_status']] ?? $h['new_status']) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            <?= fmt_datetime($h['created_at']) ?>
                            <?php if ($h['changed_by_role']): ?> · <?= e(ucfirst($h['changed_by_role'])) ?><?php endif; ?>
                        </div>
                        <?php if ($h['note']): ?><div class="small fst-italic text-muted">"<?= e($h['note']) ?>"</div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <?php if ($data['shipment']): $sh = $data['shipment']; ?>
            <div class="card-soft p-3 mb-3">
                <h6 class="fw-bold mb-3"><i class="bi bi-truck"></i> Shipment</h6>
                <div class="small mb-1"><strong><?= e($sh['shipment_no']) ?></strong></div>
                <div class="small text-muted mb-1">Vehicle: <?= e($sh['vehicle_no'] ?? '—') ?></div>
                <div class="small text-muted mb-1">Driver: <?= e($sh['driver_name'] ?? '—') ?></div>
                <div class="small text-muted mb-1">Bilty: <?= e($sh['bilty_no'] ?? '—') ?></div>
                <?php if ($sh['delivered_at']): ?>
                    <div class="small text-success mt-2"><i class="bi bi-check-circle"></i> Delivered: <?= fmt_datetime($sh['delivered_at']) ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="card-soft p-3">
            <h6 class="fw-bold mb-3">Shop Info</h6>
            <div class="small mb-1"><strong><?= e($order['shop_name']) ?></strong></div>
            <div class="small text-muted"><?= e($order['shop_address']) ?></div>
        </div>
    </div>
</div>

<script>setTimeout(() => location.reload(), 30000);</script>

<?php require_once dirname(__DIR__) . '/includes/vendor_footer.php'; ?>