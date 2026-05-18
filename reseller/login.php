<?php
require_once '../includes/config.php';
$company=DB::setting('company_name','Billing Portal');

// Check if accessed from custom domain → brand accordingly
$host_reseller=null;
$host=strtolower(explode(':',$_SERVER['HTTP_HOST']??'')[0]);
$main=strtolower(explode(':',parse_url(BASE_URL,PHP_URL_HOST)??'localhost')[0]);
if($host!==$main&&$host!=='www.'.$main){
    $host_reseller=DB::row("SELECT r.*,c.first_name FROM resellers r JOIN clients c ON c.id=r.client_id WHERE r.custom_domain=? AND r.status='active'",'s',[$host]);
    if(!$host_reseller){include INC_PATH.'/error-unauthorized-host.php';exit;}
    $company=$host_reseller['branding_name']?:$company;
}

if(Auth::isClientLoggedIn()){
    $r=DB::row("SELECT id FROM resellers WHERE client_id=?",'i',[$_SESSION['client_id']]);
    if($r){$_SESSION['reseller_id']=$r['id'];redirect(BASE_URL.'/reseller/');}
}

$error='';
if(is_post()&&csrf_verify()){
    $result=Auth::clientLogin(trim(post('email')),post('password'),!empty($_POST['remember']));
    if($result['success']){
        $r=DB::row("SELECT id FROM resellers WHERE client_id=? AND status='active'",'i',[$_SESSION['client_id']]);
        if(!$r){Auth::clientLogout();$error='No active reseller account for this login.';}
        else{$_SESSION['reseller_id']=$r['id'];log_activity('reseller_login','Reseller portal login','client',$_SESSION['client_id']);redirect(BASE_URL.'/reseller/');}
    } elseif(!empty($result['require_2fa'])){
        redirect(BASE_URL.'/reseller/2fa.php');
    } else {$error=$result['error'];}
}

$bg=$host_reseller?($host_reseller['branding_color']??'#0f172a'):'#0f172a';
?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Reseller Login — <?=h($company)?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?=BASE_URL?>/assets/css/style.css" rel="stylesheet">
<style>body{background:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:32px 16px}
.lw{width:100%;max-width:440px}.ll{text-align:center;margin-bottom:28px}
.li{width:64px;height:64px;border-radius:18px;background:<?=h($bg)?>;display:inline-flex;align-items:center;justify-content:center;font-size:28px;color:#fff;margin-bottom:12px}
.ln{font-size:22px;font-weight:800;color:#0f172a}.ls{font-size:13px;color:#64748b;margin-top:-4px}
.lc{background:#fff;border-radius:20px;box-shadow:0 4px 40px rgba(0,0,0,.1);padding:36px}
.lc h2{font-size:20px;font-weight:700;margin-bottom:4px}.lc p{color:#64748b;font-size:14px;margin-bottom:24px}
.form-label{font-size:13px;font-weight:600;color:#0f172a}.form-control{border:1.5px solid #e2e8f0;border-radius:10px;padding:11px 14px;font-size:14px}
.form-control:focus{border-color:<?=h($bg)?>;box-shadow:0 0 0 3px <?=h($bg)?>22}
.btn-l{background:<?=h($bg)?>;color:#fff;width:100%;padding:13px;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer}</style>
</head><body><div class="lw">
<div class="ll"><div class="li">🏪</div><div class="ln"><?=h($company)?></div><div class="ls">Reseller Portal</div></div>
<div class="lc"><h2>Reseller Sign In</h2><p>Access your reseller dashboard</p>
<?php if($error):?><div class="alert-custom alert-danger mb-3"><span>✕</span> <?=h($error)?></div><?php endif?>
<form method="POST"><?=csrf_input()?>
<div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?=h(post('email'))?>" required autofocus></div>
<div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
<div class="form-check mb-4"><input type="checkbox" name="remember" class="form-check-input" id="rem"><label class="form-check-label" for="rem" style="font-size:13px">Remember me</label></div>
<button type="submit" class="btn-l">Sign In to Reseller Portal →</button></form></div>
</div></body></html>
