<?php
require_once dirname(__DIR__) . '/includes/init.php';

if (!is_logged_in($pdo)) {
    json_response(['success' => false], 403);
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    json_response(['success' => false, 'message' => 'Invalid token'], 419);
}

$user = current_user($pdo);
$action = $_POST['action'] ?? '';
$id = (int)($_POST['id'] ?? 0);

switch ($action) {
    case 'mark_all':
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")
            ->execute([$user['id']]);
        json_response(['success' => true]);

    case 'mark_one':
        if ($id <= 0) json_response(['success' => false], 400);
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")
            ->execute([$id, $user['id']]);
        json_response(['success' => true]);

    default:
        json_response(['success' => false, 'message' => 'Unknown action'], 400);
}