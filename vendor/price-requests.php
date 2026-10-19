<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_VENDOR);

$statusFilter = $_GET['status'] ?? '';

$sql = "SELECT pr.*, s.shop_name,
        (SELECT COUNT(*) FROM price_request_items WHERE request_id = pr.id) AS item_count
        FROM price_requests pr
        JOIN shops s ON s.id = pr.shop_id WHERE 1=1";
$params = [];
if ($statusFilter !== '') { $sql .= " AND pr.status = ?"; $params[] = $statusFilter; }
$sql .= " ORDER BY pr.id DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

$pageTitle = 'Price Requests';
require_once dirname(__DIR__) . '/includes/vendor_header.php';
?>

<h2 class="fw-bold mb-1">Price Requests</h2>
<p class="text-muted small mb-4">Shop requests for product prices</p>

<div class="card-soft p-2 mb-3">
    <div class="d-flex flex-wrap gap-1 align-items-center">
        <span class="small text-muted ms-2">Filter:</span>
        <a href="?" class="btn btn-sm <?= $statusFilter===''?'btn-primary':'btn-outline-secondary' ?>">All</a>
        <a href="?status=pending" class="btn btn-sm <?= $statusFilter==='pending'?'btn-primary':'btn-outline-secondary' ?>">Pending</a>
        <a href="?status=sent_to_company" class="btn btn-sm <?= $statusFilter==='sent_to_company'?'btn-primary':'btn-outline-secondary' ?>">Sent to Company</a>
        <a href="?status=quoted" class="btn btn-sm <?= $statusFilter==='quoted'?'btn-primary':'btn-outline-secondary' ?>">Quoted</a>
        <a href="?status=accepted" class="btn btn-sm <?= $statusFilter==='accepted'?'btn-primary':'btn-outline-secondary' ?>">Accepted</a>
    </div>
</div>

<?php if (empty($requests)): ?>
    <div class="card-soft p-5 text-center text-muted">
        <i class="bi bi-tags fs-1 d-block mb-2 opacity-25"></i>
        No price requests found.
    </div>
<?php else: ?>
    <div class="card-soft">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Request #</th>
                        <th>Shop</th>
                        <th>Date</th>
                        <th class="text-center">Items</th>
                        <th class="text-end">Total</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($requests as $r): ?>
                    <tr>
                        <td class="small fw-semibold"><?= e($r['request_no']) ?></td>
                        <td class="small"><?= e($r['shop_name']) ?></td>
                        <td class="small text-muted"><?= fmt_datetime($r['created_at']) ?></td>
                        <td class="text-center small"><?= (int)$r['item_count'] ?></td>
                        <td class="text-end small"><?= $r['total_amount'] > 0 ? money($r['total_amount']) : '—' ?></td>
                        <td>
                            <?php
                            $clr = ['pending'=>'secondary','sent_to_company'=>'info','quoted'=>'primary','accepted'=>'success','rejected'=>'danger','countered'=>'warning','expired'=>'dark','converted'=>'success'][$r['status']] ?? 'secondary';
                            ?>
                            <span class="badge text-bg-<?= $clr ?>"><?= e(PRICE_REQUEST_STATUSES[$r['status']] ?? $r['status']) ?></span>
                        </td>
                        <td class="text-end">
                            <a href="<?= BASE_URL ?>vendor/price-request-details.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary">Open</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/vendor_footer.php'; ?>