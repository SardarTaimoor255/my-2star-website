<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/tracking_helper.php';

if (!is_logged_in($pdo)) json_response(['success' => false], 403);
$user = current_user($pdo);

$orderId = (int)($_GET['order_id'] ?? 0);
if ($orderId <= 0) json_response(['success' => false, 'message' => 'Invalid order'], 400);

// Role-based access
$allowed = false;
switch ($user['role_name']) {
    case ROLE_ADMIN:
        $allowed = true;
        break;
    case ROLE_VENDOR:
        $allowed = true;
        break;
    case ROLE_SHOP:
        $stmt = $pdo->prepare("SELECT 1 FROM orders o JOIN shops s ON s.id = o.shop_id WHERE o.id = ? AND s.user_id = ?");
        $stmt->execute([$orderId, $user['id']]);
        $allowed = (bool)$stmt->fetchColumn();
        break;
    case ROLE_COMPANY:
        $stmt = $pdo->prepare(
            "SELECT 1 FROM order_items oi
             JOIN companies c ON c.id = oi.company_id
             WHERE oi.order_id = ? AND c.user_id = ? LIMIT 1"
        );
        $stmt->execute([$orderId, $user['id']]);
        $allowed = (bool)$stmt->fetchColumn();
        break;
    case ROLE_TRANSPORT:
        $stmt = $pdo->prepare("SELECT 1 FROM shipments WHERE order_id = ? AND transport_user_id = ?");
        $stmt->execute([$orderId, $user['id']]);
        $allowed = (bool)$stmt->fetchColumn();
        break;
}

if (!$allowed) json_response(['success' => false, 'message' => 'Access denied'], 403);

$data = get_order_tracking($pdo, $orderId);
if (empty($data)) json_response(['success' => false], 404);

json_response([
    'success'   => true,
    'status'    => $data['order']['status'],
    'progress'  => tracking_progress($data['order']['status']),
    'timeline'  => $data['timeline'],
    'shipment'  => $data['shipment'],
]);