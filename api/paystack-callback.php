<?php
require_once __DIR__.'/../includes/config.php';
require_once INC_PATH.'/modules/billing.php';

$ref = trim(get_param('reference') ?: get_param('trxref'));
if (!$ref) { redirect(BASE_URL.'/client/invoices.php'); }

$result = Billing::paystackVerify($ref);
if ($result['success']) {
    $data    = $result['data'];
    $meta    = $data['metadata'] ?? [];
    $inv_id  = (int)($meta['invoice_id'] ?? 0);

    if (!$inv_id) {
        preg_match('/INV-([A-Z0-9-]+)-\d+/', $ref, $m);
        if (!empty($m[1])) $inv_id = (int)DB::value("SELECT id FROM invoices WHERE invoice_number=?",'s',[$m[1]]);
    }

    if ($inv_id) {
        $inv = DB::row("SELECT * FROM invoices WHERE id=?",'i',[$inv_id]);
        if ($inv && $inv['status'] !== 'paid') {
            Billing::markPaid($inv_id, 'paystack', $ref, $data['amount']/100);
        }
        redirect_with_flash(BASE_URL.'/client/invoices/view.php?id='.$inv_id, 'success', 'Payment successful! Your invoice has been paid.');
    }
}
redirect_with_flash(BASE_URL.'/client/invoices.php', 'danger', 'Payment verification failed. Please contact support.');
