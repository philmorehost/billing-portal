<?php
require_once '../includes/config.php';
$admin=Auth::requireAdmin(); $company=DB::setting('company_name','Billing Portal'); $page_title='Affiliates';
$currency=DB::setting('base_currency','NGN');

if(is_post()&&csrf_verify()){
    $action=post('action');
    if($action==='approve_payout'){
        $aid=(int)post('affiliate_id'); $amount=(float)post('payout_amount');
        DB::execute("UPDATE affiliates SET balance=balance-?,total_paid=total_paid+? WHERE id=? AND balance>=?",'ddid',[$amount,$amount,$aid,$amount]);
        DB::execute("UPDATE affiliate_referrals SET status='paid' WHERE affiliate_id=? AND status='approved'",'i',[$aid]);
        $aff=DB::row("SELECT a.*,c.email,c.first_name FROM affiliates a JOIN clients c ON c.id=a.client_id WHERE a.id=?",'i',[$aid]);
        if($aff) Mailer::send($aff['email'],$aff['first_name'],'Affiliate Payout Processed',"<p>Dear {$aff['first_name']},</p><p>Your affiliate payout of ".format_currency($amount,$currency)." has been processed. Thank you!</p>");
        log_activity('affiliate_payout',"Payout of {$amount} to affiliate #{$aid}",'admin',$admin['id']);
        redirect_with_flash('affiliates.php','success','Payout processed.');
    }
    if($action==='update_commission'){
        $aid=(int)post('affiliate_id');
        DB::execute("UPDATE affiliates SET commission_type=?,commission_value=? WHERE id=?",'sdi',[post('type'),( float)post('value'),$aid]);
        redirect_with_flash('affiliates.php','success','Commission updated.');
    }
}

$affiliates=DB::rows("SELECT a.*,c.first_name,c.last_name,c.email,(SELECT COUNT(*) FROM affiliate_referrals ar WHERE ar.affiliate_id=a.id) AS total_refs,(SELECT COUNT(*) FROM affiliate_referrals ar WHERE ar.affiliate_id=a.id AND ar.status='pending') AS pending_refs FROM affiliates a JOIN clients c ON c.id=a.client_id ORDER BY a.total_earned DESC");
include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title">Affiliate System</h1>
<?=flash_html()?>

<!-- Stats row -->
<div class="stat-cards" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px">
  <?php
  $total_affs=DB::value("SELECT COUNT(*) FROM affiliates WHERE status='active'")??0;
  $total_earned=DB::value("SELECT COALESCE(SUM(total_earned),0) FROM affiliates")??0;
  $total_paid=DB::value("SELECT COALESCE(SUM(total_paid),0) FROM affiliates")??0;
  $pending_balance=DB::value("SELECT COALESCE(SUM(balance),0) FROM affiliates")??0;
  ?>
  <div class="stat-card"><div class="stat-card-icon blue">🤝</div><div class="stat-card-value"><?=number_format($total_affs)?></div><div class="stat-card-label">Active Affiliates</div></div>
  <div class="stat-card"><div class="stat-card-icon green">💰</div><div class="stat-card-value"><?=format_currency($total_earned,$currency)?></div><div class="stat-card-label">Total Earned</div></div>
  <div class="stat-card"><div class="stat-card-icon cyan">✓</div><div class="stat-card-value"><?=format_currency($total_paid,$currency)?></div><div class="stat-card-label">Total Paid Out</div></div>
  <div class="stat-card"><div class="stat-card-icon amber">⏳</div><div class="stat-card-value"><?=format_currency($pending_balance,$currency)?></div><div class="stat-card-label">Pending Balance</div></div>
</div>

<div class="bp-card">
<?php if($affiliates): ?>
<table class="bp-table">
  <thead><tr><th>Affiliate</th><th>Referral Code</th><th>Commission</th><th>Refs</th><th>Balance</th><th>Total Earned</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach($affiliates as $a): ?>
  <tr>
    <td><div style="font-weight:600"><?=h($a['first_name'].' '.$a['last_name'])?></div><div style="font-size:12px;color:#64748b"><?=h($a['email'])?></div></td>
    <td><code style="background:#f1f5f9;padding:3px 8px;border-radius:5px;font-size:13px"><?=h($a['referral_code'])?></code></td>
    <td style="font-size:13px"><?=$a['commission_value']?($a['commission_type']==='percentage'?$a['commission_value'].'%':format_currency($a['commission_value'],$currency)):'—'?></td>
    <td><div style="font-weight:600"><?=$a['total_refs']?></div><?php if($a['pending_refs']>0):?><div style="font-size:11px;color:#f59e0b"><?=$a['pending_refs']?> pending</div><?php endif?></td>
    <td style="font-weight:700;color:<?=$a['balance']>0?'#10b981':'#64748b'?>"><?=format_currency($a['balance'],$currency)?></td>
    <td><?=format_currency($a['total_earned'],$currency)?></td>
    <td>
      <div class="d-flex gap-1">
        <?php if($a['balance']>0): ?>
        <button class="bp-btn bp-btn-success bp-btn-sm" onclick="openPayout(<?=$a['id']?>,<?=$a['balance']?>,'<?=h($a['first_name'])?>')">💸 Pay</button>
        <?php endif?>
        <button class="bp-btn bp-btn-outline bp-btn-sm" onclick="openCommission(<?=$a['id']?>,'<?=$a['commission_type']?>',<?=$a['commission_value']?>)">Edit</button>
      </div>
    </td>
  </tr>
  <?php endforeach?>
  </tbody>
</table>
<?php else:?>
<div class="bp-empty"><div class="bp-empty-icon">🤝</div><div class="bp-empty-title">No affiliates yet</div><div class="bp-empty-text">Clients can sign up for the affiliate program from their portal.</div></div>
<?php endif?>
</div>

<!-- Payout modal -->
<div id="modal-payout" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;display:flex;align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:#fff;border-radius:16px;padding:32px;max-width:420px;width:90%">
    <h3 style="margin:0 0 20px;font-size:18px;font-weight:700">Process Payout</h3>
    <form method="POST" id="payout-form"><?=csrf_input()?><input type="hidden" name="action" value="approve_payout"><input type="hidden" name="affiliate_id" id="payout-aid">
      <div class="bp-form-group"><label class="bp-label">Affiliate</label><div id="payout-name" style="font-weight:600;padding:10px 13px;background:#f8fafc;border-radius:9px"></div></div>
      <div class="bp-form-group"><label class="bp-label">Payout Amount</label><input type="number" name="payout_amount" id="payout-amount" class="bp-input" step="0.01" min="0" required></div>
      <div class="d-flex gap-2 mt-3"><button type="submit" class="bp-btn bp-btn-success" style="flex:1;justify-content:center">✓ Process Payout</button><button type="button" class="bp-btn bp-btn-outline" onclick="document.getElementById('modal-payout').style.display='none'">Cancel</button></div>
    </form>
  </div>
</div>
<!-- Commission modal -->
<div id="modal-commission" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;display:flex;align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:#fff;border-radius:16px;padding:32px;max-width:360px;width:90%">
    <h3 style="margin:0 0 20px;font-size:18px;font-weight:700">Edit Commission</h3>
    <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="update_commission"><input type="hidden" name="affiliate_id" id="comm-aid">
      <div class="bp-form-group"><label class="bp-label">Type</label>
        <select name="type" id="comm-type" class="bp-select"><option value="percentage">Percentage (%)</option><option value="fixed">Fixed Amount</option></select></div>
      <div class="bp-form-group"><label class="bp-label">Value</label><input type="number" name="value" id="comm-val" class="bp-input" step="0.01" min="0" required></div>
      <div class="d-flex gap-2 mt-3"><button type="submit" class="bp-btn bp-btn-primary" style="flex:1;justify-content:center">Save</button><button type="button" class="bp-btn bp-btn-outline" onclick="document.getElementById('modal-commission').style.display='none'">Cancel</button></div>
    </form>
  </div>
</div>
</div>
<script>
function openPayout(id,bal,name){const m=document.getElementById('modal-payout');document.getElementById('payout-aid').value=id;document.getElementById('payout-amount').value=bal;document.getElementById('payout-name').textContent=name;m.style.display='flex';}
function openCommission(id,type,val){const m=document.getElementById('modal-commission');document.getElementById('comm-aid').value=id;document.getElementById('comm-type').value=type;document.getElementById('comm-val').value=val;m.style.display='flex';}
</script>
<?php include 'partials/footer.php';?>
