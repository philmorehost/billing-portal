<?php
require_once '../includes/config.php';
require_once INC_PATH.'/modules/billing.php';
$client=Auth::requireClient();
$company=DB::setting('company_name','Billing Portal');
$currency=DB::setting('base_currency','NGN');
$page_title='Order';
$error='';

$pid=(int)get_param('product_id');
$product=$pid?DB::row("SELECT p.*,pg.name AS group_name FROM products p LEFT JOIN product_groups pg ON pg.id=p.group_id WHERE p.id=? AND p.visible=1",'i',[$pid]):null;

// AJAX coupon check
if(is_post()&&csrf_verify()&&post('action')==='apply_coupon'){
    $code=strtoupper(trim(post('coupon_code')));
    $price=(float)post('price');
    json_response(Billing::validateCoupon($code,$client['id'],$price));
}

// Place order
if(is_post()&&csrf_verify()&&post('action')==='place_order'){
    $pid2=(int)post('product_id');
    $cyc=post('cycle','monthly');
    $domain=trim(post('domain',''));
    $coupon_code=strtoupper(trim(post('coupon_code','')));
    $pay_method=post('payment_method','credit');

    $prod=DB::row("SELECT * FROM products WHERE id=? AND visible=1",'i',[$pid2]);
    if(!$prod) { $error='Product not found.'; }
    else {
        $price_col='price_'.$cyc;
        if (!empty($_SESSION['reseller_domain_id'])) {
            require_once INC_PATH . '/modules/reseller.php';
            $price = Reseller::getRetailPrice((int)$prod['id'], $cyc, (int)$_SESSION['reseller_domain_id']);
        } else {
            $price=(float)($prod[$price_col]??0);
        }
        if(!$price) { $error='Selected billing cycle not available.'; }
        else {
            $tax_enabled=DB::setting('tax_enabled','1')==='1';
            $tax_rate=$tax_enabled?(float)DB::setting('tax_rate',0):0;
            $tax_amt=round($price*($tax_rate/100),2);
            $discount=0; $coupon_id=null;

            if($coupon_code){
                $cv=Billing::validateCoupon($coupon_code,$client['id'],$price);
                if($cv['valid']){$discount=$cv['discount'];$coupon_id=$cv['coupon']['id'];}
                else $error=$cv['error'];
            }

            if(!$error){
                $total=max(0,$price+$tax_amt-$discount);
                if($pay_method==='credit'&&(float)$client['credit_balance']<$total){
                    $error='Insufficient credit balance. Please add funds first.';
                } else {
                    $order_num=generate_order_number();
                    $r=DB::execute("INSERT INTO orders (client_id,order_number,total,currency,status,payment_method,promo_code,promo_discount) VALUES (?,?,?,?,'pending',?,?,?)",'isdsss' . 'd',[$client['id'],$order_num,$total,$currency,$pay_method,$coupon_code,$discount]);
                    $order_id=$r['insert_id'];

                    $next_due=match($cyc){
                        'monthly'       =>date('Y-m-d',strtotime('+1 month')),
                        'quarterly'     =>date('Y-m-d',strtotime('+3 months')),
                        'semi_annually' =>date('Y-m-d',strtotime('+6 months')),
                        'annually'      =>date('Y-m-d',strtotime('+1 year')),
                        'biennially'    =>date('Y-m-d',strtotime('+2 years')),
                        default         =>date('Y-m-d',strtotime('+1 month')),
                    };

                    $reseller_id = !empty($_SESSION['reseller_domain_id']) ? (int)$_SESSION['reseller_domain_id'] : null;
                    DB::execute("INSERT INTO services (client_id,order_id,product_id,reseller_id,domain,billing_cycle,price,next_due_date,registration_date,status) VALUES (?,?,?,?,?,?,?,?,CURDATE(),'pending')",'iiiisssds',[$client['id'],$order_id,$pid2,$reseller_id,$domain,$cyc,$price,$next_due]);
                    $svc_id=DB::lastInsertId();

                    $inv_id=Billing::createInvoice([
                        'client_id'       =>$client['id'],
                        'order_id'        =>$order_id,
                        'currency'        =>$currency,
                        'discount_amount' =>$discount,
                        'items'           =>[['description'=>$prod['name'].' ('.ucfirst(str_replace('_',' ',$cyc)).')','unit_price'=>$price,'quantity'=>1,'service_id'=>$svc_id]],
                    ]);

                    if($coupon_id) Billing::applyCoupon($coupon_id);

                    if($pay_method==='credit'){
                        $pr=Billing::payInvoiceWithCredit($inv_id,$client['id']);
                        if($pr['success']){
                            DB::execute("UPDATE orders SET status='active' WHERE id=?",'i',[$order_id]);
                            log_activity('order_placed',"Order #{$order_num} paid with credit",'client',$client['id']);
                            redirect_with_flash(BASE_URL.'/client/invoices/view.php?id='.$inv_id,'success','Order placed! Your service is being activated.');
                        } else { $error='Credit payment failed: '.$pr['error']; }
                    } else {
                        log_activity('order_placed',"Order #{$order_num} created",'client',$client['id']);
                        redirect(BASE_URL.'/client/invoices/view.php?id='.$inv_id);
                    }
                }
            }
        }
    }
}

$all_products=DB::rows("SELECT p.*,pg.name AS group_name FROM products p LEFT JOIN product_groups pg ON pg.id=p.group_id WHERE p.visible=1 ORDER BY pg.sort_order,p.sort_order,p.name");
$cycles=['monthly'=>'Monthly','quarterly'=>'Quarterly','semi_annually'=>'Semi-Annual','annually'=>'Annual','biennially'=>'Biennial'];
$tax_rate_display=DB::setting('tax_enabled','1')==='1'?(float)DB::setting('tax_rate',0):0;
include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title">Order a Service</h1>
<?php if($error):?><div class="alert-custom alert-danger mb-3"><span>✕</span> <?=h($error)?></div><?php endif?>

<?php if(!$product): ?>
<?php
$last_grp='';
foreach($all_products as $p):
  if(!$p['price_monthly']&&!$p['price_annually'])continue;
  if($p['group_name']!==$last_grp):$last_grp=$p['group_name'];?>
  <h2 style="font-size:16px;font-weight:700;color:#0f172a;margin:24px 0 12px"><?=h($p['group_name']??'Products')?></h2>
  <?php endif?>
<div class="bp-card" style="margin-bottom:12px">
  <div class="bp-card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div>
      <div style="font-size:16px;font-weight:700"><?=h($p['name'])?></div>
      <?php if($p['description']):?><div style="font-size:13px;color:#64748b;margin-top:3px"><?=h($p['description'])?></div><?php endif?>
      <span class="bp-badge bp-badge-info" style="margin-top:6px;text-transform:capitalize"><?=$p['type']?></span>
    </div>
    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
      <div style="text-align:right">
        <?php 
        $p_monthly = (float)($p['price_monthly'] ?? 0);
        $p_annually = (float)($p['price_annually'] ?? 0);
        if (!empty($_SESSION['reseller_domain_id'])) {
            require_once INC_PATH . '/modules/reseller.php';
            if ($p_monthly > 0) $p_monthly = Reseller::getRetailPrice((int)$p['id'], 'monthly', (int)$_SESSION['reseller_domain_id']);
            if ($p_annually > 0) $p_annually = Reseller::getRetailPrice((int)$p['id'], 'annually', (int)$_SESSION['reseller_domain_id']);
        }
        if($p_monthly):?><div style="font-size:22px;font-weight:900;color:#0f172a"><?=format_currency($p_monthly,$p['currency']??$currency)?><span style="font-size:13px;font-weight:400;color:#64748b">/mo</span></div><?php endif?>
        <?php if($p_annually):?><div style="font-size:12px;color:#10b981">or <?=format_currency($p_annually,$p['currency']??$currency)?>/yr</div><?php endif?>
      </div>
      <a href="?product_id=<?=$p['id']?>" class="bp-btn bp-btn-primary">Order →</a>
    </div>
  </div>
</div>
<?php endforeach?>
<?php if(empty($all_products)):?><div class="bp-card"><div class="bp-empty"><div class="bp-empty-icon">📦</div><div class="bp-empty-title">No products available</div></div></div><?php endif?>

<?php else: ?>
<div class="row g-4">
  <div class="col-lg-7">
    <div class="bp-card"><div class="bp-card-header"><h3 class="bp-card-title">Configure Your Order</h3></div><div class="bp-card-body">
      <form method="POST" id="order-form">
        <?=csrf_input()?>
        <input type="hidden" name="action" value="place_order">
        <input type="hidden" name="product_id" value="<?=$product['id']?>">
        <input type="hidden" name="cycle" id="selected-cycle" value="monthly">

        <div class="d-flex align-items-center gap-3 mb-4">
          <div style="width:48px;height:48px;border-radius:12px;background:#eff6ff;display:flex;align-items:center;justify-content:center;font-size:22px">📦</div>
          <div><div style="font-size:17px;font-weight:700"><?=h($product['name'])?></div><?php if($product['group_name']):?><div style="font-size:13px;color:#64748b"><?=h($product['group_name'])?></div><?php endif?></div>
        </div>

        <div class="bp-form-group">
          <label class="bp-label">Billing Cycle</label>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:8px">
            <?php $first_cycle=true; foreach($cycles as $ck=>$cl):
              $price_col='price_'.$ck; $pr=(float)($product[$price_col]??0); if(!$pr)continue;
              if (!empty($_SESSION['reseller_domain_id'])) {
                  require_once INC_PATH . '/modules/reseller.php';
                  $pr = Reseller::getRetailPrice((int)$product['id'], $ck, (int)$_SESSION['reseller_domain_id']);
              }
            ?>
            <label style="border:1.5px solid <?=$first_cycle?'#3b82f6':'#e2e8f0'?>;border-radius:10px;padding:12px;cursor:pointer;transition:all .2s;text-align:center" class="cycle-opt" data-price="<?=$pr?>" data-cycle="<?=$ck?>">
              <input type="radio" name="cycle_radio" value="<?=$ck?>" <?=$first_cycle?'checked':''?> style="display:none">
              <div style="font-weight:600;font-size:12px;color:#64748b"><?=$cl?></div>
              <div style="font-size:15px;font-weight:800;color:#0f172a;margin-top:4px"><?=format_currency($pr,$currency)?></div>
            </label>
            <?php $first_cycle=false; endforeach?>
          </div>
        </div>

        <?php if(in_array($product['type'],['hosting','domain'])):?>
        <div class="bp-form-group">
          <label class="bp-label"><?=$product['type']==='domain'?'Domain Name':'Domain / Hostname'?> *</label>
          <input type="text" name="domain" class="bp-input" placeholder="<?=$product['type']==='domain'?'example.com':'yourdomain.com'?>" required>
        </div>
        <?php endif?>

        <div class="bp-form-group">
          <label class="bp-label">Coupon Code</label>
          <div style="display:flex;gap:8px">
            <input type="text" name="coupon_code" id="coupon-in" class="bp-input" placeholder="SAVE20" style="text-transform:uppercase;flex:1">
            <button type="button" onclick="applyCoupon()" class="bp-btn bp-btn-outline">Apply</button>
          </div>
          <div id="coupon-msg" style="font-size:13px;margin-top:6px"></div>
        </div>

        <div class="bp-form-group">
          <label class="bp-label">Payment Method</label>
          <label style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border:1.5px solid #bbf7d0;background:#f0fdf4;border-radius:10px;margin-bottom:8px;cursor:pointer">
            <div style="display:flex;align-items:center;gap:10px"><input type="radio" name="payment_method" value="credit" checked> <span style="font-weight:600;font-size:13px">💳 Account Credit</span></div>
            <span style="font-weight:800;color:#166534"><?=format_currency($client['credit_balance'],$currency)?></span>
          </label>
          <label style="display:flex;align-items:center;gap:10px;padding:12px 14px;border:1.5px solid #e2e8f0;border-radius:10px;cursor:pointer">
            <input type="radio" name="payment_method" value="invoice">
            <span style="font-size:13px">🧾 Generate Invoice (pay later)</span>
          </label>
        </div>

        <button type="submit" class="bp-btn bp-btn-primary" style="width:100%;justify-content:center;padding:13px;font-size:15px;margin-top:4px">Place Order →</button>
      </form>
    </div></div>
  </div>

  <div class="col-lg-5">
    <div class="bp-card"><div class="bp-card-header"><h3 class="bp-card-title">Order Summary</h3></div><div class="bp-card-body">
      <?php 
      $first_ck = '';
      foreach (['monthly','quarterly','semi_annually','annually','biennially'] as $c) {
          if ((float)($product['price_'.$c]??0) > 0) {
              $first_ck = $c;
              break;
          }
      }
      $first_pr = 0;
      if ($first_ck) {
          if (!empty($_SESSION['reseller_domain_id'])) {
              require_once INC_PATH . '/modules/reseller.php';
              $first_pr = Reseller::getRetailPrice((int)$product['id'], $first_ck, (int)$_SESSION['reseller_domain_id']);
          } else {
              $first_pr = (float)($product['price_'.$first_ck]??0);
          }
      }
      ?>
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:13px"><span style="color:#64748b"><?=h($product['name'])?></span><span id="sum-price" style="font-weight:600"><?=format_currency($first_pr,$currency)?></span></div>
      <?php if($tax_rate_display>0):?>
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:13px"><span style="color:#64748b"><?=h(DB::setting('tax_name','VAT'))?> (<?=$tax_rate_display?>%)</span><span id="sum-tax" style="font-weight:600"><?=format_currency(round($first_pr*($tax_rate_display/100),2),$currency)?></span></div>
      <?php endif?>
      <div id="sum-disc-row" style="display:none;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:13px"><span style="color:#10b981">Discount</span><span id="sum-disc" style="font-weight:600;color:#10b981"></span></div>
      <div style="display:flex;justify-content:space-between;padding:14px 0;font-size:18px;font-weight:800;color:#0f172a"><span>Total</span><span id="sum-total"><?=format_currency($first_pr,$currency)?></span></div>
      <a href="order.php" style="display:block;text-align:center;font-size:13px;color:#64748b;text-decoration:none;margin-top:4px">← Browse other products</a>
    </div></div>
  </div>
</div>
<?php endif?>
</div>

<script>
const taxRate=<?=$tax_rate_display?>/100;
let curPrice=<?=$product?json_encode((float)$first_pr):0?>;
let discountAmt=0;
const curr='<?=$currency?>';

function fmt(n){
    const sym={'NGN':'₦','USD':'$','GBP':'£','EUR':'€'}[curr]||curr+' ';
    return sym+n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');
}

function updateSummary(price){
    curPrice=price;
    const tax=Math.round(price*taxRate*100)/100;
    const total=Math.max(0,price+tax-discountAmt);
    document.getElementById('sum-price').textContent=fmt(price);
    const st=document.getElementById('sum-tax');if(st)st.textContent=fmt(tax);
    document.getElementById('sum-total').textContent=fmt(total);
}

document.querySelectorAll('.cycle-opt').forEach(opt=>{
    opt.addEventListener('click',()=>{
        document.querySelectorAll('.cycle-opt').forEach(o=>o.style.borderColor='#e2e8f0');
        opt.style.borderColor='#3b82f6';
        opt.querySelector('input').checked=true;
        document.getElementById('selected-cycle').value=opt.dataset.cycle;
        updateSummary(parseFloat(opt.dataset.price));
    });
});

async function applyCoupon(){
    const code=document.getElementById('coupon-in').value.trim().toUpperCase();
    if(!code)return;
    const msg=document.getElementById('coupon-msg');
    msg.textContent='Checking…';
    try{
        const fd=new FormData();
        fd.append('action','apply_coupon');fd.append('coupon_code',code);fd.append('price',curPrice);fd.append('csrf_token','<?=csrf_token()?>');
        const r=await fetch(window.location.href,{method:'POST',body:fd});
        const d=await r.json();
        if(d.valid){
            discountAmt=d.discount;
            msg.innerHTML='<span style="color:#10b981">✓ Saved '+fmt(discountAmt)+'!</span>';
            const dr=document.getElementById('sum-disc-row');
            if(dr){dr.style.display='flex';document.getElementById('sum-disc').textContent='-'+fmt(discountAmt);}
            updateSummary(curPrice);
        }else{discountAmt=0;msg.innerHTML='<span style="color:#ef4444">✕ '+d.error+'</span>';}
    }catch(e){msg.textContent='Error checking coupon.';}
}
</script>
<?php include 'partials/footer.php';?>
