<?php
if (!defined('APP_INIT')) { http_response_code(403); exit('Direct access denied.'); }

/**
 * Check if shop can review a specific order item
 */
function can_review_order_item(PDO $pdo, int $orderItemId, int $shopId): bool
{
    $stmt = $pdo->prepare(
        "SELECT oi.id, o.status, o.shop_id 
         FROM order_items oi
         JOIN orders o ON o.id = oi.order_id
         WHERE oi.id = ? AND o.shop_id = ? LIMIT 1"
    );
    $stmt->execute([$orderItemId, $shopId]);
    $row = $stmt->fetch();

    if (!$row) return false;
    if ($row['status'] !== 'delivered') return false;

    $chk = $pdo->prepare("SELECT id FROM product_reviews WHERE order_item_id = ? LIMIT 1");
    $chk->execute([$orderItemId]);
    if ($chk->fetch()) return false;

    return true;
}

/**
 * Get review for an order item (or null)
 */
function get_order_item_review(PDO $pdo, int $orderItemId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM product_reviews WHERE order_item_id = ? LIMIT 1");
    $stmt->execute([$orderItemId]);
    $r = $stmt->fetch();
    return $r ?: null;
}

/**
 * Get average rating + count for a product
 */
function get_product_rating(PDO $pdo, int $productId): array
{
    $stmt = $pdo->prepare(
        "SELECT 
            COUNT(*) AS total,
            COALESCE(AVG(rating), 0) AS average,
            SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) AS r5,
            SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) AS r4,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) AS r3,
            SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) AS r2,
            SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) AS r1
         FROM product_reviews 
         WHERE product_id = ? AND status = 'approved'"
    );
    $stmt->execute([$productId]);
    $row = $stmt->fetch();

    return [
        'total'   => (int)($row['total'] ?? 0),
        'average' => round((float)($row['average'] ?? 0), 1),
        'r5'      => (int)($row['r5'] ?? 0),
        'r4'      => (int)($row['r4'] ?? 0),
        'r3'      => (int)($row['r3'] ?? 0),
        'r2'      => (int)($row['r2'] ?? 0),
        'r1'      => (int)($row['r1'] ?? 0),
    ];
}

/**
 * Get reviews for a product
 */
function get_product_reviews(PDO $pdo, int $productId, int $limit = 50): array
{
    $stmt = $pdo->prepare(
        "SELECT r.*, s.shop_name
         FROM product_reviews r
         JOIN shops s ON s.id = r.shop_id
         WHERE r.product_id = ? AND r.status = 'approved'
         ORDER BY r.id DESC
         LIMIT $limit"
    );
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

/**
 * Render star rating HTML
 */
function render_stars(float $rating, string $size = ''): string
{
    $html = '<span class="text-warning" style="' . ($size ? 'font-size:' . $size . ';' : '') . '">';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= round($rating)) {
            $html .= '<i class="bi bi-star-fill"></i>';
        } else {
            $html .= '<i class="bi bi-star"></i>';
        }
    }
    $html .= '</span>';
    return $html;
}