<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/report_helper.php';
require_role($pdo, ROLE_ADMIN);

$range = report_get_range();
$from = $range['from'];
$to   = $range['to'];

$overview      = report_platform_overview($pdo, $from, $to);
$summary       = report_sales_summary($pdo, $from, $to, null);
$monthly       = report_monthly_sales($pdo, 12, null);
$topProducts   = report_top_products($pdo, $from, $to, 8, null);
$topShops      = report_top_shops($pdo, $from, $to, 8, null);
$byCategory    = report_sales_by_category($pdo, $from, $to, null);
$byPayment     = report_payment_methods($pdo, $from, $to, null, null);
$statusDist    = report_status_distribution($pdo, $from, $to, null);

$pageTitle = 'Reports';
require_once dirname(__DIR__) . '/includes/admin_header.php';
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
        <h2 class="fw-bold mb-1">Platform Reports</h2>
        <p class="text-muted small mb-0">Overall business analytics</p>
    </div>
</div>

<!-- Date Range -->
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

<!-- Overview -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">New Users</div>
            <div class="val"><?= (int)($overview['new_users'] ?? 0) ?></div>
            <div class="sub">in this period</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">New Shops</div>
            <div class="val text-success"><?= (int)($overview['new_shops'] ?? 0) ?></div>
            <div class="sub">registered</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">New Companies</div>
            <div class="val text-info"><?= (int)($overview['new_companies'] ?? 0) ?></div>
            <div class="sub">registered</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">New Orders</div>
            <div class="val"><?= (int)($overview['new_orders'] ?? 0) ?></div>
            <div class="sub">placed</div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">Revenue (Delivered)</div>
            <div class="val" style="font-size:1.2rem;"><?= money($overview['revenue'] ?? 0) ?></div>
            <div class="sub">completed sales</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">Total Commission</div>
            <div class="val text-success" style="font-size:1.2rem;"><?= money($overview['commission'] ?? 0) ?></div>
            <div class="sub">vendor earnings</div>
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
            <div class="sub">pending</div>
        </div>
    </div>
</div>

<!-- Monthly chart -->
<div class="card-soft mb-4">
    <div class="p-3 border-bottom"><strong>Monthly Platform Activity (Last 12 Months)</strong></div>
    <div class="p-3"><div class="chart-box"><canvas id="monthlyChart"></canvas></div></div>
</div>

<!-- Category + Payment -->
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

<!-- Top Products + Shops -->
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card-soft">
            <div class="p-3 border-bottom"><strong>Top Products</strong></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light"><tr><th>Product</th><th class="text-end">Qty</th><th class="text-end">Revenue</th></tr></thead>
                    <tbody>
                    <?php foreach ($topProducts as $p): ?>
                        <tr>
                            <td class="small"><?= e($p['name']) ?></td>
                            <td class="text-end small"><?= (int)$p['qty_sold'] ?></td>
                            <td class="text-end small"><?= money($p['revenue']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($topProducts)): ?><tr><td colspan="3" class="text-center text-muted py-3">No data</td></tr><?php endif; ?>
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
                    <thead class="table-light"><tr><th>Shop</th><th class="text-end">Orders</th><th class="text-end">Sales</th></tr></thead>
                    <tbody>
                    <?php foreach ($topShops as $s): ?>
                        <tr>
                            <td class="small"><?= e($s['shop_name']) ?></td>
                            <td class="text-end small"><?= (int)$s['total_orders'] ?></td>
                            <td class="text-end small"><?= money($s['total_sales']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($topShops)): ?><tr><td colspan="3" class="text-center text-muted py-3">No data</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const monthlyData  = <?= json_encode($monthly) ?>;
const categoryData = <?= json_encode($byCategory) ?>;
const paymentData  = <?= json_encode($byPayment) ?>;

new Chart(document.getElementById('monthlyChart'), {
    type: 'line',
    data: {
        labels: monthlyData.labels,
        datasets: [
            { label: 'Sales (PKR)', data: monthlyData.sales, borderColor: '#0b3d91', backgroundColor: 'rgba(11,61,145,.08)', tension: 0.3, fill: true, borderWidth: 2 },
            { label: 'Commission (PKR)', data: monthlyData.commission, borderColor: '#f5a623', backgroundColor: 'rgba(245,166,35,.08)', tension: 0.3, fill: true, borderWidth: 2 }
        ]
    },
    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { callback: v => 'Rs. ' + v.toLocaleString() } } } }
});

new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
        labels: categoryData.map(c => c.category || 'Uncategorized'),
        datasets: [{ data: categoryData.map(c => c.revenue), backgroundColor: ['#0b3d91','#f5a623','#10b981','#ef4444','#8b5cf6','#06b6d4','#ec4899','#84cc16'] }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { font: { size: 11 } } } } }
});

new Chart(document.getElementById('paymentChart'), {
    type: 'bar',
    data: {
        labels: paymentData.map(p => p.payment_method.charAt(0).toUpperCase() + p.payment_method.slice(1).replace('_',' ')),
        datasets: [{ label: 'Amount', data: paymentData.map(p => p.total), backgroundColor: '#0b3d91', borderRadius: 6 }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { callback: v => 'Rs. ' + v.toLocaleString() } } } }
});
</script>

<?php require_once dirname(__DIR__) . '/includes/admin_footer.php'; ?>