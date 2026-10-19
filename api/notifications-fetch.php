<?php
require_once dirname(__DIR__) . '/includes/init.php';

if (!is_logged_in($pdo)) {
    json_response(['success' => false], 403);
}

$user = current_user($pdo);

// Unread count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$user['id']]);
$unread = (int)$stmt->fetchColumn();

// Recent notifications (10)
$stmt = $pdo->prepare(
    "SELECT id, type, title, message, link, is_read, created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY id DESC
     LIMIT 10"
);
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();

// Format time ago
foreach ($notifications as &$n) {
    $n['time_ago'] = time_ago($n['created_at']);
}

json_response([
    'success' => true,
    'unread'  => $unread,
    'items'   => $notifications,
]);

/**
 * Human-readable time difference
 */
function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('d M', strtotime($datetime));
}