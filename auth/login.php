<?php
/**
 * auth/login.php
 */
require_once dirname(__DIR__) . '/includes/init.php';
// DEBUG — temporary
if (isset($_GET['debug'])) {
    echo '<pre style="background:#fffbe6;padding:15px;border:2px solid #f0b429;font-family:monospace;font-size:14px;">';
    echo '<strong>🔍 SESSION DEBUG</strong>' . "\n\n";
    echo 'Session ID:     ' . session_id() . "\n";
    echo 'Session status: ' . session_status() . "\n";
    echo 'Session data:   ' . print_r($_SESSION, true) . "\n\n";
    echo 'Is logged in?:  ' . (is_logged_in($pdo) ? 'YES ✅' : 'NO ❌') . "\n";
    if (is_logged_in($pdo)) {
        $u = current_user($pdo);
        echo 'User ID:        ' . $u['id'] . "\n";
        echo 'User email:     ' . $u['email'] . "\n";
        echo 'User role:      ' . $u['role_name'] . "\n";
        echo 'Dashboard URL:  ' . dashboard_url_for($u['role_name']) . "\n";
    }
    echo '</pre>';
    exit;
}
// Already logged in?
if (is_logged_in($pdo)) {
    redirect(dashboard_url_for(current_role($pdo)));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require($_POST['csrf_token'] ?? null);

    $email    = clean($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter email and password.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT u.*, r.name AS role_name, r.display_name AS role_label
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'Invalid email or password.';
        } elseif ($user['status'] === 'pending') {
            $error = 'Your account is pending admin approval.';
        } elseif ($user['status'] === 'suspended') {
            $error = 'Your account has been suspended. Contact admin.';
        } elseif ($user['status'] === 'rejected') {
            $error = 'Your account was rejected. Contact admin.';
        } elseif (!password_verify($password, $user['password_hash'])) {
            $error = 'Invalid email or password.';
        } else {
            // Success
            login_user($user);

            // Update last login
            $stmt = $pdo->prepare('UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?');
            $stmt->execute([client_ip(), $user['id']]);

            log_activity($pdo, 'user_login', 'auth', $user['id'], 'User logged in');

            // Redirect to intended page or dashboard
            $target = $_SESSION['redirect_after_login'] ?? dashboard_url_for($user['role_name']);
            unset($_SESSION['redirect_after_login']);
            redirect($target);
        }
    }
}

$pageTitle = 'Login';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>

      /* Custom Light Text */
    .text-light-gray {
        color: #94a3b8 !important;
    }
    /* =========================================
       PROFESSIONAL SPLIT-SCREEN LOGIN CSS
    ========================================= */
    body {
        background-color: #030305; /* Deepest Dark */
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
        background: radial-gradient(circle, rgba(76, 29, 149, 0.4) 0%, transparent 60%);
        filter: blur(60px);
        animation: floatOrb 10s infinite alternate;
    }
    .orb-2 {
        position: absolute;
        bottom: -10%; right: -10%;
        width: 40vw; height: 40vw;
        background: radial-gradient(circle, rgba(59, 130, 246, 0.3) 0%, transparent 60%);
        filter: blur(60px);
        animation: floatOrb 12s infinite alternate-reverse;
    }

    @keyframes floatOrb {
        0% { transform: translate(0, 0) scale(1); }
        100% { transform: translate(5%, 5%) scale(1.1); }
    }

    /* Split Layout */
    .login-split-wrapper {
        min-height: 85vh;
        display: flex;
        align-items: center;
    }

    /* Left Side: Branding */
    .branding-section {
        padding: 40px;
        animation: slideRight 0.8s ease-out forwards;
    }
    .branding-section h1 {
        font-size: 3.5rem;
        font-weight: 800;
        line-height: 1.1;
        background: linear-gradient(135deg, #ffffff 0%, #a78bfa 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 20px;
    }
    .branding-section p {
        font-size: 1.1rem;
        color: #94a3b8;
        max-width: 400px;
    }
    .feature-list {
        margin-top: 30px;
        list-style: none;
        padding: 0;
    }
    .feature-list li {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
        color: #cbd5e1;
        font-size: 0.95rem;
    }
    .feature-list li i {
        color: #3b82f6;
        margin-right: 12px;
        font-size: 1.2rem;
    }

    /* Right Side: Glass Form */
    .form-section {
        display: flex;
        justify-content: center;
    }
    .login-card {
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 24px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        padding: 45px 40px;
        width: 100%;
        max-width: 440px;
        
        /* Pop-in animation */
        opacity: 0;
        animation: popIn 0.7s cubic-bezier(0.175, 0.885, 0.32, 1.275) 0.2s forwards;
    }

    /* Inputs with Icons */
      /* Inputs with Icons - FIXED */
    .input-group {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        transition: all 0.3s ease;
    }
    .input-group:focus-within {
        border-color: #a78bfa;
        box-shadow: 0 0 0 4px rgba(167, 139, 250, 0.1);
        background: rgba(255, 255, 255, 0.06);
    }
    .input-group-text {
        background: transparent;
        border: none;
        color: #64748b;
        padding-left: 18px;
        transition: color 0.3s ease;
    }
    .input-group:focus-within .input-group-text {
        color: #a78bfa;
    }
    
    /* Fix for Input Fields */
    .form-control {
        background-color: transparent !important;
        border: none !important;
        color: #ffffff !important;
        padding: 14px 18px;
        box-shadow: none !important;
    }
    
    /* 🔥 MAGIC FIX: Browser Autofill Background & Text Color */
    input:-webkit-autofill,
    input:-webkit-autofill:hover, 
    input:-webkit-autofill:focus, 
    input:-webkit-autofill:active {
        -webkit-box-shadow: 0 0 0 1000px #0f172a inset !important; /* Forces dark background */
        -webkit-text-fill-color: #ffffff !important; /* Forces white text */
        transition: background-color 5000s ease-in-out 0s;
        caret-color: #ffffff;
    }
    .input-group:focus-within {
        border-color: #a78bfa;
        box-shadow: 0 0 0 4px rgba(167, 139, 250, 0.1);
        background: rgba(255, 255, 255, 0.06);
    }
    .input-group-text {
        background: transparent;
        border: none;
        color: #64748b;
        padding-left: 18px;
        transition: color 0.3s ease;
    }
    .input-group:focus-within .input-group-text {
        color: #a78bfa;
    }
    
    .form-control:-webkit-autofill {
        -webkit-text-fill-color: #ffffff;
        -webkit-box-shadow: 0 0 0px 1000px #0f172a inset;
    }
    
    /* Button Shimmer Effect */
    .btn-premium {
        background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
        border: none;
        color: #fff;
        box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
        transition: all 0.3s ease;
        font-weight: 600;
        border-radius: 12px;
        position: relative;
        overflow: hidden;
    }
    .btn-premium::after {
        content: '';
        position: absolute;
        top: -50%; left: -50%;
        width: 200%; height: 200%;
        background: linear-gradient(to right, rgba(255,255,255,0) 0%, rgba(255,255,255,0.3) 50%, rgba(255,255,255,0) 100%);
        transform: rotate(45deg);
        transition: all 0.5s ease;
        opacity: 0;
    }
    .btn-premium:hover {
        transform: translateY(-2px);
        box-shadow: 0 15px 25px rgba(99, 102, 241, 0.5);
        color: #fff;
    }
    .btn-premium:hover::after {
        opacity: 1;
        left: 100%;
    }

    /* Links & Text */
    a.custom-link {
        color: #a78bfa;
        text-decoration: none;
        font-weight: 500;
        transition: color 0.3s ease;
    }
    a.custom-link:hover {
        color: #c4b5fd;
        text-decoration: underline;
    }
    
    .alert-danger {
        background: rgba(220, 53, 69, 0.15);
        border: 1px solid rgba(220, 53, 69, 0.3);
        color: #f87171;
        border-radius: 12px;
    }

    /* Animations */
    @keyframes popIn {
        0% { opacity: 0; transform: scale(0.9) translateY(20px); }
        100% { opacity: 1; transform: scale(1) translateY(0); }
    }
    @keyframes slideRight {
        0% { opacity: 0; transform: translateX(-30px); }
        100% { opacity: 1; transform: translateX(0); }
    }

    @media (max-width: 991px) {
        .branding-section { display: none; } /* Mobile pe left side hide kar dein */
        .login-card { margin-top: 50px; margin-bottom: 50px; }
    }
</style>

<div class="aurora-bg">
    <div class="orb-1"></div>
    <div class="orb-2"></div>
</div>

<div class="container login-split-wrapper">
    <div class="row w-100 align-items-center">
        
        <!-- Left Side: Branding & Trust -->
        <div class="col-lg-6 d-none d-lg-block">
            <div class="branding-section">
                <h1>Welcome to<br><?= e(SITE_NAME) ?></h1>
                <p>The most advanced B2B ordering and delivery platform in the region. Manage your shops, vendors, and logistics all in one place.</p>
                
                <ul class="feature-list">
                    <li><i class="bi bi-check-circle-fill"></i> Real-time Order Tracking</li>
                    <li><i class="bi bi-check-circle-fill"></i> Seamless Vendor Routing</li>
                    <li><i class="bi bi-check-circle-fill"></i> Secure Payment Gateways</li>
                </ul>
            </div>
        </div>

        <!-- Right Side: Login Form -->
        <div class="col-lg-6 form-section">
            <div class="login-card">
                <div class="text-center mb-4">
                    <h3 class="fw-bold mb-2">Sign In</h3>
                   <p class="small text-light-gray">Access your secure dashboard.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 small d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i> <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <form method="post" autocomplete="off">
                    <?= csrf_field() ?>

                    <!-- Email Input -->
                    <div class="mb-3">
                        <label class="form-label small text-uppercase fw-bold" style="color: #64748b; letter-spacing: 0.5px;">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" name="email" class="form-control" required placeholder="name@company.com"
                                   value="<?= e($_POST['email'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <!-- Password Input -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small text-uppercase fw-bold mb-0" style="color: #64748b; letter-spacing: 0.5px;">Password</label>
                            <a href="<?= BASE_URL ?>auth/forgot-password.php" class="custom-link small" style="font-size: 0.75rem;">
                                Forgot Password?
                            </a>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" class="form-control" required placeholder="••••••••">
                        </div>
                    </div>

                    <!-- Remember Me Checkbox (Professional Touch) -->
                    <div class="mb-4 form-check">
                        <input type="checkbox" class="form-check-input bg-transparent border-secondary" id="rememberMe" style="box-shadow: none;">
                        <label class="form-check-label small" style="color: #94a3b8;" for="rememberMe">
                            Keep me signed in
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-premium w-100 py-3 fs-6">
                        Secure Login <i class="bi bi-arrow-right ms-2"></i>
                    </button>
                </form>

                <!-- Register Link -->
                <div class="text-center mt-4 pt-3 border-top border-secondary border-opacity-25 small">
                 <span style="color: #94a3b8;">New to the platform?</span>
                    <a href="<?= BASE_URL ?>auth/register.php" class="custom-link fw-bold ms-1">Create an Account</a>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>