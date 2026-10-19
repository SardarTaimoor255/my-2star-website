<?php
/**
 * shop/product-details.php
 */
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_SHOP);
require_once dirname(__DIR__) . '/includes/review_helper.php';
require_once dirname(__DIR__) . '/includes/product_helper.php';

$user = current_user($pdo);
$shopStmt = $pdo->prepare("SELECT id FROM shops WHERE user_id = ? LIMIT 1");
$shopStmt->execute([$user['id']]);
$shopId = (int)$shopStmt->fetchColumn();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { flash_error('Invalid product.'); redirect(BASE_URL . 'shop/products.php'); }

$stmt = $pdo->prepare(
    "SELECT p.*, c.name AS category_name, co.company_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     LEFT JOIN companies co ON co.id = p.company_id
     WHERE p.id = ? AND p.status = 'active' LIMIT 1"
);
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) { flash_error('Product not found.'); redirect(BASE_URL . 'shop/products.php'); }

// Final price
$vendorInfo = $pdo->query("SELECT * FROM vendors LIMIT 1")->fetch();
$defComm    = (float)($vendorInfo['default_commission_value'] ?? 500);
$commType   = $vendorInfo['default_commission_type'] ?? 'fixed';

$stmt = $pdo->prepare("SELECT custom_price FROM shop_specific_prices WHERE product_id = ? AND shop_id = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$id, $shopId]);
$custom = $stmt->fetchColumn();

$finalPrice = null;
if ($custom !== false && $custom !== null) {
    $finalPrice = (float)$custom;
} elseif ((int)$product['show_price'] === 1 && $product['base_price'] !== null) {
    $base = (float)$product['base_price'];
    $comm = $commType === 'percentage' ? $base * $defComm / 100 : $defComm;
    $finalPrice = $base + $comm;
}

$pageTitle = $product['name'];
require_once dirname(__DIR__) . '/includes/shop_header.php';
?>

<div class="mb-3">
    <a href="<?= BASE_URL ?>shop/products.php" class="text-decoration-none small">
        <i class="bi bi-arrow-left"></i> Back to Products
    </a>
</div>

<div class="card-soft p-4">
    <div class="row g-4">
        <div class="col-md-5 text-center">
                   <?php 
$mainImg = product_image_url($pdo, (int)$product['id']); 
$allImgs = product_images($pdo, (int)$product['id']);

// Agar koi image nahi mili toh main image ko gallery mein daalein
if (empty($allImgs)) {
    $allImgs = [['image_path' => null, 'is_primary' => 1, 'url' => $mainImg]];
} else {
    // Har image ka URL bana dein
    foreach ($allImgs as &$img) {
        $img['url'] = UPLOADS_URL . $img['image_path'];
    }
    unset($img);
}

// Current index (primary image)
$currentIndex = 0;
foreach ($allImgs as $i => $img) {
    if (!empty($img['is_primary'])) { $currentIndex = $i; break; }
}
?>

<!-- Main Image -->
<div onclick="openProductZoom()" style="cursor:zoom-in; display:inline-block; width:100%;">
    <img id="mainProductImg" 
         src="<?= e($allImgs[$currentIndex]['url']) ?>" 
         alt="<?= e($product['name']) ?>"
         class="img-fluid rounded border"
         style="max-height:280px; width:100%; object-fit:contain;"
         loading="lazy"
         onerror="this.src='<?= ASSETS_URL ?>images/placeholder.png'">
    <div class="text-muted small mt-2">
        <i class="bi bi-zoom-in"></i> Click to zoom
    </div>
</div>

<!-- Hidden GLightbox gallery -->
<div style="display:none;">
    <?php foreach ($allImgs as $index => $img): ?>
        <a href="<?= e($img['url']) ?>" 
           class="glightbox" 
           data-gallery="product"
           data-title="<?= e($product['name']) ?>"
           id="glightbox-link-<?= $index ?>"></a>
    <?php endforeach; ?>
</div>

<!-- Thumbnail Gallery -->
<?php if (count($allImgs) > 1): ?>
    <div class="row g-2 mt-3">
        <?php foreach ($allImgs as $index => $img): 
            $thumbUrl = $img['url'];
        ?>
            <div class="col-3">
                <div class="thumbnail-item position-relative" 
                     onclick="changeMainImage(<?= $index ?>, this)"
                     style="cursor:pointer; border: 2px solid <?= !empty($img['is_primary']) ? '#0b3d91' : 'transparent' ?>; border-radius:8px; overflow:hidden; transition: all .2s;">
                    <img src="<?= e($thumbUrl) ?>" 
                         alt="<?= e($product['name']) ?>"
                         class="img-fluid"
                         style="height:65px; width:100%; object-fit:cover; display:block;"
                         loading="lazy">
                    <?php if (!empty($img['is_primary'])): ?>
                        <span class="position-absolute badge text-bg-primary" 
                              style="font-size:.6rem; top:2px; left:2px;">
                            <i class="bi bi-star-fill"></i>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
// Global state
let currentImageIndex = <?= (int)$currentIndex ?>;
let productLightbox = null;
const productImages = <?= json_encode(array_column($allImgs, 'url')) ?>;

// Initialize GLightbox
document.addEventListener('DOMContentLoaded', function() {
    if (typeof GLightbox !== 'undefined') {
        productLightbox = GLightbox({
            selector: '.glightbox',
            touchNavigation: true,
            loop: false,
            zoomable: true,
            draggable: true,
        });
    }
});

// Change main image via thumbnail click
function changeMainImage(index, el) {
    currentImageIndex = index;
    
    // Change main image src
    document.getElementById('mainProductImg').src = productImages[index];
    
    // Update thumbnail borders
    document.querySelectorAll('.thumbnail-item').forEach(function(item) {
        item.style.borderColor = 'transparent';
    });
    el.style.borderColor = '#0b3d91';
}

// Open zoom of CURRENTLY displayed image
function openProductZoom() {
    if (productLightbox) {
        productLightbox.openAt(currentImageIndex);
    }
}
</script>
        </div>
        <div class="col-md-7">
            <div class="small text-muted mb-1"><?= e($product['category_name'] ?? 'Uncategorized') ?></div>
            <h3 class="fw-bold mb-2"><?= e($product['name']) ?></h3>

            <div class="mb-3">
                <?php if ($product['brand']): ?>
                    <span class="badge text-bg-light border me-1">Brand: <?= e($product['brand']) ?></span>
                <?php endif; ?>
                <span class="badge text-bg-light border me-1">SKU: <?= e($product['sku']) ?></span>
                <span class="badge text-bg-light border">Unit: <?= e($product['unit']) ?></span>
            </div>

            <?php if ($product['description']): ?>
                <p class="text-muted"><?= nl2br(e($product['description'])) ?></p>
            <?php endif; ?>

            <hr>

            <?php if ($finalPrice !== null): ?>
                <div class="mb-3">
                    <div class="text-muted small">Final Price</div>
                    <div class="fw-bold text-primary" style="font-size:1.8rem;">
                        <?= money($finalPrice) ?>
                        <small class="text-muted fw-normal" style="font-size:.9rem;">/ <?= e($product['unit']) ?></small>
                    </div>
                </div>

                <?php if ($product['availability'] === 'in_stock'): ?>
                    <form onsubmit="event.preventDefault(); addToCart(this.querySelector('button'), <?= (int)$product['id'] ?>, this.querySelector('input').value);">
                        <div class="row g-2 mb-3">
                            <div class="col-4">
                                <label class="form-label small">Quantity</label>
                                <input type="number" class="form-control" min="<?= (int)$product['min_order_qty'] ?>"
                                       value="<?= (int)$product['min_order_qty'] ?>" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-cart-plus"></i> Add to Cart
                        </button>
                    </form>
                <?php else: ?>
                    <button class="btn btn-secondary" disabled>Out of Stock</button>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="bi bi-info-circle"></i>
                    <strong>Price Pending.</strong> Request the price from the vendor to proceed.
                </div>
                <a href="<?= BASE_URL ?>shop/request-price.php?product_id=<?= (int)$product['id'] ?>"
                   class="btn btn-warning">
                    <i class="bi bi-tag"></i> Request Price
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
$ratingStats = get_product_rating($pdo, (int)$product['id']);
$reviews     = get_product_reviews($pdo, (int)$product['id'], 20);
?>

<!-- Reviews Section -->
<div class="card-soft p-4 mt-4">
    <h5 class="fw-bold mb-3">
        <i class="bi bi-star text-warning"></i> Customer Reviews
        <?php if ($ratingStats['total'] > 0): ?>
            <span class="text-muted small">(<?= $ratingStats['total'] ?> reviews)</span>
        <?php endif; ?>
    </h5>

    <?php if ($ratingStats['total'] === 0): ?>
        <div class="text-center text-muted py-4">
            <i class="bi bi-star fs-1 d-block mb-2 opacity-25"></i>
            <p class="mb-0">Abhi koi review nahi hai.</p>
        </div>
    <?php else: ?>

        <!-- Rating Summary -->
        <div class="row g-3 mb-4">
            <div class="col-md-4 text-center border-end">
                <div class="display-5 fw-bold text-primary"><?= $ratingStats['average'] ?></div>
                <div class="my-2"><?= render_stars($ratingStats['average'], '1.3rem') ?></div>
                <div class="text-muted small"><?= $ratingStats['total'] ?> reviews</div>
            </div>
            <div class="col-md-8">
                <?php foreach ([5, 4, 3, 2, 1] as $stars): 
                    $count = $ratingStats['r' . $stars];
                    $pct = $ratingStats['total'] > 0 ? ($count / $ratingStats['total']) * 100 : 0;
                ?>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="small text-muted" style="width:40px;"><?= $stars ?> ★</span>
                        <div class="progress flex-grow-1" style="height:8px;">
                            <div class="progress-bar bg-warning" style="width:<?= $pct ?>%"></div>
                        </div>
                        <span class="small text-muted" style="width:30px;"><?= $count ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <hr>

        <!-- Reviews List -->
        <?php foreach ($reviews as $rev): ?>
            <div class="border-bottom pb-3 mb-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="fw-semibold"><?= e($rev['shop_name']) ?></div>
                        <div><?= render_stars((float)$rev['rating']) ?></div>
                    </div>
                    <div class="text-muted small"><?= fmt_datetime($rev['created_at']) ?></div>
                </div>

                <?php if ($rev['title']): ?>
                    <div class="fw-semibold mb-1"><?= e($rev['title']) ?></div>
                <?php endif; ?>

                <div class="text-muted small"><?= nl2br(e($rev['comment'])) ?></div>

                <?php if ($rev['company_reply']): ?>
                    <div class="mt-2 p-2 rounded" style="background:#f0f9ff; border-left:3px solid #0b3d91;">
                        <div class="small fw-semibold text-primary mb-1">
                            <i class="bi bi-reply-fill"></i> Company Reply:
                        </div>
                        <div class="small"><?= nl2br(e($rev['company_reply'])) ?></div>
                        <div class="text-muted" style="font-size:.7rem;">
                            <?= fmt_datetime($rev['company_replied_at']) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>
</div>
<?php require_once dirname(__DIR__) . '/includes/shop_footer.php'; ?>