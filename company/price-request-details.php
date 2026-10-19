<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_COMPANY);

$user = current_user($pdo);
$coStmt = $pdo->prepare("SELECT id FROM companies WHERE user_id = ? LIMIT 1");
$coStmt->execute([$user['id']]);
$companyId = (int)$coStmt->fetchColumn();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT pr.*, s.shop_name, s.address AS shop_address
     FROM price_requests pr JOIN shops s ON s.id = pr.shop_id
     WHERE pr.id = ? LIMIT 1"
);
$stmt->execute([$id]);
$request = $stmt->fetch();
if (!$request) { flash_error('Request not found.'); redirect(BASE_URL . 'company/price-requests.php'); }

// Only my items
$stmt = $pdo->prepare(
    "SELECT pri.*, p.name AS product_name, p.sku, p.unit
     FROM price_request_items pri
     JOIN products p ON p.id = pri.product_id
     WHERE pri.request_id = ? AND pri.company_id = ?"
);
$stmt->execute([$id, $companyId]);
$items = $stmt->fetchAll();

if (empty($items)) { flash_error('No items assigned to your company.'); redirect(BASE_URL . 'company/price-requests.php'); }

$pageTitle = 'Respond to ' . $request['request_no'];
require_once dirname(__DIR__) . '/includes/company_header.php';
?>

<div class="mb-3">
    <a href="<?= BASE_URL ?>company/price-requests.php" class="text-decoration-none small">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1"><?= e($request['request_no']) ?></h2>
        <div class="text-muted small">From <strong><?= e($request['shop_name']) ?></strong> · <?= fmt_datetime($request['created_at']) ?></div>
    </div>
</div>

<?php if ($request['status'] === 'sent_to_company'): ?>
    <div class="card-soft p-4">
        <h6 class="fw-bold mb-3"><i class="bi bi-check2-square"></i> Confirm Availability & Provide Base Price</h6>
        <p class="small text-muted mb-3">Enter the base price for each item. This is your <strong>wholesale price</strong> — vendor commission is added by the vendor, not visible to you.</p>

        <form method="post" action="<?= BASE_URL ?>api/company-action.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="respond_price">
            <input type="hidden" name="id" value="<?= (int)$request['id'] ?>">

            <div class="table-responsive mb-3">
                <table class="table align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Shop Desired</th>
                            <th style="width:180px;">Availability</th>
                            <th style="width:180px;">Base Price (PKR)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td class="small">
                                <div class="fw-semibold"><?= e($it['product_name']) ?></div>
                                <div class="text-muted" style="font-size:.72rem;">SKU: <?= e($it['sku']) ?> · Unit: <?= e($it['unit']) ?></div>
                            </td>
                            <td class="text-end small"><?= (int)$it['quantity'] ?></td>
                            <td class="text-end small"><?= $it['shop_desired_price'] ? money($it['shop_desired_price']) : '—' ?></td>
                            <td>
                                <select name="availability[<?= (int)$it['id'] ?>]" class="form-select form-select-sm">
                                    <option value="available">Available</option>
                                    <option value="partial">Partial</option>
                                    <option value="unavailable">Unavailable</option>
                                </select>
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0"
                                       name="base_price[<?= (int)$it['id'] ?>]"
                                       class="form-control form-control-sm" required
                                       value="<?= e($it['company_base_price'] ?? '') ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="text-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-send"></i> Send Response to Vendor
                </button>
            </div>
        </form>
    </div>
<?php else: ?>
    <div class="card-soft">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Product</th><th class="text-end">Qty</th><th class="text-end">Your Base Price</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td class="small">
                            <div class="fw-semibold"><?= e($it['product_name']) ?></div>
                            <div class="text-muted" style="font-size:.72rem;">SKU: <?= e($it['sku']) ?></div>
                        </td>
                        <td class="text-end small"><?= (int)$it['quantity'] ?></td>
                        <td class="text-end small"><?= $it['company_base_price'] ? money($it['company_base_price']) : '—' ?></td>
                        <td>
                            <span class="badge text-bg-<?= $it['status']==='quoted'?'success':'secondary' ?>">
                                <?= e(ucfirst($it['status'])) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="alert alert-info mt-3 small">
        <i class="bi bi-info-circle"></i> You have already responded to this request. The vendor will finalize the quote.
    </div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/company_footer.php'; ?>