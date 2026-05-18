<?php
require_once '../../includes/config.php';
require_once INC_PATH.'/modules/billing.php';
$admin=Auth::requireAdmin();
$inv_id=(int)get_param('id');
if($inv_id){ Billing::markPaid($inv_id,'manual','ADMIN-'.time()); log_activity('admin_mark_paid',"Invoice #{$inv_id} marked paid",'admin',$admin['id']); }
redirect_with_flash(BASE_URL.'/admin/invoices.php','success','Invoice marked as paid.');
