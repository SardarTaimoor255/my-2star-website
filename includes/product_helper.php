<?php
if (!defined('APP_INIT')) { http_response_code(403); exit('No direct access.'); }

/**
 * Generate unique slug from product name
 */
function generate_product_slug(PDO $pdo, string $name, string $sku): string
{
    $base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
    $base = trim($base, '-');
    $slug = $base . '-' . strtolower(preg_replace('/[^a-z0-9]+/i', '', $sku));

    // Ensure unique
    $check = $pdo->prepare("SELECT COUNT(*) FROM products WHERE slug = ?");
    $check->execute([$slug]);
    if ((int)$check->fetchColumn() === 0) return $slug;

    $i = 1;
    while (true) {
        $try = $slug . '-' . $i;
        $check->execute([$try]);
        if ((int)$check->fetchColumn() === 0) return $try;
        $i++;
    }
}

/**
 * Upload multiple product images
 * Returns array of relative paths
 */
function upload_product_images(array $files, int $companyId, int $maxImages = 4): array
{
    $paths = [];
    if (empty($files['name'][0])) return $paths;

    $subFolder = 'products/' . $companyId;

    $count = min(count($files['name']), $maxImages);
    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

        $single = [
            'name'     => $files['name'][$i],
            'type'     => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error'    => $files['error'][$i],
            'size'     => $files['size'][$i],
        ];

        $path = upload_file($single, $subFolder, ALLOWED_IMAGE_EXT);
        if ($path) $paths[] = $path;
    }

    return $paths;
}

/**
 * Get primary image URL for a product
 */
function product_image_url(PDO $pdo, int $productId): string
{
    $stmt = $pdo->prepare(
        "SELECT image_path FROM product_images 
         WHERE product_id = ? ORDER BY is_primary DESC, id ASC LIMIT 1"
    );
    $stmt->execute([$productId]);
    $path = $stmt->fetchColumn();

    if ($path) {
        return UPLOADS_URL . $path;
    }
    
    // Return inline SVG placeholder (no file needed)
    return 'data:image/svg+xml;base64,' . base64_encode(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200">
            <rect width="200" height="200" fill="#f8fafc"/>
            <path d="M50 140l35-45 35 45h-70z" fill="#cbd5e1"/>
            <circle cx="70" cy="70" r="12" fill="#cbd5e1"/>
        </svg>'
    );
}

/**
 * Get all images for a product
 */
function product_images(PDO $pdo, int $productId): array
{
    $stmt = $pdo->prepare(
        "SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC"
    );
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

/**
 * Delete a product image (DB + file)
 */
function delete_product_image(PDO $pdo, int $imageId, int $companyId): bool
{
    // Verify ownership
    $stmt = $pdo->prepare(
        "SELECT pi.*, p.company_id 
         FROM product_images pi
         JOIN products p ON p.id = pi.product_id
         WHERE pi.id = ? AND p.company_id = ? LIMIT 1"
    );
    $stmt->execute([$imageId, $companyId]);
    $img = $stmt->fetch();
    if (!$img) return false;

    // Delete file
    $fullPath = UPLOADS_PATH . '/' . $img['image_path'];
    if (file_exists($fullPath)) @unlink($fullPath);

    $pdo->prepare("DELETE FROM product_images WHERE id = ?")->execute([$imageId]);
    return true;
}