<?php
require_once '../includes/config.php';
$admin=Auth::requireAdmin(); $company=DB::setting('company_name','Billing Portal'); $page_title='Invoices';
$search=trim(get_param('q')); $status=get_param('status'); $pn=max(1,(int)get_param('page',1)); $pp=20;
$where=['1=1']; $params=[]; $types='';
if($search){$where[]="(i.invoice_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ?)";$s="%{$search}%";$params=array_merge($params,[$s,$s,$s,$s]);$types.='ssss';}
if($status){$where[]="i.status=?";$params[]=$status;$types.='s';}
$ws=implode(' AND ',$where);
$total=(int)DB::value("SELECT COUNT(*) FROM invoices i JOIN clients c ON c.id=i.client_id WHERE {$ws}",$types,$params);
$pg=paginate($total,$pp,$pn);
$invoices=DB::rows("SELECT i.*,c.first_name,c.last_name,c.email FROM invoices i JOIN clients c ON c.id=i.client_id WHERE {$ws} ORDER BY i.id DESC LIMIT {$pp} OFFSET {$pg['offset']}",$types,$params);
include 'partials/header.php';
?>
<div class="bp-content">
<div class="d-flex align-items-center justify-content-between mb-4">
  <div><h1 class="bp-page-title" style="margin:0">Invoices</h1><p class="bp-page-sub" style="margin:4px 0 0"><?=number_format($total)?> total</p></div>
  <a href="invoices/create.php" class="bp-btn bp-btn-primary">➕ Create Invoice</a>
</div>
<?=flash_html()?>
<div class="bp-card mb-4"><div class="bp-card-body" style="padding:16px 22px">
  <form method="GET" class="d-flex flex-wrap gap-3 align-items-end">
    <div style="flex:1;min-width:200px"><label class="bp-label">Search</label><input type="text" name="q" class="bp-input" placeholder="Invoice #, client name, email…" value="<?=h($search)?>"></div>
    <div><label class="bp-label">Status</label>
      <select name="status" class="bp-select">
        <option value="">All</option>
        <?php foreach(['unpaid','paid','overdue','cancelled','refunded'] as $s):?>
        <option value="<?=$s?>" <?=$status===$s?'selected':''?>><?=ucfirst($s)?></option>
        <?php endforeach?>
      </select>
    </div>
    <div class="d-flex gap-2"><button type="submit" class="bp-btn bp-btn-primary">Filter</button><?php if($search||$status):?><a href="invoices.php" class="bp-btn bp-btn-outline">Clear</a><?php endif?></div>
  </form>
</div></div>
<div class="bp-card">
<?php if($invoices):?>
<table class="bp-table"><thead><tr><th>Invoice</th><th>Client</th><th>Amount</th><th>Due Date</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach($invoices as $inv):$sb=['paid'=>'success','unpaid'=>'warning','overdue'=>'danger','cancelled'=>'muted','refunded'=>'info'];?>
<tr>
  <td><div style="font-weight:600">#<?=h($inv['invoice_number'])?></div><div style="font-size:12px;color:#64748b"><?=format_date($inv['created_at'])?></div></td>
  <td><a href="clients/view.php?id=<?=$inv['client_id']?>" style="text-decoration:none"><div style="font-weight:600;color:#0f172a"><?=h($inv['first_name'].' '.$inv['last_name'])?></div><div style="font-size:12px;color:#64748b"><?=h($inv['email'])?></div></a></td>
  <td style="font-weight:700"><?=format_currency($inv['total'],$inv['currency'])?></td>
  <td style="font-size:13px;color:<?=$inv['status']==='overdue'?'#ef4444':'#64748b'?>"><?=format_date($inv['due_date'])?></td>
  <td><span class="bp-badge bp-badge-<?=$sb[$inv['status']]??'muted'?>"><?=$inv['status']?></span></td>
  <td><div class="d-flex gap-1">
    <a href="invoices/view.php?id=<?=$inv['id']?>" class="bp-btn bp-btn-outline bp-btn-sm">View</a>
    <a href="invoices/print.php?id=<?=$inv['id']?>" class="bp-btn bp-btn-outline bp-btn-sm" target="_blank">PDF</a>
    <?php if(in_array($inv['status'],['unpaid','overdue'])):?>
    <a href="invoices/mark-paid.php?id=<?=$inv['id']?>" class="bp-btn bp-btn-success bp-btn-sm" onclick="return confirm('Mark as paid?')">✓ Paid</a>
    <?php endif?>
  </div></td>
</tr>
<?php endforeach?>
</tbody></table>
<?php else:?><div class="bp-empty"><div class="bp-empty-icon">🧾</div><div class="bp-empty-title">No invoices found</div></div><?php endif?>
</div>
<?php if($pg['total_pages']>1):?>
<div class="bp-pagination">
  <?php if($pg['has_prev']):?><a href="?page=<?=$pn-1?>&q=<?=urlencode($search)?>&status=<?=urlencode($status)?>" class="bp-page-btn">‹</a><?php endif?>
  <?php for($i=max(1,$pn-2);$i<=min($pg['total_pages'],$pn+2);$i++):?>
  <a href="?page=<?=$i?>&q=<?=urlencode($search)?>&status=<?=urlencode($status)?>" class="bp-page-btn <?=$i===$pn?'active':''?>"><?=$i?></a>
  <?php endfor?>
  <?php if($pg['has_next']):?><a href="?page=<?=$pn+1?>&q=<?=urlencode($search)?>&status=<?=urlencode($status)?>" class="bp-page-btn">›</a><?php endif?>
</div>
<?php endif?>
</div>
<?php include 'partials/footer.php';?>
