<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_COMPANY);

$user = current_user($pdo);
$coStmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ? LIMIT 1");
$coStmt->execute([$user['id']]);
$company = $coStmt->fetch();
$companyId = (int)$company['id'];

// Pending price requests
$stmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT pri.request_id) FROM price_request_items pri
     JOIN price_requests pr ON pr.id = pri.request_id
     WHERE pri.company_id = ? AND pr.status = 'sent_to_company'"
);
$stmt->execute([$companyId]);
$pendingPR = (int)$stmt->fetchColumn();

// Orders
$stmt = $pdo->prepare("SELECT COUNT(*) FROM company_orders WHERE company_id = ?");
$stmt->execute([$companyId]);
$totalOrders = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM company_orders WHERE company_id = ? AND status NOT IN ('completed','cancelled')");
$stmt->execute([$companyId]);
$activeOrders = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM company_orders WHERE company_id = ? AND status = 'completed'");
$stmt->execute([$companyId]);
$completedOrders = (int)$stmt->fetchColumn();

// Recent price requests assigned to this company
$stmt = $pdo->prepare(
    "SELECT pr.*, s.shop_name, (SELECT COUNT(*) FROM price_request_items WHERE request_id = pr.id AND company_id = ?) AS my_items
     FROM price_requests pr
     JOIN shops s ON s.id = pr.shop_id
     WHERE pr.status = 'sent_to_company' AND EXISTS (
         SELECT 1 FROM price_request_items WHERE request_id = pr.id AND company_id = ?
     )
     ORDER BY pr.id DESC LIMIT 5"
);
$stmt->execute([$companyId, $companyId]);
$recentPR = $stmt->fetchAll();

$pageTitle = 'Company Dashboard';
require_once dirname(__DIR__) . '/includes/company_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1" style="color: #ffffff;">Welcome, <?= e($company['company_name']) ?>! 🏢</h2>
        <p class="small mb-0" style="color: var(--text-muted);">Here is your business overview.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="label">Pending Price Requests</div>
            <div class="value" style="color: #fbbf24;"><?= $pendingPR ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="label">Total Orders</div>
            <div class="value" style="color: #38bdf8;"><?= $totalOrders ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="label">Active Orders</div>
            <div class="value" style="color: #818cf8;"><?= $activeOrders ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="label">Completed</div>
            <div class="value" style="color: #34d399;"><?= $completedOrders ?></div>
        </div>
    </div>
</div>

<div class="card-soft">
    <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
        <strong style="color: #fff;">Recent Price Requests</strong>
        <a href="<?= BASE_URL ?>company/price-requests.php" class="small fw-semibold" style="color: var(--brand);">View All <i class="bi bi-arrow-right"></i></a>
    </div>
    <?php if (empty($recentPR)): ?>
        <div class="p-5 text-center" style="color: var(--text-muted);">
            <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
            No pending price requests. All caught up! 🎉
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr><th>Request #</th><th>Shop</th><th class="text-center">Items</th><th>Date</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($recentPR as $pr): ?>
                    <tr>
                        <td class="small fw-bold" style="color: #e2e8f0;"><?= e($pr['request_no']) ?></td>
                        <td class="small" style="color: #e2e8f0;"><?= e($pr['shop_name']) ?></td>
                        <td class="text-center small" style="color: var(--text-muted);"><?= (int)$pr['my_items'] ?></td>
                        <td class="small" style="color: var(--text-muted);"><?= fmt_date($pr['created_at']) ?></td>
                        <td class="text-end">
                            <a href="<?= BASE_URL ?>company/price-request-details.php?id=<?= (int)$pr['id'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                Respond
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/includes/company_footer.php'; ?>