<?php
require_once '../includes/config.php';
$company=DB::setting('company_name','Billing Portal'); $error='';
$id=(int)($_SESSION['2fa_pending_client']??0);
if(!$id) redirect(BASE_URL.'/client/login.php');
$client=DB::row("SELECT * FROM clients WHERE id=?",'i',[$id]);
if(!$client||!$client['two_factor_enabled']){unset($_SESSION['2fa_pending_client']);redirect(BASE_URL.'/client/login.php');}
if(is_post()&&csrf_verify()){
    if(Auth::verify2FA($client['two_factor_secret'],preg_replace('/\s/','',post('code')))){
        unset($_SESSION['2fa_pending_client']); Auth::setClientSession($client); redirect(BASE_URL.'/client/');
    } else {$error='Invalid code. Please try again.';}
}
?><!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>2FA — <?=h($company)?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?=BASE_URL?>/assets/css/style.css" rel="stylesheet">
<style>body{background:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:32px 16px}
.c2{background:#fff;border-radius:20px;box-shadow:0 4px 40px rgba(0,0,0,.09);padding:40px;max-width:400px;width:100%;text-align:center}
.ic{width:64px;height:64px;border-radius:18px;background:linear-gradient(135deg,#0f172a,#3b82f6);display:flex;align-items:center;justify-content:center;font-size:28px;color:#fff;margin:0 auto 20px}
.ci{letter-spacing:8px;font-size:22px;font-weight:700;text-align:center;border:1.5px solid #e2e8f0;border-radius:10px;padding:13px;width:100%}
.ci:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1);outline:none}
.bv{background:#0f172a;color:#fff;width:100%;padding:13px;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;margin-top:16px}</style>
</head><body><div class="c2">
<div class="ic">🔐</div>
<h2 style="font-size:20px;font-weight:700;margin-bottom:8px">Two-Factor Auth</h2>
<p style="color:#64748b;font-size:14px;margin-bottom:28px">Enter the 6-digit code from your authenticator app.</p>
<?php if($error):?><div class="alert-custom alert-danger mb-3"><span>✕</span> <?=h($error)?></div><?php endif?>
<form method="POST"><?=csrf_input()?>
<input type="text" name="code" class="ci" placeholder="000000" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" autofocus required>
<button type="submit" class="bv">Verify →</button></form>
<div style="margin-top:20px;font-size:13px;color:#64748b"><a href="<?=BASE_URL?>/client/login.php" style="color:#3b82f6;text-decoration:none">← Back to login</a></div>
</div></body></html>
