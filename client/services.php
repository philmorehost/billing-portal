<?php
require_once '../includes/config.php';
$client=Auth::requireClient(); $company=DB::setting('company_name','Billing Portal');
$currency=DB::setting('base_currency','NGN'); $page_title='My Services';
$services=DB::rows("SELECT s.*,p.name AS pname,p.type AS ptype FROM services s JOIN products p ON p.id=s.product_id WHERE s.client_id=? ORDER BY s.id DESC",'i',[$client['id']]);
include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title">My Services</h1>
<?=flash_html()?>
<div class="bp-card">
<?php if($services):?>
<table class="bp-table"><thead><tr><th>Service</th><th>Billing Cycle</th><th>Price</th><th>Next Due</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach($services as $s):$sb=['active'=>'success','suspended'=>'danger','pending'=>'warning','terminated'=>'muted'];?>
<tr>
  <td><div style="font-weight:600"><?=h($s['pname'])?></div><?php if($s['domain']):?><div style="font-size:12px;color:#64748b">🌐 <?=h($s['domain'])?></div><?php endif?></td>
  <td style="font-size:13px;text-transform:capitalize"><?=str_replace('_',' ',$s['billing_cycle'])?></td>
  <td style="font-weight:600"><?=format_currency($s['price'],$currency)?></td>
  <td style="font-size:13px;color:<?=($s['next_due_date']&&strtotime($s['next_due_date'])<time())?'#ef4444':'#64748b'?>"><?=$s['next_due_date']?format_date($s['next_due_date']):'—'?></td>
  <td><span class="bp-badge bp-badge-<?=$sb[$s['status']]??'muted'?>"><?=$s['status']?></span></td>
  <td><div class="d-flex gap-1">
    <?php if($s['status']==='suspended'):?>
    <span class="bp-btn bp-btn-outline bp-btn-sm" style="color:#ef4444;cursor:default">Suspended</span>
    <?php elseif($s['status']==='active'&&$s['ptype']==='hosting'):?>
    <a href="<?=BASE_URL?>/client/wordpress.php?service_id=<?=$s['id']?>" class="bp-btn bp-btn-outline bp-btn-sm">WordPress</a>
    <?php endif?>
    <a href="<?=BASE_URL?>/client/tickets/open.php?service_id=<?=$s['id']?>" class="bp-btn bp-btn-outline bp-btn-sm">Support</a>
  </div></td>
</tr>
<?php endforeach?>
</tbody></table>
<?php else:?><div class="bp-empty"><div class="bp-empty-icon">🖥</div><div class="bp-empty-title">No services yet</div><a href="order.php" class="bp-btn bp-btn-primary bp-btn-sm" style="margin-top:12px">Order Now</a></div><?php endif?>
</div>
</div>
<?php include 'partials/footer.php';?>
