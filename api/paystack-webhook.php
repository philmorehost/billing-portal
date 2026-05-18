<?php
/**
 * Paystack Webhook Handler
 * Verifies signature, processes payment events
 */

require_once __DIR__ . '/../includes/config.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit;
}

$secret  = DB::setting('paystack_secret_key');
$payload = file_get_contents('php://input');
$sig     = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';

// Verify HMAC signature
if (!hash_equals(hash_hmac('sha512', $payload, $secret), $sig)) {
    http_response_code(400);
    log_activity('paystack_webhook_invalid', 'Invalid signature received');
    exit('Invalid signature');
}

$event = json_decode($payload, true);
if (!$event) { http_response_code(400); exit; }

http_response_code(200); // Always ack quickly

switch ($event['event']) {
    case 'charge.success':
        $data      = $event['data'];
        $reference = $data['reference'];
        $meta      = $data['metadata'] ?? [];
        $inv_id    = (int) ($meta['invoice_id'] ?? 0);

        if (!$inv_id) {
            // Try to match by reference pattern INV-{number}-{timestamp}
            preg_match('/INV-([A-Z0-9-]+)-\d+/', $reference, $m);
            if (!empty($m[1])) {
                $inv_id = (int) DB::value("SELECT id FROM invoices WHERE invoice_number=?", 's', [$m[1]]);
            }
        }

        if ($inv_id) {
            $inv = DB::row("SELECT * FROM invoices WHERE id=?", 'i', [$inv_id]);
            if ($inv && $inv['status'] !== 'paid') {
                require_once INC_PATH . '/modules/billing.php';
                Billing::markPaid($inv_id, 'paystack', $reference, $data['amount'] / 100);
                log_activity('paystack_payment', "Invoice #{$inv['invoice_number']} paid via Paystack ref:{$reference}");
            }
        }
        break;

    case 'charge.failed':
        $reference = $event['data']['reference'];
        log_activity('paystack_failed', "Payment failed for ref:{$reference}");
        break;

    case 'refund.processed':
        $reference = $event['data']['transaction_reference'] ?? '';
        // Handle refund - mark invoice as refunded
        $inv = DB::row("SELECT * FROM invoices WHERE gateway_transaction_id=?", 's', [$reference]);
        if ($inv) {
            DB::execute("UPDATE invoices SET status='refunded' WHERE id=?", 'i', [$inv['id']]);
            log_activity('paystack_refund', "Invoice #{$inv['invoice_number']} refunded ref:{$reference}");
        }
        break;
}

exit;
