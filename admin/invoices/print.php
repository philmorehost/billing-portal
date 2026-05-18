<?php
require_once '../../includes/config.php';
require_once INC_PATH.'/modules/billing.php';
require_once INC_PATH.'/modules/pdf.php';
$admin=Auth::requireAdmin();
$inv_id=(int)get_param('id');
InvoicePDF::generate($inv_id);
