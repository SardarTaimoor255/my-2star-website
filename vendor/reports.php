<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/report_helper.php';
require_role($pdo, ROLE_VENDOR);

$user = current_user($pdo);
$vendorStmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ? LIMIT 1");
$vendorStmt->execute([$user['id']]);
$vendorId = (int)$vendorStmt->fetchColumn();

$range = report_get_range();
$from = $range['from'];
$to   = $range['to'];

$summary       = report_sales_summary($pdo, $from, $to, $vendorId);
$monthly       = report_monthly_sales($pdo, 12, $vendorId);
$topProducts   = report_top_products($pdo, $from, $to, 8, $vendorId);
$topShops      = report_top_shops($pdo, $from, $to, 8, $vendorId);
$byCategory    = report_sales_by_category($pdo, $from, $to, $vendorId);
$byPayment     = report_payment_methods($pdo, $from, $to, null, $vendorId);
$statusDist    = report_status_distribution($pdo, $from, $to, $vendorId);

$pageTitle = 'Reports';
require_once dirname(__DIR__) . '/includes/vendor_header.php';
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
        <p class="text-muted small mb-0">Business insights for the selected period</p>
    </div>
    <a href="<?= BASE_URL ?>api/report-data.php?export=1&from=<?= e($from) ?>&to=<?= e($to) ?>" class="btn btn-outline-success btn-sm">
        <i class="bi bi-download"></i> Export CSV
    </a>
</div>

<!-- Date Range Filter -->
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
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-funnel"></i> Apply Filter
            </button>
            <a href="?from=<?= date('Y-m-d') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">Today</a>
            <a href="?from=<?= date('Y-m-d', strtotime('-7 days')) ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">7 Days</a>
            <a href="?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">This Month</a>
            <a href="?from=<?= date('Y-01-01') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">This Year</a>
        </div>
    </form>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">Total Sales</div>
            <div class="val"><?= money($summary['total_sales'] ?? 0) ?></div>
            <div class="sub"><?= (int)($summary['total_orders'] ?? 0) ?> orders</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">Commission Earned</div>
            <div class="val text-success"><?= money($summary['total_commission'] ?? 0) ?></div>
            <div class="sub">from delivered + active</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">Received</div>
            <div class="val text-success" style="font-size:1.2rem;"><?= money($summary['total_paid'] ?? 0) ?></div>
            <div class="sub">cash + digital</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">Outstanding</div>
            <div class="val text-danger" style="font-size:1.2rem;"><?= money($summary['total_outstanding'] ?? 0) ?></div>
            <div class="sub">pending from shops</div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">Delivered</div>
            <div class="val text-success"><?= (int)($summary['delivered_orders'] ?? 0) ?></div>
            <div class="sub">completed orders</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">Pending</div>
            <div class="val text-warning"><?= (int)($summary['pending_orders'] ?? 0) ?></div>
            <div class="sub">in progress</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">Cancelled</div>
            <div class="val text-danger"><?= (int)($summary['cancelled_orders'] ?? 0) ?></div>
            <div class="sub">rejected / cancelled</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">Avg Order</div>
            <div class="val" style="font-size:1.2rem;">
                <?php
                $avg = ($summary['total_orders'] ?? 0) > 0
                    ? $summary['total_sales'] / $summary['total_orders']
                    : 0;
                echo money($avg);
                ?>
            </div>
            <div class="sub">per order</div>
        </div>
    </div>
</div>

<!-- Monthly Trend Chart -->
<div class="card-soft mb-4">
    <div class="p-3 border-bottom"><strong>Monthly Sales & Commission (Last 12 Months)</strong></div>
    <div class="p-3"><div class="chart-box"><canvas id="monthlyChart"></canvas></div></div>
</div>

<!-- Sales by Category + Payment Methods -->
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card-soft h-100">
            <div class="p-3 border-bottom"><strong>Sales by Category</strong></div>
            <div class="p-3"><div class="chart-box"><canvas id="categoryChart"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-soft h-100">
            <div class="p-3 border-bottom"><strong>Payment Methods</strong></div>
            <div class="p-3"><div class="chart-box"><canvas id="paymentChart"></canvas></div></div>
        </div>
    </div>
</div>

<!-- Top Products -->
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card-soft">
            <div class="p-3 border-bottom"><strong>Top Products</strong></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>Product</th><th class="text-end">Qty Sold</th><th class="text-end">Revenue</th></tr>
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
                            <td class="text-end small fw-semibold"><?= (int)$p['qty_sold'] ?></td>
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
                        <tr><th>Shop</th><th class="text-end">Orders</th><th class="text-end">Total</th><th class="text-end">Outstanding</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($topShops)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">No data</td></tr>
                    <?php else: foreach ($topShops as $s): ?>
                        <tr>
                            <td class="small fw-semibold"><?= e($s['shop_name']) ?></td>
                            <td class="text-end small"><?= (int)$s['total_orders'] ?></td>
                            <td class="text-end small"><?= money($s['total_sales']) ?></td>
                            <td class="text-end small text-danger"><?= money($s['outstanding']) ?></td>
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
const categoryData = <?= json_encode($byCategory) ?>;
const paymentData = <?= json_encode($byPayment) ?>;

// Monthly chart
new Chart(document.getElementById('monthlyChart'), {
    type: 'line',
    data: {
        labels: monthlyData.labels,
        datasets: [
            {
                label: 'Sales (PKR)',
                data: monthlyData.sales,
                borderColor: '#0b3d91',
                backgroundColor: 'rgba(11,61,145,.08)',
                tension: 0.3,
                fill: true,
                borderWidth: 2,
                pointRadius: 3,
            },
            {
                label: 'Commission (PKR)',
                data: monthlyData.commission,
                borderColor: '#f5a623',
                backgroundColor: 'rgba(245,166,35,.08)',
                tension: 0.3,
                fill: true,
                borderWidth: 2,
                pointRadius: 3,
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'top' } },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { callback: v => 'Rs. ' + v.toLocaleString() }
            }
        }
    }
});

// Category chart
new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
        labels: categoryData.map(c => c.category || 'Uncategorized'),
        datasets: [{
            data: categoryData.map(c => c.revenue),
            backgroundColor: ['#0b3d91','#f5a623','#10b981','#ef4444','#8b5cf6','#06b6d4','#ec4899','#84cc16']
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'right', labels: { font: { size: 11 } } } }
    }
});

// Payment methods chart
new Chart(document.getElementById('paymentChart'), {
    type: 'bar',
    data: {
        labels: paymentData.map(p => p.payment_method.charAt(0).toUpperCase() + p.payment_method.slice(1).replace('_',' ')),
        datasets: [{
            label: 'Amount (PKR)',
            data: paymentData.map(p => p.total),
            backgroundColor: '#0b3d91',
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => 'Rs. ' + v.toLocaleString() } }
        }
    }
});
</script>

<?php require_once dirname(__DIR__) . '/includes/vendor_footer.php'; ?>