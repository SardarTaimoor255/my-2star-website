<?php
/**
 * api/admin-action.php
 * Admin AJAX handler
 */
require_once dirname(__DIR__) . '/includes/init.php';

if (!is_ajax()) {
    json_response(['success' => false, 'message' => 'Invalid request'], 400);
}

if (!is_logged_in($pdo) || current_role($pdo) !== ROLE_ADMIN) {
    json_response(['success' => false, 'message' => 'Unauthorized'], 403);
}

// CSRF
$token = $_POST['csrf_token'] ?? null;
if (!csrf_verify($token)) {
    json_response(['success' => false, 'message' => 'Invalid security token'], 419);
}

$action = $_POST['action'] ?? '';
$id     = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    json_response(['success' => false, 'message' => 'Invalid ID'], 400);
}

try {
    switch ($action) {

        case 'approve_user':
            $stmt = $pdo->prepare(
                "UPDATE users SET status='active' WHERE id=? AND status='pending'"
            );
            $stmt->execute([$id]);
            log_activity($pdo, 'user_approved', 'admin', $id, 'User approved');
            json_response(['success' => true, 'message' => 'User approved']);

        case 'reject_user':
            $stmt = $pdo->prepare(
                "UPDATE users SET status='rejected' WHERE id=? AND status='pending'"
            );
            $stmt->execute([$id]);
            log_activity($pdo, 'user_rejected', 'admin', $id, 'User rejected');
            json_response(['success' => true, 'message' => 'User rejected']);

        case 'toggle_user':
            $to = $_POST['to'] ?? '';
            $allowed = ['active', 'suspended'];
            if (!in_array($to, $allowed, true)) {
                json_response(['success' => false, 'message' => 'Invalid status'], 400);
            }
            $stmt = $pdo->prepare("UPDATE users SET status=? WHERE id=?");
            $stmt->execute([$to, $id]);
            log_activity($pdo, 'user_status_changed', 'admin', $id, 'Status → ' . $to);
            json_response(['success' => true, 'message' => 'Status updated']);

        default:
            json_response(['success' => false, 'message' => 'Unknown action'], 400);
    }
} catch (Throwable $e) {
    error_log('admin-action error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Server error'], 500);
}