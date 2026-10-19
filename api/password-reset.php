<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/notification_helper.php';

$action = $_POST['action'] ?? '';

try {
    switch ($action) {

        // ============================================
        // 1. REQUEST RESET LINK (forgot-password form)
        // ============================================
        case 'request':
            csrf_require($_POST['csrf_token'] ?? null);

            $email = clean($_POST['email'] ?? '');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash_error('Please enter a valid email address.');
                redirect(BASE_URL . 'auth/forgot-password.php');
            }

            // Check if user exists
            $stmt = $pdo->prepare("SELECT id, full_name, email, status FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Always show same message (security — don't reveal if email exists)
            if (!$user || $user['status'] !== 'active') {
                flash_success('If this email is registered, you will receive a password reset link shortly.');
                redirect(BASE_URL . 'auth/forgot-password.php');
            }

            // Delete any existing tokens for this user
            $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$user['id']]);

                        // Generate token
            $token = bin2hex(random_bytes(32));

            // Let MySQL calculate expiry (same timezone as created_at)
            $pdo->prepare(
                "INSERT INTO password_resets (user_id, email, token, expires_at) 
                 VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))"
            )->execute([$user['id'], $user['email'], $token]);
            // Reset link
            $resetLink = BASE_URL . 'auth/reset-password.php?token=' . $token;

                        // In production, send email here
            // For now, store link in session (development mode)
            if (ENVIRONMENT === 'development') {
                $_SESSION['dev_reset_link'] = $resetLink;
            }

            log_activity($pdo, 'password_reset_requested', 'auth', $user['id'], 'Reset link generated');

            flash_success('If this email is registered, you will receive a password reset link shortly.');
            redirect(BASE_URL . 'auth/forgot-password.php');

        // ============================================
        // 2. RESET PASSWORD (reset form submit)
        // ============================================
        case 'reset':
            csrf_require($_POST['csrf_token'] ?? null);

            $token    = clean($_POST['token'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm  = $_POST['confirm_password'] ?? '';

            if ($token === '')      throw new Exception('Invalid reset token.');
            if (strlen($password) < 8) throw new Exception('Password must be at least 8 characters.');
            if (!preg_match('/[A-Z]/', $password)) throw new Exception('Password must contain an uppercase letter.');
            if (!preg_match('/[0-9]/', $password)) throw new Exception('Password must contain a number.');
            if ($password !== $confirm) throw new Exception('Passwords do not match.');

            // Validate token
            $stmt = $pdo->prepare(
                "SELECT pr.*, u.status AS user_status
                 FROM password_resets pr
                 JOIN users u ON u.id = pr.user_id
                 WHERE pr.token = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()
                 LIMIT 1"
            );
            $stmt->execute([$token]);
            $row = $stmt->fetch();

            if (!$row) throw new Exception('Reset link is invalid or has expired. Please request a new one.');
            if ($row['user_status'] !== 'active') throw new Exception('Account is not active.');

            $pdo->beginTransaction();

            // Update password
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$hash, $row['user_id']]);

            // Mark token used
            $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?")
                ->execute([$row['id']]);

            // Delete all other tokens for this user
            $pdo->prepare("DELETE FROM password_resets WHERE user_id = ? AND id <> ?")
                ->execute([$row['user_id'], $row['id']]);

            $pdo->commit();

            log_activity($pdo, 'password_reset_completed', 'auth', $row['user_id'], 'Password reset successful');

            // Notify user
            try {
                create_notification(
                    $pdo, (int)$row['user_id'], 'security',
                    'Password Changed',
                    'Your password was successfully reset. If this wasn\'t you, contact support immediately.',
                    BASE_URL . 'auth/login.php'
                );
            } catch (Throwable $ex) {
                error_log('Notify user failed: ' . $ex->getMessage());
            }

            flash_success('Password reset successfully! Please log in with your new password.');
            redirect(BASE_URL . 'auth/login.php');

        default:
            flash_error('Invalid request.');
            redirect(BASE_URL . 'auth/forgot-password.php');
    }
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('password-reset error: ' . $ex->getMessage());
    flash_error($ex->getMessage() ?: 'Something went wrong. Please try again.');
    
    // Redirect based on action
    if ($action === 'reset' && !empty($_POST['token'])) {
        redirect(BASE_URL . 'auth/reset-password.php?token=' . urlencode($_POST['token']));
    } else {
        redirect(BASE_URL . 'auth/forgot-password.php');
    }
}