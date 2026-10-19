<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_COMPANY);

$user = current_user($pdo);
$coStmt = $pdo->prepare("SELECT id FROM companies WHERE user_id = ? LIMIT 1");
$coStmt->execute([$user['id']]);
$companyId = (int)$coStmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT pr.*, s.shop_name,
        (SELECT COUNT(*) FROM price_request_items WHERE request_id = pr.id AND company_id = ?) AS my_items
     FROM price_requests pr
     JOIN shops s ON s.id = pr.shop_id
     WHERE EXISTS (SELECT 1 FROM price_request_items WHERE request_id = pr.id AND company_id = ?)
     ORDER BY pr.id DESC LIMIT 100"
);
$stmt->execute([$companyId, $companyId]);
$requests = $stmt->fetchAll();

$pageTitle = 'Price Requests';
require_once dirname(__DIR__) . '/includes/company_header.php';
?>

<h2 class="fw-bold mb-1">Price Requests</h2>
<p class="text-muted small mb-4">Requests sent to your company by the vendor</p>

<div class="alert alert-info small">
    <i class="bi bi-shield-check"></i>
    <strong>Note:</strong> You provide the <em>base price</em>. Vendor commission is private and never shown here.
</div>

<?php if (empty($requests)): ?>
    <div class="card-soft p-5 text-center text-muted">
        <i class="bi bi-tags fs-1 d-block mb-2 opacity-25"></i> No price requests assigned to you yet.
    </div>
<?php else: ?>
    <div class="card-soft">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Request #</th><th>Shop</th><th>Items</th><th>Date</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($requests as $r): ?>
                    <tr>
                        <td class="small fw-semibold"><?= e($r['request_no']) ?></td>
                        <td class="small"><?= e($r['shop_name']) ?></td>
                        <td class="text-center small"><?= (int)$r['my_items'] ?></td>
                        <td class="small text-muted"><?= fmt_date($r['created_at']) ?></td>
                        <td>
                            <?php
                            $clr = ['pending'=>'secondary','sent_to_company'=>'warning','quoted'=>'primary','accepted'=>'success','rejected'=>'danger'][$r['status']] ?? 'secondary';
                            ?>
                            <span class="badge text-bg-<?= $clr ?>"><?= e(PRICE_REQUEST_STATUSES[$r['status']] ?? $r['status']) ?></span>
                        </td>
                        <td class="text-end">
                            <a href="<?= BASE_URL ?>company/price-request-details.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary">Open</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/company_footer.php'; ?>