<?php
require_once '../includes/config.php';
$admin=Auth::requireAdmin(); $company=DB::setting('company_name','Billing Portal'); $page_title='Services';
$search=trim(get_param('q')); $status=get_param('status'); $pn=max(1,(int)get_param('page',1)); $pp=25;
$where=['1=1']; $params=[]; $types='';
if($search){$where[]="(s.domain LIKE ? OR c.email LIKE ? OR c.first_name LIKE ?)";$s="%{$search}%";$params=array_merge($params,[$s,$s,$s]);$types.='sss';}
if($status){$where[]="s.status=?";$params[]=$status;$types.='s';}
$ws=implode(' AND ',$where);
$total=(int)DB::value("SELECT COUNT(*) FROM services s JOIN clients c ON c.id=s.client_id WHERE {$ws}",$types,$params);
$pg=paginate($total,$pp,$pn);
$services=DB::rows("SELECT s.*,p.name AS pname,c.first_name,c.last_name,c.email FROM services s JOIN products p ON p.id=s.product_id JOIN clients c ON c.id=s.client_id WHERE {$ws} ORDER BY s.id DESC LIMIT {$pp} OFFSET {$pg['offset']}",$types,$params);

if(is_post()&&csrf_verify()){
    $action=post('action'); $sid=(int)post('service_id');
    $new_status=['suspend'=>'suspended','unsuspend'=>'active','terminate'=>'terminated'][$action]??null;
    if($new_status&&$sid){
        DB::execute("UPDATE services SET status=? WHERE id=?",'si',[$new_status,$sid]);
        log_activity("service_{$action}","Service #{$sid} {$action}d",'admin',$admin['id']);
        redirect_with_flash('services.php','success',"Service {$action}d successfully.");
    }
}
include 'partials/header.php';
?>
<div class="bp-content">
<div class="d-flex align-items-center justify-content-between mb-4">
  <div><h1 class="bp-page-title" style="margin:0">Services</h1><p class="bp-page-sub" style="margin:4px 0 0"><?=number_format($total)?> total</p></div>
</div>
<?=flash_html()?>
<div class="bp-card mb-4"><div class="bp-card-body" style="padding:16px 22px">
<form method="GET" class="d-flex flex-wrap gap-3 align-items-end">
  <div style="flex:1;min-width:200px"><label class="bp-label">Search</label><input type="text" name="q" class="bp-input" placeholder="Domain, email…" value="<?=h($search)?>"></div>
  <div><label class="bp-label">Status</label>
    <select name="status" class="bp-select"><option value="">All</option>
      <?php foreach(['pending','active','suspended','terminated','cancelled'] as $s):?><option value="<?=$s?>" <?=$status===$s?'selected':''?>><?=ucfirst($s)?></option><?php endforeach?>
    </select>
  </div>
  <div class="d-flex gap-2"><button type="submit" class="bp-btn bp-btn-primary">Filter</button><?php if($search||$status):?><a href="services.php" class="bp-btn bp-btn-outline">Clear</a><?php endif?></div>
</form>
</div></div>
<div class="bp-card">
<?php if($services):?>
<table class="bp-table"><thead><tr><th>Service</th><th>Client</th><th>Cycle</th><th>Price</th><th>Next Due</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach($services as $s):$sb=['active'=>'success','suspended'=>'danger','pending'=>'warning','terminated'=>'muted','cancelled'=>'muted'];?>
<tr>
  <td><div style="font-weight:600"><?=h($s['pname'])?></div><?php if($s['domain']):?><div style="font-size:12px;color:#64748b"><?=h($s['domain'])?></div><?php endif?></td>
  <td><a href="clients/view.php?id=<?=$s['client_id']?>" style="text-decoration:none"><div style="font-weight:600;color:#0f172a"><?=h($s['first_name'].' '.$s['last_name'])?></div><div style="font-size:12px;color:#64748b"><?=h($s['email'])?></div></a></td>
  <td style="font-size:13px;text-transform:capitalize"><?=str_replace('_',' ',$s['billing_cycle'])?></td>
  <td style="font-weight:600"><?=format_currency($s['price'],DB::setting('base_currency','NGN'))?></td>
  <td style="font-size:13px;color:<?=($s['next_due_date']&&strtotime($s['next_due_date'])<time())?'#ef4444':'#64748b'?>"><?=$s['next_due_date']?format_date($s['next_due_date']):'—'?></td>
  <td><span class="bp-badge bp-badge-<?=$sb[$s['status']]??'muted'?>"><?=$s['status']?></span></td>
  <td>
    <a href="services/view.php?id=<?=$s['id']?>" class="bp-btn bp-btn-outline bp-btn-sm">View</a>
          <form method="POST" style="display:inline"><?=csrf_input()?><input type="hidden" name="service_id" value="<?=$s['id']?>">
      <?php if($s['status']==='active'):?><button name="action" value="suspend" class="bp-btn bp-btn-outline bp-btn-sm" style="color:#ef4444;border-color:#fecdd3" onclick="return confirm('Suspend?')">Suspend</button><?php endif?>
      <?php if($s['status']==='suspended'):?><button name="action" value="unsuspend" class="bp-btn bp-btn-success bp-btn-sm" onclick="return confirm('Unsuspend?')">Unsuspend</button><?php endif?>
      <?php if(!in_array($s['status'],['terminated','cancelled'])):?><button name="action" value="terminate" class="bp-btn bp-btn-outline bp-btn-sm" style="color:#ef4444" onclick="return confirm('Terminate permanently?')">Terminate</button><?php endif?>
    </form>
  </td>
</tr>
<?php endforeach?>
</tbody></table>
<?php else:?><div class="bp-empty"><div class="bp-empty-icon">🖥</div><div class="bp-empty-title">No services found</div></div><?php endif?>
</div>
<?php if($pg['total_pages']>1):?>
<div class="bp-pagination">
  <?php if($pg['has_prev']):?><a href="?page=<?=$pn-1?>&q=<?=urlencode($search)?>&status=<?=urlencode($status)?>" class="bp-page-btn">‹</a><?php endif?>
  <?php for($i=max(1,$pn-2);$i<=min($pg['total_pages'],$pn+2);$i++):?><a href="?page=<?=$i?>&q=<?=urlencode($search)?>&status=<?=urlencode($status)?>" class="bp-page-btn <?=$i===$pn?'active':''?>"><?=$i?></a><?php endfor?>
  <?php if($pg['has_next']):?><a href="?page=<?=$pn+1?>&q=<?=urlencode($search)?>&status=<?=urlencode($status)?>" class="bp-page-btn">›</a><?php endif?>
</div>
<?php endif?>
</div>
<?php include 'partials/footer.php';?>
