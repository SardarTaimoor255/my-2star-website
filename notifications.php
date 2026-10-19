<?php
require_once __DIR__ . '/includes/init.php';

if (!is_logged_in($pdo)) {
    redirect(BASE_URL . 'auth/login.php');
}

$user = current_user($pdo);

// Handle mark all read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    csrf_require($_POST['csrf_token'] ?? null);
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")
        ->execute([$user['id']]);
    flash_success('All notifications marked as read.');
    redirect(BASE_URL . 'notifications.php');
}

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT * FROM notifications WHERE user_id = ?
     ORDER BY id DESC LIMIT $perPage OFFSET $offset"
);
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
$stmt->execute([$user['id']]);
$total = (int)$stmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$unread = (int)$pdo->query(
    "SELECT COUNT(*) FROM notifications WHERE user_id = {$user['id']} AND is_read = 0"
)->fetchColumn();

$pageTitle = 'Notifications';

// Include the right header based on role
$headerMap = [
    ROLE_ADMIN     => 'admin_header.php',
    ROLE_VENDOR    => 'vendor_header.php',
    ROLE_SHOP      => 'shop_header.php',
    ROLE_COMPANY   => 'company_header.php',
    ROLE_TRANSPORT => 'transport_header.php',
];
require_once __DIR__ . '/includes/' . ($headerMap[$user['role_name']] ?? 'shop_header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1">Notifications</h2>
        <p class="text-muted small mb-0">
            <?= $total ?> total 
            <?php if ($unread > 0): ?>· <span class="text-danger fw-semibold"><?= $unread ?> unread</span><?php endif; ?>
        </p>
    </div>
    <?php if ($unread > 0): ?>
        <form method="post">
            <?= csrf_field() ?>
            <button type="submit" name="mark_all_read" value="1" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-check-all"></i> Mark All as Read
            </button>
        </form>
    <?php endif; ?>
</div>

<?php if (empty($notifications)): ?>
    <div class="card-soft p-5 text-center text-muted">
        <i class="bi bi-bell-slash fs-1 d-block mb-3 opacity-25"></i>
        <h5 class="fw-bold">No notifications yet</h5>
        <p class="small mb-0">You'll see updates about your orders, payments, and messages here.</p>
    </div>
<?php else: ?>

    <div class="card-soft">
        <?php foreach ($notifications as $n): 
            $iconMap = [
                'payment'   => ['bi-cash-coin', '#10b981', '#d1fae5'],
                'order'     => ['bi-cart-check', '#0b3d91', '#dbeafe'],
                'chat'      => ['bi-chat-dots', '#8b5cf6', '#ede9fe'],
                'deliver'   => ['bi-truck', '#f59e0b', '#fef3c7'],
                'price'     => ['bi-tag', '#ec4899', '#fce7f3'],
                'shipment'  => ['bi-box-seam', '#06b6d4', '#cffafe'],
            ];
            $ic = $iconMap[array_key_first(array_filter($iconMap, fn($k) => str_contains($n['type'] ?? '', $k), ARRAY_FILTER_USE_KEY))] ?? ['bi-bell', '#64748b', '#f1f5f9'];
            $unreadClass = $n['is_read'] == 0 ? 'border-start border-4 border-primary' : '';
        ?>
            <div class="d-flex gap-3 p-3 border-bottom align-items-start <?= $unreadClass ?>" 
                 style="<?= $n['is_read'] == 0 ? 'background:#f8fafc;' : '' ?>">
                <div style="width:42px;height:42px;border-radius:50%;background:<?= $ic[2] ?>;color:<?= $ic[1] ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi <?= $ic[0] ?>"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <div class="fw-semibold"><?= e($n['title']) ?></div>
                        <?php if ($n['is_read'] == 0): ?>
                            <span class="badge text-bg-primary">New</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted small mb-2"><?= e($n['message']) ?></div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted" style="font-size:.72rem;">
                            <i class="bi bi-clock"></i> <?= fmt_datetime($n['created_at']) ?>
                        </div>
                        <?php if ($n['link']): ?>
                            <a href="<?= e($n['link']) ?>" class="small text-decoration-none">
                                View <i class="bi bi-arrow-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="mt-3">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item"><a class="page-link" href="?page=<?= $page - 1 ?>">← Previous</a></li>
                <?php endif; ?>
                <li class="page-item disabled"><span class="page-link">Page <?= $page ?> of <?= $totalPages ?></span></li>
                <?php if ($page < $totalPages): ?>
                    <li class="page-item"><a class="page-link" href="?page=<?= $page + 1 ?>">Next →</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>

<?php endif; ?>

<?php
$footerMap = [
    ROLE_ADMIN     => 'admin_footer.php',
    ROLE_VENDOR    => 'vendor_footer.php',
    ROLE_SHOP      => 'shop_footer.php',
    ROLE_COMPANY   => 'company_footer.php',
    ROLE_TRANSPORT => 'transport_footer.php',
];
require_once __DIR__ . '/includes/' . ($footerMap[$user['role_name']] ?? 'shop_footer.php');
?>