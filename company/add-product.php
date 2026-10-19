<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/product_helper.php';
require_role($pdo, ROLE_COMPANY);

$user = current_user($pdo);
$coStmt = $pdo->prepare("SELECT id FROM companies WHERE user_id = ? LIMIT 1");
$coStmt->execute([$user['id']]);
$companyId = (int)$coStmt->fetchColumn();

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

    // ============================================================
    // STRICT VALIDATION
    // ============================================================

    // ---- PRODUCT NAME ----
    if ($name === '') {
        $errors[] = 'Product name is required.';
    } elseif (mb_strlen($name) < 10) {
    $errors[] = 'Product name must be at least 10 characters.';
    } elseif (mb_strlen($name) > 200) {
        $errors[] = 'Product name cannot exceed 200 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $name)) {
        $errors[] = 'Product name must contain letters.';
    } elseif (preg_match('/(.)\1{4,}/', $name)) {
        $errors[] = 'Product name contains repeated characters (e.g. "jjjjj"). Please enter a real name.';
    } elseif (str_word_count($name) < 1) {
        $errors[] = 'Product name must contain at least one valid word.';
    }

    // ---- SKU ----
    if ($sku === '') {
        $errors[] = 'SKU is required.';
    } elseif (mb_strlen($sku) < 10) {
    $errors[] = 'SKU must be at least 10 characters.';
    } elseif (mb_strlen($sku) > 50) {
        $errors[] = 'SKU cannot exceed 50 characters.';
    } elseif (!preg_match('/^[A-Z0-9\-_]+$/', $sku)) {
        $errors[] = 'SKU can only contain A-Z, 0-9, dash (-) or underscore (_).';
    } elseif (preg_match('/^(.)\1+$/', $sku)) {
        $errors[] = 'SKU cannot be all same character (e.g. "AAAA").';
    }

    // ---- CATEGORY ----
    if ($catId <= 0) {
        $errors[] = 'Please select a category.';
    } else {
        $chk = $pdo->prepare("SELECT id FROM categories WHERE id = ? AND is_active = 1");
        $chk->execute([$catId]);
        if (!$chk->fetch()) $errors[] = 'Selected category does not exist.';
    }

    // ---- BRAND (optional, but if provided, must be valid) ----
    if ($brand !== '') {
        if (mb_strlen($brand) < 2) {
            $errors[] = 'Brand name must be at least 2 characters.';
        } elseif (mb_strlen($brand) > 100) {
            $errors[] = 'Brand name cannot exceed 100 characters.';
        } elseif (preg_match('/(.)\1{3,}/', $brand)) {
            $errors[] = 'Brand name contains too many repeated characters.';
        } elseif (!preg_match('/^[A-Za-z0-9\s\.\-&]+$/', $brand)) {
            $errors[] = 'Brand name contains invalid characters.';
        }
    }

    // ---- DESCRIPTION (optional, but if provided, must be meaningful) ----
    if ($description !== '') {
        if (mb_strlen($description) < 15) {
            $errors[] = 'Description must be at least 15 characters if provided.';
        } elseif (mb_strlen($description) > 2000) {
            $errors[] = 'Description cannot exceed 2000 characters.';
        } elseif (str_word_count($description) < 3) {
            $errors[] = 'Description must contain at least 3 words.';
        } elseif (preg_match('/(.)\1{5,}/', $description)) {
            $errors[] = 'Description contains too many repeated characters.';
        }
    }

    // ---- PRICE ----
    if ($basePrice <= 0) {
        $errors[] = 'Base price must be greater than 0.';
    } elseif ($basePrice > 10000000) {
        $errors[] = 'Base price seems too high (max 1 crore).';
    }

    // ---- MIN QTY ----
    if ($minQty < 1) {
        $errors[] = 'Minimum order quantity must be at least 1.';
    } elseif ($minQty > 999999) {
        $errors[] = 'Minimum order quantity is too high.';
    }

    // ---- UNIT ----
    $validUnits = ['pcs', 'box', 'coil', 'meter', 'kg', 'set'];
    if (!in_array($unit, $validUnits, true)) {
        $errors[] = 'Invalid unit selected.';
    }

    // ---- DUPLICATE SKU ----
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM products WHERE sku = ?");
        $stmt->execute([$sku]);
        if ($stmt->fetch()) $errors[] = 'SKU "' . $sku . '" already exists. Choose a different SKU.';

        // Duplicate name check (same company)
        $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ? AND company_id = ?");
        $stmt->execute([$name, $companyId]);
        if ($stmt->fetch()) $errors[] = 'You already have a product with this name.';
    }

    // ---- IMAGE VALIDATION ----
    if (!empty($_FILES['images']['name'][0])) {
        $allowedExt = ['jpg','jpeg','png','webp','gif'];
        $maxSize = 5 * 1024 * 1024;
        $count = 0;
        $imgErrors = [];
        foreach ($_FILES['images']['name'] as $i => $n) {
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($n, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt)) {
                $imgErrors[] = 'Invalid file type: ' . $n;
            }
            if ($_FILES['images']['size'][$i] > $maxSize) {
                $imgErrors[] = 'File too large (max 5 MB): ' . $n;
            }
            $count++;
        }
        if ($count > 4) $errors[] = 'Maximum 4 images allowed.';
        foreach ($imgErrors as $ie) $errors[] = $ie;
    }

    // ============================================================
    // INSERT
    // ============================================================
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $slug = generate_product_slug($pdo, $name, $sku);

            $stmt = $pdo->prepare(
                "INSERT INTO products (sku, name, slug, category_id, company_id, brand, description,
                  unit, base_price, show_price, min_order_qty, availability, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 'active', ?)"
            );
            $stmt->execute([
                $sku, $name, $slug, $catId, $companyId, $brand, $description,
                $unit, $basePrice, $minQty, $availability, $user['id']
            ]);
            $productId = (int)$pdo->lastInsertId();

            // Upload images
            if (!empty($_FILES['images']['name'][0])) {
                $paths = upload_product_images($_FILES['images'], $companyId, 4);
                $isPrimary = true;
                foreach ($paths as $path) {
                    $pdo->prepare(
                        "INSERT INTO product_images (product_id, image_path, is_primary, sort_order)
                         VALUES (?, ?, ?, 0)"
                    )->execute([$productId, $path, $isPrimary ? 1 : 0]);
                    $isPrimary = false;
                }
            }

            // Add to product_company_prices
            $pdo->prepare(
                "INSERT INTO product_company_prices (product_id, company_id, base_price)
                 VALUES (?, ?, ?)"
            )->execute([$productId, $companyId, $basePrice]);

            $pdo->commit();

            log_activity($pdo, 'product_created', 'company', $productId, 'Product: ' . $name);
            flash_success('Product added successfully!');
            redirect(BASE_URL . 'company/products.php');

        } catch (Throwable $ex) {
            $pdo->rollBack();
            error_log('Add product error: ' . $ex->getMessage());
            $errors[] = 'Could not add product. Please try again.';
        }
    }
}

$pageTitle = 'Add Product';
require_once dirname(__DIR__) . '/includes/company_header.php';
?>

<div class="mb-3">
    <a href="<?= BASE_URL ?>company/products.php" class="text-decoration-none small">
        <i class="bi bi-arrow-left"></i> Back to Products
    </a>
</div>

<h2 class="fw-bold mb-1">Add New Product</h2>
<p class="text-muted small mb-4">Fill the details below to add a product to your catalog</p>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <strong><i class="bi bi-exclamation-triangle"></i> Please fix the following:</strong>
        <ul class="mb-0 ps-3 mt-2">
            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" id="productForm" novalidate>
    <?= csrf_field() ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card-soft p-4 mb-3">
                <h6 class="fw-bold mb-3">Basic Information</h6>
                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label small">Product Name <span class="text-danger">*</span></label>
                       <input type="text" name="name" id="name" class="form-control" required
       minlength="10" maxlength="200"
       placeholder="e.g. XYZ Ceiling Fan 56 inch"
       value="<?= e($_POST['name'] ?? '') ?>">
<div class="form-text">Min 10 chars, meaningful name.</div>
                        <div class="field-error" data-for="name"></div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small">SKU <span class="text-danger">*</span></label>
                        <input type="text" name="sku" id="sku" class="form-control" required
       minlength="10" maxlength="50"
       placeholder="e.g. FAN-XYZ-56-2026"
       style="text-transform:uppercase;"
       value="<?= e($_POST['sku'] ?? '') ?>">
<div class="form-text">Min 10 chars — A-Z, 0-9, dash only.</div>
                        <div class="field-error" data-for="sku"></div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small">Category <span class="text-danger">*</span></label>
                        <select name="category_id" id="category_id" class="form-select" required>
                            <option value="">— Select —</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= (($_POST['category_id'] ?? 0) == $c['id']) ? 'selected' : '' ?>>
                                    <?= e($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="field-error" data-for="category_id"></div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small">Brand</label>
                        <input type="text" name="brand" id="brand" class="form-control"
                               minlength="2" maxlength="100"
                               placeholder="e.g. XYZ (optional)"
                               value="<?= e($_POST['brand'] ?? '') ?>">
                        <div class="field-error" data-for="brand"></div>
                    </div>

                    <div class="col-12">
                        <label class="form-label small">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="3"
                                  minlength="15" maxlength="2000"
                                  placeholder="Product details, specifications, features (min 15 chars if provided)..."><?= e($_POST['description'] ?? '') ?></textarea>
                        <div class="form-text">
                            <span id="descCount">0</span> / 2000 characters
                        </div>
                        <div class="field-error" data-for="description"></div>
                    </div>
                </div>
            </div>

            <div class="card-soft p-4 mb-3">
                <h6 class="fw-bold mb-3">Pricing & Availability</h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small">Base Price (PKR) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" max="10000000" name="base_price" id="base_price"
                               class="form-control" required
                               placeholder="8000"
                               value="<?= e($_POST['base_price'] ?? '') ?>">
                        <div class="form-text">Your wholesale price.</div>
                        <div class="field-error" data-for="base_price"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Unit <span class="text-danger">*</span></label>
                        <select name="unit" class="form-select" required>
                            <option value="pcs"  <?= ($_POST['unit'] ?? '') === 'pcs'  ? 'selected' : '' ?>>Pieces (pcs)</option>
                            <option value="box"  <?= ($_POST['unit'] ?? '') === 'box'  ? 'selected' : '' ?>>Box</option>
                            <option value="coil" <?= ($_POST['unit'] ?? '') === 'coil' ? 'selected' : '' ?>>Coil</option>
                            <option value="meter"<?= ($_POST['unit'] ?? '') === 'meter'? 'selected' : '' ?>>Meter</option>
                            <option value="kg"   <?= ($_POST['unit'] ?? '') === 'kg'   ? 'selected' : '' ?>>Kilogram</option>
                            <option value="set"  <?= ($_POST['unit'] ?? '') === 'set'  ? 'selected' : '' ?>>Set</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Min Order Qty</label>
                        <input type="number" min="1" max="999999" name="min_order_qty" class="form-control"
                               value="<?= (int)($_POST['min_order_qty'] ?? 1) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Availability</label>
                        <select name="availability" class="form-select">
                            <option value="in_stock"   <?= ($_POST['availability'] ?? '') === 'in_stock'   ? 'selected' : '' ?>>In Stock</option>
                            <option value="limited"    <?= ($_POST['availability'] ?? '') === 'limited'    ? 'selected' : '' ?>>Limited</option>
                            <option value="out_of_stock" <?= ($_POST['availability'] ?? '') === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card-soft p-4">
                <h6 class="fw-bold mb-3">Product Images</h6>
                <label class="form-label small">Upload Images (max 4, JPG/PNG/WebP, 5 MB each)</label>
                <input type="file" name="images[]" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
                <div class="form-text">Pehli image **primary** hogi.</div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card-soft p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-shield-check text-success"></i> Validation Rules</h6>
                <ul class="small text-muted ps-3 mb-0">
                    <li>Product name: <strong>min 10 chars</strong></li>
                    <li>SKU: <strong>min 10 chars</strong>, A-Z, 0-9, dash</li>
                    <li>Brand: min 2 chars (agar diya ho)</li>
                    <li>Description: min 15 chars (agar diya ho)</li>
                    <li>Price: between 0.01 and 1 crore</li>
                    <li>Max 4 images, 5 MB each</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="mt-4 d-flex justify-content-end gap-2">
        <a href="<?= BASE_URL ?>company/products.php" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-check-circle"></i> Add Product
        </button>
    </div>
</form>

<style>
.field-error { color: #dc3545; font-size: .75rem; margin-top: 4px; display: none; }
.field-error.show { display: block; }
.form-control.is-invalid, .form-select.is-invalid { border-color: #dc3545; }
.form-control.is-valid, .form-select.is-valid { border-color: #10b981; }
</style>

<script>
// ============================================================
// LIVE VALIDATION
// ============================================================

function showError(fieldId, message) {
    const errorEl = document.querySelector(`.field-error[data-for="${fieldId}"]`);
    const inputEl = document.getElementById(fieldId);
    if (!errorEl || !inputEl) return;

    if (message) {
        errorEl.textContent = message;
        errorEl.classList.add('show');
        inputEl.classList.add('is-invalid');
        inputEl.classList.remove('is-valid');
    } else {
        errorEl.classList.remove('show');
        inputEl.classList.remove('is-invalid');
        if (inputEl.value.trim() !== '') inputEl.classList.add('is-valid');
        else inputEl.classList.remove('is-valid');
    }
}

// Product Name
document.getElementById('name').addEventListener('input', function() {
    const v = this.value.trim();
    if (v === '')                return showError('name', '');
if (v.length < 10)           return showError('name', 'Product name must be at least 10 characters.');
    if (v.length > 200)          return showError('name', 'Product name is too long (max 200).');
    if (!/[A-Za-z]/.test(v))     return showError('name', 'Product name must contain letters.');
    if (/(.)\1{4,}/.test(v))     return showError('name', 'Product name contains repeated characters.');
    showError('name', '');
});

// SKU
document.getElementById('sku').addEventListener('input', function() {
    let v = this.value.toUpperCase().replace(/[^A-Z0-9\-_]/g, '');
    this.value = v;
    if (v === '')                    return showError('sku', '');
  if (v.length < 10)               return showError('sku', 'SKU must be at least 10 characters.');
    if (v.length > 50)               return showError('sku', 'SKU is too long (max 50).');
    if (/^(.)\1+$/.test(v))          return showError('sku', 'SKU cannot be all same character.');
    showError('sku', '');
});

// Category
document.getElementById('category_id').addEventListener('change', function() {
    if (!this.value) return showError('category_id', 'Please select a category.');
    showError('category_id', '');
});

// Brand
document.getElementById('brand').addEventListener('input', function() {
    const v = this.value.trim();
    if (v === '')                    return showError('brand', '');
    if (v.length < 2)                return showError('brand', 'Brand must be at least 2 characters.');
    if (v.length > 100)              return showError('brand', 'Brand is too long.');
    if (/(.)\1{3,}/.test(v))         return showError('brand', 'Brand contains repeated characters.');
    if (!/^[A-Za-z0-9\s\.\-&]+$/.test(v)) return showError('brand', 'Brand contains invalid characters.');
    showError('brand', '');
});

// Description + character counter
const descEl = document.getElementById('description');
const descCount = document.getElementById('descCount');
descEl.addEventListener('input', function() {
    const v = this.value.trim();
    descCount.textContent = this.value.length;
    if (v === '')                    return showError('description', '');
    if (v.length < 15)               return showError('description', 'Description must be at least 15 characters.');
    if (v.length > 2000)             return showError('description', 'Description is too long (max 2000).');
    if (v.split(/\s+/).length < 3)   return showError('description', 'Description must contain at least 3 words.');
    showError('description', '');
});

// Base Price
document.getElementById('base_price').addEventListener('input', function() {
    const v = parseFloat(this.value);
    if (isNaN(v) || v <= 0)          return showError('base_price', this.value === '' ? '' : 'Price must be greater than 0.');
    if (v > 10000000)                return showError('base_price', 'Price is too high (max 1 crore).');
    showError('base_price', '');
});

// ============================================================
// SUBMIT VALIDATION
// ============================================================
document.getElementById('productForm').addEventListener('submit', function(e) {
    let hasError = false;

    // Trigger all validation by faking input events
    ['name', 'sku', 'brand', 'description', 'base_price'].forEach(id => {
        const el = document.getElementById(id);
        const ev = new Event('input', { bubbles: true });
        el.dispatchEvent(ev);
    });

    // Category change
    const catEl = document.getElementById('category_id');
    const catEv = new Event('change', { bubbles: true });
    catEl.dispatchEvent(catEv);

    // Check if any field has error
    document.querySelectorAll('.is-invalid').forEach(() => hasError = true);

    if (hasError) {
        e.preventDefault();
        // Scroll to first error
        const firstError = document.querySelector('.is-invalid');
        if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        firstError?.focus();
    }
});
</script>

<?php require_once dirname(__DIR__) . '/includes/company_footer.php'; ?>