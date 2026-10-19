<?php
if (!defined('APP_INIT')) {
    require_once dirname(__DIR__) . '/includes/init.php';
}
$user = current_user($pdo);
if (!$user || $user['role_name'] !== ROLE_TRANSPORT) {
    http_response_code(403);
    include BASE_PATH . '/errors/403.php';
    exit;
}

$tcStmt = $pdo->prepare("SELECT * FROM transport_companies WHERE user_id = ? LIMIT 1");
$tcStmt->execute([$user['id']]);
$transportCo = $tcStmt->fetch();

// Pending shipments count
$pendingStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM shipments 
     WHERE transport_user_id = ? 
       AND status IN ('awaiting_transport','assigned','picked_up')"
);
$pendingStmt->execute([$user['id']]);
$pendingCount = (int)$pendingStmt->fetchColumn();

$currentPage = basename($_SERVER['PHP_SELF']);
$pageTitle = $pageTitle ?? 'Transport';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — Transport | <?= e(SITE_SHORT) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="icon" type="image/svg+xml" href="<?= ASSETS_URL ?>images/favicon.svg">
<link rel="alternate icon" type="image/png" href="<?= ASSETS_URL ?>images/logo.png">
<style>
    /* =========================================
       SOLID PREMIUM DARK THEME (TRANSPORT)
    ========================================= */
    :root { 
        --brand: #fbbf24; /* Amber accent */
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
    .transport-sidebar { 
        width: 250px; height: 100vh; background: var(--bg-sidebar); color: var(--text-main);
        position: fixed; top: 0; left: 0; z-index: 1030; padding-top: 12px; padding-bottom: 20px;
        overflow-y: auto; overflow-x: hidden; border-right: 1px solid var(--border-color);
    }
    .transport-sidebar .brand { padding: 14px 20px 18px; border-bottom: 1px solid var(--border-color); }
    .transport-sidebar .nav-link { color: var(--text-muted); padding: 12px 20px; font-size: .9rem; border-left: 3px solid transparent; transition: all 0.2s ease; }
    .transport-sidebar .nav-link:hover { background: rgba(255,255,255,0.03); color: var(--text-main); }
    .transport-sidebar .nav-link.active { background: rgba(251, 191, 36, 0.1); color: var(--brand); border-left-color: var(--brand); font-weight: 600; }
    .transport-sidebar .nav-link i { width: 22px; margin-right: 8px; font-size: 1.1rem; }
    .transport-sidebar .nav-section { padding: 16px 20px 8px; font-size: .7rem; text-transform: uppercase; letter-spacing: 1px; color: #475569; font-weight: 700; }
    
    /* Main Layout */
    .transport-main { margin-left: 250px; min-height: 100vh; display: flex; flex-direction: column; }
    .transport-topbar { background: var(--bg-main); border-bottom: 1px solid var(--border-color); padding: 15px 24px; position: sticky; top: 0; z-index: 1020; }
    .transport-content { padding: 30px; flex: 1; }
    
    /* Cards & Stats */
    .stat-card { border: 1px solid var(--border-color); border-radius: 16px; background: var(--bg-card); padding: 20px; height: 100%; }
    .stat-card .label { font-size: .75rem; text-transform: uppercase; letter-spacing: .5px; color: var(--text-muted); font-weight: 600; margin-bottom: 8px; }
    .stat-card .value { font-size: 1.8rem; font-weight: 800; }
    .card-soft { border: 1px solid var(--border-color); border-radius: 16px; background: var(--bg-card); }
    .border-bottom { border-color: var(--border-color) !important; }
    
    /* Typography & Links */
    a { color: var(--brand); text-decoration: none; }
    a:hover { color: #fcd34d; }
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
    .transport-topbar .bi-bell, 
    .transport-topbar .bi-bell-fill,
    .transport-topbar .notification-bell i {
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
    .btn-primary { background-color: var(--brand); border-color: var(--brand); color: #000; font-weight: 600; }
    .btn-primary:hover { background-color: #fcd34d; border-color: #fcd34d; color: #000; }
    .btn-outline-secondary { color: var(--text-muted); border-color: var(--border-color); }
    .btn-outline-secondary:hover { background: rgba(255,255,255,0.1); color: white; }

    /* Mobile Responsive */
    @media (max-width: 991px) {
        .transport-sidebar { left: -260px; transition: left .3s; }
        .transport-sidebar.show { left: 0; }
        .transport-main { margin-left: 0; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.7); z-index: 1025; }
        .sidebar-overlay.show { display: block; }
        .transport-content { padding: 16px 12px; }
        .transport-topbar { padding: 10px 14px; }
        .stat-card { padding: 15px; }
        .stat-card .value { font-size: 1.3rem; }
        .table-responsive { overflow-x: auto; border-radius: 12px; }
        .card-soft.p-4, .card-soft.p-3 { padding: 15px !important; }
    }
</style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<aside class="transport-sidebar" id="transportSidebar">
    <div class="brand text-center" style="padding: 14px 10px;">
        <img src="<?= ASSETS_URL ?>images/logo.png" alt="<?= e(SITE_NAME) ?>" style="height: 80px; width: auto; max-width: 80px; object-fit: contain; background: white; border-radius: 12px; padding: 5px;">
    </div>
    <nav class="nav flex-column mt-2">
        <a class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>transport/index.php">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="nav-section">Shipments</div>
        <a class="nav-link <?= $currentPage === 'shipments.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>transport/shipments.php">
            <i class="bi bi-truck"></i> All Shipments
            <?php if ($pendingCount > 0): ?><span class="badge text-bg-warning ms-auto rounded-pill text-dark"><?= $pendingCount ?></span><?php endif; ?>
        </a>
        <a class="nav-link <?= $currentPage === 'delivered.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>transport/delivered.php">
            <i class="bi bi-check-circle"></i> Delivered
        </a>
        <a class="nav-link <?= $currentPage === 'verify-payments.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>transport/verify-payments.php">
            <i class="bi bi-shield-check"></i> Verify Payments
            <?php
            $tpCount = (int)$pdo->query(
                "SELECT COUNT(*) FROM payments p 
                 JOIN shipments sh ON sh.id = p.shipment_id
                 WHERE p.recipient_type='transport' 
                   AND p.status='pending_verification' 
                   AND sh.transport_user_id = " . (int)$user['id']
            )->fetchColumn();
            if ($tpCount > 0): ?>
                <span class="badge text-bg-warning ms-auto rounded-pill text-dark"><?= $tpCount ?></span>
            <?php endif; ?>
        </a>
        <a class="nav-link <?= $currentPage === 'payments.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>transport/payments.php">
            <i class="bi bi-cash-stack"></i> Payments
        </a>
        
        <a class="nav-link <?= $currentPage === 'reports.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>transport/reports.php">
            <i class="bi bi-bar-chart-line"></i> Reports
        </a>

        <div class="nav-section">Account</div>
        <a class="nav-link <?= $currentPage === 'profile.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>transport/profile.php">
            <i class="bi bi-person"></i> Profile
        </a>
        <a class="nav-link" href="<?= BASE_URL ?>index.php"><i class="bi bi-globe"></i> View Website</a>
        <a class="nav-link text-danger mt-3" href="<?= BASE_URL ?>auth/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>
</aside>

<div class="transport-main">
    <header class="transport-topbar d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <button class="btn btn-sm btn-outline-secondary d-lg-none me-2 text-white border-secondary"
                    onclick="document.getElementById('transportSidebar').classList.toggle('show')">
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
                    
                    <span class="d-none d-sm-inline small fw-semibold" style="color: #ffffff !important;"><?= e($transportCo['company_name'] ?? $user['full_name']) ?></span>
                    <i class="bi bi-caret-down-fill ms-2" style="font-size: 0.7rem; color: #ffffff;"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end mt-3">
                    <li><span class="dropdown-item-text small"><?= e($user['email']) ?></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>transport/profile.php"><i class="bi bi-person me-2"></i> Profile</a></li>
                    <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </header>
    <main class="transport-content">
        <?= flash_render() ?>