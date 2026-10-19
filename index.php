<?php
/**
 * index.php — Public homepage (3D Animated Dark Theme)
 */
require_once __DIR__ . '/includes/init.php';
$user = current_user($pdo);

// Smart Links: Agar user logged in hai to dashboard, warna login page
$shopLink      = $user ? dashboard_url_for('shop') : BASE_URL . 'auth/login.php';
$vendorLink    = $user ? dashboard_url_for('vendor') : BASE_URL . 'auth/login.php';
$transportLink = $user ? dashboard_url_for('transport') : BASE_URL . 'auth/login.php';

$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Load AOS CSS for Scroll Animations -->
<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

<style>
    /* =========================================
       OUTSTANDING DARK AURORA BACKGROUND
    ========================================= */
    body {
        background-color: #05050a; /* Deep Dark Base */
        color: #ffffff;
        overflow-x: hidden;
        margin: 0;
    }

    /* Animated Glowing Background */
    .aurora-bg {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        z-index: -1;
        background: 
            radial-gradient(circle at 15% 50%, rgba(76, 29, 149, 0.4), transparent 50%),
            radial-gradient(circle at 85% 30%, rgba(59, 130, 246, 0.3), transparent 50%),
            radial-gradient(circle at 50% 80%, rgba(236, 72, 153, 0.2), transparent 50%);
        filter: blur(80px);
        animation: bgShift 15s infinite alternate ease-in-out;
    }

    @keyframes bgShift {
        0% { transform: scale(1); }
        100% { transform: scale(1.1); }
    }

    /* 1. Hero Section - Blends with Aurora */
    .hero-3d {
        position: relative;
        background: transparent; /* Transparent taake peeche ka glow nazar aaye */
        color: white;
        padding: 140px 0 100px 0;
        overflow: hidden;
    }

    /* Floating background shapes */
    .hero-shape {
        position: absolute;
        border-radius: 50%;
        background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0));
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        animation: float 6s ease-in-out infinite;
        z-index: 1;
    }
    .shape-1 { width: 150px; height: 150px; top: 10%; left: 5%; animation-delay: 0s; }
    .shape-2 { width: 100px; height: 100px; top: 60%; right: 10%; animation-delay: 2s; }
    .shape-3 { width: 200px; height: 200px; bottom: -50px; left: 40%; animation-delay: 4s; }

    @keyframes float {
        0% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-20px) rotate(10deg); }
        100% { transform: translateY(0px) rotate(0deg); }
    }

    .hero-content {
        position: relative;
        z-index: 2;
        text-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }

    /* Premium Buttons */
    .btn-premium {
        background: linear-gradient(45deg, #f5a623, #f59e0b);
        border: none;
        color: #fff;
        box-shadow: 0 10px 20px rgba(245, 166, 35, 0.4);
        transition: all 0.3s ease;
        font-weight: 600;
        border-radius: 12px;
    }
    .btn-premium:hover {
        transform: translateY(-3px) scale(1.05);
        box-shadow: 0 15px 25px rgba(245, 166, 35, 0.6);
        color: #fff;
    }

    .btn-glass {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        color: white;
        transition: all 0.3s ease;
        font-weight: 600;
        border-radius: 12px;
    }
    .btn-glass:hover {
        background: rgba(255, 255, 255, 0.15);
        color: white;
        border-color: rgba(255, 255, 255, 0.5);
        transform: translateY(-3px);
    }

    /* 2. Feature Cards - Dark Glassmorphism */
    .card-link {
        text-decoration: none;
        display: block;
        height: 100%;
    }

    .card-3d-wrapper {
        perspective: 1000px; 
        height: 100%;
    }

    .card-3d {
        /* Dark Glass Effect */
        background: rgba(255, 255, 255, 0.03);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 24px;
        padding: 40px 30px;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        transform-style: preserve-3d; 
        height: 100%;
    }

    /* Hover par card glow karega */
    .card-link:hover .card-3d {
        box-shadow: 0 25px 50px rgba(76, 29, 149, 0.5); /* Purple Glow */
        border-color: rgba(255, 255, 255, 0.2);
        background: rgba(255, 255, 255, 0.06);
        transform: translateY(-10px);
    }

    .icon-3d {
        display: inline-block;
        font-size: 3.5rem;
        background: linear-gradient(135deg, #a78bfa, #3b82f6); /* Brighter gradient */
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        transform: translateZ(30px); 
        margin-bottom: 20px;
        filter: drop-shadow(0 10px 15px rgba(59, 130, 246, 0.4));
    }

    .card-title-3d {
        transform: translateZ(20px);
        font-weight: 700;
        color: #ffffff; /* White text */
        transition: color 0.3s ease;
    }
    
    .card-link:hover .card-title-3d {
        color: #a78bfa; /* Hover par light purple */
    }

    .card-text-3d {
        transform: translateZ(10px);
        color: #94a3b8; /* Light gray text */
    }
    
    .card-action-text {
        margin-top: 20px;
        font-weight: 600;
        color: #3b82f6;
        font-size: 0.9rem;
        opacity: 0;
        transform: translateY(10px);
        transition: all 0.3s ease;
    }
    
    .card-link:hover .card-action-text {
        opacity: 1;
        transform: translateY(0);
    }
</style>

<!-- Background Aurora Div -->
<div class="aurora-bg"></div>

<!-- =========================================
     HERO SECTION
========================================= -->
<section class="hero-3d">
    <!-- Floating Background Elements -->
    <div class="hero-shape shape-1"></div>
    <div class="hero-shape shape-2"></div>
    <div class="hero-shape shape-3"></div>

    <div class="container text-center hero-content">
        <h1 class="display-3 fw-bold mb-4" data-aos="zoom-in-down" data-aos-duration="1000">
            <?= e(SITE_NAME) ?>
        </h1>
        <p class="lead mb-5 fs-4 text-light opacity-75" data-aos="fade-up" data-aos-delay="200">
            B2B Ordering, Distribution &amp; Delivery Platform<br>
            <span class="text-warning fw-bold">Serving Pakistan &amp; Azad Jammu &amp; Kashmir</span>
        </p>
        
        <div data-aos="fade-up" data-aos-delay="400">
            <?php if (!$user): ?>
                <a href="<?= BASE_URL ?>auth/register.php" class="btn btn-premium btn-lg px-5 py-3 me-3 mb-3">
                    <i class="bi bi-shop me-2"></i> Register Your Shop
                </a>
                <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-glass btn-lg px-5 py-3 mb-3">
                    <i class="bi bi-box-arrow-in-right me-2"></i> Partner Login
                </a>
            <?php else: ?>
                <a href="<?= dashboard_url_for($user['role_name']) ?>" class="btn btn-premium btn-lg px-5 py-3">
                    Go to Dashboard <i class="bi bi-arrow-right ms-2"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- =========================================
     FEATURES SECTION (CLICKABLE 3D CARDS)
========================================= -->
<section class="container pb-5 mb-5">
    <div class="row g-4">
        <!-- Shop Card -->
        <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
            <a href="<?= $shopLink ?>" class="card-link">
                <div class="card-3d-wrapper">
                    <div class="card-3d text-center" data-tilt data-tilt-glare data-tilt-max-glare="0.1">
                        <i class="bi bi-shop-window icon-3d"></i>
                        <h4 class="card-title-3d">Shop Portal</h4>
                        <p class="card-text-3d mb-0">Browse products, place orders, track delivery, and chat with the vendor seamlessly.</p>
                        <div class="card-action-text">Enter Shop Portal <i class="bi bi-arrow-right"></i></div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Vendor Card -->
        <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
            <a href="<?= $vendorLink ?>" class="card-link">
                <div class="card-3d-wrapper">
                    <div class="card-3d text-center" data-tilt data-tilt-glare data-tilt-max-glare="0.1">
                        <i class="bi bi-diagram-3 icon-3d"></i>
                        <h4 class="card-title-3d">Vendor Portal</h4>
                        <p class="card-text-3d mb-0">Manage shop orders, route to companies, set commission, and generate insightful reports.</p>
                        <div class="card-action-text">Enter Vendor Portal <i class="bi bi-arrow-right"></i></div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Transport Card -->
        <div class="col-md-4" data-aos="fade-up" data-aos-delay="500">
            <a href="<?= $transportLink ?>" class="card-link">
                <div class="card-3d-wrapper">
                    <div class="card-3d text-center" data-tilt data-tilt-glare data-tilt-max-glare="0.1">
                        <i class="bi bi-truck icon-3d"></i>
                        <h4 class="card-title-3d">Transport Portal</h4>
                        <p class="card-text-3d mb-0">View assigned shipments, update delivery status, and communicate with the vendor on the go.</p>
                        <div class="card-action-text">Enter Transport Portal <i class="bi bi-arrow-right"></i></div>
                    </div>
                </div>
            </a>
        </div>
    </div>
</section>

<!-- =========================================
     SCRIPTS (AOS & VANILLA TILT)
========================================= -->
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/vanilla-tilt/1.7.2/vanilla-tilt.min.js"></script>

<script>
    // Initialize AOS (Scroll Animations) - Hamesha chalega
    AOS.init({
        once: true, 
        offset: 50, 
    });

    // Initialize Vanilla Tilt (3D Hover Effect) - Hamesha chalega
    VanillaTilt.init(document.querySelectorAll("[data-tilt]"), {
        max: 15, 
        speed: 400, 
        glare: true, 
        "max-glare": 0.1, 
        scale: 1.02 
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>