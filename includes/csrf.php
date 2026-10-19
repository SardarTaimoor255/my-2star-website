<?php
/**
 * includes/csrf.php
 * CSRF protection
 */

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

/**
 * Get (or create) the CSRF token for the current session
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = random_token(64);
    }
    return $_SESSION['csrf_token'];
}

/**
 * Return hidden input HTML for forms
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verify the submitted CSRF token
 */
function csrf_verify(?string $token): bool
{
    return !empty($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Verify or die
 */
function csrf_require(?string $token): void
{
    if (!csrf_verify($token)) {
        http_response_code(419);
        exit('Invalid or expired security token. Please refresh the page and try again.');
    }
}