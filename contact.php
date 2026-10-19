<?php
require_once __DIR__ . '/includes/init.php';

$submitted = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require($_POST['csrf_token'] ?? null);
    
    $name    = clean($_POST['name'] ?? '');
    $email   = clean($_POST['email'] ?? '');
    $phone   = clean($_POST['phone'] ?? '');
    $subject = clean($_POST['subject'] ?? '');
    $message = clean($_POST['message'] ?? '');

    // ============================================
    // NAME VALIDATION (Min 4 chars)
    // ============================================
    if ($name === '') {
        $errors[] = 'Name is required.';
    } elseif (mb_strlen($name) < 4) {
        $errors[] = 'Name must be at least 4 characters.';
    } elseif (mb_strlen($name) > 100) {
        $errors[] = 'Name is too long (max 100 characters).';
    } elseif (!preg_match('/^[A-Za-z\s\.\-\']+$/', $name)) {
        $errors[] = 'Name can only contain letters, spaces, dots, and dashes.';
    } elseif (preg_match('/(.)\1{3,}/', $name)) {
        $errors[] = 'Name contains repeated characters. Please enter a real name.';
    }

    // ============================================
    // EMAIL VALIDATION
    // ============================================
    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (mb_strlen($email) > 150) {
        $errors[] = 'Email is too long.';
    } elseif (!preg_match('/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/', $email)) {
        $errors[] = 'Email format is invalid.';
    }

    // ============================================
    // PHONE VALIDATION (optional)
    // ============================================
    if ($phone !== '') {
        if (!preg_match('/^[0-9+\-\s]{10,20}$/', $phone)) {
            $errors[] = 'Phone must be 10-20 digits (with + or - allowed).';
        }
    }

    // ============================================
    // SUBJECT VALIDATION (optional, min 5 chars)
    // ============================================
    if ($subject !== '') {
        if (mb_strlen($subject) < 5) {
            $errors[] = 'Subject must be at least 5 characters.';
        } elseif (mb_strlen($subject) > 200) {
            $errors[] = 'Subject is too long (max 200 characters).';
        } elseif (preg_match('/(.)\1{4,}/', $subject)) {
            $errors[] = 'Subject contains repeated characters.';
        }
    }

    // ============================================
    // MESSAGE VALIDATION
    // ============================================
    if ($message === '') {
        $errors[] = 'Message is required.';
    } elseif (mb_strlen($message) < 20) {
        $errors[] = 'Message must be at least 20 characters.';
    } elseif (mb_strlen($message) > 3000) {
        $errors[] = 'Message is too long (max 3000 characters).';
    } elseif (str_word_count($message) < 5) {
        $errors[] = 'Message must contain at least 5 words.';
    } elseif (preg_match('/(.)\1{5,}/', $message)) {
        $errors[] = 'Message contains too many repeated characters.';
    }

    // ============================================
    // SAVE TO DATABASE
    // ============================================
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO contact_messages (name, email, phone, subject, message, ip_address, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $name, $email, $phone, $subject, $message,
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
            ]);
            $contactId = (int)$pdo->lastInsertId();

            // Notify VENDOR only (business owner)
            require_once __DIR__ . '/includes/notification_helper.php';
            
            $vendorUserId = (int)$pdo->query("SELECT user_id FROM vendors LIMIT 1")->fetchColumn();
            if ($vendorUserId) {
                create_notification(
                    $pdo, $vendorUserId, 'contact',
                    'New Contact Message',
                    $name . ' (' . $email . '): ' . mb_substr($message, 0, 80) . '...',
                    BASE_URL . 'vendor/contact-messages.php?id=' . $contactId
                );
            }
            log_activity($pdo, 'contact_form_submitted', 'contact', $contactId, 'From: ' . $email);
            $submitted = true;

            // Clear form
            $_POST = [];

        } catch (Throwable $ex) {
            error_log('Contact form error: ' . $ex->getMessage());
            $errors[] = 'Could not submit your message. Please try again.';
        }
    }
}

$pageTitle = 'Contact Us';
require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* =========================================
       PREMIUM DARK CONTACT PAGE CSS
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

    /* Main Glass Cards */
    .glass-card {
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 24px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        padding: 40px;
        height: 100%;
        opacity: 0;
        animation: popIn 0.7s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    }
    .glass-card-info {
        animation-delay: 0.2s;
    }

    @keyframes popIn {
        0% { opacity: 0; transform: scale(0.95) translateY(20px); }
        100% { opacity: 1; transform: scale(1) translateY(0); }
    }

    /* Headers and Text */
    .page-header h1 {
        font-weight: 800;
        background: linear-gradient(135deg, #ffffff 0%, #a78bfa 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .text-light-gray { color: #94a3b8 !important; }
    
    /* Inputs */
    .form-label {
        color: #94a3b8;
        font-size: 0.85rem;
        font-weight: 500;
    }
    .form-control {
        background-color: rgba(255, 255, 255, 0.03) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        color: #ffffff !important;
        border-radius: 12px;
        padding: 12px 18px;
        transition: all 0.3s ease;
    }
    .form-control:focus {
        background-color: rgba(255, 255, 255, 0.06) !important;
        border-color: #a78bfa !important;
        box-shadow: 0 0 0 4px rgba(167, 139, 250, 0.1) !important;
    }
    
    /* Autofill Fix */
    input:-webkit-autofill,
    input:-webkit-autofill:hover, 
    input:-webkit-autofill:focus, 
    input:-webkit-autofill:active {
        -webkit-box-shadow: 0 0 0 1000px #0f172a inset !important;
        -webkit-text-fill-color: #ffffff !important;
        transition: background-color 5000s ease-in-out 0s;
    }

    /* Validation Styles */
    .field-error { color: #f87171; font-size: .75rem; margin-top: 4px; display: none; }
    .field-error.show { display: block; }
    .form-control.is-invalid { border-color: #f87171 !important; }
    .form-control.is-valid { border-color: #34d399 !important; }

    /* Premium Button */
    .btn-premium {
        background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
        border: none;
        color: #fff;
        box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
        transition: all 0.3s ease;
        font-weight: 600;
        border-radius: 12px;
        padding: 12px 30px;
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
    .btn-premium:hover::after { opacity: 1; left: 100%; }

    /* Alerts */
    .alert-success {
        background: rgba(16, 185, 129, 0.15);
        border: 1px solid rgba(16, 185, 129, 0.3);
        color: #34d399;
        border-radius: 12px;
    }
    .alert-danger {
        background: rgba(220, 53, 69, 0.15);
        border: 1px solid rgba(220, 53, 69, 0.3);
        color: #f87171;
        border-radius: 12px;
    }
    .alert-info {
        background: rgba(59, 130, 246, 0.15);
        border: 1px solid rgba(59, 130, 246, 0.3);
        color: #60a5fa;
        border-radius: 12px;
    }
    .alert-info a { color: #93c5fd; text-decoration: underline; }

    /* Contact Info Sidebar */
    .info-item {
        display: flex;
        align-items: flex-start;
        margin-bottom: 25px;
    }
    .info-icon {
        width: 45px;
        height: 45px;
        background: rgba(168, 85, 247, 0.1);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        color: #a855f7;
        font-size: 1.2rem;
        flex-shrink: 0;
    }
    .info-text h6 { color: #ffffff; margin-bottom: 2px; font-weight: 600; }
    .info-text p { color: #94a3b8; margin: 0; font-size: 0.9rem; }
</style>

<div class="aurora-bg">
    <div class="orb-1"></div>
    <div class="orb-2"></div>
</div>

<div class="container page-header text-center pt-5 pb-4">
    <h1 class="display-5 fw-bold mb-3">Contact Us</h1>
    <p class="text-light-gray fs-5">We're here to help — get in touch with our team</p>
</div>

<section class="container pb-5 mb-5">
    <div class="row justify-content-center g-4">
        
        <!-- Form Section -->
        <div class="col-lg-7">
            <div class="glass-card">
                <?php if ($submitted): ?>
                    <div class="alert alert-success mb-4">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <strong>Message sent!</strong> We'll get back to you within 24 hours.
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger mb-4">
                        <strong><i class="bi bi-exclamation-triangle me-1"></i> Please fix the following:</strong>
                        <ul class="mb-0 ps-3 mt-2">
                            <?php foreach ($errors as $e): ?>
                                <li><?= e($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="alert alert-info mb-4">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    <strong>Note:</strong> Ye form <strong>sirf general inquiries</strong> ke liye hai. 
                    Products order karne ke liye please apni shop 
                    <a href="<?= BASE_URL ?>auth/register.php">register</a> karein.
                </div>

                <h4 class="fw-bold mb-4 text-white">Send us a message</h4>

                <form method="post" id="contactForm" novalidate>
                    <?= csrf_field() ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Your Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="c_name" class="form-control" required
                                   minlength="4" maxlength="100"
                                   placeholder="e.g. Ahmed Khan"
                                   value="<?= e($_POST['name'] ?? '') ?>">
                            <div class="field-error" data-for="c_name"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="c_email" class="form-control" required
                                   placeholder="your@email.com"
                                   value="<?= e($_POST['email'] ?? '') ?>">
                            <div class="field-error" data-for="c_email"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone <small style="color: #64748b;">(optional)</small></label>
                            <input type="text" name="phone" id="c_phone" class="form-control"
                                   placeholder="+92 300 1234567"
                                   value="<?= e($_POST['phone'] ?? '') ?>">
                            <div class="field-error" data-for="c_phone"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Subject <small style="color: #64748b;">(optional)</small></label>
                            <input type="text" name="subject" id="c_subject" class="form-control"
                                   minlength="5" maxlength="200"
                                   placeholder="What is this about?"
                                   value="<?= e($_POST['subject'] ?? '') ?>">
                            <div class="field-error" data-for="c_subject"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Message <span class="text-danger">*</span></label>
                            <textarea name="message" id="c_message" class="form-control" rows="5" required
                                      minlength="20" maxlength="3000"
                                      placeholder="Please write at least 20 characters..."><?= e($_POST['message'] ?? '') ?></textarea>
                            <div class="form-text text-end" style="color: #64748b;"><span id="msgCount">0</span> / 3000 chars</div>
                            <div class="field-error" data-for="c_message"></div>
                        </div>
                        <div class="col-12 text-end mt-4">
                            <button type="submit" class="btn btn-premium">
                                <i class="bi bi-send me-2"></i> Send Message
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Contact Info Sidebar -->
        <div class="col-lg-4">
            <div class="glass-card glass-card-info">
                <h4 class="fw-bold mb-4 text-white">Contact Info</h4>

                <div class="info-item">
                    <div class="info-icon"><i class="bi bi-telephone-fill"></i></div>
                    <div class="info-text">
                        <h6>Phone</h6>
                        <p><?= e(SITE_PHONE) ?></p>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-icon"><i class="bi bi-envelope-fill"></i></div>
                    <div class="info-text">
                        <h6>Email</h6>
                        <p><?= e(SITE_EMAIL) ?></p>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-icon"><i class="bi bi-geo-alt-fill"></i></div>
                    <div class="info-text">
                        <h6>Coverage Area</h6>
                        <p>Pakistan &amp; Azad Jammu &amp; Kashmir</p>
                    </div>
                </div>

                <div class="info-item mb-0">
                    <div class="info-icon"><i class="bi bi-clock-fill"></i></div>
                    <div class="info-text">
                        <h6>Office Hours</h6>
                        <p>Mon - Sat: 9:00 AM - 7:00 PM</p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

<script>
function showError(fieldId, message) {
    const errorEl = document.querySelector(`.field-error[data-for="${fieldId}"]`);
    const inputEl = document.getElementById(fieldId);
    if (!errorEl || !inputEl) return;
    if (message) {
        errorEl.textContent = message;
        errorEl.classList.add('show');
        inputEl.classList.add('is-invalid');
        inputEl.classList.remove('is-valid');
    } else {
        errorEl.classList.remove('show');
        inputEl.classList.remove('is-invalid');
        if (inputEl.value.trim() !== '') inputEl.classList.add('is-valid');
        else inputEl.classList.remove('is-valid');
    }
}

// Name (min 4 chars)
document.getElementById('c_name').addEventListener('input', function() {
    const v = this.value.trim();
    if (v === '') return showError('c_name', '');
    if (v.length < 4) return showError('c_name', 'Name must be at least 4 characters.');
    if (!/^[A-Za-z\s\.\-\']+$/.test(v)) return showError('c_name', 'Name can only contain letters and spaces.');
    if (/(.)\1{3,}/.test(v)) return showError('c_name', 'Name contains repeated characters.');
    showError('c_name', '');
});

// Email
document.getElementById('c_email').addEventListener('input', function() {
    const v = this.value.trim();
    if (v === '') return showError('c_email', '');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) return showError('c_email', 'Please enter a valid email.');
    showError('c_email', '');
});

// Phone
document.getElementById('c_phone').addEventListener('input', function() {
    const v = this.value.trim();
    if (v === '') return showError('c_phone', '');
    if (!/^[0-9+\-\s]{10,20}$/.test(v)) return showError('c_phone', 'Phone must be 10-20 digits.');
    showError('c_phone', '');
});

// Subject (min 5 chars)
document.getElementById('c_subject').addEventListener('input', function() {
    const v = this.value.trim();
    if (v === '') return showError('c_subject', '');
    if (v.length < 5) return showError('c_subject', 'Subject must be at least 5 characters.');
    if (/(.)\1{4,}/.test(v)) return showError('c_subject', 'Subject contains repeated characters.');
    showError('c_subject', '');
});

// Message + counter
const msgEl = document.getElementById('c_message');
const msgCount = document.getElementById('msgCount');
msgEl.addEventListener('input', function() {
    const v = this.value.trim();
    msgCount.textContent = this.value.length;
    if (v === '') return showError('c_message', '');
    if (v.length < 20) return showError('c_message', 'Message must be at least 20 characters.');
    if (v.split(/\s+/).length < 5) return showError('c_message', 'Message must contain at least 5 words.');
    if (/(.)\1{5,}/.test(v)) return showError('c_message', 'Message contains repeated characters.');
    showError('c_message', '');
});

// Submit validation
document.getElementById('contactForm').addEventListener('submit', function(e) {
    ['c_name','c_email','c_phone','c_subject','c_message'].forEach(id => {
        document.getElementById(id).dispatchEvent(new Event('input', {bubbles:true}));
    });
    const hasError = document.querySelectorAll('.is-invalid').length > 0;
    if (hasError) {
        e.preventDefault();
        document.querySelector('.is-invalid')?.scrollIntoView({behavior:'smooth', block:'center'});
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>