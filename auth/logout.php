<?php
/**
 * auth/logout.php
 */
require_once dirname(__DIR__) . '/includes/init.php';

if (is_logged_in($pdo)) {
    log_activity($pdo, 'user_logout', 'auth', current_user_id(), 'User logged out');
}

logout_user();

// Start a fresh session for the flash
session_start();
flash_success('You have been logged out.');
redirect(BASE_URL . 'auth/login.php');