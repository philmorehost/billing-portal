<?php
require_once '../../includes/config.php';
require_once INC_PATH.'/modules/billing.php';
$client  = Auth::requireClient();
$company = DB::setting('company_name','Billing Portal');
$inv_id  = (int)get_param('id');
$page_title='Invoice';

$inv=DB::row("SELECT i.*,c.first_name,c.last_name,c.email,c.company AS cc,c.address1 FROM invoices i JOIN clients c ON c.id=i.client_id WHERE i.id=? AND i.client_id=?",'ii',[$inv_id,$client['id']]);
if(!$inv) redirect(BASE_URL.'/client/invoices.php');

$items=DB::rows("SELECT * FROM invoice_items WHERE invoice_id=?",'i',[$inv_id]);
$currency=$inv['currency'];
$tax_name=DB::setting('tax_name','VAT');
$error=''; $success='';

if(is_post()&&csrf_verify()){
    $action=post('action');
    if($action==='pay_credit'){
        $r=Billing::payInvoiceWithCredit($inv_id,$client['id']);
        if($r['success']) redirect_with_flash("view.php?id={$inv_id}",'success','Invoice paid with credit balance!');
        else $error=$r['error'];
    }
    if($action==='pay_paystack'){
        $cur=post('pay_currency','NGN');
        $r=Billing::paystackInitialize($inv_id,$cur);
        if($r['success']) redirect($r['auth_url']);
        else $error=$r['error'];
    }
    if($action==='submit_manual'){
        $gw=post('gateway'); $ref=trim(post('reference')); $notes=trim(post('payment_notes'));
        DB::execute("INSERT INTO transactions (client_id,invoice_id,type,amount,currency,gateway,gateway_ref,description,status) VALUES (?,'payment',?,?,?,?,?,'Awaiting approval','pending')",'iiidssss',[$client['id'],$inv_id,$inv['total'],$currency,$gw,$ref,$notes]);
        $ae=DB::setting('company_email');
        if($ae) Mailer::send($ae,'Admin',"Manual Payment - Invoice #{$inv['invoice_number']}","<p>{$client['first_name']} submitted {$gw} payment (ref:{$ref}) for Invoice #{$inv['invoice_number']}.</p>");
        $success='Payment submitted! We will verify within 24 hours.';
    }
}
$inv=DB::row("SELECT i.*,c.first_name,c.last_name,c.email,c.company AS cc,c.address1 FROM invoices i JOIN clients c ON c.id=i.client_id WHERE i.id=? AND i.client_id=?",'ii',[$inv_id,$client['id']]);
$ps_on=DB::setting('paystack_enabled')==='1';
$bt_on=DB::setting('bank_transfer_enabled')==='1';
$cr_on=DB::setting('crypto_enabled')==='1';
$credit=(float)$client['credit_balance'];
$can_credit=$credit>=$inv['total'];
$can_pay=in_array($inv['status'],['unpaid','overdue']);
include dirname(dirname(__FILE__)).'/partials/header.php';
?>
<div class="bp-content">
<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
  <a href="<?=BASE_URL?>/client/invoices.php" class="bp-btn bp-btn-outline bp-btn-sm">← Back</a>
  <h1 class="bp-page-title" style="margin:0">Invoice #<?=h($inv['invoice_number'])?></h1>
  <?php $sb=['paid'=>'success','unpaid'=>'warning','overdue'=>'danger','cancelled'=>'muted'];?>
  <span class="bp-badge bp-badge-<?=$sb[$inv['status']]??'muted'?>"><?=$inv['status']?></span>
  <a href="print.php?id=<?=$inv_id?>" class="bp-btn bp-btn-outline bp-btn-sm ms-auto" target="_blank">🖨 Print PDF</a>
</div>
<?php if($error):?><div class="alert-custom alert-danger mb-3"><span>✕</span> <?=h($error)?></div><?php endif?>
<?php if($success):?><div class="alert-custom alert-success mb-3"><span>✓</span> <?=h($success)?></div><?php endif?>
<?=flash_html()?>
<div class="row g-4">
  <div class="col-lg-8">
    <div class="bp-card">
      <div style="background:linear-gradient(135deg,#0f172a,#1e3a5f);padding:28px;display:flex;justify-content:space-between;align-items:center">
        <div><div style="color:rgba(255,255,255,.5);font-size:11px;font-weight:700;text-transform:uppercase">Invoice</div><div style="color:#fff;font-size:22px;font-weight:800">#<?=h($inv['invoice_number'])?></div></div>
        <div style="text-align:right"><div style="color:rgba(255,255,255,.5);font-size:11px;margin-bottom:4px">Amount Due</div><div style="color:#fff;font-size:26px;font-weight:900"><?=format_currency($inv['total'],$currency)?></div></div>
      </div>
      <div class="bp-card-body">
        <div class="row g-3 mb-4">
          <div class="col-6"><div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:8px">Bill To</div>
            <div style="font-weight:700"><?=h($inv['first_name'].' '.$inv['last_name'])?></div>
            <div style="font-size:13px;color:#64748b"><?=h($inv['email'])?></div>
            <?php if($inv['cc']):?><div style="font-size:13px;color:#64748b"><?=h($inv['cc'])?></div><?php endif?>
          </div>
          <div class="col-6">
            <div class="row g-2">
              <div class="col-6"><div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:4px">Issue Date</div><div style="font-size:13px;font-weight:600"><?=format_date($inv['created_at'])?></div></div>
              <div class="col-6"><div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:4px">Due Date</div><div style="font-size:13px;font-weight:600;color:<?=$inv['status']==='overdue'?'#ef4444':'inherit'?>"><?=format_date($inv['due_date'])?></div></div>
              <?php if($inv['paid_date']):?><div class="col-12"><div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:4px">Paid</div><div style="font-size:13px;font-weight:600;color:#10b981"><?=format_date($inv['paid_date'])?></div></div><?php endif?>
            </div>
          </div>
        </div>
        <table class="bp-table" style="margin-bottom:16px">
          <thead><tr><th>Description</th><th style="text-align:center">Qty</th><th style="text-align:right">Unit Price</th><th style="text-align:right">Total</th></tr></thead>
          <tbody>
            <?php foreach($items as $item):?>
            <tr><td><?=h($item['description'])?></td><td style="text-align:center"><?=$item['quantity']?></td><td style="text-align:right"><?=format_currency($item['unit_price'],$currency)?></td><td style="text-align:right;font-weight:600"><?=format_currency($item['total'],$currency)?></td></tr>
            <?php endforeach?>
          </tbody>
        </table>
        <div style="display:flex;justify-content:flex-end"><div style="width:260px">
          <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:13px;color:#64748b"><span>Subtotal</span><span><?=format_currency($inv['subtotal'],$currency)?></span></div>
          <?php if($inv['tax_amount']>0):?><div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:13px;color:#64748b"><span><?=h($tax_name)?></span><span><?=format_currency($inv['tax_amount'],$currency)?></span></div><?php endif?>
          <?php if($inv['discount_amount']>0):?><div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:13px;color:#10b981"><span>Discount</span><span>-<?=format_currency($inv['discount_amount'],$currency)?></span></div><?php endif?>
          <div style="display:flex;justify-content:space-between;padding:14px 0;font-size:18px;font-weight:800;color:#0f172a"><span>Total</span><span><?=format_currency($inv['total'],$currency)?></span></div>
        </div></div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <?php if($can_pay):?>
    <div class="bp-card">
      <div class="bp-card-header"><h3 class="bp-card-title">💳 Pay Invoice</h3></div>
      <div class="bp-card-body">
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:14px 16px;margin-bottom:16px">
          <div style="font-size:12px;font-weight:600;color:#166534;text-transform:uppercase">Account Credit</div>
          <div style="font-size:20px;font-weight:800;color:#166534"><?=format_currency($credit,DB::setting('base_currency','NGN'))?></div>
        </div>
        <?php if($can_credit):?>
        <form method="POST" style="margin-bottom:12px"><?=csrf_input()?><input type="hidden" name="action" value="pay_credit">
        <button type="submit" class="bp-btn bp-btn-success" style="width:100%;justify-content:center">✓ Pay with Credit Balance</button></form>
        <?php endif?>
        <?php if($ps_on):?>
        <div style="background:#f8fafc;border-radius:10px;padding:16px;margin-bottom:12px">
          <div style="font-size:13px;font-weight:600;margin-bottom:10px">Pay with Card (Paystack)</div>
          <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="pay_paystack">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px">
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer"><input type="radio" name="pay_currency" value="NGN" checked> NGN</label>
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer"><input type="radio" name="pay_currency" value="USD"> USD (~$<?=number_format(Billing::convertToUSD($inv['total']),2)?>)</label>
          </div>
          <button type="submit" class="bp-btn bp-btn-accent" style="width:100%;justify-content:center">Pay with Paystack →</button></form>
        </div>
        <?php endif?>
        <?php if($bt_on||$cr_on):?>
        <div style="background:#f8fafc;border-radius:10px;padding:16px">
          <div style="font-size:13px;font-weight:600;margin-bottom:12px">Manual Payment</div>
          <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="submit_manual">
          <div class="bp-form-group"><label class="bp-label">Method</label>
            <select name="gateway" class="bp-select" onchange="showD(this.value)">
              <?php if($bt_on):?><option value="bank_transfer">Bank Transfer</option><?php endif?>
              <?php if($cr_on):?><option value="crypto">Cryptocurrency</option><?php endif?>
            </select>
          </div>
          <?php if($bt_on):?><div id="d-bank_transfer" style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:12px;font-size:12px;margin-bottom:12px;white-space:pre-line"><?=h(DB::setting('bank_transfer_details'))?></div><?php endif?>
          <?php if($cr_on):?><div id="d-crypto" style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:12px;font-size:12px;margin-bottom:12px;white-space:pre-line;display:none"><?=h(DB::setting('crypto_details'))?></div><?php endif?>
          <div class="bp-form-group"><label class="bp-label">Transaction Reference *</label><input type="text" name="reference" class="bp-input" placeholder="Transaction ID / Hash" required></div>
          <div class="bp-form-group"><label class="bp-label">Notes</label><textarea name="payment_notes" class="bp-textarea" rows="2"></textarea></div>
          <button type="submit" class="bp-btn bp-btn-primary" style="width:100%;justify-content:center">Submit Payment Proof</button></form>
        </div>
        <?php endif?>
      </div>
    </div>
    <?php elseif($inv['status']==='paid'):?>
    <div class="bp-card"><div class="bp-card-body" style="text-align:center;padding:32px">
      <div style="font-size:48px;margin-bottom:12px">✅</div>
      <div style="font-size:16px;font-weight:700;color:#166534;margin-bottom:4px">Invoice Paid</div>
      <div style="font-size:13px;color:#64748b"><?=format_date($inv['paid_date'])?></div>
      <?php if($inv['payment_method']):?><div style="font-size:12px;color:#94a3b8;margin-top:4px">via <?=h($inv['payment_method'])?></div><?php endif?>
    </div></div>
    <?php endif?>
    <?php if($can_pay&&!$can_credit):?>
    <div class="bp-card" style="margin-top:12px"><div class="bp-card-body" style="text-align:center">
      <div style="font-size:13px;color:#64748b;margin-bottom:10px">Need more credit?</div>
      <a href="<?=BASE_URL?>/client/add-funds.php" class="bp-btn bp-btn-outline" style="width:100%;justify-content:center">💳 Add Funds</a>
    </div></div>
    <?php endif?>
  </div>
</div>
</div>
<script>function showD(g){document.querySelectorAll('[id^="d-"]').forEach(e=>e.style.display='none');const el=document.getElementById('d-'+g);if(el)el.style.display='block';}</script>
<?php include dirname(dirname(__FILE__)).'/partials/footer.php';?>
