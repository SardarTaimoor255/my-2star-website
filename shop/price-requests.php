<?php
/**
 * shop/price-requests.php
 */
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_SHOP);

$user = current_user($pdo);
$shopStmt = $pdo->prepare("SELECT id FROM shops WHERE user_id = ? LIMIT 1");
$shopStmt->execute([$user['id']]);
$shopId = (int)$shopStmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT pr.*, (SELECT COUNT(*) FROM price_request_items WHERE request_id = pr.id) AS item_count
     FROM price_requests pr WHERE pr.shop_id = ? ORDER BY pr.id DESC LIMIT 50"
);
$stmt->execute([$shopId]);
$requests = $stmt->fetchAll();

$pageTitle = 'Price Requests';
require_once dirname(__DIR__) . '/includes/shop_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1">Price Requests</h2>
        <p class="text-muted small mb-0">Request prices for products with "Price Pending"</p>
    </div>
    <a href="<?= BASE_URL ?>shop/request-price.php" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle"></i> New Request
    </a>
</div>

<?php if (empty($requests)): ?>
    <div class="card-soft p-5 text-center text-muted">
        <i class="bi bi-tags fs-1 d-block mb-2 opacity-25"></i>
        <h5 class="fw-bold">No price requests yet</h5>
        <p class="small mb-0">When you need a price for a product, submit a request.</p>
    </div>
<?php else: ?>
    <div class="card-soft">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Request #</th>
                        <th>Date</th>
                        <th class="text-center">Items</th>
                        <th class="text-end">Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($requests as $r): ?>
                    <tr>
                        <td class="small fw-semibold"><?= e($r['request_no']) ?></td>
                        <td class="small text-muted"><?= fmt_datetime($r['created_at']) ?></td>
                        <td class="text-center small"><?= (int)$r['item_count'] ?></td>
                        <td class="text-end small"><?= $r['total_amount'] > 0 ? money($r['total_amount']) : '—' ?></td>
                        <td>
                            <?php
                            $colors = [
                                'pending'         => 'secondary',
                                'sent_to_company' => 'info',
                                'quoted'          => 'primary',
                                'accepted'        => 'success',
                                'rejected'        => 'danger',
                                'countered'       => 'warning',
                                'expired'         => 'dark',
                                'converted'       => 'success',
                            ];
                            $cls = $colors[$r['status']] ?? 'secondary';
                            ?>
                            <span class="badge text-bg-<?= $cls ?>">
                                <?= e(PRICE_REQUEST_STATUSES[$r['status']] ?? $r['status']) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/shop_footer.php'; ?>