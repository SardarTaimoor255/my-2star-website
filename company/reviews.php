<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/review_helper.php';
require_role($pdo, ROLE_COMPANY);

$user = current_user($pdo);
$coStmt = $pdo->prepare("SELECT id FROM companies WHERE user_id = ? LIMIT 1");
$coStmt->execute([$user['id']]);
$companyId = (int)$coStmt->fetchColumn();

// Filters
$ratingFilter = (int)($_GET['rating'] ?? 0);
$replyFilter  = $_GET['reply'] ?? '';

$sql = "SELECT r.*, p.name AS product_name, p.sku, s.shop_name
        FROM product_reviews r
        JOIN products p ON p.id = r.product_id
        JOIN shops s ON s.id = r.shop_id
        WHERE p.company_id = ?";
$params = [$companyId];

if ($ratingFilter > 0) {
    $sql .= " AND r.rating = ?";
    $params[] = $ratingFilter;
}
if ($replyFilter === 'pending') {
    $sql .= " AND (r.company_reply IS NULL OR r.company_reply = '')";
} elseif ($replyFilter === 'replied') {
    $sql .= " AND r.company_reply IS NOT NULL AND r.company_reply <> ''";
}

$sql .= " ORDER BY r.id DESC LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reviews = $stmt->fetchAll();

// Stats
$stats = $pdo->prepare(
    "SELECT 
        COUNT(*) AS total,
        COALESCE(AVG(r.rating), 0) AS avg_rating,
        SUM(CASE WHEN company_reply IS NULL OR company_reply = '' THEN 1 ELSE 0 END) AS pending_reply
     FROM product_reviews r
     JOIN products p ON p.id = r.product_id
     WHERE p.company_id = ?"
);
$stats->execute([$companyId]);
$s = $stats->fetch();

$pageTitle = 'Product Reviews';
require_once dirname(__DIR__) . '/includes/company_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1">Product Reviews</h2>
        <p class="text-muted small mb-0">Customer feedback on your products</p>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="label">Total Reviews</div>
            <div class="value"><?= (int)$s['total'] ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="label">Average Rating</div>
            <div class="value text-warning"><?= round((float)$s['avg_rating'], 1) ?> ★</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="label">Pending Reply</div>
            <div class="value text-danger"><?= (int)$s['pending_reply'] ?></div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card-soft p-3 mb-3">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small">Rating</label>
            <select name="rating" class="form-select form-select-sm">
                <option value="0">All Ratings</option>
                <?php for ($r = 5; $r >= 1; $r--): ?>
                    <option value="<?= $r ?>" <?= $ratingFilter === $r ? 'selected' : '' ?>><?= $r ?> Star</option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small">Reply Status</label>
            <select name="reply" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="pending" <?= $replyFilter === 'pending' ? 'selected' : '' ?>>Pending Reply</option>
                <option value="replied" <?= $replyFilter === 'replied' ? 'selected' : '' ?>>Replied</option>
            </select>
        </div>
        <div class="col-md-4">
            <button class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel"></i> Filter</button>
        </div>
    </form>
</div>

<?php if (empty($reviews)): ?>
    <div class="card-soft p-5 text-center text-muted">
        <i class="bi bi-star fs-1 d-block mb-2 opacity-25"></i>
        <h5 class="fw-bold">No reviews yet</h5>
        <p class="small mb-0">Customer reviews will appear here after they purchase and review your products.</p>
    </div>
<?php else: ?>

    <?php foreach ($reviews as $r): ?>
        <div class="card-soft p-3 mb-3">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                <div>
                    <div class="fw-semibold">
                        <?= e($r['product_name']) ?>
                        <span class="text-muted small">(<?= e($r['sku']) ?>)</span>
                    </div>
                    <div class="small text-muted">
                        By <?= e($r['shop_name']) ?> · <?= fmt_datetime($r['created_at']) ?>
                    </div>
                </div>
                <div><?= render_stars((float)$r['rating'], '1.1rem') ?></div>
            </div>

            <?php if ($r['title']): ?>
                <div class="fw-semibold mb-1"><?= e($r['title']) ?></div>
            <?php endif; ?>

            <div class="text-muted small mb-3"><?= nl2br(e($r['comment'])) ?></div>

            <?php if ($r['company_reply']): ?>
                <div class="p-2 rounded mb-2" style="background:#f0f9ff; border-left:3px solid #0b3d91;">
                    <div class="small fw-semibold text-primary mb-1">
                        <i class="bi bi-reply-fill"></i> Your Reply:
                    </div>
                    <div class="small"><?= nl2br(e($r['company_reply'])) ?></div>
                    <div class="text-muted" style="font-size:.7rem;">
                        <?= fmt_datetime($r['company_replied_at']) ?>
                    </div>
                </div>
                <button class="btn btn-sm btn-outline-primary" 
                        data-bs-toggle="modal" 
                        data-bs-target="#replyModal<?= (int)$r['id'] ?>">
                    <i class="bi bi-pencil"></i> Edit Reply
                </button>
            <?php else: ?>
                <button class="btn btn-sm btn-primary" 
                        data-bs-toggle="modal" 
                        data-bs-target="#replyModal<?= (int)$r['id'] ?>">
                    <i class="bi bi-reply"></i> Reply
                </button>
            <?php endif; ?>
        </div>

        <!-- Reply Modal -->
        <div class="modal fade" id="replyModal<?= (int)$r['id'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <form method="post" action="<?= BASE_URL ?>api/review-action.php" class="modal-content">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="company_reply">
                    <input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>">

                    <div class="modal-header">
                        <h5 class="modal-title">Reply to Review</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="p-2 mb-3 rounded" style="background:#f8fafc; font-size:.85rem;">
                            <div class="mb-1"><?= render_stars((float)$r['rating']) ?></div>
                            <div class="text-muted"><?= e($r['comment']) ?></div>
                        </div>

                        <label class="form-label small fw-bold">Your Reply:</label>
                        <textarea name="reply" class="form-control" rows="4" required minlength="3" maxlength="1000"><?= e($r['company_reply'] ?? '') ?></textarea>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i> Post Reply
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>

<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/company_footer.php'; ?>