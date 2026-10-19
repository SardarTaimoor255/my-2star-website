<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/report_helper.php';

if (!is_logged_in($pdo)) json_response(['success' => false], 403);
$user = current_user($pdo);

if (!in_array($user['role_name'], [ROLE_ADMIN, ROLE_VENDOR], true)) {
    json_response(['success' => false, 'message' => 'Unauthorized'], 403);
}

$range = report_get_range();
$from = $range['from'];
$to   = $range['to'];

// Vendor scope
$vendorId = null;
if ($user['role_name'] === ROLE_VENDOR) {
    $stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user['id']]);
    $vendorId = (int)$stmt->fetchColumn();
}

// CSV Export
if (!empty($_GET['export'])) {
    $topProducts = report_top_products($pdo, $from, $to, 500, $vendorId);
    $topShops    = report_top_shops($pdo, $from, $to, 500, $vendorId);
    $summary     = report_sales_summary($pdo, $from, $to, $vendorId);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sales-report-' . $from . '-to-' . $to . '.csv"');

    $out = fopen('php://output', 'w');

    fputcsv($out, ['5 Star Electric & Electronics - Sales Report']);
    fputcsv($out, ['Period', $from . ' to ' . $to]);
    fputcsv($out, []);
    fputcsv($out, ['SUMMARY']);
    fputcsv($out, ['Total Orders', $summary['total_orders'] ?? 0]);
    fputcsv($out, ['Total Sales (PKR)', $summary['total_sales'] ?? 0]);
    fputcsv($out, ['Total Commission (PKR)', $summary['total_commission'] ?? 0]);
    fputcsv($out, ['Received (PKR)', $summary['total_paid'] ?? 0]);
    fputcsv($out, ['Outstanding (PKR)', $summary['total_outstanding'] ?? 0]);
    fputcsv($out, []);
    fputcsv($out, ['TOP PRODUCTS']);
    fputcsv($out, ['Name', 'SKU', 'Qty Sold', 'Revenue']);
    foreach ($topProducts as $p) {
        fputcsv($out, [$p['name'], $p['sku'], $p['qty_sold'], $p['revenue']]);
    }
    fputcsv($out, []);
    fputcsv($out, ['TOP SHOPS']);
    fputcsv($out, ['Shop', 'Orders', 'Sales', 'Outstanding']);
    foreach ($topShops as $s) {
        fputcsv($out, [$s['shop_name'], $s['total_orders'], $s['total_sales'], $s['outstanding']]);
    }

    fclose($out);
    exit;
}

// JSON for chart
json_response([
    'success'  => true,
    'summary'  => report_sales_summary($pdo, $from, $to, $vendorId),
    'monthly'  => report_monthly_sales($pdo, 12, $vendorId),
    'category' => report_sales_by_category($pdo, $from, $to, $vendorId),
    'payment'  => report_payment_methods($pdo, $from, $to, null, $vendorId),
]);