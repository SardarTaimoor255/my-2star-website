<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/tracking_helper.php';
require_role($pdo, ROLE_COMPANY);

$user = current_user($pdo);
$coStmt = $pdo->prepare("SELECT id FROM companies WHERE user_id = ? LIMIT 1");
$coStmt->execute([$user['id']]);
$companyId = (int)$coStmt->fetchColumn();

$orderId = (int)($_GET['id'] ?? 0);

// Verify company has items in this order
$stmt = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ? AND company_id = ?");
$stmt->execute([$orderId, $companyId]);
if ((int)$stmt->fetchColumn() === 0) { flash_error('Order not found.'); redirect(BASE_URL . 'company/orders.php'); }

$data = get_order_tracking($pdo, $orderId);
if (empty($data)) { flash_error('Order not found.'); redirect(BASE_URL . 'company/orders.php'); }

$order = $data['order'];
$pageTitle = 'Track ' . $order['order_number'];
require_once dirname(__DIR__) . '/includes/company_header.php';
?>

<style>
.tracking-progress { padding: 24px 12px; overflow-x: auto; }
.tracking-stages { display: flex; align-items: flex-start; min-width: 760px; }
.track-stage { display: flex; flex-direction: column; align-items: center; flex: 0 0 70px; text-align: center; }
.track-dot { width: 40px; height: 40px; border-radius: 50%; background: #e2e8f0; color: #94a3b8; display: flex; align-items: center; justify-content: center; border: 2px solid #e2e8f0; }
.track-stage.done .track-dot { background: #0b3d91; color: #fff; border-color: #0b3d91; }
.track-stage.current .track-dot { background: #f5a623; color: #fff; border-color: #f5a623; }
.track-label { font-size: .72rem; margin-top: 6px; color: #64748b; }
.track-line { flex: 1; height: 3px; background: #e2e8f0; margin-top: 19px; }
.track-line.done { background: #0b3d91; }
</style>

<div class="mb-3 d-flex justify-content-between">
    <a href="<?= BASE_URL ?>company/order-details.php?id=<?= $orderId ?>" class="text-decoration-none small"><i class="bi bi-arrow-left"></i> Back</a>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i> Print</button>
</div>

<h2 class="fw-bold mb-1">Track <?= e($order['order_number']) ?></h2>
<p class="text-muted small mb-4">Shop: <?= e($order['shop_name']) ?> · <?= fmt_datetime($order['created_at']) ?></p>

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
                    <div class="mb-3 ps-3" style="border-left:2px solid #e2e8f0; position:relative;">
                        <span style="position:absolute;left:-7px;top:5px;width:12px;height:12px;border-radius:50%;background:#0b3d91;"></span>
                        <div class="small fw-semibold"><?= e(ORDER_STATUSES[$h['new_status']] ?? $h['new_status']) ?></div>
                        <div class="text-muted" style="font-size:.72rem;"><?= fmt_datetime($h['created_at']) ?><?php if ($h['changed_by_role']): ?> · <?= e(ucfirst($h['changed_by_role'])) ?><?php endif; ?></div>
                        <?php if ($h['note']): ?><div class="small fst-italic text-muted">"<?= e($h['note']) ?>"</div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <?php if ($data['shipment']): $sh = $data['shipment']; ?>
            <div class="card-soft p-3">
                <h6 class="fw-bold mb-3"><i class="bi bi-truck"></i> Shipment</h6>
                <div class="small mb-1"><?= e($sh['shipment_no']) ?></div>
                <div class="small text-muted mb-1">Vehicle: <?= e($sh['vehicle_no'] ?? '—') ?></div>
                <div class="small text-muted mb-1">Status: <?= e(SHIPMENT_STATUSES[$sh['status']] ?? $sh['status']) ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>setTimeout(() => location.reload(), 30000);</script>

<?php require_once dirname(__DIR__) . '/includes/company_footer.php'; ?>