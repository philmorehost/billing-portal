<?php
require_once '../../includes/config.php';
require_once INC_PATH.'/modules/reseller.php';
if(empty($_SESSION['reseller_id'])) redirect(BASE_URL.'/reseller/login.php');
$reseller_id=$_SESSION['reseller_id'];
$reseller=DB::row("SELECT * FROM resellers WHERE id=?",'i',[$reseller_id]);
$reseller_client=DB::row("SELECT * FROM clients WHERE id=?",'i',[$reseller['client_id']]);
$currency=DB::setting('base_currency','NGN'); $company=DB::setting('company_name','Billing Portal');
$cid=(int)get_param('id');
$client=DB::row("SELECT c.* FROM clients c JOIN services s ON s.client_id=c.id WHERE c.id=? AND s.reseller_id=? GROUP BY c.id",'ii',[$cid,$reseller_id]);
if(!$client) redirect(BASE_URL.'/reseller/clients.php');
$page_title=h($client['first_name'].' '.$client['last_name']);
$services=DB::rows("SELECT s.*,p.name AS pname FROM services s JOIN products p ON p.id=s.product_id WHERE s.client_id=? AND s.reseller_id=? ORDER BY s.id DESC",'ii',[$cid,$reseller_id]);
$invoices=DB::rows("SELECT * FROM invoices WHERE client_id=? ORDER BY id DESC LIMIT 8",'i',[$cid]);
$sb_status=['active'=>'success','suspended'=>'danger','pending'=>'warning','inactive'=>'muted'];
include '../partials/header.php';
?>
<div class="bp-content">
<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
  <a href="<?=BASE_URL?>/reseller/clients.php" class="bp-btn bp-btn-outline bp-btn-sm">← Back</a>
  <h1 class="bp-page-title" style="margin:0"><?=h($client['first_name'].' '.$client['last_name'])?></h1>
  <span class="bp-badge bp-badge-<?=$sb_status[$client['status']]??'muted'?>"><?=$client['status']?></span>
</div>
<?=flash_html()?>
<div class="row g-4">
  <div class="col-lg-4">
    <div class="bp-card"><div class="bp-card-body" style="text-align:center;padding:28px 20px">
      <div style="width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#06b6d4);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;color:#fff;margin:0 auto 12px"><?=strtoupper(substr($client['first_name'],0,1))?></div>
      <div style="font-size:17px;font-weight:700"><?=h($client['first_name'].' '.$client['last_name'])?></div>
      <div style="font-size:13px;color:#64748b;margin-top:3px"><?=h($client['email'])?></div>
      <?php if($client['company']):?><div style="font-size:13px;color:#64748b"><?=h($client['company'])?></div><?php endif?>
      <div style="margin-top:16px;padding-top:16px;border-top:1px solid #f1f5f9">
        <?php foreach([['Credit',format_currency($client['credit_balance'],$currency)],['Joined',format_date($client['created_at'])],['Last Login',$client['last_login']?time_ago($client['last_login']):'Never']] as [$l,$v]):?>
        <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid #f8fafc;font-size:13px"><span style="color:#64748b"><?=$l?></span><span style="font-weight:600"><?=$v?></span></div>
        <?php endforeach?>
      </div>
    </div></div>
  </div>
  <div class="col-lg-8">
    <div class="bp-card"><div class="bp-card-header"><h3 class="bp-card-title">Services (<?=count($services)?>)</h3></div>
      <?php if($services):?>
      <table class="bp-table"><thead><tr><th>Service</th><th>Cycle</th><th>Price</th><th>Next Due</th><th>Status</th></tr></thead><tbody>
      <?php foreach($services as $s):$sc=['active'=>'success','suspended'=>'danger','pending'=>'warning','terminated'=>'muted'];?>
      <tr><td><div style="font-weight:600"><?=h($s['pname'])?></div><?php if($s['domain']):?><div style="font-size:12px;color:#64748b"><?=h($s['domain'])?></div><?php endif?></td>
      <td style="font-size:13px"><?=str_replace('_',' ',ucfirst($s['billing_cycle']))?></td>
      <td style="font-weight:600"><?=format_currency($s['price'],$currency)?></td>
      <td style="font-size:13px;color:#64748b"><?=$s['next_due_date']?format_date($s['next_due_date']):'—'?></td>
      <td><span class="bp-badge bp-badge-<?=$sc[$s['status']]??'muted'?>"><?=$s['status']?></span></td></tr>
      <?php endforeach?></tbody></table>
      <?php else:?><div class="bp-empty" style="padding:24px"><div class="bp-empty-title">No services yet</div></div><?php endif?>
    </div>
    <div class="bp-card" style="margin-top:16px"><div class="bp-card-header"><h3 class="bp-card-title">Invoices</h3></div>
      <?php if($invoices):?>
      <table class="bp-table"><thead><tr><th>Invoice</th><th>Amount</th><th>Due</th><th>Status</th></tr></thead><tbody>
      <?php foreach($invoices as $inv):$sb=['paid'=>'success','unpaid'=>'warning','overdue'=>'danger','cancelled'=>'muted'];?>
      <tr><td style="font-weight:600;color:#3b82f6">#<?=h($inv['invoice_number'])?></td>
      <td style="font-weight:700"><?=format_currency($inv['total'],$inv['currency'])?></td>
      <td style="font-size:13px;color:#64748b"><?=format_date($inv['due_date'])?></td>
      <td><span class="bp-badge bp-badge-<?=$sb[$inv['status']]??'muted'?>"><?=$inv['status']?></span></td></tr>
      <?php endforeach?></tbody></table>
      <?php else:?><div class="bp-empty" style="padding:20px"><div class="bp-empty-title">No invoices</div></div><?php endif?>
    </div>
  </div>
</div>
</div>
<?php include '../partials/footer.php';?>
