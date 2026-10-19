<?php
/**
 * admin/products.php
 */
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_ADMIN);
require_once dirname(__DIR__) . '/includes/pagination.php';
// Add / Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require($_POST['csrf_token'] ?? null);

    $id          = (int)($_POST['id'] ?? 0);
    $sku         = clean($_POST['sku'] ?? '');
    $name        = clean($_POST['name'] ?? '');
    $catId       = (int)($_POST['category_id'] ?? 0);
    $companyId   = (int)($_POST['company_id'] ?? 0) ?: null;
    $brand       = clean($_POST['brand'] ?? '');
    $description = clean($_POST['description'] ?? '');
    $unit        = clean($_POST['unit'] ?? 'pcs') ?: 'pcs';
    $basePrice   = (float)($_POST['base_price'] ?? 0);
    $showPrice   = isset($_POST['show_price']) ? 1 : 0;
    $minQty      = max(1, (int)($_POST['min_order_qty'] ?? 1));
    $availability = $_POST['availability'] ?? 'in_stock';
    $status      = $_POST['status'] ?? 'active';

    $errors = [];
    if ($sku === '')    $errors[] = 'SKU required.';
    if ($name === '')   $errors[] = 'Name required.';
    if ($catId <= 0)    $errors[] = 'Category required.';

    // Duplicate SKU check
    if (empty($errors)) {
        $dup = $pdo->prepare("SELECT id FROM products WHERE sku=? AND id<>?");
        $dup->execute([$sku, $id]);
        if ($dup->fetch()) $errors[] = 'SKU already exists.';
    }

    if (empty($errors)) {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name)) . '-' . strtolower($sku);

        if ($id > 0) {
            $sql = "UPDATE products SET sku=?, name=?, slug=?, category_id=?, company_id=?,
                       brand=?, description=?, unit=?, base_price=?, show_price=?,
                       min_order_qty=?, availability=?, status=? WHERE id=?";
            $pdo->prepare($sql)->execute([
                $sku, $name, $slug, $catId, $companyId, $brand, $description, $unit,
                $basePrice, $showPrice, $minQty, $availability, $status, $id
            ]);
            flash_success('Product updated.');
        } else {
            $sql = "INSERT INTO products
                    (sku, name, slug, category_id, company_id, brand, description, unit,
                     base_price, show_price, min_order_qty, availability, status, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([
                $sku, $name, $slug, $catId, $companyId, $brand, $description, $unit,
                $basePrice, $showPrice, $minQty, $availability, $status, current_user_id()
            ]);
            flash_success('Product created.');
        }
    } else {
        foreach ($errors as $e) flash_error($e);
    }
    redirect(BASE_URL . 'admin/products.php');
}

// Delete
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM products WHERE id=?")->execute([(int)$_GET['delete']]);
    flash_success('Product deleted.');
    redirect(BASE_URL . 'admin/products.php');
}

// Edit load
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();
}

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$companies  = $pdo->query("SELECT id, company_name FROM companies ORDER BY company_name")->fetchAll();

// ============================================
// ADVANCED FILTERS
// ============================================
$search      = trim($_GET['q'] ?? '');
$categoryId  = (int)($_GET['category'] ?? 0);
$companyId   = (int)($_GET['company'] ?? 0);
$statusFil   = $_GET['status'] ?? '';
$availability = $_GET['availability'] ?? '';
$sort        = $_GET['sort'] ?? 'newest';

$where = " WHERE 1=1";
$params = [];

if ($search !== '') {
    $where .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.brand LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($categoryId > 0) {
    $where .= " AND p.category_id = ?";
    $params[] = $categoryId;
}
if ($companyId > 0) {
    $where .= " AND p.company_id = ?";
    $params[] = $companyId;
}
if ($statusFil !== '') {
    $where .= " AND p.status = ?";
    $params[] = $statusFil;
}
if ($availability !== '') {
    $where .= " AND p.availability = ?";
    $params[] = $availability;
}

// Sort
$sortSql = ' ORDER BY p.id DESC';
switch ($sort) {
    case 'oldest':     $sortSql = ' ORDER BY p.id ASC'; break;
    case 'name_az':    $sortSql = ' ORDER BY p.name ASC'; break;
    case 'name_za':    $sortSql = ' ORDER BY p.name DESC'; break;
    case 'price_low':  $sortSql = ' ORDER BY p.base_price ASC'; break;
    case 'price_high': $sortSql = ' ORDER BY p.base_price DESC'; break;
}

// Count
$countSql = "SELECT COUNT(*) FROM products p" . $where;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();

// Build base URL
$filterParams = [];
if ($search !== '')     $filterParams[] = 'q=' . urlencode($search);
if ($categoryId > 0)    $filterParams[] = 'category=' . $categoryId;
if ($companyId > 0)     $filterParams[] = 'company=' . $companyId;
if ($statusFil !== '')  $filterParams[] = 'status=' . urlencode($statusFil);
if ($availability !== '') $filterParams[] = 'availability=' . urlencode($availability);
if ($sort !== 'newest') $filterParams[] = 'sort=' . urlencode($sort);
$baseUrl = 'products.php' . (empty($filterParams) ? '' : '?' . implode('&', $filterParams));

// Pagination
$pg = paginate($totalProducts, current_page_num(), 20, $baseUrl);
$offset = $pg['offset'];

// Main query
$sql = "SELECT p.*, c.name AS category_name, co.company_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN companies co ON co.id = p.company_id"
        . $where
        . $sortSql
        . " LIMIT {$pg['perPage']} OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Fetch categories for filter
$filterCategories = $pdo->query("SELECT id, name FROM categories WHERE is_active=1 ORDER BY name")->fetchAll();

// Fetch companies for filter
$filterCompanies = $pdo->query("SELECT id, company_name FROM companies ORDER BY company_name")->fetchAll();

$pageTitle = 'Products';
require_once dirname(__DIR__) . '/includes/admin_header.php';
?>

<h2 class="fw-bold mb-1">Products</h2>
<p class="text-muted small mb-4">Manage product catalog</p>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card-soft p-3">
            <div class="card-soft p-3 mb-3">
    <h6 class="fw-bold mb-3"><i class="bi bi-funnel"></i> Filter Products</h6>
    <form method="get">
        <div class="mb-2">
            <label class="form-label small">Search</label>
            <input type="text" name="q" class="form-control form-control-sm"
                   placeholder="Name, SKU, brand..." value="<?= e($search) ?>">
        </div>
        <div class="mb-2">
            <label class="form-label small">Category</label>
            <select name="category" class="form-select form-select-sm">
                <option value="0">All Categories</option>
                <?php foreach ($filterCategories as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $categoryId === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= e($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-2">
            <label class="form-label small">Company</label>
            <select name="company" class="form-select form-select-sm">
                <option value="0">All Companies</option>
                <?php foreach ($filterCompanies as $co): ?>
                    <option value="<?= (int)$co['id'] ?>" <?= $companyId === (int)$co['id'] ? 'selected' : '' ?>>
                        <?= e($co['company_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-2">
            <label class="form-label small">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="active"   <?= $statusFil === 'active'   ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $statusFil === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <div class="mb-2">
            <label class="form-label small">Availability</label>
            <select name="availability" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="in_stock"     <?= $availability === 'in_stock'     ? 'selected' : '' ?>>In Stock</option>
                <option value="limited"      <?= $availability === 'limited'      ? 'selected' : '' ?>>Limited</option>
                <option value="out_of_stock" <?= $availability === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label small">Sort By</label>
            <select name="sort" class="form-select form-select-sm">
                <option value="newest"     <?= $sort === 'newest'     ? 'selected' : '' ?>>Newest</option>
                <option value="oldest"     <?= $sort === 'oldest'     ? 'selected' : '' ?>>Oldest</option>
                <option value="name_az"    <?= $sort === 'name_az'    ? 'selected' : '' ?>>Name A-Z</option>
                <option value="name_za"    <?= $sort === 'name_za'    ? 'selected' : '' ?>>Name Z-A</option>
                <option value="price_low"  <?= $sort === 'price_low'  ? 'selected' : '' ?>>Price Low-High</option>
                <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price High-Low</option>
            </select>
        </div>
        <div class="d-flex gap-1">
            <button type="submit" class="btn btn-primary btn-sm flex-fill">
                <i class="bi bi-search"></i> Apply
            </button>
            <a href="products.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-x-circle"></i>
            </a>
        </div>
    </form>
</div>
            <h6 class="fw-bold mb-3"><?= $editing ? 'Edit Product' : 'Add New Product' ?></h6>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $editing ? (int)$editing['id'] : 0 ?>">

                <div class="mb-2">
                    <label class="form-label small">SKU *</label>
                    <input type="text" name="sku" class="form-control form-control-sm" required
                           value="<?= e($editing['sku'] ?? '') ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Name *</label>
                    <input type="text" name="name" class="form-control form-control-sm" required
                           value="<?= e($editing['name'] ?? '') ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Category *</label>
                    <select name="category_id" class="form-select form-select-sm" required>
                        <option value="">— Select —</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"
                                <?= ($editing['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
                                <?= e($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Company</label>
                    <select name="company_id" class="form-select form-select-sm">
                        <option value="">— None —</option>
                        <?php foreach ($companies as $co): ?>
                            <option value="<?= (int)$co['id'] ?>"
                                <?= ($editing['company_id'] ?? 0) == $co['id'] ? 'selected' : '' ?>>
                                <?= e($co['company_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Brand</label>
                    <input type="text" name="brand" class="form-control form-control-sm"
                           value="<?= e($editing['brand'] ?? '') ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Description</label>
                    <textarea name="description" class="form-control form-control-sm" rows="2"><?= e($editing['description'] ?? '') ?></textarea>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small">Unit</label>
                        <input type="text" name="unit" class="form-control form-control-sm"
                               value="<?= e($editing['unit'] ?? 'pcs') ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Min Order Qty</label>
                        <input type="number" name="min_order_qty" class="form-control form-control-sm"
                               value="<?= (int)($editing['min_order_qty'] ?? 1) ?>" min="1">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Base Price (PKR)</label>
                    <input type="number" name="base_price" class="form-control form-control-sm" step="0.01"
                           value="<?= e($editing['base_price'] ?? '') ?>">
                </div>
                <div class="mb-2 form-check">
                    <input type="checkbox" name="show_price" class="form-check-input" id="show_price"
                           <?= !empty($editing['show_price']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="show_price">
                        Show price to shops directly
                    </label>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small">Availability</label>
                        <select name="availability" class="form-select form-select-sm">
                            <option value="in_stock" <?= ($editing['availability'] ?? '') == 'in_stock' ? 'selected' : '' ?>>In Stock</option>
                            <option value="limited"  <?= ($editing['availability'] ?? '') == 'limited'  ? 'selected' : '' ?>>Limited</option>
                            <option value="out_of_stock" <?= ($editing['availability'] ?? '') == 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="active"   <?= ($editing['status'] ?? '') == 'active'   ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= ($editing['status'] ?? '') == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-save"></i> <?= $editing ? 'Update Product' : 'Add Product' ?>
                </button>
                <?php if ($editing): ?>
                    <a href="<?= BASE_URL ?>admin/products.php" class="btn btn-sm btn-outline-secondary w-100 mt-2">Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card-soft">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>SKU</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th class="text-end">Base Price</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($products)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No products yet.</td></tr>
                    <?php else: foreach ($products as $p): ?>
                        <tr>
                            <td class="small"><code><?= e($p['sku']) ?></code></td>
                            <td class="small">
                                <div class="fw-semibold"><?= e($p['name']) ?></div>
                                <?php if ($p['brand']): ?>
                                    <div class="text-muted" style="font-size:.75rem;"><?= e($p['brand']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= e($p['category_name'] ?? '—') ?></td>
                            <td class="text-end small"><?= $p['base_price'] !== null ? money($p['base_price']) : '—' ?></td>
                            <td>
                                <?php if ($p['status'] === 'active'): ?>
                                    <span class="badge text-bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="?edit=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="?delete=<?= (int)$p['id'] ?>"
                                   onclick="return confirm('Delete this product?')"
                                   class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
             <?= $pg['html'] ?>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/admin_footer.php'; ?>