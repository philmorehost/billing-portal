<?php
require_once '../includes/config.php';
require_once INC_PATH.'/modules/billing.php';
$client   = Auth::requireClient();
$company  = DB::setting('company_name','Billing Portal');
$currency = DB::setting('base_currency','NGN');
$page_title='Add Funds';

$error=''; $success='';

if(is_post()&&csrf_verify()){
    $amount=(float)post('amount');
    $gateway=post('gateway','bank_transfer');

    if($amount<500){$error='Minimum top-up is '.format_currency(500,$currency).'.';}
    else {
        // Create a special "add funds" invoice
        $inv_id=Billing::createInvoice([
            'client_id'=>$client['id'],
            'currency' =>$currency,
            'items'    =>[['description'=>'Account Credit Top-Up','unit_price'=>$amount,'quantity'=>1]],
            'notes'    =>'Add funds to account balance',
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
            DB::execute("INSERT INTO transactions (client_id, invoice_id, type, amount, currency, gateway, gateway_ref, description, status) VALUES (?, ?, 'credit', ?, ?, ?, ?, 'Add funds - awaiting approval', 'pending')", 'iidsss', [$client['id'], $inv_id, $amount, $currency, $gateway, trim(post('reference'))]);
            $ae=DB::setting('company_email');
            if($ae) Mailer::send($ae,'Admin',"Add Funds Request - {$client['first_name']} {$client['last_name']}","<p>Client requested to add ".format_currency($amount,$currency)." via {$gateway}. Review in admin panel.</p>");
            $success='Top-up request submitted. Funds will be added after verification.';
        }
    }
}

$ps_on=DB::setting('paystack_enabled')==='1';
$bt_on=DB::setting('bank_transfer_enabled')==='1';
$cr_on=DB::setting('crypto_enabled')==='1';
$recent=DB::rows("SELECT * FROM transactions WHERE client_id=? AND type IN ('credit','debit') ORDER BY id DESC LIMIT 10",'i',[$client['id']]);
include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title">Add Funds</h1>
<?php if($error):?><div class="alert-custom alert-danger mb-3"><span>✕</span> <?=h($error)?></div><?php endif?>
<?php if($success):?><div class="alert-custom alert-success mb-3"><span>✓</span> <?=h($success)?></div><?php endif?>
<?=flash_html()?>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="bp-card">
      <div class="bp-card-header"><h3 class="bp-card-title">Top Up Account Credit</h3></div>
      <div class="bp-card-body">
        <!-- Current balance -->
        <div style="background:linear-gradient(135deg,#0f172a,#1e3a5f);border-radius:12px;padding:20px 24px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center">
          <div><div style="color:rgba(255,255,255,.5);font-size:12px;font-weight:600;text-transform:uppercase">Current Balance</div>
          <div style="color:#fff;font-size:28px;font-weight:900"><?=format_currency($client['credit_balance'],$currency)?></div></div>
          <div style="font-size:32px">💳</div>
        </div>

        <form method="POST" id="funds-form">
          <?=csrf_input()?>
          <!-- Quick amounts -->
          <div class="bp-form-group">
            <label class="bp-label">Select Amount</label>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:10px">
              <?php foreach([5000,10000,25000,50000] as $amt):?>
              <button type="button" onclick="setAmount(<?=$amt?>)" class="bp-btn bp-btn-outline" style="justify-content:center;padding:10px 8px;font-size:13px"><?=format_currency($amt,$currency)?></button>
              <?php endforeach?>
            </div>
            <input type="number" name="amount" id="amount-input" class="bp-input" placeholder="Or enter custom amount" min="500" step="100" required>
            <div class="bp-input-hint">Minimum: <?=format_currency(500,$currency)?></div>
          </div>

          <!-- Payment method -->
          <div class="bp-form-group">
            <label class="bp-label">Payment Method</label>
            <div style="display:flex;flex-direction:column;gap:8px" id="method-list">
              <?php if($ps_on):?>
              <label style="display:flex;align-items:center;gap:12px;padding:14px 16px;border:1.5px solid #e2e8f0;border-radius:10px;cursor:pointer;transition:border-color .2s" class="method-opt" onclick="selectMethod('paystack',this)">
                <input type="radio" name="gateway" value="paystack" style="display:none">
                <span style="font-size:22px">💳</span>
                <div><div style="font-weight:600;font-size:14px">Debit/Credit Card (Paystack)</div><div style="font-size:12px;color:#64748b">Instant. Pay in NGN or USD.</div></div>
              </label>
              <?php endif?>
              <?php if($bt_on):?>
              <label style="display:flex;align-items:center;gap:12px;padding:14px 16px;border:1.5px solid #e2e8f0;border-radius:10px;cursor:pointer" class="method-opt" onclick="selectMethod('bank_transfer',this)">
                <input type="radio" name="gateway" value="bank_transfer" style="display:none">
                <span style="font-size:22px">🏦</span>
                <div><div style="font-weight:600;font-size:14px">Bank Transfer</div><div style="font-size:12px;color:#64748b">Manual review within 24 hours.</div></div>
              </label>
              <?php endif?>
              <?php if($cr_on):?>
              <label style="display:flex;align-items:center;gap:12px;padding:14px 16px;border:1.5px solid #e2e8f0;border-radius:10px;cursor:pointer" class="method-opt" onclick="selectMethod('plisio',this)">
                <input type="radio" name="gateway" value="plisio" style="display:none">
                <span style="font-size:22px">₿</span>
                <div><div style="font-weight:600;font-size:14px">Cryptocurrency (Plisio)</div><div style="font-size:12px;color:#64748b">Instant automated coin payments.</div></div>
              </label>
              <?php endif?>
            </div>
          </div>

          <!-- Paystack currency selector (hidden by default) -->
          <div id="paystack-opts" style="display:none;margin-bottom:16px">
            <label class="bp-label">Pay In</label>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
              <label style="display:flex;align-items:center;gap:8px;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:9px;cursor:pointer;font-size:13px">
                <input type="radio" name="pay_currency" value="NGN" checked> NGN (Nigerian Naira)
              </label>
              <label style="display:flex;align-items:center;gap:8px;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:9px;cursor:pointer;font-size:13px">
                <input type="radio" name="pay_currency" value="USD"> USD (US Dollar)
              </label>
            </div>
          </div>

          <!-- Manual payment ref (hidden by default) -->
          <div id="manual-ref" style="display:none;margin-bottom:16px">
            <label class="bp-label">Transaction Reference *</label>
            <input type="text" name="reference" class="bp-input" placeholder="Transaction ID or Hash">
          </div>

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


          <button type="submit" class="bp-btn bp-btn-primary" style="width:100%;justify-content:center;padding:13px;font-size:15px">Proceed →</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Credit history -->
  <div class="col-lg-5">
    <div class="bp-card">
      <div class="bp-card-header"><h3 class="bp-card-title">Credit History</h3></div>
      <?php if($recent):?>
      <table class="bp-table">
        <thead><tr><th>Type</th><th>Amount</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach($recent as $t):$sc=['credit'=>'success','debit'=>'danger'];?>
          <tr>
            <td><div style="font-weight:600"><?=h($t['description'])?></div><div style="font-size:12px;color:#64748b"><?=h($t['gateway']??'')?></div></td>
            <td style="font-weight:700;color:<?=$t['type']==='credit'?'#10b981':'#ef4444'?>"><?=$t['type']==='credit'?'+':'-'?><?=format_currency($t['amount'],$t['currency']??$currency)?></td>
            <td style="font-size:12px;color:#64748b"><?=time_ago($t['created_at'])?></td>
          </tr>
          <?php endforeach?>
        </tbody>
      </table>
      <?php else:?><div class="bp-empty"><div class="bp-empty-icon">📊</div><div class="bp-empty-title">No transactions yet</div></div><?php endif?>
    </div>
  </div>
</div>
</div>
<script>
function setAmount(v){document.getElementById('amount-input').value=v;}
let selMethod='';
function selectMethod(m,el){
  selMethod=m;
  document.querySelectorAll('.method-opt').forEach(e=>e.style.borderColor='#e2e8f0');
  el.style.borderColor='#3b82f6';
  el.querySelector('input').checked=true;
  document.getElementById('paystack-opts').style.display=m==='paystack'?'block':'none';
  document.getElementById('manual-ref').style.display=(m!=='paystack' && m!=='plisio')?'block':'none';
  ['d-bank_transfer'].forEach(id=>{const e=document.getElementById(id);if(e)e.style.display='none';});
  const det=document.getElementById('d-'+m);if(det)det.style.display='block';
}
<?php if($ps_on):?>document.querySelector('.method-opt').click();<?php endif?>
</script>
<?php include 'partials/footer.php';?>
