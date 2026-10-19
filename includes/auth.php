<?php
/**
 * includes/auth.php
 * Authentication & Authorization
 */

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

/**
 * Return currently logged-in user (cached per request)
 */
function current_user(PDO $pdo): ?array
{
    static $cached = false;
    static $user = null;

    if ($cached) return $user;
    $cached = true;

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT u.*, r.name AS role_name, r.display_name AS role_label
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.id = :id AND u.status = "active"
         LIMIT 1'
    );
    $stmt->execute([':id' => (int)$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;

    return $user;
}

/**
 * Is the user logged in?
 */
function is_logged_in(PDO $pdo): bool
{
    return current_user($pdo) !== null;
}

/**
 * Get logged-in user's role name (slug)
 */
function current_role(PDO $pdo): ?string
{
    $u = current_user($pdo);
    return $u['role_name'] ?? null;
}

/**
 * Require login — else redirect to login page
 */
function require_login(PDO $pdo): void
{
    if (!is_logged_in($pdo)) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        flash_error('Please log in to continue.');
        redirect(BASE_URL . 'auth/login.php');
    }
}

/**
 * Require one of the given roles
 */
function require_role(PDO $pdo, $roles): void
{
    require_login($pdo);
    $roles = (array)$roles;
    $current = current_role($pdo);

    if (!in_array($current, $roles, true)) {
        // Show professional 403 page
        http_response_code(403);
        include BASE_PATH . '/errors/403.php';
        exit;
    }
}

/**
 * Is current user a given role?
 */
function has_role(PDO $pdo, string $role): bool
{
    return current_role($pdo) === $role;
}

/**
 * Get default dashboard URL for a role
 */
function dashboard_url_for(string $role): string
{
    switch ($role) {
        case ROLE_ADMIN:     return BASE_URL . 'admin/index.php';
        case ROLE_VENDOR:    return BASE_URL . 'vendor/index.php';
        case ROLE_SHOP:      return BASE_URL . 'shop/index.php';
        case ROLE_COMPANY:   return BASE_URL . 'company/index.php';
        case ROLE_TRANSPORT: return BASE_URL . 'transport/index.php';
        default:             return BASE_URL . 'index.php';
    }
}

/**
 * Log a user in (set session)
 */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']    = (int)$user['id'];
    $_SESSION['user_role']  = $user['role_name'];
    $_SESSION['user_name']  = $user['full_name'];
    $_SESSION['last_activity'] = time();
}

/**
 * Log out current user
 */
function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}