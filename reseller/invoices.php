<?php
require_once '../includes/config.php';
require_once INC_PATH.'/modules/reseller.php';
if(empty($_SESSION['reseller_id'])) redirect(BASE_URL.'/reseller/login.php');
$reseller_id=$_SESSION['reseller_id'];
$reseller=DB::row("SELECT * FROM resellers WHERE id=?",'i',[$reseller_id]);
$reseller_client=DB::row("SELECT * FROM clients WHERE id=?",'i',[$reseller['client_id']]);
$currency=DB::setting('base_currency','NGN'); $company=DB::setting('company_name','Billing Portal');
$page_title='Invoices'; $status=get_param('status'); $pn=max(1,(int)get_param('page',1)); $pp=20;
$where=["s.reseller_id=?"]; $params=[$reseller_id]; $types='i';
if($status){$where[]="i.status=?";$params[]=$status;$types.='s';}
$ws=implode(' AND ',$where);
$total=(int)DB::value("SELECT COUNT(DISTINCT i.id) FROM invoices i JOIN services s ON s.client_id=i.client_id WHERE {$ws}",$types,$params);
$pg=paginate($total,$pp,$pn);
$invoices=DB::rows("SELECT i.*,c.first_name,c.last_name FROM invoices i JOIN clients c ON c.id=i.client_id JOIN services s ON s.client_id=i.client_id WHERE {$ws} GROUP BY i.id ORDER BY i.id DESC LIMIT {$pp} OFFSET {$pg['offset']}",$types,$params);
include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title">Invoices</h1>
<div class="d-flex gap-2 mb-4 flex-wrap">
  <?php foreach([''=> 'All','unpaid'=>'⚠ Unpaid','overdue'=>'🔴 Overdue','paid'=>'✓ Paid'] as $k=>$l):?>
  <a href="?status=<?=$k?>" class="bp-btn bp-btn-<?=$status===$k&&($k||!$status)?'primary':'outline'?> bp-btn-sm"><?=$l?></a>
  <?php endforeach?>
</div>
<?=flash_html()?>
<div class="bp-card">
<?php if($invoices):?>
<table class="bp-table"><thead><tr><th>Invoice</th><th>Client</th><th>Amount</th><th>Due</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach($invoices as $inv):$sb=['paid'=>'success','unpaid'=>'warning','overdue'=>'danger','cancelled'=>'muted'];?>
<tr>
  <td style="font-weight:600">#<?=h($inv['invoice_number'])?></td>
  <td><div style="font-weight:600"><?=h($inv['first_name'].' '.$inv['last_name'])?></div></td>
  <td style="font-weight:700"><?=format_currency($inv['total'],$inv['currency'])?></td>
  <td style="font-size:13px;color:<?=$inv['status']==='overdue'?'#ef4444':'#64748b'?>"><?=format_date($inv['due_date'])?></td>
  <td><span class="bp-badge bp-badge-<?=$sb[$inv['status']]??'muted'?>"><?=$inv['status']?></span></td>
  <td><a href="invoices/view.php?id=<?=$inv['id']?>" class="bp-btn bp-btn-outline bp-btn-sm">View</a></td>
</tr>
<?php endforeach?>
</tbody></table>
<?php else:?><div class="bp-empty"><div class="bp-empty-icon">🧾</div><div class="bp-empty-title">No invoices found</div></div><?php endif?>
</div>
<?php if($pg['total_pages']>1):?>
<div class="bp-pagination">
  <?php if($pg['has_prev']):?><a href="?page=<?=$pn-1?>&status=<?=urlencode($status)?>" class="bp-page-btn">‹</a><?php endif?>
  <?php for($i=max(1,$pn-2);$i<=min($pg['total_pages'],$pn+2);$i++):?><a href="?page=<?=$i?>&status=<?=urlencode($status)?>" class="bp-page-btn <?=$i===$pn?'active':''?>"><?=$i?></a><?php endfor?>
  <?php if($pg['has_next']):?><a href="?page=<?=$pn+1?>&status=<?=urlencode($status)?>" class="bp-page-btn">›</a><?php endif?>
</div>
<?php endif?>
</div>
<?php include 'partials/footer.php';?>
