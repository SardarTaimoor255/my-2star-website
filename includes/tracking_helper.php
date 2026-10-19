<?php
if (!defined('APP_INIT')) { http_response_code(403); exit('No direct access.'); }

/**
 * Order stages in the workflow (for progress bar)
 */
function tracking_stages(): array
{
    return [
        'order_created'       => ['label' => 'Order Placed',      'icon' => 'bi-cart-check'],
        'order_accepted'      => ['label' => 'Accepted by Vendor','icon' => 'bi-check-circle'],
        'sent_to_company'     => ['label' => 'Sent to Company',   'icon' => 'bi-send'],
        'company_confirmed'   => ['label' => 'Company Confirmed', 'icon' => 'bi-building-check'],
        'ready_for_transport' => ['label' => 'Ready for Transport','icon' => 'bi-box-seam'],
        'transport_assigned'  => ['label' => 'Transport Assigned','icon' => 'bi-truck'],
        'picked_up'           => ['label' => 'Picked Up',         'icon' => 'bi-box-arrow-up'],
        'in_transit'          => ['label' => 'In Transit',        'icon' => 'bi-truck'],
        'out_for_delivery'    => ['label' => 'Out for Delivery',  'icon' => 'bi-geo-alt'],
        'delivered'           => ['label' => 'Delivered',         'icon' => 'bi-check-circle-fill'],
    ];
}

/**
 * Get index of a status in the stages array (for progress bar)
 */
function tracking_stage_index(string $status): int
{
    $stages = array_keys(tracking_stages());
    $idx = array_search($status, $stages, true);
    return $idx === false ? 0 : $idx;
}

/**
 * Calculate progress percentage
 */
function tracking_progress(string $status): int
{
    $total = count(tracking_stages()) - 1;
    $idx = tracking_stage_index($status);
    return $total > 0 ? (int)round(($idx / $total) * 100) : 0;
}

/**
 * Get tracking data for an order
 */
function get_order_tracking(PDO $pdo, int $orderId): array
{
    // Order
    $stmt = $pdo->prepare(
        "SELECT o.*, s.shop_name, s.address AS shop_address,
                s.owner_name AS shop_owner, s.user_id AS shop_user_id,
                u.full_name AS vendor_name, v.business_name AS vendor_business
         FROM orders o
         JOIN shops s ON s.id = o.shop_id
         LEFT JOIN vendors v ON v.id = o.vendor_id
         LEFT JOIN users u ON u.id = v.user_id
         WHERE o.id = ? LIMIT 1"
    );
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) return [];

    // Timeline
    $stmt = $pdo->prepare(
        "SELECT osh.*, u.full_name AS user_name
         FROM order_status_history osh
         LEFT JOIN users u ON u.id = osh.changed_by
         WHERE osh.order_id = ?
         ORDER BY osh.id ASC"
    );
    $stmt->execute([$orderId]);
    $timeline = $stmt->fetchAll();

    // Shipment
    $stmt = $pdo->prepare(
        "SELECT s.*, u.full_name AS transport_user_name, u.phone AS transport_user_phone,
                tc.company_name AS transport_company
         FROM shipments s
         LEFT JOIN users u ON u.id = s.transport_user_id
         LEFT JOIN transport_companies tc ON tc.user_id = u.id
         WHERE s.order_id = ? LIMIT 1"
    );
    $stmt->execute([$orderId]);
    $shipment = $stmt->fetch();

    // Shipment tracking events
    $shipmentEvents = [];
    if ($shipment) {
        $stmt = $pdo->prepare(
            "SELECT st.*, u.full_name AS updated_by_name
             FROM shipment_tracking st
             LEFT JOIN users u ON u.id = st.updated_by
             WHERE st.shipment_id = ?
             ORDER BY st.id ASC"
        );
        $stmt->execute([$shipment['id']]);
        $shipmentEvents = $stmt->fetchAll();
    }

    return [
        'order'          => $order,
        'timeline'       => $timeline,
        'shipment'       => $shipment,
        'shipmentEvents' => $shipmentEvents,
    ];
}

/**
 * Render progress bar HTML
 */
function render_progress_bar(string $status): string
{
    $stages = tracking_stages();
    $currentIdx = tracking_stage_index($status);
    $isCancelled = in_array($status, ['cancelled','returned','delivery_failed'], true);

    $html = '<div class="tracking-progress">';
    $html .= '<div class="tracking-stages">';

    $i = 0;
    foreach ($stages as $key => $stage) {
        $isDone = $i <= $currentIdx && !$isCancelled;
        $isCurrent = ($i === $currentIdx && !$isCancelled);
        $cls = $isDone ? 'done' : '';
        if ($isCurrent) $cls .= ' current';
        if ($isCancelled) $cls = 'cancelled';

        $html .= '<div class="track-stage ' . $cls . '">';
        $html .= '<div class="track-dot"><i class="bi ' . $stage['icon'] . '"></i></div>';
        $html .= '<div class="track-label">' . e($stage['label']) . '</div>';
        $html .= '</div>';

        if ($i < count($stages) - 1) {
            $lineDone = ($i < $currentIdx && !$isCancelled);
            $html .= '<div class="track-line ' . ($lineDone ? 'done' : '') . '"></div>';
        }
        $i++;
    }

    $html .= '</div></div>';
    return $html;
}