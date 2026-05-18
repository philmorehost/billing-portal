<?php
require_once '../../includes/config.php';
require_once INC_PATH.'/modules/billing.php';
require_once INC_PATH.'/modules/pdf.php';
$client = Auth::requireClient();
$inv_id = (int) get_param('id');
$inv    = DB::row("SELECT id FROM invoices WHERE id=? AND client_id=?", 'ii', [$inv_id, $client['id']]);
if (!$inv) { http_response_code(403); exit('Forbidden'); }
InvoicePDF::generate($inv_id);
