<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_VENDOR);

$user = current_user($pdo);
$vendorStmt = $pdo->prepare("SELECT * FROM vendors WHERE user_id = ? LIMIT 1");
$vendorStmt->execute([$user['id']]);
$vendor = $vendorStmt->fetch();

// Toggle show_price or update product base price
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require($_POST['csrf_token'] ?? null);
    $productId = (int)($_POST['product_id'] ?? 0);
    $showPrice = isset($_POST['show_price']) ? 1 : 0;

    if ($productId > 0) {
        $pdo->prepare("UPDATE products SET show_price = ? WHERE id = ?")->execute([$showPrice, $productId]);
        log_activity($pdo, 'product_price_visibility_changed', 'vendor', $productId, 'Show price: ' . $showPrice);
        flash_success('Product price visibility updated.');
    }
    redirect(BASE_URL . 'vendor/products.php');
}

$products = $pdo->query(
    "SELECT p.*, c.name AS category_name, co.company_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     LEFT JOIN companies co ON co.id = p.company_id
     ORDER BY p.id DESC"
)->fetchAll();

$pageTitle = 'Products';
require_once dirname(__DIR__) . '/includes/vendor_header.php';
?>

<h2 class="fw-bold mb-1">Products</h2>
<p class="text-muted small mb-4">Control which products show prices to shops directly</p>

<div class="alert alert-info small">
    <i class="bi bi-info-circle"></i>
    <strong>How it works:</strong> If <em>Show Price</em> is ON, shops see the final price immediately.
    If OFF, shops see "Price Pending" and must submit a price request.
</div>

<div class="card-soft">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>SKU</th><th>Product</th><th>Category</th><th class="text-end">Base Price</th>
                    <th class="text-center">Show Price</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td class="small"><code><?= e($p['sku']) ?></code></td>
                    <td class="small">
                        <div class="fw-semibold"><?= e($p['name']) ?></div>
                        <?php if ($p['brand']): ?><div class="text-muted" style="font-size:.72rem;"><?= e($p['brand']) ?></div><?php endif; ?>
                    </td>
                    <td class="small text-muted"><?= e($p['category_name'] ?? '—') ?></td>
                    <td class="text-end small"><?= $p['base_price'] !== null ? money($p['base_price']) : '—' ?></td>
                    <td class="text-center">
                        <form method="post" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                            <div class="form-check form-switch d-inline-block">
                                <input class="form-check-input" type="checkbox" name="show_price"
                                       <?= (int)$p['show_price'] === 1 ? 'checked' : '' ?>
                                       onchange="this.form.submit()">
                            </div>
                        </form>
                    </td>
                    <td class="text-end small text-muted">
                        <?= $p['show_price'] ? 'Visible' : 'Pending' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/vendor_footer.php'; ?>