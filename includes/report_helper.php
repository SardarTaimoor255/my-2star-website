<?php
if (!defined('APP_INIT')) { http_response_code(403); exit('No direct access.'); }

/**
 * Get sales summary for a date range
 */
function report_sales_summary(PDO $pdo, string $from, string $to, ?int $vendorId = null): array
{
    $sql = "SELECT 
        COUNT(*) AS total_orders,
        COALESCE(SUM(total_amount), 0) AS total_sales,
        COALESCE(SUM(commission_total), 0) AS total_commission,
        COALESCE(SUM(paid_amount), 0) AS total_paid,
        COALESCE(SUM(total_amount - paid_amount), 0) AS total_outstanding,
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered_orders,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
        SUM(CASE WHEN status NOT IN ('delivered','cancelled','returned') THEN 1 ELSE 0 END) AS pending_orders
        FROM orders
        WHERE DATE(created_at) BETWEEN ? AND ?";
    $params = [$from, $to];

    if ($vendorId) { $sql .= " AND vendor_id = ?"; $params[] = $vendorId; }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() ?: [];
}

/**
 * Monthly sales for chart (last N months)
 */
function report_monthly_sales(PDO $pdo, int $months = 12, ?int $vendorId = null): array
{
    $sql = "SELECT 
        DATE_FORMAT(created_at, '%Y-%m') AS month,
        COUNT(*) AS orders,
        COALESCE(SUM(total_amount), 0) AS sales,
        COALESCE(SUM(commission_total), 0) AS commission
        FROM orders
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
          AND status NOT IN ('cancelled')";
    $params = [$months];
    if ($vendorId) { $sql .= " AND vendor_id = ?"; $params[] = $vendorId; }
    $sql .= " GROUP BY month ORDER BY month ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Build all months (fill missing with zeros)
    $labels = [];
    $sales = [];
    $commission = [];
    $orders = [];

    $data = [];
    foreach ($stmt->fetchAll() as $r) {
        $data[$r['month']] = $r;
    }

    for ($i = $months - 1; $i >= 0; $i--) {
        $key = date('Y-m', strtotime("-$i months"));
        $labels[]     = date('M Y', strtotime($key . '-01'));
        $sales[]      = (float)($data[$key]['sales'] ?? 0);
        $commission[] = (float)($data[$key]['commission'] ?? 0);
        $orders[]     = (int)($data[$key]['orders'] ?? 0);
    }

    return [
        'labels'     => $labels,
        'sales'      => $sales,
        'commission' => $commission,
        'orders'     => $orders,
    ];
}

/**
 * Top products by quantity sold
 */
function report_top_products(PDO $pdo, string $from, string $to, int $limit = 10, ?int $vendorId = null): array
{
    $sql = "SELECT p.id, p.name, p.sku,
        SUM(oi.quantity) AS qty_sold,
        SUM(oi.line_total) AS revenue
        FROM order_items oi
        JOIN products p ON p.id = oi.product_id
        JOIN orders o ON o.id = oi.order_id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
          AND o.status NOT IN ('cancelled','returned')";
    $params = [$from, $to];
    if ($vendorId) { $sql .= " AND o.vendor_id = ?"; $params[] = $vendorId; }
    $sql .= " GROUP BY p.id ORDER BY qty_sold DESC LIMIT $limit";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Top shops by purchase volume
 */
function report_top_shops(PDO $pdo, string $from, string $to, int $limit = 10, ?int $vendorId = null): array
{
    $sql = "SELECT s.id, s.shop_name, s.address,
        COUNT(o.id) AS total_orders,
        COALESCE(SUM(o.total_amount), 0) AS total_sales,
        COALESCE(SUM(o.total_amount - o.paid_amount), 0) AS outstanding
        FROM shops s
        JOIN orders o ON o.shop_id = s.id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
          AND o.status NOT IN ('cancelled','returned')";
    $params = [$from, $to];
    if ($vendorId) { $sql .= " AND o.vendor_id = ?"; $params[] = $vendorId; }
    $sql .= " GROUP BY s.id ORDER BY total_sales DESC LIMIT $limit";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Sales grouped by category
 */
function report_sales_by_category(PDO $pdo, string $from, string $to, ?int $vendorId = null): array
{
    $sql = "SELECT c.name AS category,
        SUM(oi.quantity) AS qty_sold,
        COALESCE(SUM(oi.line_total), 0) AS revenue
        FROM order_items oi
        JOIN products p ON p.id = oi.product_id
        JOIN categories c ON c.id = p.category_id
        JOIN orders o ON o.id = oi.order_id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
          AND o.status NOT IN ('cancelled','returned')";
    $params = [$from, $to];
    if ($vendorId) { $sql .= " AND o.vendor_id = ?"; $params[] = $vendorId; }
    $sql .= " GROUP BY c.id ORDER BY revenue DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Payment method breakdown
 */
function report_payment_methods(PDO $pdo, string $from, string $to, ?int $shopId = null, ?int $vendorId = null): array
{
    $sql = "SELECT p.payment_method,
        COUNT(*) AS cnt,
        COALESCE(SUM(p.amount), 0) AS total
        FROM payments p
        JOIN orders o ON o.id = p.order_id
        WHERE DATE(p.paid_at) BETWEEN ? AND ?
          AND p.status = 'paid'";
    $params = [$from, $to];
    if ($shopId)   { $sql .= " AND p.shop_id = ?";   $params[] = $shopId; }
    if ($vendorId) { $sql .= " AND o.vendor_id = ?"; $params[] = $vendorId; }
    $sql .= " GROUP BY p.payment_method ORDER BY total DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Admin: platform overview
 */
function report_platform_overview(PDO $pdo, string $from, string $to): array
{
    $stmt = $pdo->prepare(
        "SELECT
            (SELECT COUNT(*) FROM users WHERE DATE(created_at) BETWEEN ? AND ?) AS new_users,
            (SELECT COUNT(*) FROM shops WHERE DATE(created_at) BETWEEN ? AND ?) AS new_shops,
            (SELECT COUNT(*) FROM companies WHERE DATE(created_at) BETWEEN ? AND ?) AS new_companies,
            (SELECT COUNT(*) FROM orders WHERE DATE(created_at) BETWEEN ? AND ?) AS new_orders,
            (SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status='delivered') AS revenue,
            (SELECT COALESCE(SUM(commission_total),0) FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status='delivered') AS commission
        "
    );
    $stmt->execute([$from,$to,$from,$to,$from,$to,$from,$to,$from,$to,$from,$to]);
    return $stmt->fetch() ?: [];
}

/**
 * Status distribution
 */
function report_status_distribution(PDO $pdo, string $from, string $to, ?int $vendorId = null): array
{
    $sql = "SELECT status, COUNT(*) AS cnt
            FROM orders
            WHERE DATE(created_at) BETWEEN ? AND ?";
    $params = [$from, $to];
    if ($vendorId) { $sql .= " AND vendor_id = ?"; $params[] = $vendorId; }
    $sql .= " GROUP BY status";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Default date range: last 30 days
 */
function report_default_range(): array
{
    return [
        'from' => date('Y-m-d', strtotime('-30 days')),
        'to'   => date('Y-m-d'),
    ];
}

/**
 * Parse and sanitize date range from GET
 */
function report_get_range(): array
{
    $def = report_default_range();
    $from = $_GET['from'] ?? $def['from'];
    $to   = $_GET['to']   ?? $def['to'];

    // Validate format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = $def['from'];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = $def['to'];

    return compact('from', 'to');
}