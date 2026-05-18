<?php
require_once '../../includes/config.php';
require_once INC_PATH.'/modules/reseller.php';
require_once INC_PATH.'/modules/pdf.php';
if(empty($_SESSION['reseller_id'])) redirect(BASE_URL.'/reseller/login.php');
$reseller_id=$_SESSION['reseller_id'];
$inv_id=(int)get_param('id');
$inv=DB::row("SELECT i.id FROM invoices i JOIN services s ON s.client_id=i.client_id WHERE i.id=? AND s.reseller_id=? GROUP BY i.id",'ii',[$inv_id,$reseller_id]);
if(!$inv){http_response_code(403);exit('Forbidden');}
InvoicePDF::generate($inv_id);
