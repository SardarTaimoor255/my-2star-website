<?php
/**
 * includes/admin_header.php
 * Admin layout header
 */
if (!defined('APP_INIT')) {
    require_once dirname(__DIR__) . '/includes/init.php';
}

$user = current_user($pdo);
if (!$user || $user['role_name'] !== ROLE_ADMIN) {
    http_response_code(403);
    include BASE_PATH . '/errors/403.php';
    exit;
}

// Pending approval count (badge)
$pendingCount = (int)$pdo->query(
    "SELECT COUNT(*) FROM users WHERE status = 'pending'"
)->fetchColumn();

$currentPage = basename($_SERVER['PHP_SELF']);
$pageTitle = $pageTitle ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — Admin | <?= e(SITE_SHORT) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="icon" type="image/svg+xml" href="<?= ASSETS_URL ?>images/favicon.svg">
<link rel="alternate icon" type="image/png" href="<?= ASSETS_URL ?>images/logo.png">
<style>
    /* =========================================
       SOLID PREMIUM DARK THEME (ADMIN)
    ========================================= */
    :root { 
        --brand: #f43f5e; /* Rose/Red accent for Admin */
        --brand-2: #e11d48; 
        --bg-main: #09090b; 
        --bg-sidebar: #050508;
        --bg-card: #141622;
        --border-color: #272a37;
        --text-main: #f8fafc;
        --text-muted: #94a3b8;
    }
    
    body { background: var(--bg-main); color: var(--text-main); font-family: 'Inter', system-ui, sans-serif; }
    
    /* Sidebar */
    .admin-sidebar {
        width: 250px; height: 100vh; background: var(--bg-sidebar); color: var(--text-main);
        position: fixed; top:0; left:0; z-index: 1030; padding-top: 12px; padding-bottom: 20px;
        overflow-y: auto; overflow-x: hidden; border-right: 1px solid var(--border-color);
    }
    .admin-sidebar .brand { padding: 14px 20px 18px; border-bottom: 1px solid var(--border-color); }
    .admin-sidebar .nav-link {
        color: var(--text-muted); padding: 12px 20px; font-size: .9rem;
        border-left: 3px solid transparent; transition: all 0.2s ease;
    }
    .admin-sidebar .nav-link:hover { background: rgba(255,255,255,0.03); color: var(--text-main); }
    .admin-sidebar .nav-link.active {
        background: rgba(244, 63, 94, 0.1); color: var(--brand); border-left-color: var(--brand); font-weight: 600;
    }
    .admin-sidebar .nav-link i { width: 22px; display: inline-block; margin-right: 8px; font-size: 1.1rem; }
    .admin-sidebar .nav-section { padding: 16px 20px 8px; font-size: .7rem; text-transform: uppercase; letter-spacing: 1px; color: #475569; font-weight: 700; }
    
    /* Main Layout */
    .admin-main { margin-left: 250px; min-height: 100vh; display: flex; flex-direction: column; }
    .admin-topbar { background: var(--bg-main); border-bottom: 1px solid var(--border-color); padding: 15px 24px; position: sticky; top: 0; z-index: 1020; }
    .admin-content { padding: 30px; flex: 1; }
    
    /* Cards & Stats */
    .stat-card {
        border: 1px solid var(--border-color); border-radius: 16px; background: var(--bg-card);
        padding: 20px; height: 100%; transition: transform 0.2s ease;
    }
    .stat-card:hover { border-color: #3f3f46; transform: translateY(-2px); }
    .stat-card .label { font-size: .75rem; text-transform: uppercase; letter-spacing: .6px; color: var(--text-muted); font-weight: 600; margin-bottom: 8px; }
    .stat-card .value { font-size: 1.8rem; font-weight: 800; color: #ffffff; line-height: 1.1; }
    .stat-card .icon { font-size: 2rem; opacity: .15; position: absolute; top: 20px; right: 20px; }
    
    .card-soft { border: 1px solid var(--border-color); border-radius: 16px; background: var(--bg-card); }
    .border-bottom { border-color: var(--border-color) !important; }
    
    /* Typography & Links */
    a { color: var(--brand); text-decoration: none; }
    a:hover { color: #fb7185; }
    .text-muted { color: var(--text-muted) !important; }
    
    /* Tables Fixed */
    .table { --bs-table-bg: transparent; --bs-table-color: var(--text-main); --bs-table-border-color: var(--border-color); margin-bottom: 0; }
    .table-light, .table-light > th, .table-light > td {
        background-color: #1c1e2d !important; color: var(--text-muted) !important; border-color: var(--border-color) !important;
    }
    .table-light th { font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; }
    .table-hover tbody tr:hover { background-color: rgba(255,255,255,0.02); color: var(--text-main); }
    
    /* List Groups */
    .list-group-item { background-color: transparent; border-color: var(--border-color); color: var(--text-main); }
    
    /* Dropdowns */
    .dropdown-menu { background-color: var(--bg-card); border: 1px solid var(--border-color); box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
    .dropdown-item { color: var(--text-main); }
    .dropdown-item:hover { background-color: rgba(255,255,255,0.05); color: var(--brand); }
    .dropdown-divider { border-color: var(--border-color); }
    .dropdown-item-text { color: var(--text-muted) !important; }

    /* Fix Bell Icon Visibility */
    .admin-topbar .bi-bell, .admin-topbar .bi-bell-fill, .admin-topbar .notification-bell i {
        color: #ffffff !important; font-size: 1.3rem;
    }
    
    .btn-outline-secondary { color: var(--text-muted); border-color: var(--border-color); }
    .btn-outline-secondary:hover { background: rgba(255,255,255,0.1); color: white; }

    /* Mobile Responsive */
    @media (max-width: 991px) {
        .admin-sidebar { left: -260px; transition: left .3s; }
        .admin-sidebar.show { left: 0; }
        .admin-main { margin-left: 0; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.7); z-index: 1025; }
        .sidebar-overlay.show { display: block; }
        .admin-content { padding: 16px 12px; }
        .admin-topbar { padding: 10px 14px; }
        .stat-card { padding: 15px; }
        .stat-card .value { font-size: 1.3rem; }
        .table-responsive { overflow-x: auto; border-radius: 12px; }
        .card-soft.p-4, .card-soft.p-3 { padding: 15px !important; }
    }
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<aside class="admin-sidebar" id="adminSidebar">
    <div class="brand text-center" style="padding: 14px 10px;">
        <img src="<?= ASSETS_URL ?>images/logo.png" alt="<?= e(SITE_NAME) ?>" style="height: 80px; width: auto; max-width: 80px; object-fit: contain; background: white; border-radius: 12px; padding: 5px;">
    </div>

    <nav class="nav flex-column mt-2">
        <a class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>admin/index.php">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <a class="nav-link <?= $currentPage === 'approvals.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>admin/approvals.php">
            <i class="bi bi-check2-square"></i> Approvals
            <?php if ($pendingCount > 0): ?>
                <span class="badge text-bg-warning ms-auto rounded-pill text-dark"><?= $pendingCount ?></span>
            <?php endif; ?>
        </a>

        <div class="nav-section">Management</div>
        <a class="nav-link <?= $currentPage === 'users.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>admin/users.php">
            <i class="bi bi-people"></i> Users
        </a>
        <a class="nav-link <?= $currentPage === 'categories.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>admin/categories.php">
            <i class="bi bi-tags"></i> Categories
        </a>
        <a class="nav-link <?= $currentPage === 'products.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>admin/products.php">
            <i class="bi bi-box-seam"></i> Products
        </a>
        <a class="nav-link <?= $currentPage === 'invoices.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>admin/invoices.php">
            <i class="bi bi-file-earmark-text"></i> Invoices
        </a>

        <div class="nav-section">Operations</div>
        <a class="nav-link <?= $currentPage === 'orders.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>admin/orders.php">
            <i class="bi bi-receipt"></i> Orders
        </a>
       

        <div class="nav-section">System</div>
        <a class="nav-link" href="<?= BASE_URL ?>index.php">
            <i class="bi bi-globe"></i> View Website
        </a>
        <a class="nav-link text-danger mt-3" href="<?= BASE_URL ?>auth/logout.php">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </nav>
</aside>

<!-- MAIN -->
<div class="admin-main">
    <header class="admin-topbar d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <button class="btn btn-sm btn-outline-secondary d-lg-none me-2 text-white border-secondary"
                    onclick="document.getElementById('adminSidebar').classList.toggle('show')">
                <i class="bi bi-list"></i>
            </button>
            <strong style="color: var(--text-main); font-size: 1.1rem;"><?= e($pageTitle) ?></strong>
        </div>
        <div class="d-flex align-items-center gap-3">
            <?php require dirname(__DIR__) . '/includes/notification_bell.php'; ?>
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-decoration-none text-white" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle fs-4 me-2" style="color: var(--brand);"></i>
                    <span class="d-none d-sm-inline small fw-semibold" style="color: #ffffff !important;"><?= e($user['full_name']) ?></span>
                    <i class="bi bi-caret-down-fill ms-2" style="font-size: 0.7rem; color: #ffffff;"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end mt-3">
                    <li><span class="dropdown-item-text small"><?= e($user['email']) ?></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>index.php"><i class="bi bi-globe me-2"></i> View Site</a></li>
                    <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </header>

    <main class="admin-content">
        <?= flash_render() ?>