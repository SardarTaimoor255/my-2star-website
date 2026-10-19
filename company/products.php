<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/product_helper.php';
require_role($pdo, ROLE_COMPANY);
require_once dirname(__DIR__) . '/includes/pagination.php';
$user = current_user($pdo);
$coStmt = $pdo->prepare("SELECT id FROM companies WHERE user_id = ? LIMIT 1");
$coStmt->execute([$user['id']]);
$companyId = (int)$coStmt->fetchColumn();

// Filter
$q = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';

// Build WHERE clause
$where = " WHERE p.company_id = ?";
$params = [$companyId];

if ($q !== '') { 
    $where .= " AND (p.name LIKE ? OR p.sku LIKE ?)"; 
    $params[] = "%$q%"; 
    $params[] = "%$q%"; 
}
if ($statusFilter !== '') { 
    $where .= " AND p.status = ?"; 
    $params[] = $statusFilter; 
}

// Count total for pagination
$countSql = "SELECT COUNT(*) FROM products p" . $where;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();

// Build base URL for pagination
$filterParams = [];
if ($q !== '') $filterParams[] = 'q=' . urlencode($q);
if ($statusFilter !== '') $filterParams[] = 'status=' . urlencode($statusFilter);
$baseUrl = 'products.php' . (empty($filterParams) ? '' : '?' . implode('&', $filterParams));

// Pagination
$pg = paginate($totalProducts, current_page_num(), 20, $baseUrl);
$offset = $pg['offset'];

// Main query
$sql = "SELECT p.*, c.name AS category_name 
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id"
        . $where
        . " ORDER BY p.id DESC LIMIT {$pg['perPage']} OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
$pageTitle = 'My Products';
require_once dirname(__DIR__) . '/includes/company_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1">My Products</h2>
        <p class="text-muted small mb-0"><?= count($products) ?> product(s) in your catalog</p>
    </div>
        <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>company/bulk-upload.php" class="btn btn-outline-primary">
            <i class="bi bi-cloud-upload"></i> Bulk Upload
        </a>
        <a href="<?= BASE_URL ?>company/add-product.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Add New Product
        </a>
    </div>
</div>

<?php if (empty($products)): ?>
    <div class="card-soft p-5 text-center">
        <i class="bi bi-box-seam fs-1 text-muted d-block mb-3 opacity-25"></i>
        <h5 class="fw-bold">No products yet</h5>
        <p class="text-muted">Add your first product to start selling.</p>
        <a href="<?= BASE_URL ?>company/add-product.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Add First Product
        </a>
    </div>
<?php else: ?>

    <!-- Filters -->
    <div class="card-soft p-2 mb-3">
        <form method="get" class="row g-2 align-items-center">
            <div class="col-md-5">
                <input type="text" name="q" class="form-control form-control-sm" 
                       placeholder="Search by name or SKU..." value="<?= e($q) ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <option value="active"   <?= $statusFilter==='active'   ? 'selected':'' ?>>Active</option>
                    <option value="inactive" <?= $statusFilter==='inactive' ? 'selected':'' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-4">
                <button class="btn btn-sm btn-primary"><i class="bi bi-search"></i> Search</button>
                <a href="<?= BASE_URL ?>company/products.php" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <!-- Products Grid -->
    <div class="row g-3">
        <?php foreach ($products as $p): 
            $imgUrl = product_image_url($pdo, (int)$p['id']);
            $imgCount = count(product_images($pdo, (int)$p['id']));
        ?>
            <div class="col-md-6 col-lg-4">
                <div class="card-soft h-100 overflow-hidden product-card">
                                                            <div style="position:relative; background:#f8fafc; height:200px; overflow:hidden; display:flex; align-items:center; justify-content:center;">
                        <img src="<?= e($imgUrl) ?>" alt="<?= e($p['name']) ?>" 
     loading="lazy"
     style="width:100%; height:100%; object-fit:contain; padding:12px;"
     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        <i class="bi bi-image text-muted" style="display:none; font-size:4rem; opacity:.25;"></i>
                        
                        <?php if ($p['status'] === 'inactive'): ?>
                            <span class="badge text-bg-secondary" style="position:absolute; top:10px; left:10px;">Inactive</span>
                        <?php elseif ($p['availability'] === 'out_of_stock'): ?>
                            <span class="badge text-bg-danger" style="position:absolute; top:10px; left:10px;">Out of Stock</span>
                        <?php else: ?>
                            <span class="badge text-bg-success" style="position:absolute; top:10px; left:10px;">Active</span>
                        <?php endif; ?>
                    </div>
                    <div class="p-3">
                        <div class="small text-muted"><?= e($p['category_name'] ?? 'Uncategorized') ?></div>
                        <h6 class="fw-bold mb-1" style="line-height:1.3;"><?= e($p['name']) ?></h6>
                        <div class="small text-muted mb-2">
                            <code><?= e($p['sku']) ?></code>
                            <?php if ($p['brand']): ?> · <?= e($p['brand']) ?><?php endif; ?>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="text-muted" style="font-size:.7rem;">Base Price</div>
                                <div class="fw-bold text-primary"><?= money($p['base_price']) ?></div>
                            </div>
                            <div class="text-end">
                                <div class="text-muted" style="font-size:.7rem;">Unit</div>
                                <div class="fw-semibold small"><?= e($p['unit']) ?></div>
                            </div>
                        </div>
                        <div class="d-flex gap-1">
                            <a href="<?= BASE_URL ?>company/edit-product.php?id=<?= (int)$p['id'] ?>" 
                               class="btn btn-sm btn-outline-primary flex-fill">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <button class="btn btn-sm btn-outline-secondary"
                                    onclick="toggleStatus(<?= (int)$p['id'] ?>, this)" title="Toggle Active/Inactive">
                                <i class="bi bi-power"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger"
                                    onclick="deleteProduct(<?= (int)$p['id'] ?>, '<?= e($p['name']) ?>')" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?= $pg['html'] ?>
<?php endif; ?>

<style>
.product-card { transition: transform .15s; }
.product-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(15,23,42,.08); }
</style>

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

async function toggleStatus(id, btn) {
    btn.disabled = true;
    const r = await apiPost({ action: 'toggle_status', id });
    if (r.success) location.reload();
    else { alert(r.message || 'Failed'); btn.disabled = false; }
}

async function deleteProduct(id, name) {
    const msg = 'Delete product "' + name + '"?\n\n' +
                '⚠️ Note: Agar ye product kisi order ya price request mein ' +
                'use hua hai toh delete nahi hoga.\n\n' +
                'Us case mein Power button (⚡) se Deactivate karein.';
    
    if (!confirm(msg)) return;
    
    const r = await apiPost({ action: 'delete_product', id });
    if (r.success) {
        location.reload();
    } else {
        alert('❌ ' + (r.message || 'Delete failed. Please try again.'));
    }
}
</script>

<?php require_once dirname(__DIR__) . '/includes/company_footer.php'; ?>