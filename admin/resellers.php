<?php
require_once '../includes/config.php';
$admin=Auth::requireAdmin(); $company=DB::setting('company_name','Billing Portal'); $page_title='Resellers';
$currency=DB::setting('base_currency','NGN');
if(is_post()&&csrf_verify()){
    $action=post('action'); $rid=(int)post('reseller_id');
    if($action==='update_status') DB::execute("UPDATE resellers SET status=? WHERE id=?",'si',[post('status'),$rid]);
    if($action==='add_balance'&&(float)post('amount')>0) DB::execute("UPDATE resellers SET balance=balance+? WHERE id=?",'di',[(float)post('amount'),$rid]);
    redirect_with_flash('resellers.php','success','Reseller updated.');
}
$resellers=DB::rows("SELECT r.*,c.first_name,c.last_name,c.email FROM resellers r JOIN clients c ON c.id=r.client_id ORDER BY r.id DESC");
include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title">Resellers</h1>
<?=flash_html()?>
<div class="bp-card">
<?php if($resellers):?>
<table class="bp-table"><thead><tr><th>Reseller</th><th>Company</th><th>Custom Domain</th><th>Balance</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach($resellers as $r):$sb=['active'=>'success','suspended'=>'danger','pending'=>'warning'];?>
<tr>
  <td><a href="clients/view.php?id=<?=$r['client_id']?>" style="text-decoration:none"><div style="font-weight:600;color:#0f172a"><?=h($r['first_name'].' '.$r['last_name'])?></div><div style="font-size:12px;color:#64748b"><?=h($r['email'])?></div></a></td>
  <td style="font-weight:600"><?=h($r['company_name'])?></td>
  <td style="font-size:13px;font-family:monospace"><?=$r['custom_domain']?h($r['custom_domain']):'<span style="color:#94a3b8">Not set</span>'?></td>
  <td style="font-weight:700;color:<?=$r['balance']>0?'#10b981':'#64748b'?>"><?=format_currency($r['balance'],$currency)?></td>
  <td><span class="bp-badge bp-badge-<?=$sb[$r['status']]??'muted'?>"><?=$r['status']?></span></td>
  <td>
    <form method="POST" style="display:inline"><?=csrf_input()?><input type="hidden" name="reseller_id" value="<?=$r['id']?>"><input type="hidden" name="action" value="update_status">
      <select name="status" class="bp-select" style="padding:5px 8px;font-size:12px" onchange="this.form.submit()">
        <?php foreach(['active','suspended','pending'] as $s):?><option value="<?=$s?>" <?=$r['status']===$s?'selected':''?>><?=ucfirst($s)?></option><?php endforeach?>
      </select>
    </form>
    <button class="bp-btn bp-btn-outline bp-btn-sm" onclick="document.getElementById('ab-<?=$r['id']?>').style.display='block'">Add Funds</button>
    <div id="ab-<?=$r['id']?>" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:9999;display:none;align-items:center;justify-content:center">
      <div style="background:#fff;border-radius:16px;padding:28px;width:320px">
        <h4 style="margin:0 0 16px">Add Balance — <?=h($r['company_name'])?></h4>
        <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="add_balance"><input type="hidden" name="reseller_id" value="<?=$r['id']?>">
          <div class="bp-form-group"><input type="number" name="amount" class="bp-input" step="100" min="100" placeholder="Amount" required></div>
          <div class="d-flex gap-2"><button type="submit" class="bp-btn bp-btn-success">Add</button><button type="button" class="bp-btn bp-btn-outline" onclick="this.closest('[id^=ab]').style.display='none'">Cancel</button></div>
        </form>
      </div>
    </div>
  </td>
</tr>
<?php endforeach?>
</tbody></table>
<?php else:?><div class="bp-empty"><div class="bp-empty-icon">🏪</div><div class="bp-empty-title">No resellers yet</div></div><?php endif?>
</div>
</div>
<?php include 'partials/footer.php';?>
