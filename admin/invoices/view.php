<?php
require_once '../../includes/config.php';
require_once INC_PATH.'/modules/billing.php';
$admin=Auth::requireAdmin(); $company=DB::setting('company_name','Billing Portal');
$inv_id=(int)get_param('id'); $page_title='Invoice';
$inv=DB::row("SELECT i.*,c.first_name,c.last_name,c.email,c.company AS cc FROM invoices i JOIN clients c ON c.id=i.client_id WHERE i.id=?",'i',[$inv_id]);
if(!$inv) redirect(BASE_URL.'/admin/invoices.php');
$items=DB::rows("SELECT * FROM invoice_items WHERE invoice_id=?",'i',[$inv_id]);
$transactions=DB::rows("SELECT * FROM transactions WHERE invoice_id=? ORDER BY id DESC",'i',[$inv_id]);

if(is_post()&&csrf_verify()){
    $action=post('action');
    if($action==='mark_paid'){
        $gw=post('gateway','manual'); $ref=trim(post('reference'));
        Billing::markPaid($inv_id,$gw,$ref);
        redirect_with_flash("view.php?id={$inv_id}",'success','Invoice marked as paid.');
    }
    if($action==='add_credit'&&post('credit_amount')>0){
        Billing::addCredit($inv['client_id'],(float)post('credit_amount'),'Manual credit by admin','admin');
        redirect_with_flash("view.php?id={$inv_id}",'success','Credit added to client account.');
    }
    if($action==='cancel'){
        DB::execute("UPDATE invoices SET status='cancelled' WHERE id=?",'i',[$inv_id]);
        redirect_with_flash("view.php?id={$inv_id}",'success','Invoice cancelled.');
    }
}
$inv=DB::row("SELECT i.*,c.first_name,c.last_name,c.email,c.company AS cc FROM invoices i JOIN clients c ON c.id=i.client_id WHERE i.id=?",'i',[$inv_id]);
include '../partials/header.php';
?>
<div class="bp-content">
<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
  <a href="<?=BASE_URL?>/admin/invoices.php" class="bp-btn bp-btn-outline bp-btn-sm">← Back</a>
  <h1 class="bp-page-title" style="margin:0">Invoice #<?=h($inv['invoice_number'])?></h1>
  <?php $sb=['paid'=>'success','unpaid'=>'warning','overdue'=>'danger','cancelled'=>'muted'];?>
  <span class="bp-badge bp-badge-<?=$sb[$inv['status']]??'muted'?>"><?=$inv['status']?></span>
  <a href="print.php?id=<?=$inv_id?>" class="bp-btn bp-btn-outline bp-btn-sm ms-auto" target="_blank">🖨 PDF</a>
</div>
<?=flash_html()?>
<div class="row g-4">
  <div class="col-lg-8">
    <div class="bp-card">
      <div style="background:linear-gradient(135deg,#0f172a,#1e3a5f);padding:28px;display:flex;justify-content:space-between;align-items:center">
        <div><div style="color:rgba(255,255,255,.5);font-size:11px;font-weight:700;text-transform:uppercase">Invoice</div><div style="color:#fff;font-size:22px;font-weight:800">#<?=h($inv['invoice_number'])?></div></div>
        <div style="text-align:right"><div style="color:rgba(255,255,255,.5);font-size:11px">Total</div><div style="color:#fff;font-size:26px;font-weight:900"><?=format_currency($inv['total'],$inv['currency'])?></div></div>
      </div>
      <div class="bp-card-body">
        <div class="row g-3 mb-4">
          <div class="col-6">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:8px">Client</div>
            <a href="<?=BASE_URL?>/admin/clients/view.php?id=<?=$inv['client_id']?>" style="text-decoration:none">
              <div style="font-weight:700"><?=h($inv['first_name'].' '.$inv['last_name'])?></div>
              <div style="font-size:13px;color:#3b82f6"><?=h($inv['email'])?></div>
              <?php if($inv['cc']):?><div style="font-size:13px;color:#64748b"><?=h($inv['cc'])?></div><?php endif?>
            </a>
          </div>
          <div class="col-6">
            <div class="row g-2">
              <div class="col-6"><div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:4px">Issue Date</div><div style="font-size:13px;font-weight:600"><?=format_date($inv['created_at'])?></div></div>
              <div class="col-6"><div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:4px">Due Date</div><div style="font-size:13px;font-weight:600"><?=format_date($inv['due_date'])?></div></div>
              <?php if($inv['paid_date']):?><div class="col-12"><div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:4px">Paid</div><div style="font-size:13px;font-weight:600;color:#10b981"><?=format_date($inv['paid_date']).' via '.h($inv['payment_method']??'')?></div></div><?php endif?>
            </div>
          </div>
        </div>
        <table class="bp-table" style="margin-bottom:16px">
          <thead><tr><th>Description</th><th style="text-align:center">Qty</th><th style="text-align:right">Unit Price</th><th style="text-align:right">Total</th></tr></thead>
          <tbody>
            <?php foreach($items as $item):?>
            <tr><td><?=h($item['description'])?></td><td style="text-align:center"><?=$item['quantity']?></td><td style="text-align:right"><?=format_currency($item['unit_price'],$inv['currency'])?></td><td style="text-align:right;font-weight:600"><?=format_currency($item['total'],$inv['currency'])?></td></tr>
            <?php endforeach?>
          </tbody>
        </table>
        <div style="display:flex;justify-content:flex-end"><div style="width:260px">
          <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:13px;color:#64748b"><span>Subtotal</span><span><?=format_currency($inv['subtotal'],$inv['currency'])?></span></div>
          <?php if($inv['tax_amount']>0):?><div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:13px;color:#64748b"><span>Tax</span><span><?=format_currency($inv['tax_amount'],$inv['currency'])?></span></div><?php endif?>
          <?php if($inv['discount_amount']>0):?><div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:13px;color:#10b981"><span>Discount</span><span>-<?=format_currency($inv['discount_amount'],$inv['currency'])?></span></div><?php endif?>
          <div style="display:flex;justify-content:space-between;padding:14px 0;font-size:18px;font-weight:800;color:#0f172a"><span>Total</span><span><?=format_currency($inv['total'],$inv['currency'])?></span></div>
        </div></div>
      </div>
    </div>
    <!-- Transactions -->
    <?php if($transactions):?>
    <div class="bp-card" style="margin-top:16px">
      <div class="bp-card-header"><h3 class="bp-card-title">Transactions</h3></div>
      <table class="bp-table"><thead><tr><th>Type</th><th>Amount</th><th>Gateway</th><th>Status</th><th>Date</th></tr></thead><tbody>
      <?php foreach($transactions as $t):$sb=['completed'=>'success','pending'=>'warning','failed'=>'danger'];?>
      <tr><td style="text-transform:capitalize"><?=h($t['type'])?></td><td style="font-weight:600"><?=format_currency($t['amount'],$t['currency']??$inv['currency'])?></td><td><?=h($t['gateway']??'—')?><?php if($t['gateway_ref']):?><br><span style="font-size:11px;color:#94a3b8"><?=h($t['gateway_ref'])?></span><?php endif?></td>
      <td><span class="bp-badge bp-badge-<?=$sb[$t['status']]??'muted'?>"><?=$t['status']?></span></td><td style="font-size:13px;color:#64748b"><?=time_ago($t['created_at'])?></td></tr>
      <?php endforeach?></tbody></table>
    </div>
    <?php endif?>
  </div>
  <!-- Admin Actions -->
  <div class="col-lg-4">
    <?php if(in_array($inv['status'],['unpaid','overdue'])):?>
    <div class="bp-card">
      <div class="bp-card-header"><h3 class="bp-card-title">⚡ Actions</h3></div>
      <div class="bp-card-body">
        <form method="POST" style="margin-bottom:12px">
          <?=csrf_input()?><input type="hidden" name="action" value="mark_paid">
          <div class="bp-form-group"><label class="bp-label">Gateway</label>
            <select name="gateway" class="bp-select"><option value="manual">Manual</option><option value="bank_transfer">Bank Transfer</option><option value="crypto">Crypto</option><option value="paystack">Paystack</option></select></div>
          <div class="bp-form-group"><label class="bp-label">Reference</label><input type="text" name="reference" class="bp-input" placeholder="Optional"></div>
          <button type="submit" class="bp-btn bp-btn-success" style="width:100%;justify-content:center">✓ Mark as Paid</button>
        </form>
        <form method="POST" onsubmit="return confirm('Cancel this invoice?')">
          <?=csrf_input()?><input type="hidden" name="action" value="cancel">
          <button type="submit" class="bp-btn bp-btn-outline" style="width:100%;justify-content:center;color:#ef4444;border-color:#fecdd3">✕ Cancel Invoice</button>
        </form>
      </div>
    </div>
    <?php endif?>
    <div class="bp-card" style="margin-top:<?=in_array($inv['status'],['unpaid','overdue'])?'12':'0'?>px">
      <div class="bp-card-header"><h3 class="bp-card-title">💳 Add Credit to Client</h3></div>
      <div class="bp-card-body">
        <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="add_credit">
          <div class="bp-form-group"><label class="bp-label">Amount (<?=DB::setting('base_currency','NGN')?>)</label><input type="number" name="credit_amount" class="bp-input" step="100" min="0" placeholder="0.00" required></div>
          <button type="submit" class="bp-btn bp-btn-primary" style="width:100%;justify-content:center">Add Credit</button>
        </form>
      </div>
    </div>
  </div>
</div>
</div>
<?php include '../partials/footer.php';?>
