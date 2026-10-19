<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_VENDOR);

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT pr.*, s.shop_name, s.address AS shop_address
     FROM price_requests pr JOIN shops s ON s.id = pr.shop_id
     WHERE pr.id = ? LIMIT 1"
);
$stmt->execute([$id]);
$request = $stmt->fetch();
if (!$request) { flash_error('Request not found.'); redirect(BASE_URL . 'vendor/price-requests.php'); }

$stmt = $pdo->prepare(
    "SELECT pri.*, p.name AS product_name, p.sku, p.unit,
            c.name AS category_name
     FROM price_request_items pri
     JOIN products p ON p.id = pri.product_id
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE pri.request_id = ?"
);
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$companies = $pdo->query("SELECT id, company_name FROM companies ORDER BY company_name")->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM price_request_history WHERE request_id = ? ORDER BY id ASC");
$stmt->execute([$id]);
$history = $stmt->fetchAll();

$pageTitle = 'Price Request ' . $request['request_no'];
require_once dirname(__DIR__) . '/includes/vendor_header.php';
?>

<div class="mb-3">
    <a href="<?= BASE_URL ?>vendor/price-requests.php" class="text-decoration-none small">
        <i class="bi bi-arrow-left"></i> Back to Requests
    </a>
</div>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1"><?= e($request['request_no']) ?></h2>
        <div class="text-muted small">
            From <strong><?= e($request['shop_name']) ?></strong> · <?= fmt_datetime($request['created_at']) ?>
        </div>
    </div>
    <?php
    $clr = ['pending'=>'secondary','sent_to_company'=>'info','quoted'=>'primary','accepted'=>'success','rejected'=>'danger','countered'=>'warning','expired'=>'dark','converted'=>'success'][$request['status']] ?? 'secondary';
    ?>
    <span class="badge text-bg-<?= $clr ?> fs-6"><?= e(PRICE_REQUEST_STATUSES[$request['status']] ?? $request['status']) ?></span>
</div>

<?php if ($request['notes']): ?>
    <div class="alert alert-light border small mb-4">
        <strong>Shop's note:</strong> <?= e($request['notes']) ?>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <!-- Items -->
        <div class="card-soft">
            <div class="p-3 border-bottom"><strong>Requested Items</strong></div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Shop Desired</th>
                            <th class="text-end">Company Price</th>
                            <th class="text-end">Commission</th>
                            <th class="text-end">Final</th>
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
                            <td class="text-end small"><?= $it['company_base_price'] ? money($it['company_base_price']) : '—' ?></td>
                            <td class="text-end small"><?= $it['vendor_commission'] !== null ? money($it['vendor_commission']) : '—' ?></td>
                            <td class="text-end small fw-semibold"><?= $it['final_price'] ? money($it['final_price']) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Action: Send to Company -->
        <?php if ($request['status'] === 'pending'): ?>
            <div class="card-soft p-3 mt-3">
                <h6 class="fw-bold mb-2"><i class="bi bi-send"></i> Send to Company</h6>
                <p class="small text-muted mb-3">Select which company will supply these items. The company will confirm availability and provide base price.</p>
                <form method="post" action="<?= BASE_URL ?>api/vendor-action.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="send_to_company">
                    <input type="hidden" name="id" value="<?= (int)$request['id'] ?>">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-8">
                            <label class="form-label small">Company</label>
                            <select name="company_id" class="form-select form-select-sm" required>
                                <option value="">— Select company —</option>
                                <?php foreach ($companies as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>"><?= e($c['company_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                <i class="bi bi-send"></i> Send to Company
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Action: Add Commission and Quote (after company responds) -->
        <?php if (in_array($request['status'], ['sent_to_company','countered'], true)): ?>
            <div class="card-soft p-3 mt-3">
                <h6 class="fw-bold mb-2"><i class="bi bi-cash-coin"></i> Add Commission & Send Quote</h6>
                <p class="small text-muted mb-3">
                    Company has provided base prices. Now enter your commission for each item.
                    <br><small class="text-warning"><i class="bi bi-info-circle"></i> Until Phase 9 (Company Portal) is built, enter the company base price manually.</small>
                </p>
                <form method="post" action="<?= BASE_URL ?>api/vendor-action.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="quote">
                    <input type="hidden" name="id" value="<?= (int)$request['id'] ?>">

                    <div class="table-responsive mb-3">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th class="text-end" style="width:130px;">Company Price</th>
                                    <th class="text-end" style="width:130px;">Commission</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($items as $it): ?>
                                <tr>
                                    <td class="small">
                                        <?= e($it['product_name']) ?>
                                        <div class="text-muted" style="font-size:.7rem;">Qty: <?= (int)$it['quantity'] ?></div>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0" required
                                               name="company_price[<?= (int)$it['id'] ?>]"
                                               class="form-control form-control-sm"
                                               value="<?= e($it['company_base_price'] ?? '') ?>">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0" required
                                               name="commission[<?= (int)$it['id'] ?>]"
                                               class="form-control form-control-sm"
                                               value="<?= e($it['vendor_commission'] ?? ($vendor['default_commission_value'] ?? 500)) ?>">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-send-check"></i> Send Final Quote to Shop
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right: Timeline + Info -->
    <div class="col-lg-4">
        <div class="card-soft p-3 mb-3">
            <h6 class="fw-bold mb-3">Timeline</h6>
            <div class="ps-3" style="border-left:2px solid #e6e9f2;">
                <?php foreach ($history as $h): ?>
                    <div class="mb-3 position-relative">
                        <div class="fw-semibold small"><?= e(ucwords(str_replace('_',' ',$h['new_status']))) ?></div>
                        <div class="text-muted" style="font-size:.7rem;">
                            <?= fmt_datetime($h['created_at']) ?>
                            <?php if ($h['changed_by_role']): ?> · <?= e(ucfirst($h['changed_by_role'])) ?><?php endif; ?>
                        </div>
                        <?php if ($h['note']): ?>
                            <div class="small fst-italic text-muted">"<?= e($h['note']) ?>"</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card-soft p-3">
            <h6 class="fw-bold mb-3">Shop Info</h6>
            <div class="small mb-1"><strong><?= e($request['shop_name']) ?></strong></div>
            <div class="small text-muted"><?= e($request['shop_address'] ?? '') ?></div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/vendor_footer.php'; ?>