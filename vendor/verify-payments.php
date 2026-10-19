<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/payment_helper.php';
require_role($pdo, ROLE_VENDOR);

$stmt = $pdo->query(
    "SELECT p.*, o.order_number, s.shop_name, u.full_name AS submitted_by_name
     FROM payments p
     JOIN orders o ON o.id = p.order_id
     JOIN shops s ON s.id = p.shop_id
     LEFT JOIN users u ON u.id = p.submitted_by
     WHERE p.status = 'pending_verification'
       AND p.recipient_type = 'vendor'
     ORDER BY p.id DESC"
);
$pending = $stmt->fetchAll();

$pageTitle = 'Verify Payments';
require_once dirname(__DIR__) . '/includes/vendor_header.php';
?>

<div class="mb-4">
    <h2 class="fw-bold mb-1">Verify Payments</h2>
    <p class="text-muted small mb-0">Payments submitted by shops — verify against your account</p>
</div>

<?php if (empty($pending)): ?>
    <div class="card-soft p-5 text-center text-muted">
        <i class="bi bi-check2-circle fs-1 d-block mb-2 text-success opacity-50"></i>
        <h5 class="fw-bold">All caught up! 🎉</h5>
        <p class="small mb-0">No pending payments to verify.</p>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($pending as $p): ?>
            <div class="col-md-6">
                <div class="card-soft p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="fw-bold fs-5"><?= money($p['amount']) ?></div>
                            <div class="small text-muted"><?= e($p['payment_no']) ?></div>
                        </div>
                        <span class="badge text-bg-warning">Awaiting</span>
                    </div>

                    <div class="small mb-2">
                        <div><strong>Shop:</strong> <?= e($p['shop_name']) ?></div>
                        <div><strong>Order:</strong> <?= e($p['order_number']) ?></div>
                        <div><strong>Method:</strong>
                            <i class="bi <?= payment_method_icon($p['payment_method']) ?>"></i>
                            <?= e(payment_method_label($p['payment_method'])) ?>
                        </div>
                        <div><strong>TXN Ref:</strong> <code><?= e($p['reference_no'] ?: '—') ?></code></div>
                        <div class="text-muted" style="font-size:.75rem;">
                            Submitted <?= fmt_datetime($p['created_at']) ?> by <?= e($p['submitted_by_name'] ?? '—') ?>
                        </div>
                    </div>

                    <?php if ($p['notes']): ?>
                        <div class="small fst-italic text-muted mb-2">"<?= e($p['notes']) ?>"</div>
                    <?php endif; ?>

                    <?php if ($p['proof_image']): ?>
                        <div class="mb-3">
                            <a href="<?= UPLOADS_URL . e($p['proof_image']) ?>" target="_blank"
                               class="btn btn-sm btn-outline-info">
                                <i class="bi bi-image"></i> View Screenshot
                            </a>
                        </div>
                    <?php endif; ?>

                    <hr class="my-2">

                    <div class="d-flex gap-2">
                        <form method="post" action="<?= BASE_URL ?>api/vendor-action.php" class="flex-fill">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="verify_payment">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <input type="hidden" name="decision" value="approve">
                            <button type="submit" class="btn btn-success btn-sm w-100"
                                    onclick="return confirm('Approve <?= money($p['amount']) ?>?')">
                                <i class="bi bi-check-circle"></i> Approve
                            </button>
                        </form>
                        <button type="button" class="btn btn-outline-danger btn-sm flex-fill"
                                data-bs-toggle="modal" data-bs-target="#rejectModal<?= (int)$p['id'] ?>">
                            <i class="bi bi-x-circle"></i> Reject
                        </button>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="rejectModal<?= (int)$p['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                    <form method="post" action="<?= BASE_URL ?>api/vendor-action.php" class="modal-content">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="verify_payment">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <input type="hidden" name="decision" value="reject">
                        <div class="modal-header">
                            <h5 class="modal-title">Reject Payment</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="small mb-2">Reject <strong><?= e($p['payment_no']) ?></strong> — <?= money($p['amount']) ?>?</p>
                            <label class="form-label small">Reason (shop ko dikhega)</label>
                            <textarea name="rejection_reason" class="form-control" rows="3" required
                                      placeholder="e.g. Payment not received in Easypaisa account"></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Reject</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/vendor_footer.php'; ?>