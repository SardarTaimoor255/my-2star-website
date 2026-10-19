<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/report_helper.php';
require_role($pdo, ROLE_TRANSPORT);

$user = current_user($pdo);
$tcStmt = $pdo->prepare("SELECT id, company_name FROM transport_companies WHERE user_id = ? LIMIT 1");
$tcStmt->execute([$user['id']]);
$tc = $tcStmt->fetch();

$range = report_get_range();
$from = $range['from'];
$to   = $range['to'];

// Summary — shipments assigned to this user
$stmt = $pdo->prepare(
    "SELECT 
        COUNT(*) AS total_shipments,
        SUM(CASE WHEN status='delivered' THEN 1 ELSE 0 END) AS delivered,
        SUM(CASE WHEN status='in_transit' THEN 1 ELSE 0 END) AS in_transit,
        SUM(CASE WHEN status='out_for_delivery' THEN 1 ELSE 0 END) AS out_for_delivery,
        SUM(CASE WHEN status='picked_up' THEN 1 ELSE 0 END) AS picked_up,
        SUM(CASE WHEN status='assigned' THEN 1 ELSE 0 END) AS assigned,
        SUM(CASE WHEN status='delivery_failed' THEN 1 ELSE 0 END) AS failed,
        SUM(CASE WHEN status='returned' THEN 1 ELSE 0 END) AS returned
     FROM shipments
     WHERE transport_user_id = ? AND DATE(created_at) BETWEEN ? AND ?"
);
$stmt->execute([$user['id'], $from, $to]);
$summary = $stmt->fetch();

// Delivery performance — avg days between pickup and delivery
$stmt = $pdo->prepare(
    "SELECT 
        AVG(TIMESTAMPDIFF(HOUR, pickup_date, delivered_at)) / 24 AS avg_days,
        MIN(delivered_at) AS first_delivery,
        MAX(delivered_at) AS last_delivery
     FROM shipments
     WHERE transport_user_id = ? AND status = 'delivered'
       AND pickup_date IS NOT NULL AND delivered_at IS NOT NULL
       AND DATE(created_at) BETWEEN ? AND ?"
);
$stmt->execute([$user['id'], $from, $to]);
$perf = $stmt->fetch();

// Monthly deliveries (12 months)
$stmt = $pdo->prepare(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
        COUNT(*) AS total,
        SUM(CASE WHEN status='delivered' THEN 1 ELSE 0 END) AS delivered
     FROM shipments
     WHERE transport_user_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
     GROUP BY month ORDER BY month ASC"
);
$stmt->execute([$user['id']]);
$monthlyRaw = [];
foreach ($stmt->fetchAll() as $r) $monthlyRaw[$r['month']] = $r;

$monthly = ['labels' => [], 'total' => [], 'delivered' => []];
for ($i = 11; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("-$i months"));
    $monthly['labels'][]    = date('M Y', strtotime($key . '-01'));
    $monthly['total'][]     = (int)($monthlyRaw[$key]['total'] ?? 0);
    $monthly['delivered'][] = (int)($monthlyRaw[$key]['delivered'] ?? 0);
}

// Top destinations (shop cities — approximate via delivery address string)
$stmt = $pdo->prepare(
    "SELECT s.shop_name, s.address,
        COUNT(sh.id) AS shipments,
        SUM(CASE WHEN sh.status='delivered' THEN 1 ELSE 0 END) AS delivered
     FROM shipments sh
     JOIN orders o ON o.id = sh.order_id
     JOIN shops s ON s.id = o.shop_id
     WHERE sh.transport_user_id = ? AND DATE(sh.created_at) BETWEEN ? AND ?
     GROUP BY s.id ORDER BY shipments DESC LIMIT 8"
);
$stmt->execute([$user['id'], $from, $to]);
$topShops = $stmt->fetchAll();

// Status distribution
$stmt = $pdo->prepare(
    "SELECT status, COUNT(*) AS cnt FROM shipments
     WHERE transport_user_id = ? AND DATE(created_at) BETWEEN ? AND ?
     GROUP BY status"
);
$stmt->execute([$user['id'], $from, $to]);
$statusDist = $stmt->fetchAll();

$pageTitle = 'Reports';
require_once dirname(__DIR__) . '/includes/transport_header.php';
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
        <h2 class="fw-bold mb-1">Delivery Reports</h2>
        <p class="text-muted small mb-0"><?= e($tc['company_name'] ?? 'Transport') ?> — performance insights</p>
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
            <div class="lbl">Total Shipments</div>
            <div class="val"><?= (int)$summary['total_shipments'] ?></div>
            <div class="sub">in this period</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">Delivered</div>
            <div class="val text-success"><?= (int)$summary['delivered'] ?></div>
            <div class="sub">
                <?php
                $rate = (int)$summary['total_shipments'] > 0
                    ? round(((int)$summary['delivered'] / (int)$summary['total_shipments']) * 100)
                    : 0;
                echo $rate . '% success rate';
                ?>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">In Transit</div>
            <div class="val text-primary"><?= (int)$summary['in_transit'] ?></div>
            <div class="sub">on the way</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">Out for Delivery</div>
            <div class="val text-info"><?= (int)$summary['out_for_delivery'] ?></div>
            <div class="sub">today</div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">Picked Up</div>
            <div class="val text-warning"><?= (int)$summary['picked_up'] ?></div>
            <div class="sub">awaiting transit</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">Delivery Failed</div>
            <div class="val text-danger"><?= (int)$summary['failed'] ?></div>
            <div class="sub">unsuccessful</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">Avg Delivery Time</div>
            <div class="val" style="font-size:1.2rem;">
                <?php
                $avg = (float)($perf['avg_days'] ?? 0);
                echo $avg > 0 ? number_format($avg, 1) . ' days' : '—';
                ?>
            </div>
            <div class="sub">pickup to delivery</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rpt-card">
            <div class="lbl">Returned</div>
            <div class="val text-secondary"><?= (int)$summary['returned'] ?></div>
            <div class="sub">back to origin</div>
        </div>
    </div>
</div>

<!-- Monthly Chart -->
<div class="card-soft mb-4">
    <div class="p-3 border-bottom"><strong>Monthly Deliveries (Last 12 Months)</strong></div>
    <div class="p-3"><div class="chart-box"><canvas id="monthlyChart"></canvas></div></div>
</div>

<!-- Status Distribution + Top Shops -->
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card-soft h-100">
            <div class="p-3 border-bottom"><strong>Shipment Status Breakdown</strong></div>
            <div class="p-3"><div class="chart-box"><canvas id="statusChart"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card-soft h-100">
            <div class="p-3 border-bottom"><strong>Frequent Delivery Destinations</strong></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Shop</th>
                            <th class="text-end">Shipments</th>
                            <th class="text-end">Delivered</th>
                            <th class="text-end">Success</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($topShops)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">No shipments in this period</td></tr>
                    <?php else: foreach ($topShops as $s):
                        $rate = $s['shipments'] > 0 ? round(($s['delivered'] / $s['shipments']) * 100) : 0;
                    ?>
                        <tr>
                            <td class="small">
                                <div class="fw-semibold"><?= e($s['shop_name']) ?></div>
                                <div class="text-muted" style="font-size:.72rem;"><?= e(mb_strimwidth($s['address'] ?? '', 0, 40, '...')) ?></div>
                            </td>
                            <td class="text-end small fw-semibold"><?= (int)$s['shipments'] ?></td>
                            <td class="text-end small text-success"><?= (int)$s['delivered'] ?></td>
                            <td class="text-end small">
                                <span class="badge text-bg-<?= $rate >= 80 ? 'success' : ($rate >= 50 ? 'warning' : 'danger') ?>">
                                    <?= $rate ?>%
                                </span>
                            </td>
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
const statusData  = <?= json_encode($statusDist) ?>;

new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
        labels: monthlyData.labels,
        datasets: [
            {
                label: 'Total Shipments',
                data: monthlyData.total,
                backgroundColor: 'rgba(11,61,145,.35)',
                borderRadius: 5
            },
            {
                label: 'Delivered',
                data: monthlyData.delivered,
                backgroundColor: '#0b3d91',
                borderRadius: 5
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});

const statusLabels = statusData.map(s => s.status.replace(/_/g,' ').replace(/\b\w/g, c => c.toUpperCase()));
const statusColors = {
    assigned: '#f5a623',
    picked_up: '#06b6d4',
    in_transit: '#0b3d91',
    out_for_delivery: '#3b82f6',
    delivered: '#10b981',
    delivery_failed: '#ef4444',
    returned: '#6b7280'
};

new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: statusLabels,
        datasets: [{
            data: statusData.map(s => s.cnt),
            backgroundColor: statusData.map(s => statusColors[s.status] || '#94a3b8')
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'right', labels: { font: { size: 11 } } } }
    }
});
</script>

<?php require_once dirname(__DIR__) . '/includes/transport_footer.php'; ?>