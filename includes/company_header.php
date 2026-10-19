<?php
if (!defined('APP_INIT')) {
    require_once dirname(__DIR__) . '/includes/init.php';
}
$user = current_user($pdo);
if (!$user || $user['role_name'] !== ROLE_COMPANY) {
    http_response_code(403);
    include BASE_PATH . '/errors/403.php';
    exit;
}
$coStmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ? LIMIT 1");
$coStmt->execute([$user['id']]);
$company = $coStmt->fetch();
$companyId = (int)($company['id'] ?? 0);

$prCount = 0;
if ($companyId > 0) {
    $stmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT pri.request_id)
         FROM price_request_items pri
         JOIN price_requests pr ON pr.id = pri.request_id
         WHERE pri.company_id = ? AND pr.status = 'sent_to_company'"
    );
    $stmt->execute([$companyId]);
    $prCount = (int)$stmt->fetchColumn();
}
// New orders count (company ne abhi process nahi kiye)
$newOrdersCount = 0;
if ($companyId > 0) {
    $stmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT o.id)
         FROM orders o
         WHERE o.status = 'sent_to_company'
           AND EXISTS (
             SELECT 1 FROM order_items oi 
             WHERE oi.order_id = o.id AND oi.company_id = ?
           )"
    );
    $stmt->execute([$companyId]);
    $newOrdersCount = (int)$stmt->fetchColumn();
}
$currentPage = basename($_SERVER['PHP_SELF']);
$pageTitle = $pageTitle ?? 'Company';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — Company | <?= e(SITE_SHORT) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="icon" type="image/svg+xml" href="<?= ASSETS_URL ?>images/favicon.svg">
<link rel="alternate icon" type="image/png" href="<?= ASSETS_URL ?>images/logo.png">
<style>
    /* =========================================
       SOLID PREMIUM DARK THEME (COMPANY)
    ========================================= */
    :root { 
        --brand: #3b82f6; /* Blue accent for Company */
        --brand-2: #2563eb; 
        --bg-main: #09090b; 
        --bg-sidebar: #050508;
        --bg-card: #141622;
        --border-color: #272a37;
        --text-main: #f8fafc;
        --text-muted: #94a3b8;
    }
    
    body { background: var(--bg-main); color: var(--text-main); font-family: 'Inter', system-ui, sans-serif; }
    
    /* Sidebar */
    .company-sidebar { 
        width: 250px; height: 100vh; background: var(--bg-sidebar); color: var(--text-main);
        position: fixed; top: 0; left: 0; z-index: 1030; padding-top: 12px; padding-bottom: 20px;
        overflow-y: auto; overflow-x: hidden; border-right: 1px solid var(--border-color);
    }
    .company-sidebar .brand { padding: 14px 20px 18px; border-bottom: 1px solid var(--border-color); }
    .company-sidebar .nav-link { color: var(--text-muted); padding: 12px 20px; font-size: .9rem; border-left: 3px solid transparent; transition: all 0.2s ease; }
    .company-sidebar .nav-link:hover { background: rgba(255,255,255,0.03); color: var(--text-main); }
    .company-sidebar .nav-link.active { background: rgba(59, 130, 246, 0.1); color: var(--brand); border-left-color: var(--brand); font-weight: 600; }
    .company-sidebar .nav-link i { width: 22px; margin-right: 8px; font-size: 1.1rem; }
    .company-sidebar .nav-section { padding: 16px 20px 8px; font-size: .7rem; text-transform: uppercase; letter-spacing: 1px; color: #475569; font-weight: 700; }
    
    /* Main Layout */
    .company-main { margin-left: 250px; min-height: 100vh; display: flex; flex-direction: column; }
    .company-topbar { background: var(--bg-main); border-bottom: 1px solid var(--border-color); padding: 15px 24px; position: sticky; top: 0; z-index: 1020; }
    .company-content { padding: 30px; flex: 1; }
    
    /* Cards & Stats */
    .stat-card { border: 1px solid var(--border-color); border-radius: 16px; background: var(--bg-card); padding: 20px; height: 100%; }
    .stat-card .label { font-size: .75rem; text-transform: uppercase; letter-spacing: .5px; color: var(--text-muted); font-weight: 600; margin-bottom: 8px; }
    .stat-card .value { font-size: 1.8rem; font-weight: 800; }
    .card-soft { border: 1px solid var(--border-color); border-radius: 16px; background: var(--bg-card); }
    .border-bottom { border-color: var(--border-color) !important; }
    
    /* Typography & Links */
    a { color: var(--brand); text-decoration: none; }
    a:hover { color: #60a5fa; }
    .text-muted { color: var(--text-muted) !important; }
    
    /* Tables Fixed */
    .table { --bs-table-bg: transparent; --bs-table-color: var(--text-main); --bs-table-border-color: var(--border-color); margin-bottom: 0; }
    .table-light, 
    .table-light > th, 
    .table-light > td {
        background-color: #1c1e2d !important; 
        color: var(--text-muted) !important; 
        border-color: var(--border-color) !important;
    }
    .table-light th { font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; }
    .table-hover tbody tr:hover { background-color: rgba(255,255,255,0.02); color: var(--text-main); }
    
    /* Dropdowns */
    .dropdown-menu { background-color: var(--bg-card); border: 1px solid var(--border-color); box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
    .dropdown-item { color: var(--text-main); }
    .dropdown-item:hover { background-color: rgba(255,255,255,0.05); color: var(--brand); }
    .dropdown-divider { border-color: var(--border-color); }
    .dropdown-item-text { color: var(--text-muted) !important; }

    /* Fix Bell Icon Visibility */
    .company-topbar .bi-bell, 
    .company-topbar .bi-bell-fill,
    .company-topbar .notification-bell i {
        color: #ffffff !important;
        font-size: 1.3rem;
    }

    /* Profile Avatar Style */
    .user-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--brand);
        margin-right: 10px;
        background: #1c1e2d;
    }

    /* Buttons */
    .btn-primary { background-color: var(--brand); border-color: var(--brand); color: #fff; font-weight: 600; }
    .btn-primary:hover { background-color: #2563eb; border-color: #2563eb; color: #fff; }
    .btn-outline-secondary { color: var(--text-muted); border-color: var(--border-color); }
    .btn-outline-secondary:hover { background: rgba(255,255,255,0.1); color: white; }

    /* Mobile Responsive */
    @media (max-width: 991px) {
        .company-sidebar { left: -260px; transition: left .3s; }
        .company-sidebar.show { left: 0; }
        .company-main { margin-left: 0; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.7); z-index: 1025; }
        .sidebar-overlay.show { display: block; }
        .company-content { padding: 16px 12px; }
        .company-topbar { padding: 10px 14px; }
        .stat-card { padding: 15px; }
        .stat-card .value { font-size: 1.3rem; }
        .table-responsive { overflow-x: auto; border-radius: 12px; }
        .card-soft.p-4, .card-soft.p-3 { padding: 15px !important; }
    }
</style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<aside class="company-sidebar" id="companySidebar">
    <div class="brand text-center" style="padding: 14px 10px;">
        <img src="<?= ASSETS_URL ?>images/logo.png" alt="<?= e(SITE_NAME) ?>" style="height: 80px; width: auto; max-width: 80px; object-fit: contain; background: white; border-radius: 12px; padding: 5px;">
    </div>
    <nav class="nav flex-column mt-2">
        <a class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>company/index.php">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="nav-section">Business</div>
        <a class="nav-link <?= $currentPage === 'price-requests.php' || $currentPage === 'price-request-details.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>company/price-requests.php">
            <i class="bi bi-tags"></i> Price Requests
            <?php if ($prCount > 0): ?><span class="badge text-bg-warning ms-auto rounded-pill text-dark"><?= $prCount ?></span><?php endif; ?>
        </a>
        <a class="nav-link <?= $currentPage === 'orders.php' || $currentPage === 'order-details.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>company/orders.php">
            <i class="bi bi-receipt"></i> Orders
            <?php if ($newOrdersCount > 0): ?><span class="badge text-bg-danger ms-auto rounded-pill"><?= $newOrdersCount ?></span><?php endif; ?>
        </a>
        <a class="nav-link <?= $currentPage === 'products.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>company/products.php">
            <i class="bi bi-box-seam"></i> Products
        </a>
        <a class="nav-link <?= $currentPage === 'reviews.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>company/reviews.php">
            <i class="bi bi-star"></i> Reviews
            <?php
            $pendingReviews = (int)$pdo->query(
                "SELECT COUNT(*) FROM product_reviews r
                 JOIN products p ON p.id = r.product_id
                 WHERE p.company_id = " . (int)$companyId . "
                   AND (r.company_reply IS NULL OR r.company_reply = '')"
            )->fetchColumn();
            if ($pendingReviews > 0): ?>
                <span class="badge text-bg-danger ms-auto rounded-pill"><?= $pendingReviews ?></span>
            <?php endif; ?>
        </a>
        
        <a class="nav-link <?= $currentPage === 'verify-payments.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>company/verify-payments.php">
            <i class="bi bi-shield-check"></i> Verify Payments
            <?php
            $pvCount = (int)$pdo->query(
                "SELECT COUNT(*) FROM payments 
                 WHERE recipient_type='company' AND company_id = " . (int)$companyId . "
                   AND status='pending_verification'"
            )->fetchColumn();
            if ($pvCount > 0): ?>
                <span class="badge text-bg-warning ms-auto rounded-pill text-dark"><?= $pvCount ?></span>
            <?php endif; ?>
        </a>
        <a class="nav-link <?= $currentPage === 'payments.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>company/payments.php">
            <i class="bi bi-cash-stack"></i> Payments
        </a>
        <a class="nav-link <?= $currentPage === 'reports.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>company/reports.php">
            <i class="bi bi-bar-chart-line"></i> Reports
        </a>

        <div class="nav-section">Account</div>
        <a class="nav-link <?= $currentPage === 'profile.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>company/profile.php">
            <i class="bi bi-person"></i> Profile
        </a>
        <a class="nav-link" href="<?= BASE_URL ?>index.php"><i class="bi bi-globe"></i> View Website</a>
        <a class="nav-link text-danger mt-3" href="<?= BASE_URL ?>auth/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>
</aside>

<div class="company-main">
    <header class="company-topbar d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <button class="btn btn-sm btn-outline-secondary d-lg-none me-2 text-white border-secondary"
                    onclick="document.getElementById('companySidebar').classList.toggle('show')">
                <i class="bi bi-list"></i>
            </button>
            <strong style="color: var(--text-main); font-size: 1.1rem;"><?= e($pageTitle) ?></strong>
        </div>
        <div class="d-flex align-items-center gap-3">
            <?php require dirname(__DIR__) . '/includes/notification_bell.php'; ?>
            
            <?php 
            $avatar = $user['avatar'] ?? null; 
            $avatarUrl = $avatar ? UPLOADS_URL . 'avatars/' . $avatar : null; 
            ?>
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-decoration-none text-white" data-bs-toggle="dropdown">
                    <?php if ($avatarUrl && file_exists(UPLOADS_PATH . '/avatars/' . $avatar)): ?>
                        <img src="<?= $avatarUrl ?>" alt="<?= e($user['full_name']) ?>" class="user-avatar">
                    <?php else: ?>
                        <i class="bi bi-person-circle fs-4 me-2" style="color: var(--brand);"></i>
                    <?php endif; ?>
                    
                    <span class="d-none d-sm-inline small fw-semibold" style="color: #ffffff !important;"><?= e($company['company_name'] ?? $user['full_name']) ?></span>
                    <i class="bi bi-caret-down-fill ms-2" style="font-size: 0.7rem; color: #ffffff;"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end mt-3">
                    <li><span class="dropdown-item-text small"><?= e($user['email']) ?></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>company/profile.php"><i class="bi bi-person me-2"></i> Profile</a></li>
                    <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </header>
    <main class="company-content">
        <?= flash_render() ?>