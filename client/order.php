<?php
require_once '../includes/config.php';
require_once INC_PATH.'/modules/billing.php';
$client=Auth::client();
$company=DB::setting('company_name','Billing Portal');
$currency=DB::setting('base_currency','NGN');
$page_title='Order';
$error='';

$pid=(int)get_param('product_id');
if(!$pid && get_param('type')==='domain'){
    $domain_prod=DB::row("SELECT id FROM products WHERE type='domain' AND visible=1 LIMIT 1");
    if($domain_prod) $pid=(int)$domain_prod['id'];
}
$product=$pid?DB::row("SELECT p.*,pg.name AS group_name FROM products p LEFT JOIN product_groups pg ON pg.id=p.group_id WHERE p.id=? AND p.visible=1",'i',[$pid]):null;

// AJAX coupon check
if(is_post()&&csrf_verify()&&post('action')==='apply_coupon'){
    $code=strtoupper(trim(post('coupon_code')));
    $price=(float)post('price');
    json_response(Billing::validateCoupon($code,$client ? $client['id'] : 0,$price));
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
        // Handle inline guest registration or login if client not logged in
        if (!$client) {
            $auth_type = post('checkout_auth_type', 'register');
            if ($auth_type === 'register') {
                $fname = trim(post('first_name',''));
                $lname = trim(post('last_name',''));
                $email = trim(post('email',''));
                $phone = trim(post('phone',''));
                $pass = post('password','');

                if (!$fname || !$lname || !$email || !$pass) {
                    $error = 'First name, last name, email and password are required to create an account.';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Please enter a valid email address.';
                } else {
                    // Check if email already registered
                    $exists = DB::value("SELECT COUNT(*) FROM clients WHERE email=?", 's', [$email]);
                    if ($exists > 0) {
                        $error = 'An account with this email address already exists. Please sign in instead.';
                    } else {
                        $pw_hash = password_hash($pass, PASSWORD_BCRYPT);
                        DB::execute(
                            "INSERT INTO clients (first_name, last_name, email, password, phone, status, email_verified, credit_balance) VALUES (?, ?, ?, ?, ?, 'active', 1, 0.00)",
                            'sssss', [$fname, $lname, $email, $pw_hash, $phone]
                        );
                        $new_cid = DB::lastInsertId();
                        $client = DB::row("SELECT * FROM clients WHERE id=?", 'i', [$new_cid]);
                        Auth::setClientSession($client);
                    }
                }
            } else {
                // Sign In
                $lemail = trim(post('login_email',''));
                $lpass = post('login_password','');
                if (!$lemail || !$lpass) {
                    $error = 'Please enter both your email address and password to sign in.';
                } else {
                    $auth_res = Auth::clientLogin($lemail, $lpass);
                    if ($auth_res['success']) {
                        $client = Auth::client();
                    } else {
                        $error = $auth_res['error'] ?? 'Invalid sign in credentials.';
                    }
                }
            }
        }

        if (!$error && $client) {
            if ($prod['type'] === 'domain') {
                require_once INC_PATH . '/modules/reseller.php';
                $reseller_id = !empty($_SESSION['reseller_domain_id']) ? (int)$_SESSION['reseller_domain_id'] : null;
                $domain_pricing = Reseller::getDomainPricing($domain, $reseller_id);
                $price = $domain_pricing['register'];
            } else {
                $price_col='price_'.$cyc;
                if (!empty($_SESSION['reseller_domain_id'])) {
                    require_once INC_PATH . '/modules/reseller.php';
                    $price = Reseller::getRetailPrice((int)$prod['id'], $cyc, (int)$_SESSION['reseller_domain_id']);
                } else {
                    $price=(float)($prod[$price_col]??0);
                }
            }
            if(!$price) { $error='Selected pricing or domain registration not available.'; }
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
}

$store_groups = DB::rows("SELECT * FROM product_groups WHERE visible=1 ORDER BY sort_order, name");
$selected_group_id = (isset($_GET['group']) && $_GET['group'] !== '') ? ($_GET['group'] === 'all' ? 'all' : (int)$_GET['group']) : null;

$is_domain_view = (isset($_GET['type']) && $_GET['type'] === 'domain');
if ($selected_group_id === null && !$is_domain_view && !empty($store_groups) && !get_param('product_id')) {
    $default_config = (int)DB::setting('default_product_group');
    if ($default_config > 0) {
        $selected_group_id = $default_config;
    } else {
        // Automatically default to the first group that actually has visible products in it
        $first_populated = (int)DB::value(
            "SELECT pg.id FROM product_groups pg 
             JOIN products p ON p.group_id = pg.id 
             WHERE pg.visible = 1 AND p.visible = 1 
             ORDER BY pg.sort_order, pg.name LIMIT 1"
        );
        if ($first_populated > 0) {
            $selected_group_id = $first_populated;
        } else {
            $selected_group_id = (int)$store_groups[0]['id'];
        }
    }
}

$all_products=DB::rows("SELECT p.*,pg.name AS group_name FROM products p LEFT JOIN product_groups pg ON pg.id=p.group_id WHERE p.visible=1 ORDER BY pg.sort_order,p.sort_order,p.name");

if ($selected_group_id === 'all') {
    $products_to_show = $all_products;
} elseif ($selected_group_id !== null) {
    $products_to_show = DB::rows("SELECT p.*, pg.name AS group_name FROM products p LEFT JOIN product_groups pg ON pg.id=p.group_id WHERE p.visible=1 AND p.group_id=? ORDER BY p.sort_order, p.name", 'i', [$selected_group_id]);
} else {
    $products_to_show = [];
}

$cycles=['monthly'=>'Monthly','quarterly'=>'Quarterly','semi_annually'=>'Semi-Annual','annually'=>'Annual','biennially'=>'Biennial'];
$tax_rate_display=DB::setting('tax_enabled','1')==='1'?(float)DB::setting('tax_rate',0):0;
include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title" style="margin-bottom: 24px">🛒 Services Order Form</h1>
<?php if($error):?><div class="alert-custom alert-danger mb-3"><span>✕</span> <?=h($error)?></div><?php endif?>

<?php if(!$product && !get_param('product_id')): ?>
<div class="row g-4">
  <!-- WHMCS Style Sidebar Categories -->
  <div class="col-lg-3">
    <div class="bp-card mb-4" style="padding: 0; overflow: hidden; border-radius: 12px; border: 1px solid #e2e8f0">
      <div style="background: #f8fafc; padding: 16px; border-bottom: 1px solid #e2e8f0; font-weight: 700; color: #0f172a; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px">
        🛒 Categories
      </div>
      <div style="display: flex; flex-direction: column">
        <?php foreach ($store_groups as $g): 
          $active = ($selected_group_id !== 'all' && $selected_group_id !== null && (int)$selected_group_id === (int)$g['id'] && !$is_domain_view);
        ?>
          <a href="?group=<?=$g['id']?>" style="display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; text-decoration: none; color: <?=$active ? '#2563eb' : '#475569'?>; background: <?=$active ? '#eff6ff' : 'transparent'?>; border-left: 3px solid <?=$active ? '#2563eb' : 'transparent'?>; font-weight: <?=$active ? '700' : '500'?>; font-size: 14px; border-bottom: 1px solid #f1f5f9; transition: all 0.2s">
            <span>📁 <?=h($g['name'])?></span>
            <span style="font-size: 12px; background: <?=$active ? '#3b82f6' : '#e2e8f0'?>; color: <?=$active ? '#ffffff' : '#475569'?>; padding: 2px 8px; border-radius: 20px; font-weight: 700">
              <?=(int)DB::value("SELECT COUNT(*) FROM products WHERE group_id=? AND visible=1", 'i', [$g['id']])?>
            </span>
          </a>
        <?php endforeach; ?>

        <?php 
          $all_active = ($selected_group_id === 'all' && !$is_domain_view);
        ?>
        <a href="?group=all" style="display: flex; align-items: center; padding: 14px 16px; text-decoration: none; color: <?=$all_active ? '#2563eb' : '#475569'?>; background: <?=$all_active ? '#eff6ff' : 'transparent'?>; border-left: 3px solid <?=$all_active ? '#2563eb' : 'transparent'?>; font-weight: <?=$all_active ? '700' : '500'?>; font-size: 14px; border-bottom: 1px solid #f1f5f9; transition: all 0.2s">
          <span>📦 All Categories</span>
        </a>

        <?php
          $domain_prod = DB::row("SELECT id FROM products WHERE type='domain' AND visible=1 LIMIT 1");
          if ($domain_prod):
        ?>
          <a href="?type=domain" style="display: flex; align-items: center; padding: 14px 16px; text-decoration: none; color: <?=$is_domain_view ? '#2563eb' : '#475569'?>; background: <?=$is_domain_view ? '#eff6ff' : 'transparent'?>; border-left: 3px solid <?=$is_domain_view ? '#2563eb' : 'transparent'?>; font-weight: <?=$is_domain_view ? '700' : '500'?>; font-size: 14px; transition: all 0.2s">
            <span>🌐 Register a Domain</span>
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Actions/Shortcut Sidebar Widget -->
    <div class="bp-card" style="padding: 16px; border-radius: 12px; border: 1px solid #e2e8f0; background: #fafafa">
      <h4 style="font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 8px">Shortcuts</h4>
      <div style="display: flex; flex-direction: column; gap: 8px">
        <a href="invoices.php" style="text-decoration: none; color: #3b82f6; font-size: 13px; font-weight: 600">🧾 View Invoices</a>
        <a href="services.php" style="text-decoration: none; color: #3b82f6; font-size: 13px; font-weight: 600">💻 Active Services</a>
        <a href="domains.php" style="text-decoration: none; color: #3b82f6; font-size: 13px; font-weight: 600">🌐 Manage Domains</a>
      </div>
    </div>
  </div>

  <!-- WHMCS Style Main Products Panel -->
  <div class="col-lg-9">
    <?php if ($is_domain_view && $domain_prod): ?>
      <!-- Domain Registration Showcase -->
      <div style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); padding: 32px; border-radius: 12px; color: #ffffff; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1)">
        <h2 style="font-size: 24px; font-weight: 800; margin: 0">Find Your Perfect Domain Name</h2>
        <p style="opacity: 0.9; font-size: 14px; margin: 8px 0 20px">Check availability and register domains instantly at competitive pricing.</p>
        <form method="GET" action="order.php" style="display: flex; gap: 8px; max-width: 600px">
          <input type="hidden" name="product_id" value="<?=$domain_prod['id']?>">
          <input type="text" name="domain" class="bp-input" style="flex: 1; border: none; height: 48px; border-radius: 8px; font-size: 16px; padding: 0 16px; color: #0f172a" placeholder="yourbrandname.com" required>
          <button type="submit" class="bp-btn bp-btn-primary" style="background: #0f172a; border-color: #0f172a; height: 48px; padding: 0 24px; font-weight: 700">Check →</button>
        </form>
      </div>

      <div class="bp-card">
        <div class="bp-card-header"><h3 class="bp-card-title">🌐 Supported Extensions Pricing Matrix</h3></div>
        <div class="bp-card-body" style="padding: 0">
          <?php 
            $tld_matrix = DB::rows("SELECT * FROM domain_tlds WHERE status='active' ORDER BY tld ASC");
            if ($tld_matrix):
          ?>
            <table class="bp-table">
              <thead>
                <tr>
                  <th>Extension</th>
                  <th>Register Price</th>
                  <th>Renewal Price</th>
                  <th>Transfer Price</th>
                  <th style="text-align: right">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($tld_matrix as $t): ?>
                  <tr>
                    <td style="font-weight: 700; color: #2563eb; font-family: monospace; font-size: 15px">.<?=h($t['tld'])?></td>
                    <td style="font-weight: 600; color: #0f172a"><?=format_currency($t['retail_price_register'], $currency)?></td>
                    <td style="color: #475569"><?=format_currency($t['retail_price_renew'], $currency)?></td>
                    <td style="color: #475569"><?=format_currency($t['retail_price_transfer'], $currency)?></td>
                    <td style="text-align: right">
                      <a href="?product_id=<?=$domain_prod['id']?>&domain=mysite.<?=$t['tld']?>" class="bp-btn bp-btn-primary bp-btn-sm" style="padding: 4px 12px; font-size: 12px">Register</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <div class="bp-empty"><div class="bp-empty-icon">📡</div><div class="bp-empty-title">No extensions currently supported</div></div>
          <?php endif; ?>
        </div>
      </div>

    <?php else: ?>
      <!-- Product Group Browse View -->
      <?php 
        if ($selected_group_id !== 'all') {
          $current_group = DB::row("SELECT * FROM product_groups WHERE id=?", 'i', [$selected_group_id]);
          if ($current_group):
      ?>
        <div class="mb-4" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px 24px">
          <h2 style="font-size: 20px; font-weight: 800; color: #0f172a; margin: 0">📦 <?=h($current_group['name'])?></h2>
          <?php if ($current_group['description']): ?>
            <p style="color: #64748b; font-size: 14px; margin: 8px 0 0"><?=h($current_group['description'])?></p>
          <?php endif; ?>
        </div>
      <?php 
          endif;
        } else {
      ?>
        <div class="mb-4" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px 24px">
          <h2 style="font-size: 20px; font-weight: 800; color: #0f172a; margin: 0">📦 All Categories</h2>
          <p style="color: #64748b; font-size: 14px; margin: 8px 0 0">Browse our comprehensive catalogue of services, plans, and cloud provisions.</p>
        </div>
      <?php
        }
      ?>

      <!-- Products Listing Grid -->
      <div style="display: flex; flex-direction: column; gap: 16px">
        <?php 
        $last_grp = '';
        foreach ($products_to_show as $p): 
          if (!$p['price_monthly'] && !$p['price_annually']) continue;
          if ($selected_group_id === 'all' && $p['group_name'] !== $last_grp): $last_grp = $p['group_name'];
        ?>
          <h3 style="font-size: 12px; font-weight: 800; color: #475569; text-transform: uppercase; margin: 16px 0 4px; letter-spacing: 0.5px"><?=h($p['group_name'] ?: 'General Products')?></h3>
        <?php endif; ?>

          <div class="bp-card" style="border: 1px solid #e2e8f0; transition: border-color 0.2s, box-shadow 0.2s" onmouseover="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 4px 12px rgba(59, 130, 246, 0.05)'" onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none'">
            <div class="bp-card-body" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; padding: 20px 24px">
              <div style="flex: 1; min-width: 250px">
                <div style="font-size: 18px; font-weight: 800; color: #0f172a"><?=h($p['name'])?></div>
                <?php if ($p['description']): ?>
                  <div style="font-size: 13.5px; color: #64748b; margin-top: 6px; line-height: 1.5"><?=h($p['description'])?></div>
                <?php endif; ?>
                <span class="bp-badge bp-badge-info" style="margin-top: 10px; text-transform: capitalize; background: #eff6ff; color: #2563eb; font-weight: 700; border: 1px solid rgba(37, 99, 235, 0.1)"><?=$p['type']?></span>
              </div>
              
              <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap">
                <div style="text-align: right">
                  <?php 
                  $p_monthly = (float)($p['price_monthly'] ?? 0);
                  $p_annually = (float)($p['price_annually'] ?? 0);
                  if (!empty($_SESSION['reseller_domain_id'])) {
                      require_once INC_PATH . '/modules/reseller.php';
                      if ($p_monthly > 0) $p_monthly = Reseller::getRetailPrice((int)$p['id'], 'monthly', (int)$_SESSION['reseller_domain_id']);
                      if ($p_annually > 0) $p_annually = Reseller::getRetailPrice((int)$p['id'], 'annually', (int)$_SESSION['reseller_domain_id']);
                  }
                  if ($p_monthly): 
                  ?>
                    <div style="font-size: 24px; font-weight: 900; color: #0f172a">
                      <?=format_currency($p_monthly, $p['currency'] ?? $currency)?>
                      <span style="font-size: 13px; font-weight: 500; color: #64748b">/mo</span>
                    </div>
                  <?php endif; ?>
                  <?php if ($p_annually): ?>
                    <div style="font-size: 12.5px; color: #10b981; font-weight: 600; margin-top: 2px">or <?=format_currency($p_annually, $p['currency'] ?? $currency)?>/yr</div>
                  <?php endif; ?>
                </div>
                
                <a href="?product_id=<?=$p['id']?>" class="bp-btn bp-btn-primary" style="padding: 10px 24px; font-weight: 700; border-radius: 8px">
                  Order Now →
                </a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>

        <?php if (empty($products_to_show)): ?>
          <div class="bp-card"><div class="bp-empty"><div class="bp-empty-icon">📦</div><div class="bp-empty-title">No products available in this category</div></div></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
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
          <input type="text" name="domain" class="bp-input" placeholder="<?=$product['type']==='domain'?'example.com':'yourdomain.com'?>" value="<?=h(get_param('domain'))?>" required>
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

        <?php if(!$client): ?>
        <div class="bp-form-group" style="margin-top:24px;border-top:1px solid #f1f5f9;padding-top:20px">
          <label class="bp-label" style="font-size:15px;font-weight:800;color:#0f172a;margin-bottom:12px">👤 Account Details</label>
          <div style="display:flex;background:#f8fafc;border-radius:10px;padding:4px;margin-bottom:16px">
            <button type="button" id="tab-reg-btn" onclick="switchAuthTab('register')" style="flex:1;border:none;background:#3b82f6;color:#ffffff;font-size:13px;font-weight:600;padding:10px;border-radius:8px;transition:all 0.2s">Create Account</button>
            <button type="button" id="tab-login-btn" onclick="switchAuthTab('login')" style="flex:1;border:none;background:transparent;color:#64748b;font-size:13px;font-weight:600;padding:10px;border-radius:8px;transition:all 0.2s">Already Registered? Sign In</button>
          </div>
          <input type="hidden" name="checkout_auth_type" id="checkout-auth-type" value="register">

          <!-- Registration Fields -->
          <div id="auth-reg-fields" style="display:block">
            <div class="bp-form-row bp-form-row-2" style="margin-bottom:12px">
              <div class="bp-form-group" style="margin-bottom:0"><label class="bp-label">First Name *</label><input type="text" name="first_name" class="bp-input" placeholder="John"></div>
              <div class="bp-form-group" style="margin-bottom:0"><label class="bp-label">Last Name *</label><input type="text" name="last_name" class="bp-input" placeholder="Doe"></div>
            </div>
            <div class="bp-form-group"><label class="bp-label">Email Address *</label><input type="email" name="email" class="bp-input" placeholder="john.doe@example.com"></div>
            <div class="bp-form-group"><label class="bp-label">Phone Number</label><input type="text" name="phone" class="bp-input" placeholder="+2348000000000"></div>
            <div class="bp-form-group"><label class="bp-label">Account Password *</label><input type="password" name="password" class="bp-input" placeholder="Create secure password"></div>
          </div>

          <!-- Login Fields -->
          <div id="auth-login-fields" style="display:none">
            <div class="bp-form-group"><label class="bp-label">Email Address *</label><input type="email" name="login_email" class="bp-input" placeholder="john.doe@example.com"></div>
            <div class="bp-form-group"><label class="bp-label">Password *</label><input type="password" name="login_password" class="bp-input" placeholder="Enter account password"></div>
          </div>
        </div>

        <div class="bp-form-group">
          <label class="bp-label">Payment Method</label>
          <label style="display:flex;align-items:center;gap:10px;padding:12px 14px;border:1.5px solid #3b82f6;background:#f0f9ff;border-radius:10px;cursor:pointer">
            <input type="radio" name="payment_method" value="invoice" checked>
            <div>
              <span style="font-weight:700;font-size:13px;color:#0369a1">🧾 Generate Invoice</span>
              <div style="font-size:12px;color:#0284c7;margin-top:2px">Pay using Bank Transfer, Stripe, Paystack, etc.</div>
            </div>
          </label>
        </div>
        <?php else: ?>
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
        <?php endif; ?>

        <button type="submit" class="bp-btn bp-btn-primary" style="width:100%;justify-content:center;padding:13px;font-size:15px;margin-top:4px">Place Order →</button>
      </form>
    </div></div>
  </div>

  <div class="col-lg-5">
    <div class="bp-card"><div class="bp-card-header"><h3 class="bp-card-title">Order Summary</h3></div><div class="bp-card-body">
      <?php 
      $first_pr = 0;
      $first_ck = 'annually';
      $reseller_id = !empty($_SESSION['reseller_domain_id']) ? (int)$_SESSION['reseller_domain_id'] : null;
      require_once INC_PATH . '/modules/reseller.php';

      if ($product['type'] === 'domain') {
          $searched_domain = trim(get_param('domain', 'example.com'));
          $domain_pricing = Reseller::getDomainPricing($searched_domain, $reseller_id);
          $first_pr = $domain_pricing['register'];
          $first_ck = 'annually';
      } else {
          foreach (['monthly','quarterly','semi_annually','annually','biennially'] as $c) {
              if ((float)($product['price_'.$c]??0) > 0) {
                  $first_ck = $c;
                  break;
              }
          }
          if ($first_ck) {
              if ($reseller_id) {
                  $first_pr = Reseller::getRetailPrice((int)$product['id'], $first_ck, $reseller_id);
              } else {
                  $first_pr = (float)($product['price_'.$first_ck]??0);
              }
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

function switchAuthTab(type) {
    const regBtn = document.getElementById('tab-reg-btn');
    const loginBtn = document.getElementById('tab-login-btn');
    const regFields = document.getElementById('auth-reg-fields');
    const loginFields = document.getElementById('auth-login-fields');
    const authType = document.getElementById('checkout-auth-type');

    if (type === 'register') {
        regBtn.style.background = '#3b82f6';
        regBtn.style.color = '#ffffff';
        loginBtn.style.background = 'transparent';
        loginBtn.style.color = '#64748b';
        regFields.style.display = 'block';
        loginFields.style.display = 'none';
        authType.value = 'register';
    } else {
        loginBtn.style.background = '#3b82f6';
        loginBtn.style.color = '#ffffff';
        regBtn.style.background = 'transparent';
        regBtn.style.color = '#64748b';
        regFields.style.display = 'none';
        loginFields.style.display = 'block';
        authType.value = 'login';
    }
}
</script>
<?php include 'partials/footer.php';?>
