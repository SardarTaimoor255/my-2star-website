<?php
/**
 * shop/products.php — Browse products
 */
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_SHOP);
require_once dirname(__DIR__) . '/includes/product_helper.php';
require_once dirname(__DIR__) . '/includes/pagination.php';
require_once dirname(__DIR__) . '/includes/cache.php';
$user = current_user($pdo);
$shopStmt = $pdo->prepare("SELECT id FROM shops WHERE user_id = ? LIMIT 1");
$shopStmt->execute([$user['id']]);
$shopId = (int)$shopStmt->fetchColumn();

$q          = trim($_GET['q'] ?? '');
$catIds     = array_filter(array_map('intval', (array)($_GET['cat'] ?? [])));
$brands     = array_filter(array_map('trim', (array)($_GET['brand'] ?? [])));
$priceMin   = isset($_GET['price_min']) && $_GET['price_min'] !== '' ? (float)$_GET['price_min'] : null;
$priceMax   = isset($_GET['price_max']) && $_GET['price_max'] !== '' ? (float)$_GET['price_max'] : null;
$availability = $_GET['availability'] ?? '';
$sort       = $_GET['sort'] ?? 'newest';

// Build WHERE clause
$where = " WHERE p.status = 'active'";
$params = [];

if ($q !== '') { 
    $where .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.brand LIKE ?)";
    array_push($params, "%$q%", "%$q%", "%$q%"); 
}
if (!empty($catIds)) {
    $placeholders = implode(',', array_fill(0, count($catIds), '?'));
    $where .= " AND p.category_id IN ($placeholders)";
    foreach ($catIds as $cid) $params[] = $cid;
}
if (!empty($brands)) {
    $placeholders = implode(',', array_fill(0, count($brands), '?'));
    $where .= " AND p.brand IN ($placeholders)";
    foreach ($brands as $b) $params[] = $b;
}
if ($priceMin !== null) {
    $where .= " AND p.base_price >= ?";
    $params[] = $priceMin;
}
if ($priceMax !== null) {
    $where .= " AND p.base_price <= ?";
    $params[] = $priceMax;
}
if ($availability !== '') {
    $where .= " AND p.availability = ?";
    $params[] = $availability;
}

// Sort
$sortSql = ' ORDER BY p.id DESC';  // default: newest
switch ($sort) {
    case 'price_low':  $sortSql = ' ORDER BY p.base_price ASC, p.id DESC'; break;
    case 'price_high': $sortSql = ' ORDER BY p.base_price DESC, p.id DESC'; break;
    case 'name_az':    $sortSql = ' ORDER BY p.name ASC'; break;
    case 'name_za':    $sortSql = ' ORDER BY p.name DESC'; break;
    case 'oldest':     $sortSql = ' ORDER BY p.id ASC'; break;
    case 'newest':
    default:           $sortSql = ' ORDER BY p.id DESC';
}

// Count total
$countSql = "SELECT COUNT(*) FROM products p" . $where;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();

// Build base URL for pagination (preserve ALL filters)
$filterParams = [];
if ($q !== '')       $filterParams[] = 'q=' . urlencode($q);
foreach ($catIds as $cid)   $filterParams[] = 'cat[]=' . $cid;
foreach ($brands as $b)     $filterParams[] = 'brand[]=' . urlencode($b);
if ($priceMin !== null) $filterParams[] = 'price_min=' . $priceMin;
if ($priceMax !== null) $filterParams[] = 'price_max=' . $priceMax;
if ($availability !== '') $filterParams[] = 'availability=' . urlencode($availability);
if ($sort !== 'newest')   $filterParams[] = 'sort=' . urlencode($sort);
$baseUrl = 'products.php' . (empty($filterParams) ? '' : '?' . implode('&', $filterParams));

// Pagination
$pg = paginate($totalProducts, current_page_num(), 20, $baseUrl);
$offset = $pg['offset'];

// Main query
$sql = "SELECT p.*, c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id"
        . $where
        . $sortSql
        . " LIMIT {$pg['perPage']} OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Fetch available brands for filter
$allBrands = $pdo->query(
    "SELECT DISTINCT brand FROM products 
     WHERE status='active' AND brand IS NOT NULL AND brand <> '' 
     ORDER BY brand"
)->fetchAll(PDO::FETCH_COLUMN);

// Categories for filter
// Categories for filter
$categories = cache_remember('shop_categories', function() use ($pdo) {
    return $pdo->query("SELECT id, name FROM categories WHERE is_active=1 ORDER BY name")->fetchAll();
}, 600);
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

// Vendor default commission for price display
$vendorInfo = $pdo->query(
    "SELECT v.* FROM vendors v LIMIT 1"
)->fetch();
$defaultCommission = (float)($vendorInfo['default_commission_value'] ?? 500);
$commissionType    = $vendorInfo['default_commission_type'] ?? 'fixed';

// Helper: get final price for a product
function productFinalPrice(array $p, int $shopId, PDO $pdo, float $defComm, string $commType): ?float {
    // Check shop-specific price first
    $stmt = $pdo->prepare("SELECT custom_price FROM shop_specific_prices WHERE product_id = ? AND shop_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$p['id'], $shopId]);
    $custom = $stmt->fetchColumn();
    if ($custom !== false && $custom !== null) return (float)$custom;

    if ((int)$p['show_price'] !== 1) return null;
    if ($p['base_price'] === null) return null;

    $base = (float)$p['base_price'];
    $comm = $commType === 'percentage' ? $base * $defComm / 100 : $defComm;
    return $base + $comm;
}

$pageTitle = 'Products';
require_once dirname(__DIR__) . '/includes/shop_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1">Products</h2>
        <p class="text-muted small mb-0"><?= count($products) ?> product(s) available</p>
    </div>
</div>

<!-- Advanced Filters -->
<div class="card-soft p-3 mb-3">
    <form method="get" id="filterForm">
        <!-- Row 1: Search + Sort -->
        <div class="row g-2 mb-2">
            <div class="col-md-6">
                <input type="text" name="q" class="form-control form-control-sm"
                       placeholder="Search by name, SKU, brand..." value="<?= e($q) ?>">
            </div>
            <div class="col-md-4">
                <select name="sort" class="form-select form-select-sm">
                    <option value="newest"     <?= $sort === 'newest'     ? 'selected' : '' ?>>Newest First</option>
                    <option value="oldest"     <?= $sort === 'oldest'     ? 'selected' : '' ?>>Oldest First</option>
                    <option value="price_low"  <?= $sort === 'price_low'  ? 'selected' : '' ?>>Price: Low to High</option>
                    <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
                    <option value="name_az"    <?= $sort === 'name_az'    ? 'selected' : '' ?>>Name: A-Z</option>
                    <option value="name_za"    <?= $sort === 'name_za'    ? 'selected' : '' ?>>Name: Z-A</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-search"></i> Search
                </button>
            </div>
        </div>

        <!-- Row 2: Advanced Filters (Collapsible) -->
        <div class="collapse" id="advancedFilters">
            <div class="row g-3 pt-3 border-top">
                <!-- Category Multi-select -->
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Categories</label>
                    <div style="max-height:150px; overflow-y:auto; border:1px solid #e6e9f2; border-radius:6px; padding:6px;">
                        <?php foreach ($categories as $c): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" 
                                       name="cat[]" value="<?= (int)$c['id'] ?>"
                                       id="cat<?= (int)$c['id'] ?>"
                                       <?= in_array((int)$c['id'], $catIds, true) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="cat<?= (int)$c['id'] ?>">
                                    <?= e($c['name']) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Brand Multi-select -->
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Brands</label>
                    <div style="max-height:150px; overflow-y:auto; border:1px solid #e6e9f2; border-radius:6px; padding:6px;">
                        <?php if (empty($allBrands)): ?>
                            <div class="small text-muted">No brands yet</div>
                        <?php else: foreach ($allBrands as $b): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"
                                       name="brand[]" value="<?= e($b) ?>"
                                       id="brand<?= md5($b) ?>"
                                       <?= in_array($b, $brands, true) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="brand<?= md5($b) ?>">
                                    <?= e($b) ?>
                                </label>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>

                <!-- Price Range -->
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Price Range (PKR)</label>
                    <div class="row g-1">
                        <div class="col-6">
                            <input type="number" name="price_min" class="form-control form-control-sm" 
                                   placeholder="Min" min="0" value="<?= $priceMin !== null ? $priceMin : '' ?>">
                        </div>
                        <div class="col-6">
                            <input type="number" name="price_max" class="form-control form-control-sm" 
                                   placeholder="Max" min="0" value="<?= $priceMax !== null ? $priceMax : '' ?>">
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        <a href="?<?= http_build_query(array_merge($_GET, ['price_min'=>0, 'price_max'=>500])) ?>" 
                           class="btn btn-outline-secondary btn-sm">Under 500</a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['price_min'=>500, 'price_max'=>2000])) ?>" 
                           class="btn btn-outline-secondary btn-sm">500-2K</a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['price_min'=>2000, 'price_max'=>10000])) ?>" 
                           class="btn btn-outline-secondary btn-sm">2K-10K</a>
                    </div>
                </div>

                <!-- Availability -->
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Availability</label>
                    <select name="availability" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="in_stock"     <?= $availability === 'in_stock'     ? 'selected' : '' ?>>In Stock</option>
                        <option value="limited"      <?= $availability === 'limited'      ? 'selected' : '' ?>>Limited</option>
                        <option value="out_of_stock" <?= $availability === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                    </select>
                </div>

                <!-- Action buttons -->
                <div class="col-12 d-flex justify-content-between">
                    <a href="products.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-circle"></i> Clear All
                    </a>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-funnel"></i> Apply Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Toggle Button -->
        <div class="text-center mt-2">
            <button type="button" class="btn btn-link btn-sm text-decoration-none" 
                    data-bs-toggle="collapse" data-bs-target="#advancedFilters">
                <i class="bi bi-sliders"></i> Advanced Filters
                <?php 
                $activeFilterCount = count($catIds) + count($brands) 
                    + ($priceMin !== null ? 1 : 0) + ($priceMax !== null ? 1 : 0)
                    + ($availability !== '' ? 1 : 0);
                if ($activeFilterCount > 0): 
                ?>
                    <span class="badge text-bg-primary ms-1"><?= $activeFilterCount ?></span>
                <?php endif; ?>
            </button>
        </div>
    </form>

    <!-- Active Filters Chips -->
    <?php if ($activeFilterCount > 0): ?>
        <div class="d-flex flex-wrap gap-2 mt-2 pt-2 border-top">
            <span class="small text-muted">Active:</span>
            <?php foreach ($catIds as $cid): 
                $catName = '';
                foreach ($categories as $c) if ((int)$c['id'] === $cid) $catName = $c['name'];
            ?>
                <span class="badge text-bg-light border">
                    <?= e($catName) ?>
                </span>
            <?php endforeach; ?>
            <?php foreach ($brands as $b): ?>
                <span class="badge text-bg-light border"><?= e($b) ?></span>
            <?php endforeach; ?>
            <?php if ($priceMin !== null || $priceMax !== null): ?>
                <span class="badge text-bg-light border">
                    Rs. <?= $priceMin !== null ? number_format($priceMin) : '0' ?> – 
                    <?= $priceMax !== null ? number_format($priceMax) : '∞' ?>
                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (empty($products)): ?>
    <div class="card-soft p-5 text-center text-muted">
        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
        No products found.
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($products as $p):
            $finalPrice = productFinalPrice($p, $shopId, $pdo, $defaultCommission, $commissionType);
            $hasPrice   = $finalPrice !== null;
                       $img        = product_image_url($pdo, (int)$p['id']);
        ?>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card-soft product-card p-3 d-flex flex-column">
                    <div class="mb-2 text-center">
                       <img src="<?= e($img) ?>" alt="<?= e($p['name']) ?>" class="product-img w-100"
     loading="lazy"
     onerror="this.style.display='none'">
                    </div>
                    <div class="small text-muted"><?= e($p['category_name'] ?? 'Uncategorized') ?></div>
                    <div class="fw-semibold mb-1" style="font-size:.95rem;line-height:1.3;"><?= e($p['name']) ?></div>
                    <?php if ($p['brand']): ?>
                        <div class="small text-muted mb-2"><?= e($p['brand']) ?></div>
                    <?php endif; ?>

                    <div class="mt-auto">
                        <?php if ($hasPrice): ?>
                            <div class="fw-bold text-primary mb-2">
                                <?= money($finalPrice) ?>
                                <small class="text-muted fw-normal">/ <?= e($p['unit']) ?></small>
                            </div>
                            <div class="d-grid gap-1">
                                <a href="<?= BASE_URL ?>shop/product-details.php?id=<?= (int)$p['id'] ?>"
                                   class="btn btn-sm btn-outline-primary">
                                    View Details
                                </a>
                                <?php if ($p['availability'] === 'in_stock'): ?>
                                    <button class="btn btn-sm btn-primary"
                                            onclick="addToCart(this, <?= (int)$p['id'] ?>)">
                                        <i class="bi bi-cart-plus"></i> Add to Cart
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-secondary" disabled>Out of Stock</button>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="mb-2">
                                <span class="badge text-bg-warning">Price Pending</span>
                            </div>
                            <a href="<?= BASE_URL ?>shop/request-price.php?product_id=<?= (int)$p['id'] ?>"
                               class="btn btn-sm btn-outline-warning w-100">
                                <i class="bi bi-tag"></i> Request Price
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
     <?= $pg['html'] ?>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/shop_footer.php'; ?>