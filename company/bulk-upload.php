<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/product_helper.php';
require_role($pdo, ROLE_COMPANY);

$user = current_user($pdo);
$coStmt = $pdo->prepare("SELECT id FROM companies WHERE user_id = ? LIMIT 1");
$coStmt->execute([$user['id']]);
$companyId = (int)$coStmt->fetchColumn();

// Load categories for validation
$categories = $pdo->query("SELECT id, LOWER(name) AS name_lower, name FROM categories WHERE is_active = 1")->fetchAll();
$categoryMap = [];
foreach ($categories as $c) {
    $categoryMap[$c['name_lower']] = (int)$c['id'];
}

/**
 * Parse CSV file → array of rows
 */
function parseCsvFile(string $path): array
{
    $handle = fopen($path, 'r');
    if (!$handle) throw new Exception('Cannot read CSV file.');

    // Strip BOM
    $firstBytes = fread($handle, 3);
    if ($firstBytes !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    // Read first line for delimiter detection
    $headerLine = fgets($handle);
    rewind($handle);
    $firstBytes = fread($handle, 3);
    if ($firstBytes !== "\xEF\xBB\xBF") rewind($handle);

    $delimiter = ',';
    if ($headerLine !== false) {
        $semi  = substr_count($headerLine, ';');
        $tab   = substr_count($headerLine, "\t");
        $comma = substr_count($headerLine, ',');
        if ($semi > $comma && $semi > $tab) {
            $delimiter = ';';
        } elseif ($tab > $comma && $tab > $semi) {
            $delimiter = "\t";
        }
    }

    $rows = [];
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        if (empty(array_filter($row))) continue;
        $rows[] = $row;
    }
    fclose($handle);
    return $rows;
}

$previewData = [];
$fileName = '';

// ============================================
// STEP 1: FILE UPLOAD → PREVIEW
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preview'])) {
    csrf_require($_POST['csrf_token'] ?? null);

    if (empty($_FILES['csv_file']['name'])) {
        flash_error('Please select a file.');
        redirect(BASE_URL . 'company/bulk-upload.php');
    }

    $file = $_FILES['csv_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        flash_error('File upload failed. Please try again.');
        redirect(BASE_URL . 'company/bulk-upload.php');
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        flash_error('File too large. Max 5 MB.');
        redirect(BASE_URL . 'company/bulk-upload.php');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'xlsx'], true)) {
        flash_error('Only .csv or .xlsx files allowed.');
        redirect(BASE_URL . 'company/bulk-upload.php');
    }

    // ============================================
    // PARSE FILE → ARRAY OF ROWS
    // ============================================
    try {
        if ($ext === 'csv') {
            $rows = parseCsvFile($file['tmp_name']);
        } else {
            require_once dirname(__DIR__) . '/includes/xlsx_reader.php';
            $reader = new SimpleXLSXReader($file['tmp_name']);
            $rows = $reader->getRows();
            $reader->close();
            $rows = array_values(array_filter($rows, fn($r) => !empty(array_filter($r))));
        }
    } catch (Throwable $ex) {
        flash_error('Could not read file: ' . $ex->getMessage());
        redirect(BASE_URL . 'company/bulk-upload.php');
    }

    if (empty($rows)) {
        flash_error('File is empty.');
        redirect(BASE_URL . 'company/bulk-upload.php');
    }

    // Header = first row
    $header = array_map(fn($h) => strtolower(trim((string)$h)), $rows[0]);

    // Strip BOM from first cell (CSV)
    if (!empty($header[0])) {
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
    }

    // Required columns
    $required = ['sku', 'name', 'category', 'base_price'];
    $missing = [];
    foreach ($required as $r) {
        if (!in_array($r, $header, true)) $missing[] = $r;
    }
    if (!empty($missing)) {
        flash_error('Missing required columns: ' . implode(', ', $missing));
        redirect(BASE_URL . 'company/bulk-upload.php');
    }

    // ============================================
    // VALIDATE EACH DATA ROW
    // ============================================
    $rowNum = 0;
    $seenSkus = [];

    foreach (array_slice($rows, 1) as $row) {
        $rowNum++;

        if (empty(array_filter($row))) continue;

        $row = array_pad($row, count($header), '');
        $data = array_combine($header, $row);
        if ($data === false) continue;

        $sku          = strtoupper(trim((string)($data['sku'] ?? '')));
        $name         = trim((string)($data['name'] ?? ''));
        $category     = trim((string)($data['category'] ?? ''));
        $brand        = trim((string)($data['brand'] ?? ''));
        $description  = trim((string)($data['description'] ?? ''));
        $unit         = strtolower(trim((string)($data['unit'] ?? 'pcs')));
        $basePrice    = (float)($data['base_price'] ?? 0);
        $minQty       = max(1, (int)($data['min_order_qty'] ?? 1));
        $availability = strtolower(trim((string)($data['availability'] ?? 'in_stock')));

        $rowErrors = [];

        // SKU validation
        if ($sku === '')                              $rowErrors[] = 'SKU required';
        elseif (mb_strlen($sku) < 4)                  $rowErrors[] = 'SKU min 4 chars';
        elseif (!preg_match('/^[A-Z0-9\-_]+$/', $sku)) $rowErrors[] = 'SKU format invalid';
        elseif (in_array($sku, $seenSkus, true))      $rowErrors[] = 'Duplicate SKU in file';
        else {
            $chk = $pdo->prepare("SELECT id FROM products WHERE sku = ? LIMIT 1");
            $chk->execute([$sku]);
            if ($chk->fetch()) $rowErrors[] = 'SKU already exists in DB';
        }

        // Name validation
        if ($name === '')                             $rowErrors[] = 'Name required';
        elseif (mb_strlen($name) < 5)                 $rowErrors[] = 'Name min 5 chars';
        elseif (mb_strlen($name) > 200)               $rowErrors[] = 'Name too long';

        // Category validation
        if ($category === '')                         $rowErrors[] = 'Category required';
        elseif (!isset($categoryMap[strtolower($category)])) $rowErrors[] = 'Category not found: ' . $category;

        // Brand validation
        if ($brand !== '' && mb_strlen($brand) < 2)   $rowErrors[] = 'Brand min 2 chars';

        // Price validation
        if ($basePrice <= 0)                          $rowErrors[] = 'Price must be > 0';
        elseif ($basePrice > 10000000)                $rowErrors[] = 'Price too high';

        // Unit validation
        $validUnits = ['pcs','box','coil','meter','kg','set'];
        if (!in_array($unit, $validUnits, true))      $rowErrors[] = 'Invalid unit';

        // Availability validation
        $validAvail = ['in_stock','limited','out_of_stock'];
        if (!in_array($availability, $validAvail, true)) $availability = 'in_stock';

        if ($sku !== '') $seenSkus[] = $sku;

        $previewData[] = [
            'row'          => $rowNum + 1,
            'sku'          => $sku,
            'name'         => $name,
            'category'     => $category,
            'brand'        => $brand,
            'description'  => $description,
            'unit'         => $unit,
            'base_price'   => $basePrice,
            'min_order_qty'=> $minQty,
            'availability' => $availability,
            'errors'       => $rowErrors,
            'valid'        => empty($rowErrors),
        ];
    }

    if (empty($previewData)) {
        flash_error('No data rows found.');
        redirect(BASE_URL . 'company/bulk-upload.php');
    }

    $fileName = $file['name'];
    $_SESSION['bulk_upload_data'] = $previewData;
    $_SESSION['bulk_upload_file'] = $fileName;
}

// Clear session
if (isset($_GET['clear'])) {
    unset($_SESSION['bulk_upload_data'], $_SESSION['bulk_upload_file']);
    redirect(BASE_URL . 'company/bulk-upload.php');
}

// Load from session
if (!empty($_SESSION['bulk_upload_data'])) {
    $previewData = $_SESSION['bulk_upload_data'];
    $fileName = $_SESSION['bulk_upload_file'] ?? '';
}

$validCount = count(array_filter($previewData, fn($r) => $r['valid']));
$invalidCount = count($previewData) - $validCount;

$pageTitle = 'Bulk Product Upload';
require_once dirname(__DIR__) . '/includes/company_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1">Bulk Product Upload</h2>
        <p class="text-muted small mb-0">Upload multiple products using CSV or Excel file</p>
    </div>
    <a href="<?= BASE_URL ?>company/products.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to Products
    </a>
</div>

<?php if (empty($previewData)): ?>

    <!-- STEP 1: UPLOAD FORM -->
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card-soft p-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-cloud-upload text-primary"></i> Upload File</h5>

                <form method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="preview" value="1">

                    <div class="mb-3">
                        <label class="form-label">File <span class="text-danger">*</span></label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv,.xlsx" required>
                        <div class="form-text">Max 5 MB. Accepted: <strong>.csv</strong> ya <strong>.xlsx</strong></div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-eye"></i> Preview Upload
                    </button>
                </form>

                <hr class="my-4">

                <h6 class="fw-bold mb-2">Required Columns</h6>
                <table class="table table-sm table-bordered small">
                    <thead class="table-light">
                        <tr>
                            <th>Column</th>
                            <th>Required</th>
                            <th>Example</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>sku</code></td><td>✅ Yes</td><td>FAN-XYZ-56</td></tr>
                        <tr><td><code>name</code></td><td>✅ Yes</td><td>XYZ Ceiling Fan 56 inch</td></tr>
                        <tr><td><code>category</code></td><td>✅ Yes</td><td>Fans</td></tr>
                        <tr><td><code>base_price</code></td><td>✅ Yes</td><td>8000</td></tr>
                        <tr><td><code>brand</code></td><td>Optional</td><td>XYZ</td></tr>
                        <tr><td><code>description</code></td><td>Optional</td><td>Copper motor fan</td></tr>
                        <tr><td><code>unit</code></td><td>Optional</td><td>pcs / box / coil / kg</td></tr>
                        <tr><td><code>min_order_qty</code></td><td>Optional</td><td>1</td></tr>
                        <tr><td><code>availability</code></td><td>Optional</td><td>in_stock / limited</td></tr>
                    </tbody>
                </table>

                <div class="alert alert-info small mb-0">
                    <i class="bi bi-info-circle"></i>
                    <strong>Tip:</strong> 
                    <a href="<?= BASE_URL ?>company/download-template.php" class="alert-link">Download sample CSV</a> 
                    — usko Excel mein khol kar edit karein, phir CSV ya XLSX save karke upload karein.
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card-soft p-4">
                <h6 class="fw-bold mb-3">Tips</h6>
                <ul class="small text-muted ps-3 mb-0">
                    <li>File <strong>UTF-8</strong> format mein honi chahiye</li>
                    <li><strong>Category name</strong> wahi likhein jo system mein hai</li>
                    <li><strong>SKU</strong> unique hona chahiye</li>
                    <li><strong>Base Price</strong> sirf number (no Rs., no comma)</li>
                    <li>Max <strong>500 products</strong> ek file mein</li>
                    <li>XLSX mein formulas avoid karein — sirf values rakhein</li>
                </ul>
            </div>
        </div>
    </div>

<?php else: ?>

    <!-- STEP 2: PREVIEW & IMPORT -->
    <div class="alert alert-info">
        <i class="bi bi-file-earmark-spreadsheet"></i>
        <strong>File:</strong> <?= e($fileName) ?> · 
        <strong>Total Rows:</strong> <?= count($previewData) ?> · 
        <strong class="text-success">Valid: <?= $validCount ?></strong> · 
        <strong class="text-danger">Invalid: <?= $invalidCount ?></strong>
    </div>

    <?php if ($validCount > 0): ?>
        <form method="post" action="<?= BASE_URL ?>api/bulk-upload-process.php" id="importForm">
            <?= csrf_field() ?>
            <div class="d-flex gap-2 mb-3 flex-wrap">
                <button type="submit" class="btn btn-success" 
                        onclick="return confirm('<?= $validCount ?> products import karein?')">
                    <i class="bi bi-check-circle"></i> Import <?= $validCount ?> Valid Products
                </button>
                <a href="?clear=1" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> Cancel & Upload New
                </a>
            </div>
        </form>
    <?php else: ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            Koi valid product nahi hai. Errors fix karke dobara try karein.
        </div>
        <a href="?clear=1" class="btn btn-primary">
            <i class="bi bi-arrow-left"></i> Upload New File
        </a>
    <?php endif; ?>

    <!-- Preview Table -->
    <div class="card-soft">
        <div class="p-3 border-bottom"><strong>Preview (First 20 Rows)</strong></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Row</th>
                        <th>Status</th>
                        <th>SKU</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th class="text-end">Price</th>
                        <th>Issues</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (array_slice($previewData, 0, 20) as $row): ?>
                    <tr class="<?= $row['valid'] ? '' : 'table-danger' ?>">
                        <td class="small">#<?= (int)$row['row'] ?></td>
                        <td>
                            <?php if ($row['valid']): ?>
                                <span class="badge text-bg-success">✓ Valid</span>
                            <?php else: ?>
                                <span class="badge text-bg-danger">✗ Invalid</span>
                            <?php endif; ?>
                        </td>
                        <td class="small"><code><?= e($row['sku']) ?></code></td>
                        <td class="small"><?= e($row['name']) ?></td>
                        <td class="small"><?= e($row['category']) ?></td>
                        <td class="text-end small"><?= number_format($row['base_price'], 2) ?></td>
                        <td class="small text-danger">
                            <?php if (!empty($row['errors'])): ?>
                                <?= e(implode('; ', $row['errors'])) ?>
                            <?php else: ?>
                                <span class="text-success">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($previewData) > 20): ?>
            <div class="p-3 border-top text-muted small text-center">
                Showing first 20 of <?= count($previewData) ?> rows
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/company_footer.php'; ?>