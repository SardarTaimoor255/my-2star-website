<?php
/**
 * shop/request-price.php
 */
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_SHOP);

$user = current_user($pdo);
$shopStmt = $pdo->prepare("SELECT id FROM shops WHERE user_id = ? LIMIT 1");
$shopStmt->execute([$user['id']]);
$shopId = (int)$shopStmt->fetchColumn();

$preselect = (int)($_GET['product_id'] ?? 0);

// Products without confirmed price
$stmt = $pdo->prepare(
    "SELECT p.*, c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.status='active' AND p.show_price = 0
     ORDER BY p.name LIMIT 100"
);
$stmt->execute();
$products = $stmt->fetchAll();

$pageTitle = 'Request Price';
require_once dirname(__DIR__) . '/includes/shop_header.php';
?>

<div class="mb-3">
    <a href="<?= BASE_URL ?>shop/price-requests.php" class="text-decoration-none small">
        <i class="bi bi-arrow-left"></i> Back to Price Requests
    </a>
</div>

<div class="card-soft p-4">
    <h4 class="fw-bold mb-1">Request Price</h4>
    <p class="text-muted small mb-4">Select product and quantity. We'll get back to you with the final price.</p>

    <form method="post" action="<?= BASE_URL ?>api/price-request.php">
        <?= csrf_field() ?>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">Product <span class="text-danger">*</span></label>
                <select name="product_id" class="form-select" required>
                    <option value="">— Select a product —</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= $preselect === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= e($p['name']) ?> (<?= e($p['sku']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Quantity <span class="text-danger">*</span></label>
                <input type="number" name="quantity" class="form-control" min="1" value="1" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Desired Price <small class="text-muted">(optional)</small></label>
                <input type="number" name="desired_price" class="form-control" step="0.01" min="0"
                       placeholder="e.g. 8000">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Note (optional)</label>
            <textarea name="notes" class="form-control" rows="3"
                      placeholder="Any additional information for the vendor..."></textarea>
        </div>

        <div class="text-end">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-send"></i> Submit Request
            </button>
        </div>
    </form>
</div>

<?php require_once dirname(__DIR__) . '/includes/shop_footer.php'; ?>