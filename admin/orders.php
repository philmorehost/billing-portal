<?php
require_once '../includes/config.php';
$admin=Auth::requireAdmin(); $company=DB::setting('company_name','Billing Portal'); $page_title='Orders';
$pn=max(1,(int)get_param('page',1)); $pp=25;
$total=(int)DB::value("SELECT COUNT(*) FROM orders");
$pg=paginate($total,$pp,$pn);
$orders=DB::rows("SELECT o.*,c.first_name,c.last_name,c.email FROM orders o JOIN clients c ON c.id=o.client_id ORDER BY o.id DESC LIMIT {$pp} OFFSET {$pg['offset']}");
$currency=DB::setting('base_currency','NGN');
include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title">Orders</h1>
<div class="bp-card">
<?php if($orders):?>
<table class="bp-table"><thead><tr><th>Order #</th><th>Client</th><th>Total</th><th>Status</th><th>Gateway</th><th>Date</th></tr></thead><tbody>
<?php foreach($orders as $o):$sb=['active'=>'success','pending'=>'warning','cancelled'=>'muted','fraud'=>'danger'];?>
<tr>
  <td style="font-weight:700;font-family:monospace"><?=h($o['order_number'])?></td>
  <td><a href="clients/view.php?id=<?=$o['client_id']?>" style="text-decoration:none"><div style="font-weight:600;color:#0f172a"><?=h($o['first_name'].' '.$o['last_name'])?></div><div style="font-size:12px;color:#64748b"><?=h($o['email'])?></div></a></td>
  <td style="font-weight:700"><?=format_currency($o['total'],$o['currency']??$currency)?></td>
  <td><span class="bp-badge bp-badge-<?=$sb[$o['status']]??'muted'?>"><?=$o['status']?></span></td>
  <td style="font-size:13px;text-transform:capitalize"><?=str_replace('_',' ',$o['payment_method']??'—')?></td>
  <td style="font-size:12px;color:#64748b"><?=time_ago($o['created_at'])?></td>
</tr>
<?php endforeach?>
</tbody></table>
<?php else:?><div class="bp-empty"><div class="bp-empty-icon">🛒</div><div class="bp-empty-title">No orders yet</div></div><?php endif?>
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
