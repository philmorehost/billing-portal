<?php
require_once '../includes/config.php';
$client=Auth::requireClient(); $company=DB::setting('company_name','Billing Portal');
$currency=DB::setting('base_currency','NGN'); $page_title='Become a Reseller';
$error=''; $success='';

// Check if already reseller
$existing=DB::row("SELECT id,status FROM resellers WHERE client_id=?",'i',[$client['id']]);

if(is_post()&&csrf_verify()&&!$existing){
    $cname=trim(post('company_name'));
    if(!$cname){$error='Company name required.';}
    else{
        DB::execute("UPDATE clients SET account_type='reseller' WHERE id=?",'i',[$client['id']]);
        DB::execute("INSERT INTO resellers (client_id,company_name,markup_percentage,status) VALUES (?,?,20,'pending')",'is',[$client['id'],$cname]);
        // Create affiliate account automatically
        if(!DB::value("SELECT id FROM affiliates WHERE client_id=?",'i',[$client['id']])){
            $code=strtoupper(substr(md5($client['id'].time()),0,8));
            DB::execute("INSERT INTO affiliates (client_id,referral_code,commission_type,commission_value,status) VALUES (?,?,'percentage',10,'active')",'is',[$client['id'],$code]);
        }
        $ae=DB::setting('company_email');
        if($ae) Mailer::send($ae,'Admin',"New Reseller Application","<p>{$client['first_name']} {$client['last_name']} applied to become a reseller (Company: {$cname}). Review in admin panel.</p>");
        log_activity('reseller_apply',"Client #{$client['id']} applied for reseller account",'client',$client['id']);
        $existing=DB::row("SELECT id,status FROM resellers WHERE client_id=?",'i',[$client['id']]);
        $success='Your reseller application has been submitted! We will review and activate your account shortly.';
    }
}

$default_disc=(float)DB::setting('reseller_default_discount',20);
include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title">Become a Reseller</h1>

<?php if($existing): ?>
  <?php if($existing['status']==='active'): ?>
  <div class="alert-custom alert-success mb-4"><span>✓</span> Your reseller account is active. <a href="<?=BASE_URL?>/reseller/" style="font-weight:700;color:#166534">Access Reseller Portal →</a></div>
  <?php elseif($existing['status']==='pending'): ?>
  <div class="alert-custom alert-warning mb-4"><span>⏳</span> Your reseller application is under review. We'll notify you once approved.</div>
  <?php endif?>
<?php else: ?>

<?php if($error):?><div class="alert-custom alert-danger mb-3"><span>✕</span> <?=h($error)?></div><?php endif?>
<?php if($success):?><div class="alert-custom alert-success mb-3"><span>✓</span> <?=h($success)?></div><?php endif?>

<div class="row g-4">
  <div class="col-lg-7">
    <!-- Benefits -->
    <div class="bp-card mb-4">
      <div style="background:linear-gradient(135deg,#0f172a,#1e3a5f);padding:28px 32px;border-radius:16px 16px 0 0">
        <div style="font-size:24px;margin-bottom:12px">🏪</div>
        <h2 style="color:#fff;font-size:22px;font-weight:800;margin:0 0 8px">Start Your Own Hosting Business</h2>
        <p style="color:rgba(255,255,255,.65);font-size:14px;margin:0">Resell our services under your own brand. You set the prices, we handle the infrastructure.</p>
      </div>
      <div class="bp-card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
          <?php
          $benefits=[
            ['💰','Wholesale Pricing',"Up to {$default_disc}% off retail prices"],
            ['🎨','White-Label Branding','Your logo, colors, company name'],
            ['🌐','Custom Domain','Use billing.yourdomain.com'],
            ['🔒','Free SSL Cert','Auto-provisioned Let\'s Encrypt SSL'],
            ['👥','Manage Clients','Full client management portal'],
            ['📊','Your Own Markup','Set your retail prices freely'],
          ];
          foreach($benefits as [$icon,$title,$desc]):?>
          <div style="background:#f8fafc;border-radius:12px;padding:16px">
            <div style="font-size:24px;margin-bottom:8px"><?=$icon?></div>
            <div style="font-weight:700;font-size:13px;margin-bottom:4px"><?=$title?></div>
            <div style="font-size:12px;color:#64748b"><?=$desc?></div>
          </div>
          <?php endforeach?>
        </div>
      </div>
    </div>

    <!-- Application form -->
    <div class="bp-card"><div class="bp-card-header"><h3 class="bp-card-title">Apply Now</h3></div><div class="bp-card-body">
      <form method="POST"><?=csrf_input()?>
        <div class="bp-form-group">
          <label class="bp-label">Company / Brand Name *</label>
          <input type="text" name="company_name" class="bp-input" placeholder="e.g. TechHost Solutions" value="<?=h(post('company_name'))?>" required>
          <div class="bp-input-hint">This will be your brand name in the white-label portal.</div>
        </div>
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:14px 16px;margin-bottom:16px;font-size:13px;color:#166534">
          ✓ By applying, you agree to our reseller terms. Your account will be reviewed within 24 hours.
        </div>
        <button type="submit" class="bp-btn bp-btn-primary" style="padding:13px 32px;font-size:15px">Submit Application →</button>
      </form>
    </div></div>
  </div>

  <div class="col-lg-5">
    <!-- Pricing example -->
    <div class="bp-card"><div class="bp-card-body">
      <div style="font-weight:700;font-size:15px;margin-bottom:16px">💡 Sample Profit Calculation</div>
      <?php
      $sample_retail=10000; $ws=round($sample_retail*(1-$default_disc/100),2);
      $markup_20=round($ws*1.20,2); $profit=round($markup_20-$ws,2);
      ?>
      <div style="display:flex;flex-direction:column;gap:8px;font-size:13px">
        <div style="display:flex;justify-content:space-between;padding:10px 14px;background:#f8fafc;border-radius:8px">
          <span style="color:#64748b">Service retail price</span>
          <span style="font-weight:600"><?=format_currency($sample_retail,$currency)?></span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:10px 14px;background:#fff1f2;border-radius:8px">
          <span style="color:#64748b">You pay (wholesale)</span>
          <span style="font-weight:700;color:#ef4444"><?=format_currency($ws,$currency)?></span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:10px 14px;background:#f0fdf4;border-radius:8px">
          <span style="color:#64748b">Client pays (20% markup)</span>
          <span style="font-weight:700;color:#166534"><?=format_currency($markup_20,$currency)?></span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:14px;background:linear-gradient(135deg,#0f172a,#1e3a5f);border-radius:10px">
          <span style="color:rgba(255,255,255,.7);font-weight:600">Your Profit</span>
          <span style="font-weight:900;color:#10b981;font-size:18px"><?=format_currency($profit,$currency)?></span>
        </div>
        <div style="font-size:11px;color:#94a3b8;text-align:center">Per <?=format_currency($sample_retail,$currency)?> service at 20% markup</div>
      </div>
    </div></div>
  </div>
</div>
<?php endif?>
</div>
<?php include 'partials/footer.php';?>
