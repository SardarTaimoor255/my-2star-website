<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_TRANSPORT);

$user = current_user($pdo);
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT s.*, o.order_number, o.total_amount, o.status AS order_status,
            sh.shop_name, sh.owner_name AS shop_owner, sh.address AS shop_address
     FROM shipments s
     JOIN orders o ON o.id = s.order_id
     JOIN shops sh ON sh.id = o.shop_id
     WHERE s.id = ? AND s.transport_user_id = ? LIMIT 1"
);
$stmt->execute([$id, $user['id']]);
$shipment = $stmt->fetch();

if (!$shipment) { flash_error('Shipment not found.'); redirect(BASE_URL . 'transport/shipments.php'); }

// Tracking history
$stmt = $pdo->prepare(
    "SELECT st.*, u.full_name AS updated_by_name
     FROM shipment_tracking st
     LEFT JOIN users u ON u.id = st.updated_by
     WHERE st.shipment_id = ? ORDER BY st.id ASC"
);
$stmt->execute([$id]);
$tracking = $stmt->fetchAll();

$pageTitle = 'Shipment ' . $shipment['shipment_no'];
require_once dirname(__DIR__) . '/includes/transport_header.php';

// Determine next status
$flow = [
    'assigned'            => 'picked_up',
    'picked_up'           => 'in_transit',
    'in_transit'          => 'arrived_destination',
    'arrived_destination' => 'out_for_delivery',
    'out_for_delivery'    => 'delivered',
];
$nextStatus = $flow[$shipment['status']] ?? null;
$nextLabel = $nextStatus ? SHIPMENT_STATUSES[$nextStatus] : null;
$isFinal = in_array($shipment['status'], ['delivered','delivery_failed','returned'], true);
?>

<div class="mb-3">
    <a href="<?= BASE_URL ?>transport/shipments.php" class="text-decoration-none small">
        <i class="bi bi-arrow-left"></i> Back to Shipments
    </a>
</div>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1"><?= e($shipment['shipment_no']) ?></h2>
        <div class="text-muted small">Order <strong><?= e($shipment['order_number']) ?></strong></div>
    </div>
    <?php
    $clr = ['assigned'=>'warning','picked_up'=>'info','in_transit'=>'primary','arrived_destination'=>'info','out_for_delivery'=>'info','delivered'=>'success','delivery_failed'=>'danger','returned'=>'dark'][$shipment['status']] ?? 'secondary';
    ?>
        <div class="d-flex gap-2 align-items-center">
        <span class="badge text-bg-<?= $clr ?> fs-6"><?= e(SHIPMENT_STATUSES[$shipment['status']] ?? $shipment['status']) ?></span>
        <a href="<?= BASE_URL ?>transport/track-order.php?id=<?= (int)$shipment['order_id'] ?>" class="btn btn-sm btn-primary">
            <i class="bi bi-geo-alt"></i> Track Order
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">

        <!-- Set Charges Panel -->
         
                 <!-- Set Vehicle & Bilty Panel -->
       <?php if (!$isFinal && (empty($shipment['vehicle_no']) || empty($shipment['bilty_no']))): ?>
            
            <div class="card-soft p-4 mb-3" style="border-left:4px solid #06b6d4;">
                <h6 class="fw-bold mb-3"><i class="bi bi-truck text-info"></i> Set Vehicle & Bilty Details</h6>
                <p class="small text-muted mb-3">
                    Shipment ki vehicle number aur bilty number set karein — shop, vendor, aur company ko notification jayegi.
                </p>
                <form method="post" action="<?= BASE_URL ?>api/transport-action.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="set_vehicle_bilty">
                    <input type="hidden" name="id" value="<?= (int)$shipment['id'] ?>">
                    <div class="row g-2">
                        <div class="col-md-5">
                            <label class="form-label small">Vehicle Number <span class="text-danger">*</span></label>
                            <input type="text" name="vehicle_no" class="form-control" required
                                   placeholder="e.g. KBS-4421"
                                   value="<?= e($shipment['vehicle_no'] ?? '') ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small">Bilty Number <span class="text-danger">*</span></label>
                            <input type="text" name="bilty_no" class="form-control" required
                                   placeholder="e.g. KB-2026-88741"
                                   value="<?= e($shipment['bilty_no'] ?? '') ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-info w-100">
                                <i class="bi bi-check"></i> Save
                            </button>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Driver Name (optional)</label>
                            <input type="text" name="driver_name" class="form-control"
                                   placeholder="Driver name"
                                   value="<?= e($shipment['driver_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Driver Phone (optional)</label>
                            <input type="text" name="driver_phone" class="form-control"
                                   placeholder="+92 300 1234567"
                                   value="<?= e($shipment['driver_phone'] ?? '') ?>">
                        </div>
                    </div>
                </form>
            </div>
        <?php endif; ?>
<?php if (!$isFinal && (float)$shipment['transport_charges'] <= 0): ?>
            <div class="card-soft p-4 mb-3" style="border-left:4px solid #f5a623;">
                <h6 class="fw-bold mb-3"><i class="bi bi-cash-coin text-warning"></i> Set Delivery Charges</h6>
                <p class="small text-muted mb-3">
                    Ye shipment aap ko assign hui hai. Apni delivery charges set karein — shop ko notification jayegi aur wo pay karegi.
                </p>
                <form method="post" action="<?= BASE_URL ?>api/transport-action.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="set_transport_charges">
                    <input type="hidden" name="id" value="<?= (int)$shipment['id'] ?>">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label small">Delivery Charges (PKR) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="1" name="charges" 
                                   class="form-control" required placeholder="e.g. 1500">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Note (optional)</label>
                            <input type="text" name="note" class="form-control" 
                                   placeholder="e.g. Lahore to Muzaffarabad">
                        </div>
                        <div class="col-12 mt-3">
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-send"></i> Set Charges & Notify Shop
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="card-soft p-4 mb-3" style="border-left:4px solid #10b981;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0"><i class="bi bi-cash-coin text-success"></i> Delivery Charges</h6>
                    <?php
                    $pcolors = ['pending'=>'danger','partially_paid'=>'warning','paid'=>'success'];
                    $pc = $pcolors[$shipment['payment_status']] ?? 'secondary';
                    ?>
                    <span class="badge text-bg-<?= $pc ?>">
                        <?= e(ucwords(str_replace('_',' ', $shipment['payment_status']))) ?>
                    </span>
                </div>
                <div class="row text-center mt-3">
                    <div class="col-4">
                        <div class="small text-muted">Total</div>
                        <div class="fw-bold"><?= money($shipment['transport_charges']) ?></div>
                    </div>
                    <div class="col-4">
                        <div class="small text-muted">Received</div>
                        <div class="fw-bold text-success"><?= money($shipment['paid_amount']) ?></div>
                    </div>
                    <div class="col-4">
                        <div class="small text-muted">Pending</div>
                        <div class="fw-bold text-danger">
                            <?= money((float)$shipment['transport_charges'] - (float)$shipment['paid_amount']) ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    

        <!-- Action Panel -->
        <?php if (!$isFinal): ?>
            <div class="card-soft p-4 mb-3">
                <h6 class="fw-bold mb-3"><i class="bi bi-arrow-right-circle"></i> Update Delivery Status</h6>

                <?php if ($nextStatus): ?>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button class="btn btn-success" onclick="doTransportAction(this,'update_status',<?= (int)$shipment['id'] ?>,{status:'<?= e($nextStatus) ?>'},'Mark as <?= e($nextLabel) ?>?')">
                            <i class="bi bi-check-circle"></i> Mark as <?= e($nextLabel) ?>
                        </button>
                    </div>
                <?php endif; ?>

                <hr>
                <h6 class="small fw-bold text-muted mb-2">Other actions:</h6>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-sm btn-outline-danger"
                            onclick="doTransportAction(this,'update_status',<?= (int)$shipment['id'] ?>,{status:'delivery_failed'},'Mark as Delivery Failed?')">
                        <i class="bi bi-x-circle"></i> Delivery Failed
                    </button>
                    <button class="btn btn-sm btn-outline-dark"
                            onclick="doTransportAction(this,'update_status',<?= (int)$shipment['id'] ?>,{status:'returned'},'Mark as Returned?')">
                        <i class="bi bi-arrow-return-left"></i> Returned
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-<?= $shipment['status']==='delivered'?'success':'danger' ?>">
                <i class="bi bi-info-circle"></i>
                This shipment is <strong><?= e(SHIPMENT_STATUSES[$shipment['status']]) ?></strong>
                <?php if ($shipment['delivered_at']): ?>
                    on <?= fmt_datetime($shipment['delivered_at']) ?>
                <?php endif; ?>.
            </div>
        <?php endif; ?>

        <!-- Pickup / Delivery Info -->
        <div class="card-soft p-3 mb-3">
            <h6 class="fw-bold mb-3">Shipment Information</h6>
            <div class="row g-2 small">
                <div class="col-md-6">
                    <div class="text-muted">Vehicle</div>
                    <div class="fw-semibold"><?= e($shipment['vehicle_no'] ?? '—') ?> <?= $shipment['vehicle_type'] ? '(' . e($shipment['vehicle_type']) . ')' : '' ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted">Bilty #</div>
                    <div class="fw-semibold"><?= e($shipment['bilty_no'] ?? '—') ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted">Driver</div>
                    <div class="fw-semibold"><?= e($shipment['driver_name'] ?? '—') ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted">Driver Phone</div>
                    <div class="fw-semibold"><?= e($shipment['driver_phone'] ?? '—') ?></div>
                </div>
                <div class="col-12">
                    <div class="text-muted">Pickup Address</div>
                    <div class="fw-semibold"><?= e($shipment['pickup_address'] ?? '—') ?></div>
                </div>
                <div class="col-12">
                    <div class="text-muted">Delivery Address</div>
                    <div class="fw-semibold"><?= e($shipment['delivery_address'] ?? $shipment['shop_address']) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted">Expected Delivery</div>
                    <div class="fw-semibold"><?= $shipment['expected_delivery'] ? fmt_date($shipment['expected_delivery']) : '—' ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted">Delivered At</div>
                    <div class="fw-semibold"><?= $shipment['delivered_at'] ? fmt_datetime($shipment['delivered_at']) : '—' ?></div>
                </div>
            </div>
        </div>

        <!-- Shop Info -->
        <div class="card-soft p-3">
            <h6 class="fw-bold mb-3">Shop Information</h6>
            <div class="small mb-1"><strong><?= e($shipment['shop_name']) ?></strong></div>
            <div class="small text-muted mb-1"><i class="bi bi-person"></i> <?= e($shipment['shop_owner']) ?></div>
            <div class="small text-muted"><i class="bi bi-geo-alt"></i> <?= e($shipment['shop_address']) ?></div>
        </div>
    </div>

    <!-- Timeline -->
    <div class="col-lg-5">
        <div class="card-soft">
            <div class="p-3 border-bottom"><strong>Tracking Timeline</strong></div>
            <div class="p-3">
                <?php if (empty($tracking)): ?>
                    <p class="text-muted small mb-0">No tracking events yet.</p>
                <?php else: ?>
                    <?php foreach ($tracking as $t): ?>
                        <div class="mb-3 ps-3 position-relative">
                            <span class="position-absolute bg-primary rounded-circle" style="width:8px;height:8px;left:0;top:6px;"></span>
                            <div class="small fw-semibold"><?= e(SHIPMENT_STATUSES[$t['status']] ?? $t['status']) ?></div>
                            <div class="text-muted" style="font-size:.72rem;"><?= fmt_datetime($t['created_at']) ?></div>
                            <?php if ($t['note']): ?>
                                <div class="small fst-italic text-muted">"<?= e($t['note']) ?>"</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/transport_footer.php'; ?>