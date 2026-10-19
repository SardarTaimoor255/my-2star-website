<?php
date_default_timezone_set('Asia/Karachi');
/**
 * includes/init.php
 * Bootstrap file — include this at the top of every page
 */

if (defined('APP_INIT')) {
    return;
}
define('APP_INIT', true);

// ---- Base path ----
define('BASE_PATH', dirname(__DIR__));

// ---- Load config first ----
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/constants.php';

// ---- Error log path ----
if (!is_dir(LOGS_PATH)) {
    @mkdir(LOGS_PATH, 0775, true);
}

// ---- Secure session ----
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('5STAR_SESS');
    session_start();
}

// ---- Session timeout (30 min inactivity) ----
$timeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['flash_error'] = 'Session expired. Please log in again.';
}
$_SESSION['last_activity'] = time();

// ---- Load helpers ----
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/flash.php';
require_once BASE_PATH . '/includes/auth.php';