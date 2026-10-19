<?php
require_once dirname(__DIR__) . '/includes/init.php';

if (is_logged_in($pdo)) {
    redirect(dashboard_url_for(current_role($pdo)));
}

$pageTitle = 'Forgot Password';

require_once dirname(__DIR__) . '/includes/header.php';
?>
<?php
// Development mode — show reset link if exists
$devLink = $_SESSION['dev_reset_link'] ?? null;
unset($_SESSION['dev_reset_link']);
?>
<div class="container py-5">
    <?php if ($devLink): ?>
    <div class="alert alert-warning border-warning">
        <strong><i class="bi bi-tools"></i> Development Mode — Reset Link:</strong>
        <div class="mt-2">
            <a href="<?= e($devLink) ?>" class="btn btn-sm btn-warning">
                <i class="bi bi-key"></i> Click Here to Reset Password
            </a>
        </div>
        <div class="mt-2 small text-muted text-break">
            <?= e($devLink) ?>
        </div>
    </div>
<?php endif; ?>
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card-soft p-4">

        <div class="text-center mb-4">
          <i class="bi bi-key fs-1 text-primary"></i>
          <h3 class="fw-bold mt-2 mb-1">Forgot Password?</h3>
          <p class="text-muted small mb-0">
            Apna email daalein — hum reset link bhejenge.
          </p>
        </div>

        <form method="post" action="<?= BASE_URL ?>api/password-reset.php" autocomplete="off">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="request">

          <div class="mb-3">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" 
                   placeholder="aapka.email@example.com" required autofocus>
          </div>

          <button type="submit" class="btn btn-primary w-100 py-2">
            <i class="bi bi-envelope"></i> Send Reset Link
          </button>
        </form>

        <div class="text-center mt-3 small">
          <a href="<?= BASE_URL ?>auth/login.php" class="text-decoration-none">
            <i class="bi bi-arrow-left"></i> Back to Login
          </a>
        </div>

      </div>

      <div class="alert alert-info small mt-3">
        <i class="bi bi-info-circle"></i>
        <strong>Development Mode:</strong> Email ke bajaye, reset link upar <em>flash message</em> mein show hoga.
      </div>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>