<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/product_helper.php';
require_role($pdo, ROLE_COMPANY);

$user = current_user($pdo);
$coStmt = $pdo->prepare("SELECT id FROM companies WHERE user_id = ? LIMIT 1");
$coStmt->execute([$user['id']]);
$companyId = (int)$coStmt->fetchColumn();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND company_id = ? LIMIT 1");
$stmt->execute([$id, $companyId]);
$product = $stmt->fetch();
if (!$product) { flash_error('Product not found.'); redirect(BASE_URL . 'company/products.php'); }

require_once dirname(__DIR__) . '/includes/cache.php';

$categories = cache_remember('company_categories', function() use ($pdo) {
    return $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
}, 600);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require($_POST['csrf_token'] ?? null);

    $sku         = strtoupper(trim($_POST['sku'] ?? ''));
    $name        = clean($_POST['name'] ?? '');
    $catId       = (int)($_POST['category_id'] ?? 0);
    $brand       = clean($_POST['brand'] ?? '');
    $description = clean($_POST['description'] ?? '');
    $unit        = clean($_POST['unit'] ?? 'pcs');
    $basePrice   = (float)($_POST['base_price'] ?? 0);
    $minQty      = max(1, (int)($_POST['min_order_qty'] ?? 1));
    $availability = $_POST['availability'] ?? 'in_stock';
    $status      = $_POST['status'] ?? 'active';

    if ($sku === '')              $errors[] = 'SKU is required.';
    if (!preg_match('/^[A-Z0-9\-]+$/', $sku)) $errors[] = 'SKU format invalid.';
    if ($name === '')             $errors[] = 'Product name is required.';
    if ($catId <= 0)              $errors[] = 'Category is required.';
    if ($basePrice <= 0)          $errors[] = 'Base price required.';

    // Duplicate SKU (excluding current)
    if (empty($errors)) {
        $chk = $pdo->prepare("SELECT id FROM products WHERE sku = ? AND id <> ?");
        $chk->execute([$sku, $id]);
        if ($chk->fetch()) $errors[] = 'SKU already used by another product.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $pdo->prepare(
                "UPDATE products SET sku=?, name=?, category_id=?, brand=?, description=?,
                 unit=?, base_price=?, min_order_qty=?, availability=?, status=?
                 WHERE id=?"
            )->execute([
                $sku, $name, $catId, $brand, $description,
                $unit, $basePrice, $minQty, $availability, $status, $id
            ]);

            // Update product_company_prices
            $pdo->prepare(
                "INSERT INTO product_company_prices (product_id, company_id, base_price)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE base_price = VALUES(base_price)"
            )->execute([$id, $companyId, $basePrice]);

            // New images
            if (!empty($_FILES['images']['name'][0])) {
                $existingCount = count(product_images($pdo, $id));
                $slots = max(0, 4 - $existingCount);
                if ($slots > 0) {
                    $paths = upload_product_images($_FILES['images'], $companyId, $slots);
                    $hasPrimary = (bool)$pdo->query("SELECT COUNT(*) FROM product_images WHERE product_id = $id AND is_primary = 1")->fetchColumn();
                    foreach ($paths as $path) {
                        $pdo->prepare(
                            "INSERT INTO product_images (product_id, image_path, is_primary) VALUES (?, ?, ?)"
                        )->execute([$id, $path, $hasPrimary ? 0 : 1]);
                        $hasPrimary = true;
                    }
                }
            }

            $pdo->commit();

            log_activity($pdo, 'product_updated', 'company', $id, 'Product: ' . $name);
            flash_success('Product updated successfully.');
            redirect(BASE_URL . 'company/edit-product.php?id=' . $id);

        } catch (Throwable $ex) {
            $pdo->rollBack();
            error_log('Edit product error: ' . $ex->getMessage());
            $errors[] = 'Update failed. Please try again.';
        }
    }
}

$images = product_images($pdo, $id);
$pageTitle = 'Edit Product';
require_once dirname(__DIR__) . '/includes/company_header.php';
?>

<div class="mb-3">
    <a href="<?= BASE_URL ?>company/products.php" class="text-decoration-none small">
        <i class="bi bi-arrow-left"></i> Back to Products
    </a>
</div>

<h2 class="fw-bold mb-1">Edit Product</h2>
<p class="text-muted small mb-4">Update details, images, and pricing</p>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card-soft p-4 mb-3">
                <h6 class="fw-bold mb-3">Basic Information</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small">Product Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required
                               value="<?= e($_POST['name'] ?? $product['name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">SKU <span class="text-danger">*</span></label>
                        <input type="text" name="sku" class="form-control" required style="text-transform:uppercase;"
                               value="<?= e($_POST['sku'] ?? $product['sku']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Category <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-select" required>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= (($_POST['category_id'] ?? $product['category_id']) == $c['id']) ? 'selected' : '' ?>>
                                    <?= e($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Brand</label>
                        <input type="text" name="brand" class="form-control"
                               value="<?= e($_POST['brand'] ?? $product['brand']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Description</label>
                        <textarea name="description" class="form-control" rows="3"><?= e($_POST['description'] ?? $product['description']) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card-soft p-4 mb-3">
                <h6 class="fw-bold mb-3">Pricing & Availability</h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small">Base Price (PKR) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="base_price" class="form-control" required
                               value="<?= e($_POST['base_price'] ?? $product['base_price']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Unit</label>
                        <select name="unit" class="form-select">
                            <?php foreach (['pcs'=>'Pieces','box'=>'Box','coil'=>'Coil','meter'=>'Meter','kg'=>'Kilogram','set'=>'Set'] as $k=>$v): ?>
                                <option value="<?= $k ?>" <?= ($_POST['unit'] ?? $product['unit']) === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Min Order Qty</label>
                        <input type="number" min="1" name="min_order_qty" class="form-control"
                               value="<?= (int)($_POST['min_order_qty'] ?? $product['min_order_qty']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Availability</label>
                        <select name="availability" class="form-select">
                            <?php foreach (['in_stock'=>'In Stock','limited'=>'Limited','out_of_stock'=>'Out of Stock'] as $k=>$v): ?>
                                <option value="<?= $k ?>" <?= ($_POST['availability'] ?? $product['availability']) === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Status</label>
                        <select name="status" class="form-select">
                            <option value="active"   <?= ($_POST['status'] ?? $product['status']) === 'active'   ? 'selected' : '' ?>>Active (Visible)</option>
                            <option value="inactive" <?= ($_POST['status'] ?? $product['status']) === 'inactive' ? 'selected' : '' ?>>Inactive (Hidden)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Existing Images -->
            <?php if (!empty($images)): ?>
                <div class="card-soft p-4 mb-3">
                    <h6 class="fw-bold mb-3">Current Images</h6>
                    <div class="row g-2">
                        <?php foreach ($images as $img): ?>
                            <div class="col-6 col-md-3">
                                <div style="position:relative; border:1px solid #e6e9f2; border-radius:8px; overflow:hidden;">
                                    <img src="<?= UPLOADS_URL . e($img['image_path']) ?>" 
                                         style="width:100%; height:120px; object-fit:cover;">
                                    <?php if ($img['is_primary']): ?>
                                        <span class="badge text-bg-primary" style="position:absolute; top:6px; left:6px; font-size:.65rem;">Primary</span>
                                    <?php endif; ?>
                                    <div style="position:absolute; top:6px; right:6px; display:flex; gap:3px;">
                                        <?php if (!$img['is_primary']): ?>
                                            <button type="button" class="btn btn-sm btn-light" style="padding:2px 6px;"
                                                    onclick="setPrimary(<?= (int)$img['id'] ?>)" title="Make primary">
                                                <i class="bi bi-star"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-danger" style="padding:2px 6px;"
                                                onclick="deleteImage(<?= (int)$img['id'] ?>)" title="Delete">
                                            <i class="bi bi-x"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card-soft p-4">
                <h6 class="fw-bold mb-3">Add More Images</h6>
                <input type="file" name="images[]" class="form-control" accept="image/*" multiple>
                <div class="form-text">Max 4 total images per product.</div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card-soft p-4">
                <h6 class="fw-bold mb-3">Product Info</h6>
                <div class="small text-muted mb-2"><strong>Created:</strong><br><?= fmt_datetime($product['created_at']) ?></div>
                <div class="small text-muted mb-2"><strong>Last Updated:</strong><br><?= fmt_datetime($product['updated_at']) ?></div>
                <div class="small text-muted"><strong>Product ID:</strong> #<?= (int)$product['id'] ?></div>
            </div>
        </div>
    </div>

    <div class="mt-4 d-flex justify-content-end gap-2">
        <a href="<?= BASE_URL ?>company/products.php" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-check-circle"></i> Save Changes
        </button>
    </div>
</form>

<script>
async function apiPost(data) {
    const fd = new FormData();
    for (const k in data) fd.append(k, data[k]);
    fd.append('csrf_token', '<?= e(csrf_token()) ?>');
    const res = await fetch('<?= BASE_URL ?>api/company-product-action.php', {
        method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    return res.json();
}
async function deleteImage(id) {
    if (!confirm('Delete this image?')) return;
    const r = await apiPost({ action: 'delete_image', image_id: id });
    if (r.success) location.reload();
    else alert(r.message || 'Failed');
}
async function setPrimary(id) {
    const r = await apiPost({ action: 'set_primary_image', image_id: id });
    if (r.success) location.reload();
    else alert(r.message || 'Failed');
}
</script>

<?php require_once dirname(__DIR__) . '/includes/company_footer.php'; ?>