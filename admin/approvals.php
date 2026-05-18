<?php
require_once '../includes/config.php';
require_once INC_PATH.'/modules/billing.php';
$admin=Auth::requireAdmin(); $company=DB::setting('company_name','Billing Portal'); $page_title='Payment Approvals';

if(is_post()&&csrf_verify()){
    $action=post('action'); $txn_id=(int)post('txn_id');
    $txn=DB::row("SELECT * FROM transactions WHERE id=?",'i',[$txn_id]);
    if($txn){
        if($action==='approve'){
            DB::execute("UPDATE transactions SET status='completed' WHERE id=?",'i',[$txn_id]);
            if($txn['invoice_id']) Billing::markPaid($txn['invoice_id'],$txn['gateway'],$txn['gateway_ref']);
            elseif($txn['type']==='credit') Billing::addCredit($txn['client_id'],$txn['amount'],'Manual top-up approved',$txn['gateway']);
            log_activity('payment_approved',"Transaction #{$txn_id} approved",'admin',$admin['id']);
            redirect_with_flash('approvals.php','success','Payment approved and invoice marked as paid.');
        }
        if($action==='reject'){
            DB::execute("UPDATE transactions SET status='failed' WHERE id=?",'i',[$txn_id]);
            $client=DB::row("SELECT email,first_name FROM clients WHERE id=?",'i',[$txn['client_id']]);
            if($client) Mailer::send($client['email'],$client['first_name'],'Payment Rejected',"<p>Dear {$client['first_name']},</p><p>Your ".ucfirst($txn['gateway'])." payment submission could not be verified. Please contact support or try another payment method.</p>");
            redirect_with_flash('approvals.php','danger','Payment rejected and client notified.');
        }
    }
}

$pending=DB::rows("SELECT t.*,c.first_name,c.last_name,c.email,i.invoice_number,i.total AS inv_total FROM transactions t JOIN clients c ON c.id=t.client_id LEFT JOIN invoices i ON i.id=t.invoice_id WHERE t.status='pending' AND t.gateway IN ('bank_transfer','crypto') ORDER BY t.id ASC");
include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title">Payment Approvals</h1>
<p class="bp-page-sub"><?=count($pending)?> pending approval(s) require your review.</p>
<?=flash_html()?>
<?php if($pending):?>
<div class="row g-4">
<?php foreach($pending as $t):?>
<div class="col-lg-6">
  <div class="bp-card" style="border-left:4px solid <?=$t['gateway']==='crypto'?'#f59e0b':'#3b82f6'?>">
    <div class="bp-card-body">
      <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
          <span class="bp-badge bp-badge-<?=$t['gateway']==='crypto'?'warning':'info'?>" style="margin-bottom:8px"><?=ucfirst(str_replace('_',' ',$t['gateway']))?></span>
          <div style="font-size:18px;font-weight:800;color:#0f172a"><?=format_currency($t['amount'],$t['currency']??'NGN')?></div>
          <div style="font-size:12px;color:#64748b;margin-top:2px"><?=time_ago($t['created_at'])?></div>
        </div>
        <?php if($t['invoice_number']):?>
        <div style="text-align:right"><div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#94a3b8">Invoice</div><div style="font-weight:700">#<?=h($t['invoice_number'])?></div></div>
        <?php endif?>
      </div>
      <div style="background:#f8fafc;border-radius:10px;padding:14px;margin-bottom:16px">
        <div class="row g-2">
          <div class="col-6"><div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-bottom:3px">Client</div><div style="font-size:13px;font-weight:600"><?=h($t['first_name'].' '.$t['last_name'])?></div><div style="font-size:12px;color:#64748b"><?=h($t['email'])?></div></div>
          <div class="col-6"><div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-bottom:3px">Reference</div><div style="font-size:13px;font-weight:600;word-break:break-all"><?=h($t['gateway_ref']??'—')?></div></div>
          <?php if($t['description']&&strlen($t['description'])>20):?>
          <div class="col-12"><div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-bottom:3px">Notes</div><div style="font-size:12px;color:#374151"><?=h($t['description'])?></div></div>
          <?php endif?>
        </div>
      </div>
      <div class="d-flex gap-2">
        <form method="POST" style="flex:1"><?=csrf_input()?>
          <input type="hidden" name="txn_id" value="<?=$t['id']?>">
          <input type="hidden" name="action" value="approve">
          <button type="submit" class="bp-btn bp-btn-success" style="width:100%;justify-content:center" onclick="return confirm('Approve this payment?')">✓ Approve</button>
        </form>
        <form method="POST" style="flex:1"><?=csrf_input()?>
          <input type="hidden" name="txn_id" value="<?=$t['id']?>">
          <input type="hidden" name="action" value="reject">
          <button type="submit" class="bp-btn bp-btn-danger" style="width:100%;justify-content:center" onclick="return confirm('Reject and notify client?')">✕ Reject</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endforeach?>
</div>
<?php else:?>
<div class="bp-card"><div class="bp-empty"><div class="bp-empty-icon">✅</div><div class="bp-empty-title">No pending approvals</div><div class="bp-empty-text">All manual payments have been reviewed.</div></div></div>
<?php endif?>
</div>
<?php include 'partials/footer.php';?>
