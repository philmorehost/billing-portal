<?php
require_once '../includes/config.php';
$admin=Auth::requireAdmin(); $company=DB::setting('company_name','Billing Portal'); $page_title='Support Tickets';
$search=trim(get_param('q')); $status=get_param('status',''); $pn=max(1,(int)get_param('page',1)); $pp=20;
$where=['1=1']; $params=[]; $types='';
if($search){$where[]="(t.ticket_number LIKE ? OR t.subject LIKE ? OR c.email LIKE ?)";$s="%{$search}%";$params=array_merge($params,[$s,$s,$s]);$types.='sss';}
if($status){$where[]="t.status=?";$params[]=$status;$types.='s';}
$ws=implode(' AND ',$where);
$total=(int)DB::value("SELECT COUNT(*) FROM tickets t JOIN clients c ON c.id=t.client_id WHERE {$ws}",$types,$params);
$pg=paginate($total,$pp,$pn);
$tickets=DB::rows("SELECT t.*,c.first_name,c.last_name,c.email FROM tickets t JOIN clients c ON c.id=t.client_id WHERE {$ws} ORDER BY FIELD(t.status,'client_reply','open','answered','closed'), t.updated_at DESC LIMIT {$pp} OFFSET {$pg['offset']}",$types,$params);
include 'partials/header.php';
?>
<div class="bp-content">
<div class="d-flex align-items-center justify-content-between mb-4">
  <div><h1 class="bp-page-title" style="margin:0">Support Tickets</h1><p class="bp-page-sub" style="margin:4px 0 0"><?=number_format($total)?> total</p></div>
</div>
<?=flash_html()?>
<div class="d-flex gap-2 mb-4 flex-wrap">
  <?php foreach([''=> 'All','open'=>'🔴 Open','client_reply'=>'💬 Client Reply','answered'=>'✓ Answered','closed'=>'Closed'] as $k=>$label):?>
  <a href="?status=<?=$k?>" class="bp-btn bp-btn-<?=$status===$k&&($k!==''||!$status)?'primary':'outline'?> bp-btn-sm"><?=$label?></a>
  <?php endforeach?>
</div>
<div class="bp-card mb-4"><div class="bp-card-body" style="padding:14px 20px">
  <form method="GET" class="d-flex gap-3">
    <input type="text" name="q" class="bp-input" placeholder="Search ticket # or subject…" value="<?=h($search)?>" style="max-width:340px">
    <input type="hidden" name="status" value="<?=h($status)?>">
    <button type="submit" class="bp-btn bp-btn-primary">Search</button>
    <?php if($search):?><a href="?status=<?=h($status)?>" class="bp-btn bp-btn-outline">Clear</a><?php endif?>
  </form>
</div></div>
<div class="bp-card">
<?php if($tickets):?>
<table class="bp-table"><thead><tr><th>Ticket</th><th>Client</th><th>Priority</th><th>Status</th><th>Updated</th><th>Actions</th></tr></thead><tbody>
<?php foreach($tickets as $t):
  $pb=['urgent'=>'danger','high'=>'warning','medium'=>'info','low'=>'muted'];
  $sb=['open'=>'danger','client_reply'=>'warning','answered'=>'success','closed'=>'muted'];
?>
<tr>
  <td><a href="tickets/view.php?id=<?=$t['id']?>" style="text-decoration:none"><div style="font-weight:600;color:#0f172a">#<?=h($t['ticket_number'])?></div><div style="font-size:12px;color:#64748b;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=h($t['subject'])?></div></a></td>
  <td><div style="font-weight:600;font-size:13px"><?=h($t['first_name'].' '.$t['last_name'])?></div><div style="font-size:12px;color:#64748b"><?=h($t['email'])?></div></td>
  <td><span class="bp-badge bp-badge-<?=$pb[$t['priority']]??'muted'?>"><?=$t['priority']?></span></td>
  <td><span class="bp-badge bp-badge-<?=$sb[$t['status']]??'muted'?>"><?=str_replace('_',' ',$t['status'])?></span></td>
  <td style="font-size:12px;color:#64748b"><?=time_ago($t['updated_at'])?></td>
  <td><a href="tickets/view.php?id=<?=$t['id']?>" class="bp-btn bp-btn-outline bp-btn-sm"><?=$t['status']==='client_reply'?'⚡ Reply':'View'?></a></td>
</tr>
<?php endforeach?>
</tbody></table>
<?php else:?><div class="bp-empty"><div class="bp-empty-icon">🎫</div><div class="bp-empty-title">No tickets found</div></div><?php endif?>
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
