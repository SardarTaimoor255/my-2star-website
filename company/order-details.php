<?php
/**
 * company/order-details.php
 * Company view of an order containing their products
 */
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_COMPANY);

$user = current_user($pdo);
$coStmt = $pdo->prepare("SELECT id FROM companies WHERE user_id = ? LIMIT 1");
$coStmt->execute([$user['id']]);
$companyId = (int)$coStmt->fetchColumn();

$id = (int)($_GET['id'] ?? 0);

// Fetch order + verify company has items in it
$stmt = $pdo->prepare(
    "SELECT o.*, s.shop_name, s.address AS shop_address, s.owner_name AS shop_owner
     FROM orders o
     JOIN shops s ON s.id = o.shop_id
     WHERE o.id = ? AND EXISTS (
         SELECT 1 FROM order_items WHERE order_id = o.id AND company_id = ?
     ) LIMIT 1"
);
$stmt->execute([$id, $companyId]);
$order = $stmt->fetch();

if (!$order) { flash_error('Order not found or not assigned to you.'); redirect(BASE_URL . 'company/orders.php'); }

// Fetch only MY items
$stmt = $pdo->prepare(
    "SELECT oi.*, p.name AS product_name, p.sku, p.unit
     FROM order_items oi
     JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = ? AND oi.company_id = ?"
);
$stmt->execute([$id, $companyId]);
$items = $stmt->fetchAll();

// Timeline
$stmt = $pdo->prepare("SELECT * FROM order_status_history WHERE order_id = ? ORDER BY id ASC");
$stmt->execute([$id]);
$timeline = $stmt->fetchAll();

// Check shipment
$stmt = $pdo->prepare("SELECT * FROM shipments WHERE order_id = ? LIMIT 1");
$stmt->execute([$id]);
$shipment = $stmt->fetch();

$pageTitle = 'Order ' . $order['order_number'];
require_once dirname(__DIR__) . '/includes/company_header.php';
?>

<div class="mb-3">
    <a href="<?= BASE_URL ?>company/orders.php" class="text-decoration-none small">
        <i class="bi bi-arrow-left"></i> Back to Orders
    </a>
</div>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1"><?= e($order['order_number']) ?></h2>
        <div class="text-muted small">From <strong><?= e($order['shop_name']) ?></strong> · <?= fmt_datetime($order['created_at']) ?></div>
    </div>
        <div class="d-flex gap-2 align-items-center">
        <?= status_badge($order['status']) ?>
        <a href="<?= BASE_URL ?>company/track-order.php?id=<?= (int)$order['id'] ?>" class="btn btn-sm btn-primary">
            <i class="bi bi-geo-alt"></i> Track Order
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <!-- Items -->
        <div class="card-soft mb-3">
            <div class="p-3 border-bottom"><strong>Your Products in This Order</strong></div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Your Base Price</th>
                            <th class="text-end">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $myTotal = 0; foreach ($items as $it): $line = (float)$it['company_base_price'] * (int)$it['quantity']; $myTotal += $line; ?>
                        <tr>
                            <td class="small">
                                <div class="fw-semibold"><?= e($it['product_name']) ?></div>
                                <div class="text-muted" style="font-size:.72rem;">SKU: <?= e($it['sku']) ?></div>
                            </td>
                            <td class="text-end small"><?= (int)$it['quantity'] ?></td>
                            <td class="text-end small"><?= money($it['company_base_price']) ?></td>
                            <td class="text-end small fw-semibold"><?= money($line) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="3" class="text-end fw-bold">Your Total</td>
                            <td class="text-end fw-bold text-primary"><?= money($myTotal) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Action: Confirm Availability -->
        <?php if (in_array($order['status'], ['order_accepted','sent_to_company'], true)): ?>
            <div class="card-soft p-4 mb-3">
                <h6 class="fw-bold mb-3"><i class="bi bi-check2-square"></i> Confirm Order</h6>
                <p class="small text-muted mb-3">Confirm that you can supply these products. Add any note for the vendor.</p>
                <form method="post" action="<?= BASE_URL ?>api/company-action.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="confirm_order">
                    <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
                    <div class="mb-3">
                        <label class="form-label small">Note (optional)</label>
                        <textarea name="note" class="form-control form-control-sm" rows="2"
                                  placeholder="e.g. Available, ready to prepare"></textarea>
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Confirm Availability
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Action: Mark Prepared / Ready for Transport -->
        <?php if ($order['status'] === 'company_confirmed'): ?>
            <div class="card-soft p-4 mb-3">
                <h6 class="fw-bold mb-3"><i class="bi bi-box-seam"></i> Mark as Ready for Transport</h6>
                <p class="small text-muted mb-3">Once products are packed and ready for pickup, mark this order. The vendor will then assign transport.</p>
                <form method="post" action="<?= BASE_URL ?>api/company-action.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="ready_for_transport">
                    <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
                    <div class="mb-3">
                        <label class="form-label small">Note (optional)</label>
                        <textarea name="note" class="form-control form-control-sm" rows="2"
                                  placeholder="e.g. 2 boxes packed, ready for pickup"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-truck"></i> Mark Ready for Transport
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($order['status'] === 'ready_for_transport'): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i>
                <strong>Ready for Transport.</strong> Waiting for vendor to assign transport.
            </div>
        <?php endif; ?>

        <?php if ($shipment): ?>
            <div class="alert alert-info">
                <i class="bi bi-truck"></i>
                <strong>Transport assigned:</strong>
                <?= e($shipment['vehicle_no'] ?? 'N/A') ?> · Bilty: <?= e($shipment['bilty_no'] ?? 'N/A') ?>
                · Status: <?= e(SHIPMENT_STATUSES[$shipment['status']] ?? $shipment['status']) ?>
            </div>
        <?php endif; ?>
<?php
// Handover info (company ke liye)
if ($shipment && in_array($shipment['status'], ['assigned', 'picked_up', 'in_transit'], true)): 
    $tStmt = $pdo->prepare(
        "SELECT u.full_name, tc.company_name, u.phone 
         FROM users u 
         LEFT JOIN transport_companies tc ON tc.user_id = u.id 
         WHERE u.id = ? LIMIT 1"
    );
    $tStmt->execute([(int)$shipment['transport_user_id']]);
    $tInfo = $tStmt->fetch();
    $tName = $tInfo ? ($tInfo['company_name'] ?? $tInfo['full_name']) : 'N/A';
?>
    <div class="card-soft p-3 mb-3" style="border-left: 4px solid #06b6d4;">
        <h6 class="fw-bold mb-2">
            <i class="bi bi-box-arrow-up text-info"></i> Handover to Transport
        </h6>
        
        <div class="alert alert-light border small mb-3 py-2">
            <div><strong>Transport:</strong> <?= e($tName) ?></div>
            <?php if ($tInfo && $tInfo['phone']): ?>
                <div><strong>Phone:</strong> <?= e($tInfo['phone']) ?></div>
            <?php endif; ?>
            <?php if (!empty($shipment['vehicle_no'])): ?>
                <div><strong>Vehicle:</strong> <?= e($shipment['vehicle_no']) ?></div>
            <?php endif; ?>
        </div>

        <div class="text-muted small">
            <i class="bi bi-info-circle"></i>
            Products transport company ko handover karein. Delivery aur payment vendor manage karega.
        </div>
    </div>
<?php endif; ?>

        <!-- Timeline -->
        <!-- Timeline -->
        <div class="card-soft">
            <div class="p-3 border-bottom"><strong>Timeline</strong></div>
            <div class="p-3">
                <?php foreach ($timeline as $h): ?>
                    <div class="mb-3 ps-3 position-relative">
                        <span class="position-absolute bg-primary rounded-circle" style="width:8px;height:8px;left:0;top:6px;"></span>
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

    <div class="col-lg-4">
        <div class="card-soft p-3 mb-3">
            <h6 class="fw-bold mb-3">Shop Info</h6>
            <div class="small mb-1"><strong><?= e($order['shop_name']) ?></strong></div>
            <div class="small text-muted mb-1"><i class="bi bi-person"></i> <?= e($order['shop_owner']) ?></div>
            <div class="small text-muted"><i class="bi bi-geo-alt"></i> <?= e($order['shop_address']) ?></div>
        </div>

        <div class="card-soft p-3">
            <h6 class="fw-bold mb-3">Order Info</h6>
            <div class="d-flex justify-content-between small mb-1">
                <span class="text-muted">Order Date</span>
                <span><?= fmt_date($order['created_at']) ?></span>
            </div>
            <div class="d-flex justify-content-between small mb-1">
                <span class="text-muted">Status</span>
                <span><?= status_badge($order['status']) ?></span>
            </div>
            <div class="d-flex justify-content-between small">
                <span class="text-muted">Your Items</span>
                <span><?= count($items) ?></span>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/company_footer.php'; ?>