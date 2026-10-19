<?php
if (!defined('APP_INIT')) {
    require_once dirname(__DIR__) . '/includes/init.php';
}
$user = current_user($pdo);
if (!$user || $user['role_name'] !== ROLE_VENDOR) {
    http_response_code(403);
    include BASE_PATH . '/errors/403.php';
    exit;
}
$vendorStmt = $pdo->prepare("SELECT * FROM vendors WHERE user_id = ? LIMIT 1");
$vendorStmt->execute([$user['id']]);
$vendor = $vendorStmt->fetch();

// Pending counts for sidebar badges
$prCount = (int)$pdo->query("SELECT COUNT(*) FROM price_requests WHERE status IN ('pending','sent_to_company')")->fetchColumn();
$ordCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'order_created'")->fetchColumn();

$currentPage = basename($_SERVER['PHP_SELF']);
$pageTitle = $pageTitle ?? 'Vendor';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — Vendor | <?= e(SITE_SHORT) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="icon" type="image/svg+xml" href="<?= ASSETS_URL ?>images/favicon.svg">
<style>
    /* =========================================
       SOLID PREMIUM DARK THEME (FAST PERFORMANCE)
    ========================================= */
    :root { 
        --brand: #a78bfa; /* Purple accent */
        --brand-2: #f5a623; 
        --bg-main: #09090b; 
        --bg-sidebar: #050508;
        --bg-card: #141622;
        --border-color: #272a37;
        --text-main: #f8fafc;
        --text-muted: #94a3b8;
    }
    
    body { background: var(--bg-main); color: var(--text-main); font-family: 'Inter', system-ui, sans-serif; }
    
    /* Sidebar */
    .vendor-sidebar { 
        width: 250px; height: 100vh; background: var(--bg-sidebar); color: var(--text-main);
        position: fixed; top: 0; left: 0; z-index: 1030; padding-top: 12px; padding-bottom: 20px;
        overflow-y: auto; overflow-x: hidden; border-right: 1px solid var(--border-color);
    }
    .vendor-sidebar .brand { padding: 14px 20px 18px; border-bottom: 1px solid var(--border-color); }
    .vendor-sidebar .nav-link { color: var(--text-muted); padding: 12px 20px; font-size: .9rem; border-left: 3px solid transparent; transition: all 0.2s ease; }
    .vendor-sidebar .nav-link:hover { background: rgba(255,255,255,0.03); color: var(--text-main); }
    .vendor-sidebar .nav-link.active { background: rgba(167, 139, 250, 0.1); color: var(--brand); border-left-color: var(--brand); font-weight: 600; }
    .vendor-sidebar .nav-link i { width: 22px; margin-right: 8px; font-size: 1.1rem; }
    .vendor-sidebar .nav-section { padding: 16px 20px 8px; font-size: .7rem; text-transform: uppercase; letter-spacing: 1px; color: #475569; font-weight: 700; }
    
    /* Main Layout */
    .vendor-main { margin-left: 250px; min-height: 100vh; }
    .vendor-topbar { background: var(--bg-main); border-bottom: 1px solid var(--border-color); padding: 15px 24px; position: sticky; top: 0; z-index: 1020; }
    .vendor-content { padding: 30px; }
    
    /* Cards */
    .stat-card { border: 1px solid var(--border-color); border-radius: 16px; background: var(--bg-card); padding: 20px; height: 100%; transition: transform 0.2s ease, border-color 0.2s ease; }
    .stat-card:hover { border-color: #3f3f46; transform: translateY(-2px); }
    .stat-card .label { font-size: .75rem; text-transform: uppercase; letter-spacing: .5px; color: var(--text-muted); font-weight: 600; margin-bottom: 8px; }
    .stat-card .value { font-size: 1.8rem; font-weight: 800; }
    
    .card-soft { border: 1px solid var(--border-color); border-radius: 16px; background: var(--bg-card); }
    .border-bottom { border-color: var(--border-color) !important; }
    .border-top { border-color: var(--border-color) !important; }
    
    /* Typography & Links */
    a { color: var(--brand); text-decoration: none; transition: color 0.2s; }
    a:hover { color: #c4b5fd; }
    .text-muted { color: var(--text-muted) !important; }
    
    /* Tables */
    .table { --bs-table-bg: transparent; --bs-table-color: var(--text-main); --bs-table-border-color: var(--border-color); margin-bottom: 0; }
    .table-light { background-color: #1c1e2d !important; color: var(--text-main) !important; }
    .table-light th { border-bottom: none; color: var(--text-muted); font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; }
    .table-hover tbody tr:hover { background-color: rgba(255,255,255,0.02); color: var(--text-main); }
    
    /* Dropdowns */
    .dropdown-menu { background-color: var(--bg-card); border: 1px solid var(--border-color); box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
    .dropdown-item { color: var(--text-main); }
    .dropdown-item:hover { background-color: rgba(255,255,255,0.05); color: var(--brand); }
    .dropdown-divider { border-color: var(--border-color); }
    .dropdown-item-text { color: var(--text-muted) !important; }
    
    /* List Groups */
    .list-group-item { background-color: transparent; border-color: var(--border-color); color: var(--text-main); }
    
    /* Buttons */
    .btn-outline-secondary { color: var(--text-muted); border-color: var(--border-color); }
    .btn-outline-secondary:hover { background: rgba(255,255,255,0.1); color: white; }

    /* Mobile Responsive */
    @media (max-width: 991px) {
        .vendor-sidebar { left:-260px; transition:left .3s; }
        .vendor-sidebar.show { left:0; }
        .vendor-main { margin-left:0; }
        
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.7); z-index: 1025; }
        .sidebar-overlay.show { display: block; }
        
        .vendor-content { padding: 16px 12px; }
        .vendor-topbar { padding: 10px 14px; }
        
        .stat-card { padding: 15px; }
        .stat-card .value { font-size: 1.3rem; }
        
        .table-responsive { overflow-x: auto; border-radius: 12px; }
        .card-soft.p-4, .card-soft.p-3 { padding: 15px !important; }
    }

    /* Fix Bell Icon Visibility */
    .vendor-topbar .bi-bell, 
    .vendor-topbar .bi-bell-fill,
    .vendor-topbar .notification-bell i {
        color: #ffffff !important; /* Bell ko white karega */
        font-size: 1.3rem;
    }

    /* Profile Avatar Style */
    .user-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--brand); /* Purple border */
        margin-right: 10px;
        background: #1c1e2d;
    }

</style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<aside class="vendor-sidebar" id="vendorSidebar">
    <div class="brand text-center" style="padding: 14px 10px;">
        <img src="<?= ASSETS_URL ?>images/logo.png" 
             alt="<?= e(SITE_NAME) ?>" 
             style="height: 80px; width: auto; max-width: 80px; object-fit: contain; background: white; border-radius: 12px; padding: 5px;">
    </div>
    
    <nav class="nav flex-column mt-2">
        <a class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>vendor/index.php">
            <i class="bi bi-grid-1x2"></i> Dashboard
        </a>

        <div class="nav-section">Business</div>
        <a class="nav-link <?= $currentPage === 'price-requests.php' || $currentPage === 'price-request-details.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>vendor/price-requests.php">
            <i class="bi bi-tags"></i> Price Requests
            <?php if ($prCount > 0): ?><span class="badge text-bg-warning ms-auto rounded-pill"><?= $prCount ?></span><?php endif; ?>
        </a>
        <a class="nav-link <?= $currentPage === 'orders.php' || $currentPage === 'order-details.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>vendor/orders.php">
            <i class="bi bi-receipt"></i> Orders
            <?php if ($ordCount > 0): ?><span class="badge text-bg-danger ms-auto rounded-pill"><?= $ordCount ?></span><?php endif; ?>
        </a>
        <a class="nav-link <?= $currentPage === 'products.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>vendor/products.php">
            <i class="bi bi-box-seam"></i> Products
        </a>
        <a class="nav-link <?= $currentPage === 'payments.php' || $currentPage === 'record-payment.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>vendor/payments.php">
            <i class="bi bi-cash-stack"></i> Payments
        </a>
        <a class="nav-link <?= $currentPage === 'verify-payments.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>vendor/verify-payments.php">
            <i class="bi bi-shield-check"></i> Verify Payments
            <?php
            $pvCount = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending_verification' AND recipient_type = 'vendor'")->fetchColumn();
            if ($pvCount > 0): ?>
                <span class="badge text-bg-warning ms-auto rounded-pill"><?= $pvCount ?></span>
            <?php endif; ?>
        </a>
        <a class="nav-link <?= $currentPage === 'invoices.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>vendor/invoices.php">
            <i class="bi bi-file-earmark-text"></i> Invoices
        </a>
        <a class="nav-link <?= $currentPage === 'reports.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>vendor/reports.php">
            <i class="bi bi-bar-chart-line"></i> Reports
        </a>
        <a class="nav-link <?= $currentPage === 'contact-messages.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>vendor/contact-messages.php">
            <i class="bi bi-envelope-paper"></i> Messages
            <?php
            $cmCount = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")->fetchColumn();
            if ($cmCount > 0): ?>
                <span class="badge text-bg-danger ms-auto rounded-pill"><?= $cmCount ?></span>
            <?php endif; ?>
        </a>
        
        <div class="nav-section">Account</div>
        <a class="nav-link <?= $currentPage === 'profile.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>vendor/profile.php">
            <i class="bi bi-person"></i> Profile
        </a>
        <a class="nav-link" href="<?= BASE_URL ?>index.php"><i class="bi bi-globe"></i> View Website</a>
        <a class="nav-link text-danger mt-3" href="<?= BASE_URL ?>auth/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>
</aside>

<div class="vendor-main">
    <header class="vendor-topbar d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <button class="btn btn-sm btn-outline-secondary d-lg-none me-2"
                    onclick="document.getElementById('vendorSidebar').classList.toggle('show')">
                <i class="bi bi-list"></i>
            </button>
            <strong style="color: var(--text-main); font-size: 1.1rem;"><?= e($pageTitle) ?></strong>
        </div>
        <div class="d-flex align-items-center gap-3">
            <?php require dirname(__DIR__) . '/includes/notification_bell.php'; ?>
            <div class="dropdown">
                <?php 
// Profile picture logic (Make sure your DB column is 'avatar' or 'profile_image')
$avatar = $user['avatar'] ?? $user['profile_image'] ?? null; 
// Path to the upload folder (Adjust this path if your folder structure is different)
$avatarUrl = $avatar ? UPLOADS_URL . 'avatars/' . $avatar : null; 
?>
<a href="#" class="d-flex align-items-center text-decoration-none text-white" data-bs-toggle="dropdown">
    <?php if ($avatarUrl && file_exists(UPLOADS_PATH . '/avatars/' . $avatar)): ?>
        <!-- Agar picture upload ki hui hai -->
        <img src="<?= $avatarUrl ?>" alt="<?= e($user['full_name']) ?>" class="user-avatar">
    <?php else: ?>
        <!-- Agar picture nahi hai toh default icon -->
        <i class="bi bi-person-circle fs-4 me-2" style="color: var(--brand);"></i>
    <?php endif; ?>
    
    <span class="d-none d-sm-inline small fw-semibold"><?= e($user['full_name']) ?></span>
    <i class="bi bi-caret-down-fill ms-2" style="font-size: 0.7rem; color: var(--text-muted);"></i>
</a>
                <ul class="dropdown-menu dropdown-menu-end mt-3">
                    <li><span class="dropdown-item-text small"><?= e($user['email']) ?></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>vendor/profile.php"><i class="bi bi-person me-2"></i> Profile</a></li>
                    <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </header>
    <main class="vendor-content">
        <?= flash_render() ?>