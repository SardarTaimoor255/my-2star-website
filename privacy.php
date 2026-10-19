<?php
require_once __DIR__ . '/includes/init.php';
$pageTitle = 'Privacy Policy';
require_once __DIR__ . '/includes/header.php';
?>

<section class="hero" style="padding: 50px 0;">
    <div class="container text-center">
        <h1 class="display-6 fw-bold mb-2">Privacy Policy</h1>
        <p class="mb-0" style="color:#c9d6f2;">Last updated: <?= date('d F Y') ?></p>
    </div>
</section>

<section class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card-soft p-4">

                <h5 class="fw-bold mb-3">1. Information We Collect</h5>
                <p class="small text-muted mb-2">We collect the following information when you register:</p>
                <ul class="small text-muted mb-4">
                    <li>Business name, owner name, contact details</li>
                    <li>Email address and phone number</li>
                    <li>Business address, province, city</li>
                    <li>Product catalog data (for companies)</li>
                    <li>Order history and payment records</li>
                    <li>Chat messages between authorized parties</li>
                </ul>

                <h5 class="fw-bold mb-3">2. How We Use Your Information</h5>
                <ul class="small text-muted mb-4">
                    <li>To process orders and facilitate deliveries</li>
                    <li>To enable communication between authorized parties</li>
                    <li>To send notifications about orders and payments</li>
                    <li>To generate reports and business insights</li>
                    <li>To improve our platform services</li>
                    <li>To prevent fraud and ensure security</li>
                </ul>

                <h5 class="fw-bold mb-3">3. Information Sharing</h5>
                <p class="small text-muted mb-2">Your information is shared only with authorized parties:</p>
                <ul class="small text-muted mb-4">
                    <li><strong>Shops:</strong> Order details shared with vendor, company, and transport as needed</li>
                    <li><strong>Vendors:</strong> Order and payment info shared with shops, companies, transport</li>
                    <li><strong>Companies:</strong> Order details shared with vendor only</li>
                    <li><strong>Transport:</strong> Delivery info (shop name, address, contact) only</li>
                </ul>
                <p class="small text-muted mb-4">
                    <strong>We never sell your data to third parties.</strong>
                </p>

                <h5 class="fw-bold mb-3">4. Data Security</h5>
                <ul class="small text-muted mb-4">
                    <li>Passwords are stored using bcrypt hashing (never plain text)</li>
                    <li>All database queries use prepared statements (SQL injection protection)</li>
                    <li>CSRF tokens protect all form submissions</li>
                    <li>Session security with secure cookies and timeout</li>
                    <li>File uploads are validated and stored securely</li>
                </ul>

                <h5 class="fw-bold mb-3">5. Payment Data</h5>
                <p class="small text-muted mb-4">
                    We <strong>never store</strong> credit card numbers, CVV codes, or sensitive 
                    payment credentials. Payment records contain only transaction references 
                    (e.g., Easypaisa TXN ID) for record-keeping.
                </p>

                <h5 class="fw-bold mb-3">6. Chat &amp; Messages</h5>
                <p class="small text-muted mb-4">
                    Chat messages are stored in our database for order history and dispute 
                    resolution. Messages are visible only to authorized participants 
                    (shop, vendor, company, transport as applicable to the order).
                </p>

                <h5 class="fw-bold mb-3">7. Cookies &amp; Sessions</h5>
                <p class="small text-muted mb-4">
                    We use session cookies to keep you logged in. These cookies are 
                    HTTP-only and secure. We do not use third-party tracking cookies.
                </p>

                <h5 class="fw-bold mb-3">8. Your Rights</h5>
                <ul class="small text-muted mb-4">
                    <li>Access your personal data</li>
                    <li>Update or correct your information</li>
                    <li>Request account deletion (subject to pending orders)</li>
                    <li>Contact us about data concerns</li>
                </ul>

                <h5 class="fw-bold mb-3">9. Data Retention</h5>
                <p class="small text-muted mb-4">
                    Order history and payment records are retained for legal and business 
                    purposes. Chat messages are retained for dispute resolution.
                </p>

                <h5 class="fw-bold mb-3">10. Contact Us</h5>
                <p class="small text-muted mb-0">
                    For privacy concerns: <strong><?= e(SITE_EMAIL) ?></strong><br>
                    Phone: <strong><?= e(SITE_PHONE) ?></strong>
                </p>

            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>