<?php
require_once '../includes/config.php';
require_once INC_PATH.'/modules/reseller.php';
if(empty($_SESSION['reseller_id'])) redirect(BASE_URL.'/reseller/login.php');
$reseller_id=$_SESSION['reseller_id'];
$reseller=DB::row("SELECT * FROM resellers WHERE id=?",'i',[$reseller_id]);
$reseller_client=DB::row("SELECT * FROM clients WHERE id=?",'i',[$reseller['client_id']]);
$company=DB::setting('company_name','Billing Portal');
$page_title='Branding & Domain'; $error=''; $success='';

if(is_post()&&csrf_verify()){
    $action=post('action');
    if($action==='branding'){
        $bn=trim(post('branding_name')); $bc=trim(post('branding_color','#0f172a'));
        $markup=(float)post('markup_percentage',20);
        // Validate hex color
        if(!preg_match('/^#[0-9a-f]{6}$/i',$bc)) $bc='#0f172a';
        DB::execute("UPDATE resellers SET branding_name=?,branding_color=?,markup_percentage=? WHERE id=?",'ssdi',[$bn,$bc,$markup,$reseller_id]);
        $success='Branding settings saved.';
        $reseller=DB::row("SELECT * FROM resellers WHERE id=?",'i',[$reseller_id]);
    }
    if($action==='domain'){
        $domain=strtolower(trim(post('custom_domain')));
        if($domain){
            $r=Reseller::registerDomain($reseller_id,$domain);
            if($r['success']) $success=$r['message'];
            else $error=$r['error'];
        } else {
            DB::execute("UPDATE resellers SET custom_domain=NULL,ssl_status='none' WHERE id=?",'i',[$reseller_id]);
            $success='Custom domain removed.';
        }
        $reseller=DB::row("SELECT * FROM resellers WHERE id=?",'i',[$reseller_id]);
    }
    if($action==='verify_ssl'){
        $domain=$reseller['custom_domain'];
        if($domain){
            if(Reseller::verifyCNAME($domain)){
                $r=Reseller::provisionSSL($domain);
                $r['success']?($success='SSL certificate provisioned successfully!'):($error='SSL provisioning failed: '.$r['error']);
            } else {
                $error='CNAME not yet resolving to this server. Please check your DNS settings and try again.';
            }
        }
    }
}
$server_host=$_SERVER['HTTP_HOST']??'yourserver.com';
include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title">Branding & Domain Settings</h1>
<?php if($error):?><div class="alert-custom alert-danger mb-3"><span>✕</span> <?=h($error)?></div><?php endif?>
<?php if($success):?><div class="alert-custom alert-success mb-3"><span>✓</span> <?=h($success)?></div><?php endif?>

<div class="row g-4">
  <!-- Branding -->
  <div class="col-lg-6">
    <div class="bp-card"><div class="bp-card-header"><h3 class="bp-card-title">🎨 White-Label Branding</h3></div><div class="bp-card-body">
      <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="branding">
        <div class="bp-form-group"><label class="bp-label">Company / Brand Name</label>
          <input type="text" name="branding_name" class="bp-input" value="<?=h($reseller['branding_name']??'')?>" placeholder="My Hosting Company">
          <div class="bp-input-hint">Shown as the portal name to your clients.</div>
        </div>
        <div class="bp-form-group"><label class="bp-label">Brand Color</label>
          <div style="display:flex;gap:10px;align-items:center">
            <input type="color" name="branding_color" class="bp-input" value="<?=h($reseller['branding_color']??'#0f172a')?>" style="width:60px;height:42px;padding:4px;cursor:pointer">
            <input type="text" id="color-hex" class="bp-input" value="<?=h($reseller['branding_color']??'#0f172a')?>" placeholder="#0f172a" style="flex:1" pattern="^#[0-9a-fA-F]{6}$">
          </div>
          <div class="bp-input-hint">Used for the sidebar and accent elements in your portal.</div>
        </div>
        <div class="bp-form-group"><label class="bp-label">Your Retail Markup (%)</label>
          <input type="number" name="markup_percentage" class="bp-input" value="<?=h($reseller['markup_percentage']??20)?>" step="0.1" min="0" max="500">
          <div class="bp-input-hint">You buy at wholesale price. This % is added on top for your clients.</div>
        </div>
        <!-- Preview -->
        <div style="background:<?=h($reseller['branding_color']??'#0f172a')?>;border-radius:12px;padding:16px 20px;margin:16px 0;display:flex;align-items:center;gap:12px" id="preview">
          <div style="width:36px;height:36px;border-radius:9px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:18px">🏪</div>
          <div><div style="color:#fff;font-weight:700;font-size:15px" id="preview-name"><?=h($reseller['branding_name']??'My Company')?></div><div style="color:rgba(255,255,255,.5);font-size:11px">Reseller Portal</div></div>
        </div>
        <button type="submit" class="bp-btn bp-btn-primary">💾 Save Branding</button>
      </form>
    </div></div>
  </div>

  <!-- Custom Domain -->
  <div class="col-lg-6">
    <div class="bp-card"><div class="bp-card-header"><h3 class="bp-card-title">🌐 Custom Domain</h3></div><div class="bp-card-body">
      <!-- Current status -->
      <?php if($reseller['custom_domain']): $ssl_badge=['none'=>'muted','pending'=>'warning','active'=>'success','expired'=>'danger'][$reseller['ssl_status']]??'muted';?>
      <div style="background:#f8fafc;border-radius:10px;padding:16px;margin-bottom:20px">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:6px">Current Domain</div>
        <div style="font-size:16px;font-weight:700;font-family:monospace;color:#0f172a"><?=h($reseller['custom_domain'])?></div>
        <div style="display:flex;align-items:center;gap:8px;margin-top:8px">
          <span class="bp-badge bp-badge-<?=$ssl_badge?>">SSL: <?=strtoupper($reseller['ssl_status'])?></span>
          <?php if($reseller['ssl_expires']):?><span style="font-size:12px;color:#64748b">Expires <?=format_date($reseller['ssl_expires'])?></span><?php endif?>
        </div>
      </div>
      <?php if(in_array($reseller['ssl_status'],['none','pending','expired'])):?>
      <form method="POST" style="margin-bottom:16px"><?=csrf_input()?><input type="hidden" name="action" value="verify_ssl">
        <button type="submit" class="bp-btn bp-btn-success bp-btn-sm">🔒 Provision / Renew SSL Certificate</button>
      </form>
      <?php endif?>
      <?php endif?>

      <!-- DNS instructions -->
      <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:14px 16px;margin-bottom:20px;font-size:13px">
        <strong style="display:block;margin-bottom:8px">📋 DNS Setup Instructions</strong>
        <div style="color:#374151;line-height:1.8">
          1. Log in to your domain's DNS provider<br>
          2. Add a <code>CNAME</code> record:<br>
          <div style="background:#fff;border-radius:6px;padding:8px 12px;margin:8px 0;font-family:monospace;font-size:12px">
            <span style="color:#6366f1">Type:</span> CNAME<br>
            <span style="color:#6366f1">Name:</span> billing (or @)<br>
            <span style="color:#6366f1">Value:</span> <?=h($server_host)?>
          </div>
          3. Wait for DNS propagation (up to 48h)<br>
          4. Click "Provision SSL" above
        </div>
      </div>

      <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="domain">
        <div class="bp-form-group"><label class="bp-label">Custom Domain</label>
          <input type="text" name="custom_domain" class="bp-input" value="<?=h($reseller['custom_domain']??'')?>" placeholder="billing.yourdomain.com" style="font-family:monospace">
          <div class="bp-input-hint">Leave blank to remove custom domain.</div>
        </div>
        <button type="submit" class="bp-btn bp-btn-primary">Save Domain</button>
      </form>
    </div></div>

    <!-- Pricing preview -->
    <div class="bp-card" style="margin-top:16px"><div class="bp-card-header"><h3 class="bp-card-title">💡 Your Pricing Margins</h3></div><div class="bp-card-body">
      <div style="font-size:13px;color:#374151;line-height:1.9">
        <?php
        $markup=(float)($reseller['markup_percentage']??20);
        $default_disc=(float)DB::setting('reseller_default_discount',20);
        $sample=10000;
        $ws=round($sample*(1-$default_disc/100),2);
        $retail=round($ws*(1+$markup/100),2);
        $profit=$retail-$ws;
        ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
          <div style="background:#f8fafc;border-radius:8px;padding:12px;text-align:center">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:4px">You Pay</div>
            <div style="font-size:18px;font-weight:800;color:#0f172a"><?=format_currency($ws,$currency)?></div>
            <div style="font-size:11px;color:#64748b">wholesale</div>
          </div>
          <div style="background:#f0fdf4;border-radius:8px;padding:12px;text-align:center">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#166534;margin-bottom:4px">Client Pays</div>
            <div style="font-size:18px;font-weight:800;color:#166534"><?=format_currency($retail,$currency)?></div>
            <div style="font-size:11px;color:#64748b">retail</div>
          </div>
        </div>
        <div style="text-align:center;margin-top:12px;padding:10px;background:#eff6ff;border-radius:8px">
          <span style="font-size:14px;font-weight:700;color:#1e40af">Your profit per <?=format_currency($sample,$currency)?> service: <span style="color:#10b981"><?=format_currency($profit,$currency)?></span></span>
        </div>
        <div style="font-size:11px;color:#94a3b8;margin-top:8px;text-align:center">Based on sample retail price of <?=format_currency($sample,$currency)?></div>
      </div>
    </div></div>
  </div>
</div>
</div>
<script>
const colorInput=document.querySelector('[name="branding_color"]');
const hexInput=document.getElementById('color-hex');
const preview=document.getElementById('preview');
const pName=document.getElementById('preview-name');
const nameInput=document.querySelector('[name="branding_name"]');
if(colorInput&&hexInput){
  colorInput.addEventListener('input',()=>{hexInput.value=colorInput.value;if(preview)preview.style.background=colorInput.value;});
  hexInput.addEventListener('input',()=>{if(/^#[0-9a-f]{6}$/i.test(hexInput.value)){colorInput.value=hexInput.value;if(preview)preview.style.background=hexInput.value;}});
}
if(nameInput&&pName) nameInput.addEventListener('input',()=>{pName.textContent=nameInput.value||'My Company';});
</script>
<?php include 'partials/footer.php';?>
