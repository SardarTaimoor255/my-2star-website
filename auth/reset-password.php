<?php
require_once dirname(__DIR__) . '/includes/init.php';

if (is_logged_in($pdo)) {
    redirect(dashboard_url_for(current_role($pdo)));
}

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$validToken = false;
$email = '';

if ($token !== '') {
    // Validate token exists and not expired
    $stmt = $pdo->prepare(
        "SELECT pr.email FROM password_resets pr
         JOIN users u ON u.id = pr.user_id
         WHERE pr.token = ? AND pr.used_at IS NULL AND pr.expires_at > NOW() 
           AND u.status = 'active'
         LIMIT 1"
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if ($row) {
        $validToken = true;
        $email = $row['email'];
    }
}

$pageTitle = 'Reset Password';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
.password-wrap { position: relative; }
.password-wrap .toggle-pw {
    position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
    background: none; border: none; color: #64748b; cursor: pointer;
    padding: 4px 8px; font-size: 1.1rem;
}
.password-wrap .toggle-pw:hover { color: #0b3d91; }
.strength-bar { height: 4px; border-radius: 2px; background: #e2e8f0; margin-top: 6px; overflow: hidden; }
.strength-bar > div { height: 100%; transition: all .3s; width: 0; }
.strength-text { font-size: .72rem; margin-top: 4px; }
</style>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card-soft p-4">

        <?php if (!$validToken): ?>
          <div class="text-center mb-4">
            <i class="bi bi-exclamation-triangle fs-1 text-danger"></i>
            <h3 class="fw-bold mt-2 mb-1">Invalid Reset Link</h3>
            <p class="text-muted small mb-3">
              Ye link invalid hai ya expire ho gaya hai (1 hour ke baad expire hota hai).
            </p>
            <a href="<?= BASE_URL ?>auth/forgot-password.php" class="btn btn-primary">
              <i class="bi bi-arrow-repeat"></i> Request New Link
            </a>
          </div>
        <?php else: ?>

          <div class="text-center mb-4">
            <i class="bi bi-shield-lock fs-1 text-success"></i>
            <h3 class="fw-bold mt-2 mb-1">Reset Password</h3>
            <p class="text-muted small mb-0">
              Email: <strong><?= e($email) ?></strong>
            </p>
          </div>

          <form method="post" action="<?= BASE_URL ?>api/password-reset.php" autocomplete="off" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset">
            <input type="hidden" name="token" value="<?= e($token) ?>">

            <div class="mb-3">
              <label class="form-label">New Password</label>
              <div class="password-wrap">
                <input type="password" name="password" id="password" class="form-control" required
                       placeholder="Min 8 chars, 1 uppercase, 1 number">
                <button type="button" class="toggle-pw" data-target="password">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
              <div class="strength-bar"><div id="strengthBar"></div></div>
              <div class="strength-text" id="strengthText"></div>
            </div>

            <div class="mb-3">
              <label class="form-label">Confirm Password</label>
              <div class="password-wrap">
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                <button type="button" class="toggle-pw" data-target="confirm_password">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2">
              <i class="bi bi-check-circle"></i> Reset Password
            </button>
          </form>

          <div class="text-center mt-3 small">
            <a href="<?= BASE_URL ?>auth/login.php" class="text-decoration-none">
              <i class="bi bi-arrow-left"></i> Back to Login
            </a>
          </div>

        <?php endif; ?>

      </div>
    </div>
  </div>
</div>

<script>
// Show/hide password
document.querySelectorAll('.toggle-pw').forEach(btn => {
    btn.addEventListener('click', () => {
        const target = document.getElementById(btn.dataset.target);
        const icon = btn.querySelector('i');
        if (target.type === 'password') {
            target.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            target.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    });
});

// Password strength
const pwInput = document.getElementById('password');
if (pwInput) {
    const bar = document.getElementById('strengthBar');
    const txt = document.getElementById('strengthText');
    pwInput.addEventListener('input', () => {
        const pw = pwInput.value;
        let score = 0;
        if (pw.length >= 8) score++;
        if (/[A-Z]/.test(pw)) score++;
        if (/[0-9]/.test(pw)) score++;
        if (/[!@#$%^&*(),.?":{}|<>]/.test(pw)) score++;

        if (pw.length === 0) { bar.style.width = '0'; txt.textContent = ''; }
        else if (score <= 1) { bar.style.width = '33%'; bar.style.background = '#ef4444'; txt.textContent = 'Weak'; txt.style.color = '#ef4444'; }
        else if (score === 2 || score === 3) { bar.style.width = '66%'; bar.style.background = '#f59e0b'; txt.textContent = 'Medium'; txt.style.color = '#f59e0b'; }
        else { bar.style.width = '100%'; bar.style.background = '#10b981'; txt.textContent = 'Strong ✓'; txt.style.color = '#10b981'; }
    });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>