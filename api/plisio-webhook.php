<?php
/**
 * Plisio Webhook Handler
 * Verifies HMAC SHA1 signature and completes payments automatically.
 */

require_once __DIR__ . '/../includes/config.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$secretKey = DB::setting('crypto_plisio_api_key');
if (!$secretKey) {
    http_response_code(400);
    exit('Plisio API key not configured.');
}

$post = $_POST;
if (!isset($post['verify_hash'])) {
    http_response_code(400);
    exit('Missing signature verify_hash.');
}

$verifyHash = $post['verify_hash'];
unset($post['verify_hash']);

// Sort remaining POST data alphabetically by key
ksort($post);

// Re-align data types as expected by Plisio serialization logic
if (isset($post['expire_utc'])) {
    $post['expire_utc'] = (string)$post['expire_utc'];
}
if (isset($post['tx_urls'])) {
    $post['tx_urls'] = html_entity_decode($post['tx_urls']);
}

// Generate comparison signature using PHP serialize
$postString = serialize($post);
$generatedHash = hash_hmac('sha1', $postString, $secretKey);

// Validate signature using hash_equals
if (!hash_equals($generatedHash, $verifyHash)) {
    http_response_code(400);
    log_activity('plisio_webhook_invalid', 'Invalid Plisio signature verify_hash received.');
    exit('Invalid verify_hash.');
}

// Return 200 OK early to acknowledge
http_response_code(200);
echo "OK";

// Handle completed transaction status
if (($post['status'] ?? '') === 'completed') {
    $ref = $post['order_number'] ?? '';
    $txId = $post['tx_id'] ?? $ref;
    $amount = (float)($post['amount'] ?? 0);

    // Try to match by reference pattern INV-{number}-{timestamp}
    preg_match('/INV-([A-Z0-9-]+)-\d+/', $ref, $m);
    $inv_id = 0;
    if (!empty($m[1])) {
        $inv_id = (int)DB::value("SELECT id FROM invoices WHERE invoice_number=?", 's', [$m[1]]);
    }

    if ($inv_id) {
        $inv = DB::row("SELECT * FROM invoices WHERE id=?", 'i', [$inv_id]);
        if ($inv && $inv['status'] !== 'paid') {
            require_once INC_PATH . '/modules/billing.php';
            // Mark invoice paid, record transaction history, activate services
            Billing::markPaid($inv_id, 'plisio', $txId, $amount);
            log_activity('plisio_payment', "Invoice #{$inv['invoice_number']} paid via Plisio ref:{$ref} coin:{$post['currency']} amount:{$amount}");
        }
    }
}
exit;
