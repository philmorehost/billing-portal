<?php
require_once '../includes/config.php';
require_once INC_PATH.'/modules/reseller.php';
if(empty($_SESSION['reseller_id'])) redirect(BASE_URL.'/reseller/login.php');
$reseller_id=$_SESSION['reseller_id'];
$reseller=DB::row("SELECT * FROM resellers WHERE id=?",'i',[$reseller_id]);
$reseller_client=DB::row("SELECT * FROM clients WHERE id=?",'i',[$reseller['client_id']]);
$currency=DB::setting('base_currency','NGN'); $company=DB::setting('company_name','Billing Portal');
$page_title='Services'; $status=get_param('status'); $pn=max(1,(int)get_param('page',1)); $pp=25;
$where=["s.reseller_id=?"]; $params=[$reseller_id]; $types='i';
if($status){$where[]="s.status=?";$params[]=$status;$types.='s';}
$ws=implode(' AND ',$where);
$total=(int)DB::value("SELECT COUNT(*) FROM services s WHERE {$ws}",$types,$params);
$pg=paginate($total,$pp,$pn);
$services=DB::rows("SELECT s.*,p.name AS pname,c.first_name,c.last_name,c.email FROM services s JOIN products p ON p.id=s.product_id JOIN clients c ON c.id=s.client_id WHERE {$ws} ORDER BY s.id DESC LIMIT {$pp} OFFSET {$pg['offset']}",$types,$params);
include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title">Services</h1>
<div class="d-flex gap-2 mb-4 flex-wrap">
  <?php foreach([''=> 'All','active'=>'Active','suspended'=>'Suspended','pending'=>'Pending','terminated'=>'Terminated'] as $k=>$l):?>
  <a href="?status=<?=$k?>" class="bp-btn bp-btn-<?=$status===$k&&($k||!$status)?'primary':'outline'?> bp-btn-sm"><?=$l?></a>
  <?php endforeach?>
</div>
<div class="bp-card">
<?php if($services):?>
<table class="bp-table"><thead><tr><th>Service</th><th>Client</th><th>Cycle / Price</th><th>Next Due</th><th>Status</th></tr></thead><tbody>
<?php foreach($services as $s):$sc=['active'=>'success','suspended'=>'danger','pending'=>'warning','terminated'=>'muted'];?>
<tr>
  <td><div style="font-weight:600"><?=h($s['pname'])?></div><?php if($s['domain']):?><div style="font-size:12px;color:#64748b">🌐 <?=h($s['domain'])?></div><?php endif?></td>
  <td><a href="clients/view.php?id=<?=$s['client_id']?>" style="text-decoration:none"><div style="font-weight:600;color:#0f172a;font-size:13px"><?=h($s['first_name'].' '.$s['last_name'])?></div><div style="font-size:11px;color:#64748b"><?=h($s['email'])?></div></a></td>
  <td style="font-size:13px"><?=ucfirst(str_replace('_',' ',$s['billing_cycle']))?><br><span style="font-weight:600"><?=format_currency($s['price'],$currency)?></span></td>
  <td style="font-size:13px;color:<?=($s['next_due_date']&&strtotime($s['next_due_date'])<time())?'#ef4444':'#64748b'?>"><?=$s['next_due_date']?format_date($s['next_due_date']):'—'?></td>
  <td><span class="bp-badge bp-badge-<?=$sc[$s['status']]??'muted'?>"><?=$s['status']?></span></td>
</tr>
<?php endforeach?></tbody></table>
<?php else:?><div class="bp-empty"><div class="bp-empty-icon">🖥</div><div class="bp-empty-title">No services found</div></div><?php endif?>
</div>
<?php if($pg['total_pages']>1):?><div class="bp-pagination">
  <?php if($pg['has_prev']):?><a href="?page=<?=$pn-1?>&status=<?=urlencode($status)?>" class="bp-page-btn">‹</a><?php endif?>
  <?php for($i=max(1,$pn-2);$i<=min($pg['total_pages'],$pn+2);$i++):?><a href="?page=<?=$i?>&status=<?=urlencode($status)?>" class="bp-page-btn <?=$i===$pn?'active':''?>"><?=$i?></a><?php endfor?>
  <?php if($pg['has_next']):?><a href="?page=<?=$pn+1?>&status=<?=urlencode($status)?>" class="bp-page-btn">›</a><?php endif?>
</div><?php endif?>
</div>
<?php include 'partials/footer.php';?>
