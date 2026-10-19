<?php
/**
 * includes/functions.php
 * Helper functions
 */

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

/**
 * Escape HTML output (XSS prevention)
 */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Sanitize a string input
 */
function clean(?string $value): string
{
    return trim((string)$value);
}

/**
 * Redirect to a URL and exit
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Format money (PKR)
 */
function money($amount): string
{
    return CURRENCY_SYMBOL . ' ' . number_format((float)$amount, 2);
}

/**
 * Format date
 */
function fmt_date(?string $dt, string $format = 'd-M-Y'): string
{
    if (!$dt) return '—';
    try {
        return (new DateTime($dt))->format($format);
    } catch (Exception $e) {
        return '—';
    }
}

/**
 * Format datetime
 */
function fmt_datetime(?string $dt): string
{
    return fmt_date($dt, 'd-M-Y h:i A');
}

/**
 * Generate a random token
 */
function random_token(int $length = 32): string
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * Get client IP
 */
function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Get current logged-in user id (or null)
 */
function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/**
 * Check if request is AJAX
 */
function is_ajax(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Return JSON response & exit
 */
function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/**
 * Log an activity
 */
function log_activity(PDO $pdo, string $action, string $module = null, ?int $refId = null, ?string $desc = null): void
{
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO activity_logs (user_id, action, module, reference_id, description, ip_address, user_agent)
             VALUES (:uid, :action, :module, :ref, :desc, :ip, :ua)'
        );
        $stmt->execute([
            ':uid'    => current_user_id(),
            ':action' => $action,
            ':module' => $module,
            ':ref'    => $refId,
            ':desc'   => $desc,
            ':ip'     => client_ip(),
            ':ua'     => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    } catch (Throwable $e) {
        error_log('log_activity failed: ' . $e->getMessage());
    }
}

/**
 * Generate sequential numbers: ORD-2026-000001
 */
function generate_number(PDO $pdo, string $prefix, string $table, string $column): string
{
    $year = date('Y');
    $like = $prefix . '-' . $year . '-%';

    $stmt = $pdo->prepare("SELECT $column FROM $table WHERE $column LIKE :like ORDER BY id DESC LIMIT 1");
    $stmt->execute([':like' => $like]);
    $last = $stmt->fetchColumn();

    $next = 1;
    if ($last) {
        $parts = explode('-', $last);
        $next = (int)end($parts) + 1;
    }

    return sprintf('%s-%s-%06d', $prefix, $year, $next);
}

/**
 * Safe file upload
 * Returns relative path inside uploads/ or null
 */
function upload_file(array $file, string $subFolder, array $allowedExt): ?string
{
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return null;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return null;
    }

    $subFolder = trim($subFolder, '/');
    $dir = UPLOADS_PATH . '/' . $subFolder;
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $filename = date('Ymd_His') . '_' . random_token(8) . '.' . $ext;
    $target = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return null;
    }

    return $subFolder . '/' . $filename;
}