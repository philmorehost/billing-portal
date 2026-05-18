<?php
require_once '../includes/config.php';
$client=Auth::requireClient(); $company=DB::setting('company_name','Billing Portal'); $page_title='Security & 2FA';
$error=''; $success='';

if(is_post()&&csrf_verify()){
    $action=post('action');
    if($action==='enable_2fa'){
        $secret=trim(post('secret')); $code=trim(post('code'));
        if(!Auth::verify2FA($secret,$code)){$error='Invalid verification code. Please try again.';}
        else{
            DB::execute("UPDATE clients SET two_factor_secret=?,two_factor_enabled=1 WHERE id=?",'si',[$secret,$client['id']]);
            $success='Two-factor authentication enabled successfully!';
            $client=DB::row("SELECT * FROM clients WHERE id=?",'i',[$client['id']]);
        }
    }
    if($action==='disable_2fa'){
        $pw=post('confirm_password');
        if(!password_verify($pw,$client['password'])){$error='Incorrect password.';}
        else{
            DB::execute("UPDATE clients SET two_factor_secret=NULL,two_factor_enabled=0 WHERE id=?",'i',[$client['id']]);
            $success='Two-factor authentication disabled.';
            $client=DB::row("SELECT * FROM clients WHERE id=?",'i',[$client['id']]);
        }
    }
}

// Generate new secret for setup
$setup_secret=Auth::generate2FASecret();
$qr_url='otpauth://totp/'.urlencode($company.':'.$client['email']).'?secret='.$setup_secret.'&issuer='.urlencode($company);
$qr_img='https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='.urlencode($qr_url);
include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title">Security Settings</h1>
<?php if($error):?><div class="alert-custom alert-danger mb-3"><span>✕</span> <?=h($error)?></div><?php endif?>
<?php if($success):?><div class="alert-custom alert-success mb-3"><span>✓</span> <?=h($success)?></div><?php endif?>

<div class="row g-4">
  <div class="col-lg-6">
    <!-- 2FA Status -->
    <div class="bp-card"><div class="bp-card-header"><h3 class="bp-card-title">🔐 Two-Factor Authentication</h3></div><div class="bp-card-body">
      <?php if($client['two_factor_enabled']): ?>
      <div style="display:flex;align-items:center;gap:12px;padding:16px;background:#f0fdf4;border-radius:10px;margin-bottom:20px">
        <div style="font-size:32px">✅</div>
        <div><div style="font-weight:700;color:#166534">2FA is Active</div><div style="font-size:13px;color:#4b7c59">Your account is protected with an authenticator app.</div></div>
      </div>
      <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="disable_2fa">
        <div class="bp-form-group"><label class="bp-label">Confirm Password to Disable</label><input type="password" name="confirm_password" class="bp-input" placeholder="Enter your password" required></div>
        <button type="submit" class="bp-btn bp-btn-outline" style="color:#ef4444;border-color:#fecdd3" onclick="return confirm('Disable 2FA? Your account will be less secure.')">Disable 2FA</button>
      </form>

      <?php else: ?>
      <div style="display:flex;align-items:center;gap:12px;padding:16px;background:#fffbeb;border-radius:10px;margin-bottom:20px">
        <div style="font-size:32px">⚠️</div>
        <div><div style="font-weight:700;color:#92400e">2FA Not Enabled</div><div style="font-size:13px;color:#92400e">Enable 2FA to add an extra layer of security.</div></div>
      </div>
      <p style="font-size:13px;color:#374151;margin-bottom:16px;line-height:1.7">Scan the QR code with an authenticator app like <strong>Google Authenticator</strong>, <strong>Authy</strong>, or <strong>1Password</strong>. Then enter the 6-digit code to confirm.</p>
      <div style="text-align:center;margin-bottom:20px">
        <img src="<?=h($qr_img)?>" alt="QR Code" style="border-radius:12px;border:4px solid #f1f5f9" width="180" height="180">
        <div style="margin-top:12px;font-size:13px;color:#64748b">Or enter manually:</div>
        <code style="background:#f1f5f9;padding:6px 14px;border-radius:8px;font-size:14px;letter-spacing:2px;display:inline-block;margin-top:6px"><?=h($setup_secret)?></code>
      </div>
      <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="enable_2fa"><input type="hidden" name="secret" value="<?=h($setup_secret)?>">
        <div class="bp-form-group"><label class="bp-label">Enter Verification Code</label><input type="text" name="code" class="bp-input" placeholder="000000" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" required style="letter-spacing:6px;font-size:20px;text-align:center"></div>
        <button type="submit" class="bp-btn bp-btn-success" style="width:100%;justify-content:center">✓ Enable Two-Factor Auth</button>
      </form>
      <?php endif?>
    </div></div>
  </div>

  <div class="col-lg-6">
    <!-- Login history -->
    <div class="bp-card"><div class="bp-card-header"><h3 class="bp-card-title">🕐 Login History</h3></div>
    <div class="bp-card-body">
      <?php $logins=DB::rows("SELECT * FROM activity_log WHERE actor_type='client' AND actor_id=? AND action='client_login' ORDER BY id DESC LIMIT 8",'i',[$client['id']]);?>
      <?php if($logins): foreach($logins as $l):?>
      <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #f8fafc">
        <div style="width:36px;height:36px;border-radius:10px;background:#f0f9ff;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:16px">🔑</div>
        <div style="flex:1"><div style="font-size:13px;font-weight:600">Login</div><div style="font-size:12px;color:#94a3b8;font-family:monospace"><?=h($l['ip_address']??'')?></div></div>
        <div style="font-size:12px;color:#64748b"><?=time_ago($l['created_at'])?></div>
      </div>
      <?php endforeach; else:?><div style="text-align:center;color:#94a3b8;padding:20px;font-size:14px">No login history yet.</div><?php endif?>
    </div></div>
  </div>
</div>
</div>
<?php include 'partials/footer.php';?>
