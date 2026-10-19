<?php
/**
 * config/constants.php
 * Enums and status arrays
 */

if (!defined('BASE_PATH')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

// ---- User Roles (slugs) ----
const ROLE_ADMIN     = 'super_admin';
const ROLE_VENDOR    = 'vendor';
const ROLE_SHOP      = 'shop';
const ROLE_COMPANY   = 'company';
const ROLE_TRANSPORT = 'transport';

const ALL_ROLES = [
    ROLE_ADMIN, ROLE_VENDOR, ROLE_SHOP, ROLE_COMPANY, ROLE_TRANSPORT
];

// ---- Order Statuses ----
const ORDER_STATUSES = [
    'price_requested'     => 'Price Requested',
    'price_quoted'        => 'Price Quoted',
    'price_accepted'      => 'Price Accepted',
    'order_created'       => 'Order Created',
    'order_accepted'      => 'Accepted by Vendor',
    'sent_to_company'     => 'Sent to Company',
    'company_confirmed'   => 'Company Confirmed',
    'company_preparing'   => 'Preparing',
    'ready_for_transport' => 'Ready for Transport',
    'transport_assigned'  => 'Transport Assigned',
    'picked_up'           => 'Picked Up',
    'in_transit'          => 'In Transit',
    'arrived_destination' => 'Arrived at Destination',
    'out_for_delivery'    => 'Out for Delivery',
    'delivered'           => 'Delivered',
    'delivery_failed'     => 'Delivery Failed',
    'returned'            => 'Returned',
    'cancelled'           => 'Cancelled',
];

// ---- Order Status Colors (Bootstrap) ----
const ORDER_STATUS_COLORS = [
    'price_requested'     => 'secondary',
    'price_quoted'        => 'info',
    'price_accepted'      => 'primary',
    'order_created'       => 'secondary',
    'order_accepted'      => 'primary',
    'sent_to_company'     => 'info',
    'company_confirmed'   => 'primary',
    'company_preparing'   => 'warning',
    'ready_for_transport' => 'warning',
    'transport_assigned'  => 'info',
    'picked_up'           => 'info',
    'in_transit'          => 'warning',
    'arrived_destination' => 'info',
    'out_for_delivery'    => 'info',
    'delivered'           => 'success',
    'delivery_failed'     => 'danger',
    'returned'            => 'dark',
    'cancelled'           => 'dark',
];

// ---- Shipment Statuses ----
const SHIPMENT_STATUSES = [
    'awaiting_transport' => 'Awaiting Transport',
    'assigned'           => 'Assigned to Transport',
    'picked_up'          => 'Picked Up',
    'in_transit'         => 'In Transit',
    'arrived_destination'=> 'Arrived at Destination',
    'out_for_delivery'   => 'Out for Delivery',
    'delivered'          => 'Delivered',
    'delivery_failed'    => 'Delivery Failed',
    'returned'           => 'Returned',
];

// ---- Payment Statuses ----
const PAYMENT_STATUSES = [
    'pending'        => 'Pending',
    'partially_paid' => 'Partially Paid',
    'paid'           => 'Paid',
    'failed'         => 'Failed',
    'refunded'       => 'Refunded',
];

const PAYMENT_METHODS = [
    'cash'          => 'Cash',
    'bank_transfer' => 'Bank Transfer',
    'easypaisa'     => 'Easypaisa',
    'jazzcash'      => 'JazzCash',
    'sadapay'       => 'SadaPay',
    'nayapay'       => 'NayaPay',
    'raast'         => 'Raast',
    'cheque'        => 'Cheque',
    'other'         => 'Other',
];

// ---- User Statuses ----
const USER_STATUSES = [
    'pending'   => 'Pending Approval',
    'active'    => 'Active',
    'suspended' => 'Suspended',
    'rejected'  => 'Rejected',
];

// ---- Price Request Statuses ----
const PRICE_REQUEST_STATUSES = [
    'pending'         => 'Pending',
    'sent_to_company' => 'Sent to Company',
    'quoted'          => 'Quoted',
    'accepted'        => 'Accepted',
    'rejected'        => 'Rejected',
    'countered'       => 'Countered',
    'expired'         => 'Expired',
    'converted'       => 'Converted to Order',
];

/**
 * Status badge helper (Bootstrap HTML)
 */
function status_badge(string $status, array $colors = ORDER_STATUS_COLORS, array $labels = ORDER_STATUSES): string
{
    $color = $colors[$status] ?? 'secondary';
    $label = $labels[$status] ?? ucwords(str_replace('_', ' ', $status));
    return '<span class="badge text-bg-' . htmlspecialchars($color) . '">' . htmlspecialchars($label) . '</span>';
}