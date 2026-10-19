<?php
/**
 * company/download-template.php
 * Download sample CSV template
 */
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_COMPANY);

// Fetch 5 real categories
$cats = $pdo->query("SELECT name FROM categories WHERE is_active = 1 ORDER BY name LIMIT 5")->fetchAll();
$cat1 = $cats[0]['name'] ?? 'Fans';
$cat2 = $cats[1]['name'] ?? 'LED Lights';
$cat3 = $cats[2]['name'] ?? 'Switches & Sockets';

$filename = 'sample-products-' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 support
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Header
fputcsv($out, [
    'sku', 'name', 'category', 'brand', 'description', 
    'unit', 'base_price', 'min_order_qty', 'availability'
]);

// Sample rows
fputcsv($out, [
    'SAMPLE-FAN-01',
    'Sample Ceiling Fan 56 inch',
    $cat1,
    'SampleBrand',
    'High-speed ceiling fan with copper motor',
    'pcs',
    '8000',
    '1',
    'in_stock'
]);

fputcsv($out, [
    'SAMPLE-LED-02',
    'Sample LED Bulb 18W',
    $cat2,
    'SampleLED',
    'Energy saving LED bulb',
    'pcs',
    '350',
    '5',
    'in_stock'
]);

fputcsv($out, [
    'SAMPLE-SWT-03',
    'Sample Switch 1-Gang',
    $cat3,
    '',
    'Single gang switch premium quality',
    'pcs',
    '120',
    '10',
    'in_stock'
]);

fputcsv($out, [
    'SAMPLE-WIR-04',
    'Sample Wire 7/90 Copper',
    'Wires & Cables',
    'SamplePak',
    'Copper wire 90 meter coil',
    'coil',
    '4500',
    '1',
    'limited'
]);

fclose($out);
exit;