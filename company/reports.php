<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/report_helper.php';
require_role($pdo, ROLE_COMPANY);

$user = current_user($pdo);
$coStmt = $pdo->prepare("SELECT id, company_name FROM companies WHERE user_id = ? LIMIT 1");
$coStmt->execute([$user['id']]);
$company = $coStmt->fetch();
$companyId = (int)$company['id'];

$range = report_get_range();
$from = $range['from'];
$to   = $range['to'];

// Summary
$stmt = $pdo->prepare(
    "SELECT 
        COUNT(DISTINCT oi.order_id) AS total_orders,
        COALESCE(SUM(oi.company_base_price * oi.quantity), 0) AS total_value,
        SUM(oi.quantity) AS total_qty
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE oi.company_id = ? AND DATE(o.created_at) BETWEEN ? AND ?
       AND o.status NOT IN ('cancelled','returned')"
);
$stmt->execute([$companyId, $from, $to]);
$summary = $stmt->fetch();

// Received payments
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(amount), 0) FROM payments 
     WHERE recipient_type='company' AND company_id = ? AND status='paid'
       AND DATE(paid_at) BETWEEN ? AND ?"
);
$stmt->execute([$companyId, $from, $to]);
$received = (float)$stmt->fetchColumn();

$outstanding = max(0, (float)$summary['total_value'] - $received);

// Monthly performance
$stmt = $pdo->prepare(
    "SELECT DATE_FORMAT(o.created_at, '%Y-%m') AS month,
        COUNT(DISTINCT o.id) AS orders,
        COALESCE(SUM(oi.company_base_price * oi.quantity), 0) AS value
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE oi.company_id = ? AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
       AND o.status NOT IN ('cancelled','returned')
     GROUP BY month ORDER BY month ASC"
);
$stmt->execute([$companyId]);
$monthlyRaw = [];
foreach ($stmt->fetchAll() as $r) $monthlyRaw[$r['month']] = $r;

$monthly = ['labels' => [], 'orders' => [], 'value' => []];
for ($i = 11; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("-$i months"));
    $monthly['labels'][] = date('M Y', strtotime($key . '-01'));
    $monthly['orders'][] = (int)($monthlyRaw[$key]['orders'] ?? 0);
    $monthly['value'][]  = (float)($monthlyRaw[$key]['value'] ?? 0);
}

// Top products supplied
$stmt = $pdo->prepare(
    "SELECT p.name, p.sku,
        SUM(oi.quantity) AS qty,
        COALESCE(SUM(oi.company_base_price * oi.quantity), 0) AS revenue
     FROM order_items oi
     JOIN products p ON p.id = oi.product_id
     JOIN orders o ON o.id = oi.order_id
     WHERE oi.company_id = ? AND DATE(o.created_at) BETWEEN ? AND ?
       AND o.status NOT IN ('cancelled','returned')
     GROUP BY p.id ORDER BY qty DESC LIMIT 8"
);
$stmt->execute([$companyId, $from, $to]);
$topProducts = $stmt->fetchAll();

// Top shops
$stmt = $pdo->prepare(
    "SELECT s.shop_name, COUNT(DISTINCT o.id) AS orders,
        COALESCE(SUM(oi.company_base_price * oi.quantity), 0) AS value
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     JOIN shops s ON s.id = o.shop_id
     WHERE oi.company_id = ? AND DATE(o.created_at) BETWEEN ? AND ?
       AND o.status NOT IN ('cancelled','returned')
     GROUP BY s.id ORDER BY value DESC LIMIT 8"
);
$stmt->execute([$companyId, $from, $to]);
$topShops = $stmt->fetchAll();

$pageTitle = 'Reports';
require_once dirname(__DIR__) . '/includes/company_header.php';
?>

<style>
.rpt-card { border:1px solid #e6e9f2; border-radius:12px; background:#fff; padding:16px; height:100%; }
.rpt-card .lbl { font-size:.72rem; text-transform:uppercase; letter-spacing:.5px; color:#64748b; font-weight:600; }
.rpt-card .val { font-size:1.5rem; font-weight:800; color:#0b3d91; line-height:1.2; }
.rpt-card .sub { font-size:.72rem; color:#94a3b8; }
.chart-box { position: relative; height: 280px; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1">Reports & Analytics</h2>
        <p class="text-muted small mb-0"><?= e($company['company_name']) ?> — performance insights</p>
    </div>
</div>

<div class="card-soft p-3 mb-4">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label for="rptFrom" class="form-label small mb-1">From</label>
            <input type="date" id="rptFrom" name="from" class="form-control form-control-sm" value="<?= e($from) ?>">
        </div>
        <div class="col-md-3">
            <label for="rptTo" class="form-label small mb-1">To</label>
            <input type="date" id="rptTo" name="to" class="form-control form-control-sm" value="<?= e($to) ?>">
        </div>
        <div class="col-md-6 d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Apply</button>
            <a href="?from=<?= date('Y-m-d') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">Today</a>
            <a href="?from=<?= date('Y-m-d', strtotime('-7 days')) ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">7 Days</a>
            <a href="?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">This Month</a>
            <a href="?from=<?= date('Y-01-01') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">This Year</a>
        </div>
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">Orders</div>
            <div class="val"><?= (int)$summary['total_orders'] ?></div>
            <div class="sub">containing your items</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">Total Value</div>
            <div class="val"><?= money($summary['total_value']) ?></div>
            <div class="sub">base price value</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">Received</div>
            <div class="val text-success"><?= money($received) ?></div>
            <div class="sub">cleared payments</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">Outstanding</div>
            <div class="val text-danger"><?= money($outstanding) ?></div>
            <div class="sub">pending from vendor</div>
        </div>
    </div>
</div>

<div class="card-soft mb-4">
    <div class="p-3 border-bottom"><strong>Monthly Performance (Last 12 Months)</strong></div>
    <div class="p-3"><div class="chart-box"><canvas id="monthlyChart"></canvas></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card-soft">
            <div class="p-3 border-bottom"><strong>Top Products Supplied</strong></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>Product</th><th class="text-end">Qty</th><th class="text-end">Value</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($topProducts)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-4">No data</td></tr>
                    <?php else: foreach ($topProducts as $p): ?>
                        <tr>
                            <td class="small">
                                <div class="fw-semibold"><?= e($p['name']) ?></div>
                                <div class="text-muted" style="font-size:.72rem;"><?= e($p['sku']) ?></div>
                            </td>
                            <td class="text-end small fw-semibold"><?= (int)$p['qty'] ?></td>
                            <td class="text-end small text-success"><?= money($p['revenue']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-soft">
            <div class="p-3 border-bottom"><strong>Top Shops</strong></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>Shop</th><th class="text-end">Orders</th><th class="text-end">Value</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($topShops)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-4">No data</td></tr>
                    <?php else: foreach ($topShops as $s): ?>
                        <tr>
                            <td class="small fw-semibold"><?= e($s['shop_name']) ?></td>
                            <td class="text-end small"><?= (int)$s['orders'] ?></td>
                            <td class="text-end small"><?= money($s['value']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const monthlyData = <?= json_encode($monthly) ?>;
new Chart(document.getElementById('monthlyChart'), {
    type: 'line',
    data: {
        labels: monthlyData.labels,
        datasets: [
            { label: 'Order Value (PKR)', data: monthlyData.value, borderColor: '#0b3d91', backgroundColor: 'rgba(11,61,145,.08)', tension: 0.3, fill: true, borderWidth: 2 },
            { label: 'Orders', data: monthlyData.orders, borderColor: '#f5a623', backgroundColor: 'rgba(245,166,35,.08)', tension: 0.3, fill: true, borderWidth: 2, yAxisID: 'y1' }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        scales: {
            y:  { beginAtZero: true, position: 'left',  ticks: { callback: v => 'Rs. ' + v.toLocaleString() } },
            y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { stepSize: 1 } }
        }
    }
});
</script>

<?php require_once dirname(__DIR__) . '/includes/company_footer.php'; ?>