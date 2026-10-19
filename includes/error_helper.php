<?php
if (!defined('APP_INIT')) { http_response_code(403); exit('Direct access denied.'); }

/**
 * Show 403 Access Denied page and exit
 */
function error_403(string $message = ''): void
{
    http_response_code(403);
    if ($message) $_SESSION['error_message'] = $message;
    include BASE_PATH . '/errors/403.php';
    exit;
}

/**
 * Show 404 Not Found page and exit
 */
function error_404(string $message = ''): void
{
    http_response_code(404);
    if ($message) $_SESSION['error_message'] = $message;
    include BASE_PATH . '/errors/404.php';
    exit;
}

/**
 * Show 500 Server Error page and exit
 */
function error_500(string $message = ''): void
{
    http_response_code(500);
    if ($message) $_SESSION['error_message'] = $message;
    include BASE_PATH . '/errors/500.php';
    exit;
}

/**
 * Show Maintenance page and exit
 */
function error_maintenance(string $message = ''): void
{
    http_response_code(503);
    if ($message) $_SESSION['error_message'] = $message;
    include BASE_PATH . '/errors/maintenance.php';
    exit;
}

/**
 * User-friendly error response
 * - Development: shows details
 * - Production: shows generic message, logs full error
 */
function friendly_error(Throwable $ex, string $userMessage = 'Kuch masla ho gaya. Dobara try karein.'): void
{
    // Log full error
    error_log('[' . date('Y-m-d H:i:s') . '] ' . $ex->getMessage() . ' in ' . $ex->getFile() . ':' . $ex->getLine());

    if (ENVIRONMENT === 'development') {
        echo '<pre style="background:#fee; padding:15px; border:1px solid #c00;">';
        echo '<strong>Error (development mode):</strong>' . "\n\n";
        echo htmlspecialchars($ex->getMessage()) . "\n";
        echo htmlspecialchars($ex->getFile() . ':' . $ex->getLine()) . "\n\n";
        echo htmlspecialchars($ex->getTraceAsString());
        echo '</pre>';
    } else {
        // Production: show user-friendly error page
        error_500($userMessage);
    }
}