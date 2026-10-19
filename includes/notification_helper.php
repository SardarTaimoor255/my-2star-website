<?php
if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

/**
 * Create a notification for a user
 */
function create_notification(PDO $pdo, int $userId, string $type, string $title, string $message, ?string $link = null): void
{
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO notifications (user_id, type, title, message, link)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $type, $title, $message, $link]);
    } catch (Throwable $e) {
        error_log('create_notification failed: ' . $e->getMessage());
    }
}

/**
 * Get admin user id (first super_admin)
 */
function get_admin_user_id(PDO $pdo): ?int
{
    $stmt = $pdo->query(
        "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
         WHERE r.name = 'super_admin' AND u.status='active' LIMIT 1"
    );
    $id = $stmt->fetchColumn();
    return $id ? (int)$id : null;
}

/**
 * Notify shop owner
 */
function notify_shop(PDO $pdo, int $shopId, string $type, string $title, string $message, ?string $link = null): void
{
    $stmt = $pdo->prepare("SELECT user_id FROM shops WHERE id = ?");
    $stmt->execute([$shopId]);
    $uid = $stmt->fetchColumn();
    if ($uid) create_notification($pdo, (int)$uid, $type, $title, $message, $link);
}

/**
 * Notify vendor (first vendor)
 */
function notify_vendor(PDO $pdo, string $type, string $title, string $message, ?string $link = null): void
{
    $stmt = $pdo->query(
        "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
         WHERE r.name = 'vendor' AND u.status='active' LIMIT 1"
    );
    $uid = $stmt->fetchColumn();
    if ($uid) create_notification($pdo, (int)$uid, $type, $title, $message, $link);
}