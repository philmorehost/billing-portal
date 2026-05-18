<?php
require_once '../includes/config.php';
$admin=Auth::requireAdmin(); $company=DB::setting('company_name','Billing Portal'); $page_title='Activity Log';
$pn=max(1,(int)get_param('page',1)); $pp=50;
$total=(int)DB::value("SELECT COUNT(*) FROM activity_log");
$pg=paginate($total,$pp,$pn);
$logs=DB::rows("SELECT * FROM activity_log ORDER BY id DESC LIMIT {$pp} OFFSET {$pg['offset']}");
include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title">Activity Log</h1>
<div class="bp-card">
<?php if($logs):?>
<table class="bp-table"><thead><tr><th>Actor</th><th>Action</th><th>Description</th><th>IP</th><th>Time</th></tr></thead><tbody>
<?php foreach($logs as $l):$sc=['admin'=>'info','client'=>'success','system'=>'muted','cron'=>'warning'];?>
<tr>
  <td><span class="bp-badge bp-badge-<?=$sc[$l['actor_type']]??'muted'?>"><?=$l['actor_type']?></span><?php if($l['actor_id']):?><div style="font-size:11px;color:#94a3b8">#<?=$l['actor_id']?></div><?php endif?></td>
  <td style="font-weight:600;font-size:13px"><?=h($l['action'])?></td>
  <td style="font-size:13px;color:#374151;max-width:300px"><?=h($l['description']??'')?></td>
  <td style="font-size:12px;color:#94a3b8;font-family:monospace"><?=h($l['ip_address']??'')?></td>
  <td style="font-size:12px;color:#64748b;white-space:nowrap"><?=time_ago($l['created_at'])?></td>
</tr>
<?php endforeach?>
</tbody></table>
<?php else:?><div class="bp-empty"><div class="bp-empty-icon">📜</div><div class="bp-empty-title">No activity yet</div></div><?php endif?>
</div>
<?php if($pg['total_pages']>1):?>
<div class="bp-pagination">
  <?php if($pg['has_prev']):?><a href="?page=<?=$pn-1?>" class="bp-page-btn">‹</a><?php endif?>
  <?php for($i=max(1,$pn-2);$i<=min($pg['total_pages'],$pn+2);$i++):?><a href="?page=<?=$i?>" class="bp-page-btn <?=$i===$pn?'active':''?>"><?=$i?></a><?php endfor?>
  <?php if($pg['has_next']):?><a href="?page=<?=$pn+1?>" class="bp-page-btn">›</a><?php endif?>
</div>
<?php endif?>
</div>
<?php include 'partials/footer.php';?>
