<?php
require_once '../../includes/config.php';
require_once INC_PATH.'/modules/reseller.php';
require_once INC_PATH.'/modules/pdf.php';
if(empty($_SESSION['reseller_id'])) redirect(BASE_URL.'/reseller/login.php');
$reseller_id=$_SESSION['reseller_id'];
$reseller=DB::row("SELECT * FROM resellers WHERE id=?",'i',[$reseller_id]);
$reseller_client=DB::row("SELECT * FROM clients WHERE id=?",'i',[$reseller['client_id']]);
$currency=DB::setting('base_currency','NGN'); $company=DB::setting('company_name','Billing Portal');
$inv_id=(int)get_param('id');
$inv=DB::row("SELECT i.*,c.first_name,c.last_name,c.email FROM invoices i JOIN clients c ON c.id=i.client_id JOIN services s ON s.client_id=i.client_id WHERE i.id=? AND s.reseller_id=? GROUP BY i.id",'ii',[$inv_id,$reseller_id]);
if(!$inv) redirect(BASE_URL.'/reseller/invoices.php');
$page_title='Invoice #'.h($inv['invoice_number']);
$items=DB::rows("SELECT * FROM invoice_items WHERE invoice_id=?",'i',[$inv_id]);
$sb=['paid'=>'success','unpaid'=>'warning','overdue'=>'danger','cancelled'=>'muted'];
include '../partials/header.php';
?>
<div class="bp-content">
<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
  <a href="<?=BASE_URL?>/reseller/invoices.php" class="bp-btn bp-btn-outline bp-btn-sm">← Back</a>
  <h1 class="bp-page-title" style="margin:0">Invoice #<?=h($inv['invoice_number'])?></h1>
  <span class="bp-badge bp-badge-<?=$sb[$inv['status']]??'muted'?>"><?=$inv['status']?></span>
  <a href="print.php?id=<?=$inv_id?>" class="bp-btn bp-btn-outline bp-btn-sm ms-auto" target="_blank">🖨 PDF</a>
</div>
<div class="bp-card">
  <div style="background:linear-gradient(135deg,#0f172a,#1e3a5f);padding:28px;display:flex;justify-content:space-between;align-items:center">
    <div><div style="color:rgba(255,255,255,.5);font-size:11px;font-weight:700;text-transform:uppercase">Invoice</div><div style="color:#fff;font-size:22px;font-weight:800">#<?=h($inv['invoice_number'])?></div></div>
    <div style="text-align:right"><div style="color:rgba(255,255,255,.5);font-size:11px">Total</div><div style="color:#fff;font-size:26px;font-weight:900"><?=format_currency($inv['total'],$inv['currency'])?></div></div>
  </div>
  <div class="bp-card-body">
    <div class="row g-3 mb-4">
      <div class="col-6">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:8px">Client</div>
        <div style="font-weight:700"><?=h($inv['first_name'].' '.$inv['last_name'])?></div>
        <div style="font-size:13px;color:#64748b"><?=h($inv['email'])?></div>
      </div>
      <div class="col-6"><div class="row g-2">
        <div class="col-6"><div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:4px">Issue Date</div><div style="font-size:13px;font-weight:600"><?=format_date($inv['created_at'])?></div></div>
        <div class="col-6"><div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:4px">Due Date</div><div style="font-size:13px;font-weight:600;color:<?=$inv['status']==='overdue'?'#ef4444':'inherit'?>"><?=format_date($inv['due_date'])?></div></div>
        <?php if($inv['paid_date']):?><div class="col-12"><div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:4px">Paid</div><div style="font-size:13px;font-weight:600;color:#10b981"><?=format_date($inv['paid_date'])?></div></div><?php endif?>
      </div></div>
    </div>
    <table class="bp-table" style="margin-bottom:16px"><thead><tr><th>Description</th><th style="text-align:center">Qty</th><th style="text-align:right">Price</th><th style="text-align:right">Total</th></tr></thead><tbody>
      <?php foreach($items as $item):?><tr><td><?=h($item['description'])?></td><td style="text-align:center"><?=$item['quantity']?></td><td style="text-align:right"><?=format_currency($item['unit_price'],$inv['currency'])?></td><td style="text-align:right;font-weight:600"><?=format_currency($item['total'],$inv['currency'])?></td></tr><?php endforeach?>
    </tbody></table>
    <div style="display:flex;justify-content:flex-end"><div style="width:260px">
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:13px;color:#64748b"><span>Subtotal</span><span><?=format_currency($inv['subtotal'],$inv['currency'])?></span></div>
      <?php if($inv['tax_amount']>0):?><div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:13px;color:#64748b"><span>Tax</span><span><?=format_currency($inv['tax_amount'],$inv['currency'])?></span></div><?php endif?>
      <div style="display:flex;justify-content:space-between;padding:14px 0;font-size:18px;font-weight:800;color:#0f172a"><span>Total</span><span><?=format_currency($inv['total'],$inv['currency'])?></span></div>
    </div></div>
  </div>
</div>
</div>
<?php include '../partials/footer.php';?>
