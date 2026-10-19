<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/report_helper.php';
require_role($pdo, ROLE_SHOP);

$user = current_user($pdo);
$shopStmt = $pdo->prepare("SELECT id, shop_name FROM shops WHERE user_id = ? LIMIT 1");
$shopStmt->execute([$user['id']]);
$shop = $shopStmt->fetch();
$shopId = (int)$shop['id'];

$range = report_get_range();
$from = $range['from'];
$to   = $range['to'];

// ---- SHOP-SPECIFIC QUERIES ----

// 1. Summary
$stmt = $pdo->prepare(
    "SELECT 
        COUNT(*) AS total_orders,
        COALESCE(SUM(total_amount), 0) AS total_spent,
        COALESCE(SUM(paid_amount), 0) AS total_paid,
        COALESCE(SUM(total_amount - paid_amount), 0) AS outstanding,
        SUM(CASE WHEN status='delivered' THEN 1 ELSE 0 END) AS delivered,
        SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelled,
        SUM(CASE WHEN status NOT IN ('delivered','cancelled','returned') THEN 1 ELSE 0 END) AS pending
     FROM orders
     WHERE shop_id = ? AND DATE(created_at) BETWEEN ? AND ?"
);
$stmt->execute([$shopId, $from, $to]);
$summary = $stmt->fetch();

// 2. Monthly spending (12 months)
$stmt = $pdo->prepare(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
        COUNT(*) AS orders,
        COALESCE(SUM(total_amount), 0) AS spent
     FROM orders
     WHERE shop_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
       AND status NOT IN ('cancelled')
     GROUP BY month ORDER BY month ASC"
);
$stmt->execute([$shopId]);
$monthlyRaw = [];
foreach ($stmt->fetchAll() as $r) $monthlyRaw[$r['month']] = $r;

$monthly = ['labels' => [], 'spent' => [], 'orders' => []];
for ($i = 11; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("-$i months"));
    $monthly['labels'][] = date('M Y', strtotime($key . '-01'));
    $monthly['spent'][]  = (float)($monthlyRaw[$key]['spent'] ?? 0);
    $monthly['orders'][] = (int)($monthlyRaw[$key]['orders'] ?? 0);
}

// 3. Top products purchased
$stmt = $pdo->prepare(
    "SELECT p.name, p.sku,
        SUM(oi.quantity) AS qty,
        COALESCE(SUM(oi.line_total), 0) AS total
     FROM order_items oi
     JOIN products p ON p.id = oi.product_id
     JOIN orders o ON o.id = oi.order_id
     WHERE o.shop_id = ? AND DATE(o.created_at) BETWEEN ? AND ?
       AND o.status NOT IN ('cancelled','returned')
     GROUP BY p.id ORDER BY qty DESC LIMIT 8"
);
$stmt->execute([$shopId, $from, $to]);
$topProducts = $stmt->fetchAll();

// 4. Sales by category (for shop)
$stmt = $pdo->prepare(
    "SELECT c.name AS category,
        SUM(oi.quantity) AS qty,
        COALESCE(SUM(oi.line_total), 0) AS revenue
     FROM order_items oi
     JOIN products p ON p.id = oi.product_id
     JOIN categories c ON c.id = p.category_id
     JOIN orders o ON o.id = oi.order_id
     WHERE o.shop_id = ? AND DATE(o.created_at) BETWEEN ? AND ?
       AND o.status NOT IN ('cancelled','returned')
     GROUP BY c.id ORDER BY revenue DESC"
);
$stmt->execute([$shopId, $from, $to]);
$byCategory = $stmt->fetchAll();

// 5. Payment methods
$stmt = $pdo->prepare(
    "SELECT payment_method, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total
     FROM payments
     WHERE shop_id = ? AND status = 'paid' AND DATE(paid_at) BETWEEN ? AND ?
     GROUP BY payment_method ORDER BY total DESC"
);
$stmt->execute([$shopId, $from, $to]);
$byPayment = $stmt->fetchAll();

// 6. Status distribution
$stmt = $pdo->prepare(
    "SELECT status, COUNT(*) AS cnt FROM orders
     WHERE shop_id = ? AND DATE(created_at) BETWEEN ? AND ?
     GROUP BY status"
);
$stmt->execute([$shopId, $from, $to]);
$statusDist = $stmt->fetchAll();

$pageTitle = 'Reports';
require_once dirname(__DIR__) . '/includes/shop_header.php';
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
        <h2 class="fw-bold mb-1">Purchase Reports</h2>
        <p class="text-muted small mb-0"><?= e($shop['shop_name']) ?> — business insights</p>
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

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">Total Orders</div>
            <div class="val"><?= (int)$summary['total_orders'] ?></div>
            <div class="sub">in this period</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">Total Spent</div>
            <div class="val"><?= money($summary['total_spent']) ?></div>
            <div class="sub">purchases</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">Paid</div>
            <div class="val text-success"><?= money($summary['total_paid']) ?></div>
            <div class="sub">cleared payments</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">Outstanding</div>
            <div class="val text-danger"><?= money($summary['outstanding']) ?></div>
            <div class="sub">due amount</div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">Delivered</div>
            <div class="val text-success"><?= (int)$summary['delivered'] ?></div>
            <div class="sub">completed</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">Pending</div>
            <div class="val text-warning"><?= (int)$summary['pending'] ?></div>
            <div class="sub">in progress</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">Cancelled</div>
            <div class="val text-danger"><?= (int)$summary['cancelled'] ?></div>
            <div class="sub">rejected</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">Avg Order</div>
            <div class="val" style="font-size:1.1rem;">
                <?php
                $avg = (int)$summary['total_orders'] > 0
                    ? (float)$summary['total_spent'] / (int)$summary['total_orders']
                    : 0;
                echo money($avg);
                ?>
            </div>
            <div class="sub">per order</div>
        </div>
    </div>
</div>

<!-- Monthly Spending Chart -->
<div class="card-soft mb-4">
    <div class="p-3 border-bottom"><strong>Monthly Purchases (Last 12 Months)</strong></div>
    <div class="p-3"><div class="chart-box"><canvas id="monthlyChart"></canvas></div></div>
</div>

<!-- Category + Payment -->
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card-soft h-100">
            <div class="p-3 border-bottom"><strong>Purchases by Category</strong></div>
            <div class="p-3"><div class="chart-box"><canvas id="categoryChart"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-soft h-100">
            <div class="p-3 border-bottom"><strong>Payment Methods Used</strong></div>
            <div class="p-3"><div class="chart-box"><canvas id="paymentChart"></canvas></div></div>
        </div>
    </div>
</div>

<!-- Top Products -->
<div class="card-soft">
    <div class="p-3 border-bottom"><strong>Products You Buy Most</strong></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr><th>Product</th><th class="text-end">Qty Purchased</th><th class="text-end">Total Spent</th></tr>
            </thead>
            <tbody>
            <?php if (empty($topProducts)): ?>
                <tr><td colspan="3" class="text-center text-muted py-4">No purchases in this period</td></tr>
            <?php else: foreach ($topProducts as $p): ?>
                <tr>
                    <td class="small">
                        <div class="fw-semibold"><?= e($p['name']) ?></div>
                        <div class="text-muted" style="font-size:.72rem;"><?= e($p['sku']) ?></div>
                    </td>
                    <td class="text-end small fw-semibold"><?= (int)$p['qty'] ?></td>
                    <td class="text-end small"><?= money($p['total']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
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
        datasets: [{
            label: 'Spent (PKR)',
            data: monthlyData.spent,
            borderColor: '#0b3d91',
            backgroundColor: 'rgba(11,61,145,.08)',
            tension: 0.3, fill: true, borderWidth: 2, pointRadius: 3
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { callback: v => 'Rs. ' + v.toLocaleString() } } }
    }
});

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

new Chart(document.getElementById('paymentChart'), {
    type: 'bar',
    data: {
        labels: paymentData.map(p => p.payment_method.charAt(0).toUpperCase() + p.payment_method.slice(1).replace('_',' ')),
        datasets: [{ data: paymentData.map(p => p.total), backgroundColor: '#0b3d91', borderRadius: 6 }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { callback: v => 'Rs. ' + v.toLocaleString() } } }
    }
});
</script>

<?php require_once dirname(__DIR__) . '/includes/shop_footer.php'; ?>