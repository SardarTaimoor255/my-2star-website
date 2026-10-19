<?php
/**
 * includes/header.php
 * Public site header
 */
if (!defined('APP_INIT')) {
    require_once dirname(__DIR__) . '/includes/init.php';
}

$user = current_user($pdo);
$pageTitle = $pageTitle ?? SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — <?= e(SITE_SHORT) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="icon" type="image/svg+xml" href="<?= ASSETS_URL ?>images/favicon.svg">
<link rel="alternate icon" type="image/png" href="<?= ASSETS_URL ?>images/logo.png">
<style>
    /* =========================================
       BASE STYLES
    ========================================= */
    :root { --brand: #0b3d91; --brand-2: #f5a623; }
    body { background: #030305; color: #ffffff; } /* Dark base to prevent white flash */
    .navbar-brand { font-weight: 700; }
    .btn-brand { background: var(--brand-2); color: #1b1b1b; font-weight: 600; }
    .btn-brand:hover { background: #e0940f; color: #1b1b1b; }
    .hero { background: linear-gradient(135deg,#0b3d91,#081f4d); color: #fff; padding: 60px 0; }
    .card-soft { border: 1px solid #e6e9f2; border-radius: 12px; background: #fff; box-shadow: 0 4px 18px rgba(15,23,42,.05); }

    /* =========================================
       PREMIUM SOLID NAVBAR (FIXED)
    ========================================= */
    .navbar-premium {
        background: #081f4d !important; /* Solid Dark Blue */
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
        z-index: 1050;
    }

    /* =========================================
       BASE PERFORMANCE FIX (Default Mode)
    ========================================= */
    /* Blur ko thora kam kiya taake default mode fast ho */
    .glass-card, .login-card, .card-3d, [style*="backdrop-filter"] {
        backdrop-filter: blur(6px) !important;
        -webkit-backdrop-filter: blur(6px) !important;
    }

    /* Peeche ke moving background (orbs) ko rukwa diya taake GPU par load na pade */
    .aurora-bg {
        filter: blur(40px) !important; 
        animation: none !important; 
    }
    .orb-1, .orb-2, .hero-shape {
        animation: none !important;
    }

    /* =========================================
       ECO MODE (Lite Mode - Keeps Animations!)
    ========================================= */
    body.eco-mode .glass-card, 
    body.eco-mode .card-3d, 
    body.eco-mode .login-card {
        backdrop-filter: blur(2px) !important;
        -webkit-backdrop-filter: blur(2px) !important;
        box-shadow: 0 4px 10px rgba(0,0,0,0.2) !important;
    }
    
    body.eco-mode .aurora-bg {
        filter: blur(15px) !important;
        opacity: 0.4; 
    }

    /* Eco Mode Button Style */
    .eco-toggle-btn {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 9999;
        background: #10b981; 
        color: white;
        border: none;
        padding: 10px 18px;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.85rem;
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
    }
    .eco-toggle-btn:hover {
        background: #059669;
        transform: translateY(-2px);
    }
    body.eco-mode .eco-toggle-btn {
        background: #f43f5e; 
        box-shadow: 0 4px 15px rgba(244, 63, 94, 0.4);
    }
</style>
</head>
<body>

<!-- Navbar aligned properly -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-premium sticky-top py-0 align-items-center">
  <div class="container align-items-center">
    <a class="navbar-brand d-flex align-items-center py-2" href="<?= BASE_URL ?>">
      <img src="<?= ASSETS_URL ?>images/logo.png" 
           alt="<?= e(SITE_NAME) ?>" 
           style="height: 80px; width: auto; max-width: 100px; object-fit: contain; background: white; border-radius: 8px; padding: 4px;">
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav ms-auto align-items-lg-center">
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>about.php">About</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>contact.php">Contact</a></li>
        <?php if ($user): ?>
            <li class="nav-item">
              <a class="nav-link" href="<?= dashboard_url_for($user['role_name']) ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
              </a>
            </li>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                <i class="bi bi-person-circle"></i> <?= e($user['full_name']) ?>
              </a>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><span class="dropdown-item-text small text-muted"><?= e($user['role_label']) ?></span></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>auth/logout.php">Logout</a></li>
              </ul>
            </li>
        <?php else: ?>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>auth/login.php">Login</a></li>
            <li class="nav-item ms-lg-2">
              <a class="btn btn-brand btn-sm px-3" href="<?= BASE_URL ?>auth/register.php">Register</a>
            </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<main>
<?= flash_render() ?>