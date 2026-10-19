<?php
/**
 * includes/flash.php
 * Flash messages
 */

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash_' . $type] = $message;
}

function flash_success(string $message): void { flash_set('success', $message); }
function flash_error(string $message): void   { flash_set('error', $message); }
function flash_info(string $message): void    { flash_set('info', $message); }
function flash_warning(string $message): void { flash_set('warning', $message); }

function flash_get(string $type): ?string
{
    $key = 'flash_' . $type;
    if (!empty($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $msg;
    }
    return null;
}

/**
 * Render all pending flash messages as Bootstrap alerts
 */
function flash_render(): string
{
    $types = [
        'success' => 'success',
        'error'   => 'danger',
        'info'    => 'info',
        'warning' => 'warning',
    ];
    $out = '';
    foreach ($types as $key => $bsClass) {
        $msg = flash_get($key);
        if ($msg !== null) {
            $out .= '<div class="alert alert-' . $bsClass . ' alert-dismissible fade show" role="alert">'
                 . e($msg)
                 . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
                 . '</div>';
        }
    }
    return $out;
}