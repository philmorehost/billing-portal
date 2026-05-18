<?php
require_once '../includes/config.php';
require_once INC_PATH.'/modules/reseller.php';
require_once INC_PATH.'/modules/billing.php';
if(empty($_SESSION['reseller_id'])) redirect(BASE_URL.'/reseller/login.php');
$reseller_id=$_SESSION['reseller_id'];
$reseller=DB::row("SELECT * FROM resellers WHERE id=?",'i',[$reseller_id]);
$reseller_client=DB::row("SELECT * FROM clients WHERE id=?",'i',[$reseller['client_id']]);
$currency=DB::setting('base_currency','NGN'); $company=DB::setting('company_name','Billing Portal');
$page_title='Top Up Balance'; $error=''; $success='';

if(is_post()&&csrf_verify()){
    $amount=(float)post('amount'); $gateway=post('gateway','bank_transfer');
    if($amount<1000){$error='Minimum top-up is '.format_currency(1000,$currency).'.';}
    else{
        // Create invoice for this reseller's client account
        $inv_id=Billing::createInvoice([
            'client_id'=>$reseller['client_id'],
            'currency' =>$currency,
            'items'    =>[['description'=>'Reseller Balance Top-Up','unit_price'=>$amount,'quantity'=>1]],
            'notes'    =>'Reseller pre-paid balance top-up',
        ]);
        if($gateway==='paystack'){
            $r=Billing::paystackInitialize($inv_id,post('pay_currency','NGN'));
            if($r['success']) redirect($r['auth_url']);
            else $error=$r['error'];
        } elseif($gateway==='plisio'){
            $r=Billing::plisioInitialize($inv_id);
            if($r['success']) redirect($r['auth_url']);
            else $error=$r['error'];
        } else {
            DB::execute("INSERT INTO transactions (client_id,invoice_id,type,amount,currency,gateway,gateway_ref,description,status) VALUES (?,'credit',?,?,?,?,?,'Reseller top-up - awaiting approval','pending')",'iiidssss',[$reseller['client_id'],$inv_id,$amount,$currency,$gateway,trim(post('reference')),'']);
            $ae=DB::setting('company_email');
            if($ae) Mailer::send($ae,'Admin',"Reseller Top-Up Request","<p>Reseller #{$reseller_id} submitted a top-up of ".format_currency($amount,$currency)." via {$gateway}. Please review in admin panel.</p>");
            $success='Top-up request submitted. Balance will be credited after verification.';
        }
    }
}

$ps_on=DB::setting('paystack_enabled')==='1';
$bt_on=DB::setting('bank_transfer_enabled')==='1';
$cr_on=DB::setting('crypto_enabled')==='1';
include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title">Top Up Reseller Balance</h1>
<?php if($error):?><div class="alert-custom alert-danger mb-3"><span>✕</span> <?=h($error)?></div><?php endif?>
<?php if($success):?><div class="alert-custom alert-success mb-3"><span>✓</span> <?=h($success)?></div><?php endif?>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="bp-card"><div class="bp-card-body">
      <!-- Current balance -->
      <div style="background:linear-gradient(135deg,#0f172a,#1e3a5f);border-radius:12px;padding:22px 26px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center">
        <div>
          <div style="color:rgba(255,255,255,.5);font-size:12px;font-weight:600;text-transform:uppercase;margin-bottom:4px">Current Balance</div>
          <div style="color:#fff;font-size:32px;font-weight:900"><?=format_currency($reseller['balance'],$currency)?></div>
        </div>
        <div style="font-size:40px">💰</div>
      </div>

      <form method="POST" id="topup-form">
        <?=csrf_input()?>
        <div class="bp-form-group">
          <label class="bp-label">Amount to Add</label>
          <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:10px">
            <?php foreach([10000,25000,50000,100000] as $amt):?>
            <button type="button" onclick="document.getElementById('amount-in').value=<?=$amt?>" class="bp-btn bp-btn-outline" style="justify-content:center;padding:10px 6px;font-size:12px">
              <?=format_currency($amt,$currency,'short')?>
            </button>
            <?php endforeach?>
          </div>
          <input type="number" name="amount" id="amount-in" class="bp-input" placeholder="Enter amount" min="1000" step="500" required>
          <div class="bp-input-hint">Minimum: <?=format_currency(1000,$currency)?></div>
        </div>

        <div class="bp-form-group">
          <label class="bp-label">Payment Method</label>
          <div style="display:flex;flex-direction:column;gap:8px">
            <?php if($ps_on):?>
            <label style="display:flex;align-items:center;gap:12px;padding:13px 16px;border:1.5px solid #e2e8f0;border-radius:10px;cursor:pointer" class="mopt" onclick="selMethod('paystack',this)">
              <input type="radio" name="gateway" value="paystack" style="display:none">
              <span style="font-size:20px">💳</span>
              <div><div style="font-weight:600;font-size:13px">Card Payment (Paystack)</div><div style="font-size:11px;color:#64748b">Instant. Pay in NGN or USD.</div></div>
            </label>
            <?php endif?>
            <?php if($bt_on):?>
            <label style="display:flex;align-items:center;gap:12px;padding:13px 16px;border:1.5px solid #e2e8f0;border-radius:10px;cursor:pointer" class="mopt" onclick="selMethod('bank_transfer',this)">
              <input type="radio" name="gateway" value="bank_transfer" style="display:none">
              <span style="font-size:20px">🏦</span>
              <div><div style="font-weight:600;font-size:13px">Bank Transfer</div><div style="font-size:11px;color:#64748b">Manual review, credited within 24h.</div></div>
            </label>
            <?php endif?>
            <?php if($cr_on):?>
            <label style="display:flex;align-items:center;gap:12px;padding:13px 16px;border:1.5px solid #e2e8f0;border-radius:10px;cursor:pointer" class="mopt" onclick="selMethod('plisio',this)">
              <input type="radio" name="gateway" value="plisio" style="display:none">
              <span style="font-size:20px">₿</span>
              <div><div style="font-weight:600;font-size:13px">Cryptocurrency (Plisio)</div><div style="font-size:11px;color:#64748b">Instant automated coin payments.</div></div>
            </label>
            <?php endif?>
          </div>
        </div>

        <!-- Paystack currency -->
        <div id="ps-opts" style="display:none;margin-bottom:16px">
          <label class="bp-label">Pay In</label>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
            <label style="display:flex;align-items:center;gap:8px;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:9px;cursor:pointer;font-size:13px"><input type="radio" name="pay_currency" value="NGN" checked> NGN</label>
            <label style="display:flex;align-items:center;gap:8px;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:9px;cursor:pointer;font-size:13px"><input type="radio" name="pay_currency" value="USD"> USD</label>
          </div>
        </div>

        <!-- Manual ref -->
        <div id="manual-ref" style="display:none;margin-bottom:16px">
          <label class="bp-label">Transaction Reference *</label>
          <input type="text" name="reference" class="bp-input" placeholder="Transaction ID or Hash">
        </div>

        <!-- Bank/crypto details -->
        <!-- Bank/crypto details -->
        <?php if($bt_on):?>
        <div id="d-bank_transfer" style="display:none;background:#fff;border:1.5px solid #e2e8f0;border-radius:10px;padding:16px;font-size:13px;margin-bottom:16px">
          <div style="font-weight:700;color:#0f172a;margin-bottom:12px;display:flex;align-items:center;gap:6px">
            <span>🏦</span> Bank Transfer Details
          </div>
          <div style="display:flex;flex-direction:column;gap:8px">
            <?php
            $details = Billing::getBankDetails(0);
            $lines = explode("\n", $details);
            foreach ($lines as $line) {
                $parts = explode(":", $line, 2);
                if (count($parts) === 2 && !empty(trim($parts[0]))) {
                    $label = trim($parts[0]);
                    $val = trim($parts[1]);
                    echo '<div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px dashed #f1f5f9;padding-bottom:6px;">';
                    echo '<span style="color:#64748b;font-weight:500;">' . h($label) . '</span>';
                    if (stripos($label, 'Number') !== false) {
                        echo '<span style="font-family:monospace;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:4px;">' . h($val) . ' <button type="button" onclick="navigator.clipboard.writeText(\''.addslashes($val).'\');alert(\'Account number copied!\')" style="border:none;background:none;cursor:pointer;padding:2px;font-size:12px;" title="Copy">📋</button></span>';
                    } else {
                        echo '<span style="font-weight:600;color:#0f172a;text-align:right;">' . h($val) . '</span>';
                    }
                    echo '</div>';
                } else if (!empty(trim($line))) {
                    echo '<div style="color:#374151;font-weight:500;padding:4px 0;">' . h($line) . '</div>';
                }
            }
            ?>
          </div>
        </div>
        <?php endif?>


        <button type="submit" class="bp-btn bp-btn-primary" style="width:100%;justify-content:center;padding:13px;font-size:15px;margin-top:8px">Proceed →</button>
      </form>
    </div></div>
  </div>

  <div class="col-lg-6">
    <div class="bp-card"><div class="bp-card-body">
      <div style="font-weight:600;font-size:14px;margin-bottom:16px">ℹ Why Top Up?</div>
      <div style="font-size:13px;color:#374151;line-height:1.9">
        <div style="margin-bottom:12px">Your reseller account operates on a <strong>pre-paid balance</strong> system.</div>
        <div style="background:#f8fafc;border-radius:10px;padding:14px;margin-bottom:12px">
          <div style="font-size:12px;font-weight:700;text-transform:uppercase;color:#64748b;margin-bottom:8px">How it works</div>
          <div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:8px"><span>1️⃣</span><span>You top up your balance here.</span></div>
          <div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:8px"><span>2️⃣</span><span>When a client orders a service, the <strong>wholesale cost is instantly deducted</strong> from your balance.</span></div>
          <div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:8px"><span>3️⃣</span><span>Your client pays you the <strong>retail price</strong> (your markup).</span></div>
          <div style="display:flex;align-items:flex-start;gap:10px"><span>4️⃣</span><span>The difference is <strong>your profit</strong>.</span></div>
        </div>
        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px;font-size:12px;color:#92400e">
          ⚠ If your balance is insufficient when a client orders, the order will be held until you top up.
        </div>
      </div>
    </div></div>

    <!-- Balance history -->
    <div class="bp-card" style="margin-top:16px"><div class="bp-card-header"><h3 class="bp-card-title">Recent Activity</h3></div>
      <?php $logs=DB::rows("SELECT * FROM activity_log WHERE actor_type='system' AND actor_id=? AND action IN ('reseller_debit','reseller_credit') ORDER BY id DESC LIMIT 8",'i',[$reseller_id]);?>
      <?php if($logs):?>
      <table class="bp-table"><thead><tr><th>Type</th><th>Description</th><th>Time</th></tr></thead><tbody>
      <?php foreach($logs as $l):$ic=$l['action']==='reseller_credit';?>
      <tr><td><span class="bp-badge bp-badge-<?=$ic?'success':'danger'?>"><?=$ic?'Credit':'Debit'?></span></td>
      <td style="font-size:12px"><?=h(mb_strimwidth($l['description']??'',0,60,'…'))?></td>
      <td style="font-size:11px;color:#94a3b8;white-space:nowrap"><?=time_ago($l['created_at'])?></td></tr>
      <?php endforeach?></tbody></table>
      <?php else:?><div class="bp-empty" style="padding:24px"><div class="bp-empty-title">No activity yet</div></div><?php endif?>
    </div></div>
  </div>
</div>
</div>
<script>
function selMethod(m,el){
  document.querySelectorAll('.mopt').forEach(e=>e.style.borderColor='#e2e8f0');
  el.style.borderColor='#3b82f6'; el.querySelector('input').checked=true;
  document.getElementById('ps-opts').style.display=m==='paystack'?'block':'none';
  document.getElementById('manual-ref').style.display=(m!=='paystack' && m!=='plisio')?'block':'none';
  ['d-bank_transfer'].forEach(id=>{const e=document.getElementById(id);if(e)e.style.display='none';});
  const d=document.getElementById('d-'+m);if(d)d.style.display='block';
}
<?php if($ps_on):?>document.querySelector('.mopt')?.click();<?php elseif($bt_on):?>document.querySelectorAll('.mopt')[0]?.click();<?php endif?>
</script>
<?php include 'partials/footer.php';?>
