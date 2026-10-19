<?php
require_once __DIR__ . '/includes/init.php';
$pageTitle = 'Terms of Service';
require_once __DIR__ . '/includes/header.php';
?>

<section class="hero" style="padding: 50px 0;">
    <div class="container text-center">
        <h1 class="display-6 fw-bold mb-2">Terms of Service</h1>
        <p class="mb-0" style="color:#c9d6f2;">Last updated: <?= date('d F Y') ?></p>
    </div>
</section>

<section class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card-soft p-4">

                <h5 class="fw-bold mb-3">1. Acceptance of Terms</h5>
                <p class="small text-muted mb-4">
                    By registering on <?= e(SITE_NAME) ?> platform, you agree to these terms. 
                    If you do not agree, please do not use our services.
                </p>

                <h5 class="fw-bold mb-3">2. User Responsibilities</h5>
                <ul class="small text-muted mb-4">
                    <li>Provide accurate business information during registration</li>
                    <li>Maintain the confidentiality of your account password</li>
                    <li>Use the platform only for legitimate business purposes</li>
                    <li>Do not engage in fraudulent or illegal activities</li>
                    <li>Respond to communications and orders promptly</li>
                </ul>

                <h5 class="fw-bold mb-3">3. Orders &amp; Payments</h5>
                <ul class="small text-muted mb-4">
                    <li>All orders are subject to company confirmation and stock availability</li>
                    <li>Prices are confirmed after price request approval</li>
                    <li>Payment must be made as agreed (cash, Easypaisa, JazzCash, bank transfer)</li>
                    <li>Partial payments are allowed but balance must be cleared as agreed</li>
                    <li>Delivery charges are set by transport company and are separate from product cost</li>
                </ul>

                <h5 class="fw-bold mb-3">4. Vendor Commission</h5>
                <p class="small text-muted mb-4">
                    Vendor commission is confidential and not disclosed to companies. 
                    However, it is transparently added to the final shop price shown to the buyer.
                </p>

                <h5 class="fw-bold mb-3">5. Delivery &amp; Risk</h5>
                <ul class="small text-muted mb-4">
                    <li>Goods are delivered through third-party transport/bilty services</li>
                    <li>Risk of loss/damage transfers to the shop upon delivery</li>
                    <li>Any damage must be reported within 24 hours of delivery</li>
                    <li>Transport company contact is provided for delivery coordination</li>
                </ul>

                <h5 class="fw-bold mb-3">6. Cancellation &amp; Returns</h5>
                <ul class="small text-muted mb-4">
                    <li>Orders can be cancelled by shop before vendor acceptance</li>
                    <li>Vendor may reject orders if products are unavailable</li>
                    <li>Returns are subject to vendor approval and may incur charges</li>
                </ul>

                <h5 class="fw-bold mb-3">7. Account Suspension</h5>
                <p class="small text-muted mb-4">
                    We reserve the right to suspend or terminate accounts that violate 
                    these terms, engage in fraudulent activity, or misuse the platform.
                </p>

                <h5 class="fw-bold mb-3">8. Limitation of Liability</h5>
                <p class="small text-muted mb-4">
                    <?= e(SITE_NAME) ?> acts as a connecting platform between shops, vendors, 
                    companies, and transport. We are not liable for direct product quality issues 
                    or third-party logistics failures.
                </p>

                <h5 class="fw-bold mb-3">9. Modifications</h5>
                <p class="small text-muted mb-4">
                    We may update these terms at any time. Continued use of the platform 
                    after changes means you accept the updated terms.
                </p>

                <h5 class="fw-bold mb-3">10. Governing Law</h5>
                <p class="small text-muted mb-4">
                    These terms are governed by the laws of Pakistan and Azad Jammu &amp; Kashmir. 
                    Any disputes will be resolved under local jurisdiction.
                </p>

                <h5 class="fw-bold mb-3">11. Contact</h5>
                <p class="small text-muted mb-0">
                    For any questions regarding these terms, please contact us at 
                    <strong><?= e(SITE_EMAIL) ?></strong> or 
                    <strong><?= e(SITE_PHONE) ?></strong>.
                </p>

            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>