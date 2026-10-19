<?php
require_once __DIR__ . '/includes/init.php';
$pageTitle = 'About Us';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Load AOS CSS for Scroll Animations -->
<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

<style>
    /* =========================================
       PREMIUM DARK ABOUT PAGE CSS
    ========================================= */
    body {
        background-color: #030305;
        color: #ffffff;
        margin: 0;
        overflow-x: hidden;
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }

    /* Animated Glowing Orbs Background */
    .aurora-bg {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        z-index: -1;
        overflow: hidden;
    }
    .orb-1 {
        position: absolute;
        top: -10%; left: -10%;
        width: 50vw; height: 50vw;
        background: radial-gradient(circle, rgba(76, 29, 149, 0.3) 0%, transparent 60%);
        filter: blur(60px);
        animation: floatOrb 10s infinite alternate;
    }
    .orb-2 {
        position: absolute;
        bottom: -10%; right: -10%;
        width: 40vw; height: 40vw;
        background: radial-gradient(circle, rgba(59, 130, 246, 0.25) 0%, transparent 60%);
        filter: blur(60px);
        animation: floatOrb 12s infinite alternate-reverse;
    }

    @keyframes floatOrb {
        0% { transform: translate(0, 0) scale(1); }
        100% { transform: translate(5%, 5%) scale(1.1); }
    }

    /* Typography & Hero */
    .page-header {
        padding: 80px 0 40px;
        position: relative;
        z-index: 1;
    }
    .page-header h1 {
        font-weight: 800;
        background: linear-gradient(135deg, #ffffff 0%, #a78bfa 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .text-light-gray { color: #94a3b8 !important; }

    /* Main Content Wrapper Fix */
    .main-content-wrapper {
        position: relative;
        z-index: 10; /* Ensures content stays above background */
        margin-bottom: 80px; /* Space for footer */
    }

    /* Glass Cards */
    .glass-card {
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 24px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        padding: 40px;
        transition: transform 0.3s ease, border-color 0.3s ease;
    }
    
    .glass-card:hover {
        border-color: rgba(167, 139, 250, 0.3);
        transform: translateY(-5px);
    }

    .glass-card p {
        color: #cbd5e1;
        line-height: 1.7;
    }

    /* Feature Icons */
    .icon-box {
        width: 60px;
        height: 60px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 16px;
        font-size: 1.8rem;
        margin-bottom: 20px;
    }
    .icon-blue { background: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.2); }
    .icon-purple { background: rgba(168, 85, 247, 0.1); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.2); }
    .icon-green { background: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.2); }
    .icon-yellow { background: rgba(245, 158, 11, 0.1); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.2); }

    /* Custom Badges for Regions */
    .region-badge {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #e2e8f0;
        padding: 8px 16px;
        border-radius: 50px;
        font-size: 0.9rem;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    .region-badge:hover {
        background: rgba(255, 255, 255, 0.1);
        border-color: rgba(255, 255, 255, 0.3);
    }
    .region-badge.ajk {
        background: rgba(245, 158, 11, 0.15);
        border: 1px solid rgba(245, 158, 11, 0.3);
        color: #fbbf24;
    }

    /* Checkmark List */
    .check-item {
        display: flex;
        gap: 15px;
        align-items: flex-start;
    }
    .check-icon {
        color: #34d399;
        font-size: 1.4rem;
        margin-top: 2px;
    }
    .check-text strong {
        display: block;
        color: #ffffff;
        font-size: 1.05rem;
        margin-bottom: 4px;
    }
    .check-text span {
        color: #94a3b8;
        font-size: 0.9rem;
    }
    
    /* Fix for features row */
    .feature-col {
        display: flex;
        flex-direction: column;
    }
    .feature-col .glass-card {
        flex: 1; /* Makes all cards equal height */
    }
</style>

<div class="aurora-bg">
    <div class="orb-1"></div>
    <div class="orb-2"></div>
</div>

<!-- Hero Section -->
<section class="page-header">
    <div class="container text-center" data-aos="fade-down" data-aos-duration="1000">
        <h1 class="display-5 fw-bold mb-3">About <?= e(SITE_NAME) ?></h1>
        <p class="lead mb-0 text-light-gray">
            Empowering Electric &amp; Electronics Distribution in Pakistan &amp; AJK
        </p>
    </div>
</section>

<div class="container main-content-wrapper">
    <div class="row justify-content-center">
        <div class="col-lg-10">

            <!-- Who We Are -->
            <div class="glass-card mb-4" data-aos="fade-up" data-aos-delay="100">
                <h3 class="fw-bold mb-4 text-white">
                    <span class="icon-box icon-yellow me-3 align-middle"><i class="bi bi-lightning-charge-fill"></i></span> 
                    Who We Are
                </h3>
                <p class="fs-5">
                    <strong><?= e(SITE_NAME) ?></strong> ek B2B electric aur electronics distribution platform hai — 
                    jo Pakistan aur Azad Jammu &amp; Kashmir mein retail shops ko direct companies se connect karta hai.
                </p>
                <p class="mb-0 fs-6">
                    Hum traditional distribution business ko digital banate hain — jahan shop owners 
                    asaani se products order kar sakte hain, order track kar sakte hain, aur payments 
                    digitally manage kar sakte hain. Har cheez transparent, fast, aur reliable.
                </p>
            </div>

            <!-- Features Row -->
            <div class="row g-4 mb-4">
                <div class="col-md-4 feature-col" data-aos="fade-up" data-aos-delay="200">
                    <div class="glass-card text-center">
                        <div class="icon-box icon-blue mx-auto"><i class="bi bi-shop"></i></div>
                        <h5 class="fw-bold text-white">Shops</h5>
                        <p class="small mb-0">
                            Retail electric aur electronics shops ke liye one-stop ordering platform.
                        </p>
                    </div>
                </div>
                <div class="col-md-4 feature-col" data-aos="fade-up" data-aos-delay="300">
                    <div class="glass-card text-center">
                        <div class="icon-box icon-purple mx-auto"><i class="bi bi-building"></i></div>
                        <h5 class="fw-bold text-white">Companies</h5>
                        <p class="small mb-0">
                            Manufacturers aur suppliers ke liye direct shop orders, digital management.
                        </p>
                    </div>
                </div>
                <div class="col-md-4 feature-col" data-aos="fade-up" data-aos-delay="400">
                    <div class="glass-card text-center">
                        <div class="icon-box icon-green mx-auto"><i class="bi bi-truck"></i></div>
                        <h5 class="fw-bold text-white">Transport</h5>
                        <p class="small mb-0">
                            Bilty aur logistics partners ke liye smart shipment tracking.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Coverage Area -->
            <div class="glass-card mb-4" data-aos="fade-up" data-aos-delay="500">
                <h3 class="fw-bold mb-4 text-white">
                    <span class="icon-box icon-blue me-3 align-middle" style="width: 50px; height: 50px; font-size: 1.4rem;"><i class="bi bi-geo-alt-fill"></i></span>
                    Where We Operate
                </h3>
                <p class="fs-5 mb-4">Serving all major cities across:</p>
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <span class="region-badge">Punjab</span>
                    <span class="region-badge">Sindh</span>
                    <span class="region-badge">Khyber Pakhtunkhwa</span>
                    <span class="region-badge">Balochistan</span>
                    <span class="region-badge">Gilgit-Baltistan</span>
                    <span class="region-badge ajk">Azad Jammu &amp; Kashmir</span>
                </div>
                <p class="small text-light-gray mb-0">
                    <i class="bi bi-info-circle me-1"></i> Future mein international expansion plan hai — 
                    filhal Pakistan aur AJK par focus hai.
                </p>
            </div>

            <!-- Why Choose Us -->
            <div class="glass-card" data-aos="fade-up" data-aos-delay="600">
                <h3 class="fw-bold mb-4 text-white">
                    <span class="icon-box icon-purple me-3 align-middle"><i class="bi bi-stars"></i></span>
                    Why Choose Us
                </h3>
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="check-item">
                            <i class="bi bi-check-circle-fill check-icon"></i>
                            <div class="check-text">
                                <strong>Direct Company Rates</strong>
                                <span>Middleman ke bina, base price + transparent commission</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="check-item">
                            <i class="bi bi-check-circle-fill check-icon"></i>
                            <div class="check-text">
                                <strong>Live Order Tracking</strong>
                                <span>Har order kis stage par hai — poora timeline</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="check-item">
                            <i class="bi bi-check-circle-fill check-icon"></i>
                            <div class="check-text">
                                <strong>Digital Payments</strong>
                                <span>Easypaisa, JazzCash, Bank Transfer, Cash</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="check-item">
                            <i class="bi bi-check-circle-fill check-icon"></i>
                            <div class="check-text">
                                <strong>Direct Chat</strong>
                                <span>Vendor, Transport, Company — sab se baat karein</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- AOS Script -->
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({
        once: true,
        offset: 50,
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>