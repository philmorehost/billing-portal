<?php
require_once '../includes/config.php';
require_once INC_PATH.'/modules/reseller.php';
if(empty($_SESSION['reseller_id'])) redirect(BASE_URL.'/reseller/login.php');
$reseller_id=$_SESSION['reseller_id'];
$reseller=DB::row("SELECT * FROM resellers WHERE id=?",'i',[$reseller_id]);
$reseller_client=DB::row("SELECT * FROM clients WHERE id=?",'i',[$reseller['client_id']]);
$company=DB::setting('company_name','Billing Portal');
$currency=DB::setting('base_currency','NGN');
$page_title='My Clients';

$search=trim(get_param('q')); $pn=max(1,(int)get_param('page',1)); $pp=20;
$where=["s.reseller_id=?"]; $params=[$reseller_id]; $types='i';
if($search){$where[]="(c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ?)";$s="%{$search}%";$params=array_merge($params,[$s,$s,$s]);$types.='sss';}
$ws=implode(' AND ',$where);
$total=(int)DB::value("SELECT COUNT(DISTINCT c.id) FROM clients c JOIN services s ON s.client_id=c.id WHERE {$ws}",$types,$params);
$pg=paginate($total,$pp,$pn);
$clients=DB::rows("SELECT c.*,(SELECT COUNT(*) FROM services sv WHERE sv.client_id=c.id AND sv.reseller_id=? AND sv.status='active') AS active_svcs,(SELECT COUNT(*) FROM invoices i WHERE i.client_id=c.id AND i.status IN ('unpaid','overdue')) AS unpaid_inv FROM clients c JOIN services s ON s.client_id=c.id WHERE {$ws} GROUP BY c.id ORDER BY c.id DESC LIMIT {$pp} OFFSET {$pg['offset']}",
    $types[0].$types,$reseller_id,...$params);
include 'partials/header.php';
?>
<div class="bp-content">
<div class="d-flex align-items-center justify-content-between mb-4">
  <div><h1 class="bp-page-title" style="margin:0">My Clients</h1><p class="bp-page-sub" style="margin:4px 0 0"><?=number_format($total)?> clients</p></div>
  <a href="clients/add.php" class="bp-btn bp-btn-primary">➕ Add Client</a>
</div>
<?=flash_html()?>
<div class="bp-card mb-4"><div class="bp-card-body" style="padding:14px 20px">
  <form method="GET" class="d-flex gap-3">
    <input type="text" name="q" class="bp-input" placeholder="Search name or email…" value="<?=h($search)?>" style="max-width:320px">
    <button type="submit" class="bp-btn bp-btn-primary">Search</button>
    <?php if($search):?><a href="clients.php" class="bp-btn bp-btn-outline">Clear</a><?php endif?>
  </form>
</div></div>
<div class="bp-card">
<?php if($clients):?>
<table class="bp-table"><thead><tr><th>Client</th><th>Services</th><th>Unpaid</th><th>Credit</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach($clients as $c):$sb=['active'=>'success','suspended'=>'danger','pending'=>'warning','inactive'=>'muted'];?>
<tr>
  <td><div style="font-weight:600"><?=h($c['first_name'].' '.$c['last_name'])?></div><div style="font-size:12px;color:#64748b"><?=h($c['email'])?></div></td>
  <td style="font-weight:600"><?=$c['active_svcs']?></td>
  <td><?=$c['unpaid_inv']>0?'<span style="color:#ef4444;font-weight:700">'.$c['unpaid_inv'].'</span>':'<span style="color:#94a3b8">0</span>'?></td>
  <td><?=format_currency($c['credit_balance'],$currency)?></td>
  <td><span class="bp-badge bp-badge-<?=$sb[$c['status']]??'muted'?>"><?=$c['status']?></span></td>
  <td><a href="clients/view.php?id=<?=$c['id']?>" class="bp-btn bp-btn-outline bp-btn-sm">View</a></td>
</tr>
<?php endforeach?>
</tbody></table>
<?php else:?><div class="bp-empty"><div class="bp-empty-icon">👥</div><div class="bp-empty-title">No clients yet</div><a href="clients/add.php" class="bp-btn bp-btn-primary bp-btn-sm" style="margin-top:10px">Add First Client</a></div><?php endif?>
</div>
<?php if($pg['total_pages']>1):?>
<div class="bp-pagination">
  <?php if($pg['has_prev']):?><a href="?page=<?=$pn-1?>&q=<?=urlencode($search)?>" class="bp-page-btn">‹</a><?php endif?>
  <?php for($i=max(1,$pn-2);$i<=min($pg['total_pages'],$pn+2);$i++):?><a href="?page=<?=$i?>&q=<?=urlencode($search)?>" class="bp-page-btn <?=$i===$pn?'active':''?>"><?=$i?></a><?php endfor?>
  <?php if($pg['has_next']):?><a href="?page=<?=$pn+1?>&q=<?=urlencode($search)?>" class="bp-page-btn">›</a><?php endif?>
</div>
<?php endif?>
</div>
<?php include 'partials/footer.php';?>
