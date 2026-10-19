<?php
/**
 * api/bulk-upload-process.php
 * Process bulk product upload from session data
 */
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/product_helper.php';

if (!is_logged_in($pdo) || current_role($pdo) !== ROLE_COMPANY) {
    redirect(BASE_URL . 'auth/login.php');
}

csrf_require($_POST['csrf_token'] ?? null);

$user = current_user($pdo);
$coStmt = $pdo->prepare("SELECT id FROM companies WHERE user_id = ? LIMIT 1");
$coStmt->execute([$user['id']]);
$companyId = (int)$coStmt->fetchColumn();

$previewData = $_SESSION['bulk_upload_data'] ?? [];
if (empty($previewData)) {
    flash_error('No upload data found. Please upload a CSV file.');
    redirect(BASE_URL . 'company/bulk-upload.php');
}

// Category name → id map
$categories = $pdo->query("SELECT id, LOWER(name) AS name_lower FROM categories WHERE is_active = 1")->fetchAll();
$categoryMap = [];
foreach ($categories as $c) {
    $categoryMap[$c['name_lower']] = (int)$c['id'];
}

$imported = 0;
$skipped  = 0;
$failed   = 0;
$errors   = [];

try {
    $pdo->beginTransaction();

    foreach ($previewData as $row) {
        if (!$row['valid']) {
            $skipped++;
            continue;
        }

        try {
            // Resolve category
            $catId = $categoryMap[strtolower($row['category'])] ?? 0;
            if ($catId <= 0) {
                $errors[] = 'Row #' . $row['row'] . ': Category not found';
                $failed++;
                continue;
            }

            // Check duplicate SKU (final check)
            $chk = $pdo->prepare("SELECT id FROM products WHERE sku = ? LIMIT 1");
            $chk->execute([$row['sku']]);
            if ($chk->fetch()) {
                $errors[] = 'Row #' . $row['row'] . ': SKU ' . $row['sku'] . ' already exists';
                $skipped++;
                continue;
            }

            // Generate unique slug
            $slug = generate_product_slug($pdo, $row['name'], $row['sku']);

            // Insert product
            $stmt = $pdo->prepare(
                "INSERT INTO products 
                 (sku, name, slug, category_id, company_id, brand, description, unit, 
                  base_price, show_price, min_order_qty, availability, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 'active', ?)"
            );
            $stmt->execute([
                $row['sku'],
                $row['name'],
                $slug,
                $catId,
                $companyId,
                $row['brand'] ?: null,
                $row['description'] ?: null,
                $row['unit'],
                $row['base_price'],
                $row['min_order_qty'],
                $row['availability'],
                $user['id']
            ]);
            $productId = (int)$pdo->lastInsertId();

            // Add to product_company_prices
            $pdo->prepare(
                "INSERT INTO product_company_prices (product_id, company_id, base_price)
                 VALUES (?, ?, ?)"
            )->execute([$productId, $companyId, $row['base_price']]);

            $imported++;

        } catch (Throwable $ex) {
            error_log('Bulk import row ' . $row['row'] . ': ' . $ex->getMessage());
            $errors[] = 'Row #' . $row['row'] . ': ' . $ex->getMessage();
            $failed++;
        }
    }

    $pdo->commit();

    log_activity($pdo, 'bulk_upload_completed', 'company', null, 
        "Imported: $imported, Skipped: $skipped, Failed: $failed");

    // Clear session
    unset($_SESSION['bulk_upload_data'], $_SESSION['bulk_upload_file']);

    // Flash summary
    $summary = "✅ Import complete! ";
    $summary .= "Imported: $imported · Skipped: $skipped · Failed: $failed";
    if ($imported > 0) flash_success($summary);
    else flash_warning($summary);

    if (!empty($errors)) {
        $errorSummary = implode(' | ', array_slice($errors, 0, 5));
        if (count($errors) > 5) $errorSummary .= ' | ...and ' . (count($errors) - 5) . ' more';
        flash_error('Errors: ' . $errorSummary);
    }

    redirect(BASE_URL . 'company/products.php');

} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Bulk upload error: ' . $ex->getMessage());
    flash_error('Import failed: ' . $ex->getMessage());
    redirect(BASE_URL . 'company/bulk-upload.php');
}