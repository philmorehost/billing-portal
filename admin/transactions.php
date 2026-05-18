<?php
require_once '../includes/config.php';
$admin=Auth::requireAdmin(); $company=DB::setting('company_name','Billing Portal'); $page_title='Transactions';
$search=trim(get_param('q')); $gw=get_param('gateway'); $pn=max(1,(int)get_param('page',1)); $pp=25;
$where=['1=1']; $params=[]; $types='';
if($search){$where[]="(c.email LIKE ? OR c.first_name LIKE ? OR t.gateway_ref LIKE ?)";$s="%{$search}%";$params=array_merge($params,[$s,$s,$s]);$types.='sss';}
if($gw){$where[]="t.gateway=?";$params[]=$gw;$types.='s';}
$ws=implode(' AND ',$where);
$total=(int)DB::value("SELECT COUNT(*) FROM transactions t JOIN clients c ON c.id=t.client_id WHERE {$ws}",$types,$params);
$pg=paginate($total,$pp,$pn);
$txns=DB::rows("SELECT t.*,c.first_name,c.last_name,c.email,i.invoice_number FROM transactions t JOIN clients c ON c.id=t.client_id LEFT JOIN invoices i ON i.id=t.invoice_id WHERE {$ws} ORDER BY t.id DESC LIMIT {$pp} OFFSET {$pg['offset']}",$types,$params);
include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title">Transactions</h1>
<?=flash_html()?>
<div class="bp-card mb-4"><div class="bp-card-body" style="padding:16px 22px">
<form method="GET" class="d-flex flex-wrap gap-3 align-items-end">
  <div style="flex:1;min-width:200px"><label class="bp-label">Search</label><input type="text" name="q" class="bp-input" placeholder="Email, reference…" value="<?=h($search)?>"></div>
  <div><label class="bp-label">Gateway</label>
    <select name="gateway" class="bp-select">
      <option value="">All Gateways</option>
      <?php foreach(['paystack','bank_transfer','crypto','credit','manual'] as $g):?>
      <option value="<?=$g?>" <?=$gw===$g?'selected':''?>><?=ucfirst(str_replace('_',' ',$g))?></option>
      <?php endforeach?>
    </select>
  </div>
  <div class="d-flex gap-2"><button type="submit" class="bp-btn bp-btn-primary">Filter</button><?php if($search||$gw):?><a href="transactions.php" class="bp-btn bp-btn-outline">Clear</a><?php endif?></div>
</form>
</div></div>
<div class="bp-card">
<?php if($txns):?>
<table class="bp-table"><thead><tr><th>#</th><th>Client</th><th>Type</th><th>Amount</th><th>Gateway / Ref</th><th>Invoice</th><th>Status</th><th>Date</th></tr></thead><tbody>
<?php foreach($txns as $t):$sb=['completed'=>'success','pending'=>'warning','failed'=>'danger','refunded'=>'info'];?>
<tr>
  <td style="color:#94a3b8;font-size:12px"><?=$t['id']?></td>
  <td><div style="font-weight:600"><?=h($t['first_name'].' '.$t['last_name'])?></div><div style="font-size:12px;color:#64748b"><?=h($t['email'])?></div></td>
  <td><span class="bp-badge bp-badge-<?=$t['type']==='payment'?'success':($t['type']==='credit'?'info':'muted')?>" style="text-transform:capitalize"><?=$t['type']?></span></td>
  <td style="font-weight:700"><?=format_currency($t['amount'],$t['currency']??'NGN')?></td>
  <td><div style="font-size:13px"><?=h(str_replace('_',' ',ucfirst($t['gateway']??'')))?></div><?php if($t['gateway_ref']):?><div style="font-size:11px;color:#94a3b8;word-break:break-all"><?=h($t['gateway_ref'])?></div><?php endif?></td>
  <td><?=$t['invoice_number']?'<a href="invoices/view.php?id='.$t['invoice_id'].'" style="color:#3b82f6;text-decoration:none;font-weight:600">#'.h($t['invoice_number']).'</a>':'<span style="color:#94a3b8">—</span>'?></td>
  <td><span class="bp-badge bp-badge-<?=$sb[$t['status']]??'muted'?>"><?=$t['status']?></span></td>
  <td style="font-size:12px;color:#64748b"><?=time_ago($t['created_at'])?></td>
</tr>
<?php endforeach?>
</tbody></table>
<?php else:?><div class="bp-empty"><div class="bp-empty-icon">💳</div><div class="bp-empty-title">No transactions found</div></div><?php endif?>
</div>
<?php if($pg['total_pages']>1):?>
<div class="bp-pagination">
  <?php if($pg['has_prev']):?><a href="?page=<?=$pn-1?>&q=<?=urlencode($search)?>" class="bp-page-btn">‹</a><?php endif?>
  <?php for($i=max(1,$pn-2);$i<=min($pg['total_pages'],$pn+2);$i++):?>
  <a href="?page=<?=$i?>&q=<?=urlencode($search)?>" class="bp-page-btn <?=$i===$pn?'active':''?>"><?=$i?></a>
  <?php endfor?>
  <?php if($pg['has_next']):?><a href="?page=<?=$pn+1?>&q=<?=urlencode($search)?>" class="bp-page-btn">›</a><?php endif?>
</div>
<?php endif?>
</div>
<?php include 'partials/footer.php';?>
