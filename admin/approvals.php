<?php
/**
 * admin/approvals.php
 */
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_ADMIN);

// Fetch pending users by role
function pendingByRole(PDO $pdo, string $role): array {
    $stmt = $pdo->prepare(
        "SELECT u.*, r.display_name AS role_label
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.status = 'pending' AND r.name = ?
         ORDER BY u.id DESC"
    );
    $stmt->execute([$role]);
    return $stmt->fetchAll();
}

// Get profile info based on role
function profileInfo(PDO $pdo, int $userId, string $role): array {
    if ($role === ROLE_SHOP) {
        $s = $pdo->prepare("SELECT * FROM shops WHERE user_id = ? LIMIT 1");
        $s->execute([$userId]);
        return $s->fetch() ?: [];
    }
    if ($role === ROLE_COMPANY) {
        $s = $pdo->prepare("SELECT * FROM companies WHERE user_id = ? LIMIT 1");
        $s->execute([$userId]);
        return $s->fetch() ?: [];
    }
    if ($role === ROLE_TRANSPORT) {
        $s = $pdo->prepare("SELECT * FROM transport_companies WHERE user_id = ? LIMIT 1");
        $s->execute([$userId]);
        return $s->fetch() ?: [];
    }
    return [];
}

$pendingShops      = pendingByRole($pdo, ROLE_SHOP);
$pendingCompanies  = pendingByRole($pdo, ROLE_COMPANY);
$pendingTransport  = pendingByRole($pdo, ROLE_TRANSPORT);

$pageTitle = 'Pending Approvals';
require_once dirname(__DIR__) . '/includes/admin_header.php';
?>

<h2 class="fw-bold mb-1">Pending Approvals</h2>
<p class="text-muted small mb-4">Approve or reject new registrations</p>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-shops">
            Shops <span class="badge text-bg-secondary ms-1"><?= count($pendingShops) ?></span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-companies">
            Companies <span class="badge text-bg-secondary ms-1"><?= count($pendingCompanies) ?></span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-transport">
            Transport <span class="badge text-bg-secondary ms-1"><?= count($pendingTransport) ?></span>
        </button>
    </li>
</ul>

<div class="tab-content">

<?php
$tabs = [
    'tab-shops'     => ['items' => $pendingShops,     'role' => ROLE_SHOP,      'label' => 'Shop'],
    'tab-companies' => ['items' => $pendingCompanies, 'role' => ROLE_COMPANY,   'label' => 'Company'],
    'tab-transport' => ['items' => $pendingTransport, 'role' => ROLE_TRANSPORT, 'label' => 'Transport'],
];
foreach ($tabs as $tabId => $tab):
?>
    <div class="tab-pane fade <?= $tabId === 'tab-shops' ? 'show active' : '' ?>" id="<?= $tabId ?>">
        <?php if (empty($tab['items'])): ?>
            <div class="card-soft p-4 text-center text-muted">
                No pending <?= strtolower($tab['label']) ?> approvals.
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($tab['items'] as $u):
                    $info = profileInfo($pdo, (int)$u['id'], $tab['role']);
                    $name = $info['shop_name'] ?? $info['company_name'] ?? $info['company_name'] ?? $u['full_name'];
                ?>
                <div class="col-md-6">
                    <div class="card-soft p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="fw-bold mb-0"><?= e($name) ?></h6>
                                <div class="small text-muted"><?= e($u['email']) ?></div>
                            </div>
                            <span class="badge text-bg-warning">Pending</span>
                        </div>
                        <div class="small text-muted mb-1">
                            <i class="bi bi-person"></i> <?= e($u['full_name']) ?>
                            <?php if (!empty($u['phone'])): ?>
                                · <i class="bi bi-telephone"></i> <?= e($u['phone']) ?>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($info['address'])): ?>
                            <div class="small text-muted mb-3">
                                <i class="bi bi-geo-alt"></i> <?= e($info['address']) ?>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-success flex-fill"
                                    onclick="handleApprove(this,'approve_user',<?= (int)$u['id'] ?>)">
                                <i class="bi bi-check-circle"></i> Approve
                            </button>
                            <button class="btn btn-sm btn-outline-danger flex-fill"
                                    onclick="handleApprove(this,'reject_user',<?= (int)$u['id'] ?>)">
                                <i class="bi bi-x-circle"></i> Reject
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

</div>

<?php require_once dirname(__DIR__) . '/includes/admin_footer.php'; ?>