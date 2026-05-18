<?php
require_once '../includes/config.php';
$client=Auth::requireClient(); $company=DB::setting('company_name','Billing Portal'); $page_title='My Tickets';
$tickets=DB::rows("SELECT * FROM tickets WHERE client_id=? ORDER BY FIELD(status,'client_reply','open','answered','closed'),updated_at DESC",'i',[$client['id']]);
include 'partials/header.php';
?>
<div class="bp-content">
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="bp-page-title" style="margin:0">My Support Tickets</h1>
  <a href="tickets/open.php" class="bp-btn bp-btn-primary">➕ Open Ticket</a>
</div>
<?=flash_html()?>
<div class="bp-card">
<?php if($tickets):?>
<table class="bp-table"><thead><tr><th>Ticket</th><th>Priority</th><th>Status</th><th>Last Update</th><th></th></tr></thead><tbody>
<?php foreach($tickets as $t):
  $pb=['urgent'=>'danger','high'=>'warning','medium'=>'info','low'=>'muted'];
  $sb=['open'=>'danger','client_reply'=>'warning','answered'=>'success','closed'=>'muted'];
?>
<tr>
  <td><a href="tickets/view.php?id=<?=$t['id']?>" style="text-decoration:none"><div style="font-weight:600;color:#0f172a">#<?=h($t['ticket_number'])?></div><div style="font-size:13px;color:#64748b"><?=h($t['subject'])?></div></a></td>
  <td><span class="bp-badge bp-badge-<?=$pb[$t['priority']]??'muted'?>"><?=$t['priority']?></span></td>
  <td><span class="bp-badge bp-badge-<?=$sb[$t['status']]??'muted'?>"><?=str_replace('_',' ',$t['status'])?></span></td>
  <td style="font-size:13px;color:#64748b"><?=time_ago($t['updated_at'])?></td>
  <td><a href="tickets/view.php?id=<?=$t['id']?>" class="bp-btn bp-btn-outline bp-btn-sm"><?=$t['status']==='answered'?'View Reply':'View'?></a></td>
</tr>
<?php endforeach?>
</tbody></table>
<?php else:?><div class="bp-empty"><div class="bp-empty-icon">🎫</div><div class="bp-empty-title">No tickets yet</div><div class="bp-empty-text">Need help? Open a support ticket.</div><a href="tickets/open.php" class="bp-btn bp-btn-primary bp-btn-sm" style="margin-top:12px">Open Ticket</a></div><?php endif?>
</div>
</div>
<?php include 'partials/footer.php';?>
