<?php
if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

/**
 * Recalculate paid_amount and payment_status for an order.
 * ONLY counts 'paid' payments (not pending_verification).
 */
function recalc_order_payment(PDO $pdo, int $orderId): void
{
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM payments
         WHERE order_id = ? AND status = 'paid'"
    );
    $stmt->execute([$orderId]);
    $paid = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT total_amount FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $total = (float)$stmt->fetchColumn();

    if ($paid <= 0) {
        $status = 'pending';
    } elseif ($paid + 0.001 < $total) {
        $status = 'partially_paid';
    } else {
        $status = 'paid';
    }

    $pdo->prepare(
        "UPDATE orders SET paid_amount = ?, payment_status = ? WHERE id = ?"
    )->execute([$paid, $status, $orderId]);

    $pdo->prepare(
        "UPDATE invoices SET payment_status = ? WHERE order_id = ?"
    )->execute([$status, $orderId]);
}

function generate_payment_no(PDO $pdo): string
{
    return generate_number($pdo, 'PAY', 'payments', 'payment_no');
}

function payment_method_label(string $method): string
{
    return PAYMENT_METHODS[$method] ?? ucfirst($method);
}

function payment_method_icon(string $method): string
{
    return [
        'cash'          => 'bi-cash-stack',
        'bank_transfer' => 'bi-bank',
        'easypaisa'     => 'bi-phone',
        'jazzcash'      => 'bi-phone',
        'cheque'        => 'bi-file-earmark-text',
        'other'         => 'bi-three-dots',
    ][$method] ?? 'bi-cash';
}

function payment_status_color(string $status): string
{
    return [
        'pending'              => 'danger',
        'pending_verification' => 'warning',
        'partially_paid'       => 'warning',
        'paid'                 => 'success',
        'failed'               => 'danger',
        'refunded'             => 'secondary',
    ][$status] ?? 'secondary';
}

function payment_status_label(string $status): string
{
    return [
        'pending'              => 'Pending',
        'pending_verification' => 'Awaiting Verification',
        'partially_paid'       => 'Partially Paid',
        'paid'                 => 'Paid',
        'failed'               => 'Failed',
        'refunded'             => 'Refunded',
    ][$status] ?? ucfirst($status);
}
/**
 * Validate payment reference number based on payment method.
 * @return string|null Error message if invalid, null if valid.
 */
function validate_reference_no(string $ref, string $method): ?string
{
    $ref = trim($ref);

    if ($ref === '') {
        return 'Reference ID required hai.';
    }
    if (strlen($ref) < 6) {
        return 'Reference ID kam se kam 6 characters ki honi chahiye.';
    }
    if (strlen($ref) > 60) {
        return 'Reference ID 60 characters se zyada nahi ho sakti.';
    }

    // Allowed: letters, numbers, dash, underscore
    if (!preg_match('/^[A-Za-z0-9_-]+$/', $ref)) {
        return 'Reference ID mein sirf letters, numbers, dash (-), aur underscore (_) allowed hain. Spaces allowed nahi.';
    }

    switch ($method) {

        // ----- Easypaisa -----
        // Real TID: 41210468367 (11 digits) ya 260914218043386533 (18 digits)
        case 'easypaisa':
            if (!preg_match('/^(EP-?|TID-?)?[0-9]{8,25}$/i', $ref)) {
                return 'Easypaisa TID galat format mein hai. Example: 41210468367 ya EP-41210468367';
            }
            break;

        // ----- JazzCash -----
        // Similar to Easypaisa: 10-20 digits, optional JC-/TID- prefix
        case 'jazzcash':
            if (!preg_match('/^(JC-?|TID-?)?[0-9]{8,25}$/i', $ref)) {
                return 'JazzCash TID galat format mein hai. Example: JC-41210468367 ya 41210468367';
            }
            break;

        // ----- SadaPay -----
        // Format: Raast-467914, SDP-XXXXXX, ya numeric 6-20
        case 'sadapay':
            if (!preg_match('/^(SDP-?|Raast-?)?[0-9A-Za-z]{6,25}$/i', $ref)) {
                return 'SadaPay reference galat format mein hai. Example: Raast-467914';
            }
            if (preg_match_all('/[0-9]/', $ref) < 4) {
                return 'SadaPay reference mein kam se kam 4 digits hone chahiye.';
            }
            break;

        // ----- NayaPay -----
        // Format: NP-XXXXXX, NayaPay-XXXX, ya numeric
        case 'nayapay':
            if (!preg_match('/^(NP-?|NayaPay-?)?[0-9A-Za-z]{6,25}$/i', $ref)) {
                return 'NayaPay reference galat format mein hai. Example: NP-467914 ya 467914123';
            }
            if (preg_match_all('/[0-9]/', $ref) < 4) {
                return 'NayaPay reference mein kam se kam 4 digits hone chahiye.';
            }
            break;

        // ----- Raast -----
        // Format: Raast-XXXXXX (Raast ID always has prefix in State Bank)
        case 'raast':
            if (!preg_match('/^Raast-?[0-9]{6,20}$/i', $ref)) {
                return 'Raast ID galat format mein hai. Example: Raast-467914';
            }
            break;

        // ----- Bank Transfer -----
        // Format: TRF-2026-123456, IBAN, ya koi bhi alphanumeric with digits
        case 'bank_transfer':
            if (preg_match_all('/[0-9]/', $ref) < 4) {
                return 'Bank Transfer reference mein kam se kam 4 digits hone chahiye. Example: TRF-2026-123456';
            }
            break;

        // ----- Cheque -----
        // Format: 4-20 alphanumeric (cheque number usually numeric)
        case 'cheque':
            if (!preg_match('/^[A-Za-z0-9-]{4,20}$/', $ref)) {
                return 'Cheque number galat format mein hai. Example: CHQ-123456';
            }
            break;

        // ----- Cash -----
        case 'cash':
            if (preg_match_all('/[0-9]/', $ref) < 3) {
                return 'Cash reference mein kam se kam 3 digits hone chahiye. Example: CASH-2026-001';
            }
            break;

        // ----- Other -----
        case 'other':
            if (preg_match_all('/[0-9]/', $ref) < 3) {
                return 'Reference mein kam se kam 3 digits hone chahiye.';
            }
            break;
    }

    return null; // Valid
}