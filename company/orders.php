<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_COMPANY);
require_once dirname(__DIR__) . '/includes/pagination.php';
$user = current_user($pdo);
$coStmt = $pdo->prepare("SELECT id FROM companies WHERE user_id = ? LIMIT 1");
$coStmt->execute([$user['id']]);
$companyId = (int)$coStmt->fetchColumn();

// Fetch orders that have items assigned to this company
// Build WHERE clause (reused in count + main)
$where = " WHERE EXISTS (SELECT 1 FROM order_items WHERE order_id = o.id AND company_id = ?)
           AND o.status NOT IN ('order_created','order_accepted','cancelled')";
$params = [$companyId];

// Count total
$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM orders o JOIN shops s ON s.id = o.shop_id" . $where
);
$countStmt->execute($params);
$totalOrders = (int)$countStmt->fetchColumn();

// Pagination
$pg = paginate($totalOrders, current_page_num(), 5, 'orders.php');
$offset = $pg['offset'];

// Main query
$stmt = $pdo->prepare(
    "SELECT o.*, s.shop_name,
        (SELECT COUNT(*) FROM order_items WHERE order_id = o.id AND company_id = ?) AS my_items
     FROM orders o
     JOIN shops s ON s.id = o.shop_id"
    . $where
    . " ORDER BY o.id DESC LIMIT {$pg['perPage']} OFFSET $offset"
);
$stmt->execute([$companyId, $companyId]);
$orders = $stmt->fetchAll();
$pageTitle = 'Orders';
require_once dirname(__DIR__) . '/includes/company_header.php';
?>

<h2 class="fw-bold mb-1">Orders</h2>
<p class="text-muted small mb-4">Orders containing your products</p>

<?php if (empty($orders)): ?>
    <div class="card-soft p-5 text-center text-muted">
        <i class="bi bi-receipt fs-1 d-block mb-2 opacity-25"></i> No orders yet.
    </div>
<?php else: ?>
    <div class="card-soft">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Order #</th><th>Shop</th><th>Date</th><th class="text-center">Your Items</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td class="small fw-semibold"><?= e($o['order_number']) ?></td>
                        <td class="small"><?= e($o['shop_name']) ?></td>
                        <td class="small text-muted"><?= fmt_date($o['created_at']) ?></td>
                        <td class="text-center small"><?= (int)$o['my_items'] ?></td>
                        <td><?= status_badge($o['status']) ?></td>
                        <td class="text-end">
                            <a href="<?= BASE_URL ?>company/order-details.php?id=<?= (int)$o['id'] ?>" class="btn btn-sm btn-outline-primary">Open</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
          <?= $pg['html'] ?>
    </div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/company_footer.php'; ?>