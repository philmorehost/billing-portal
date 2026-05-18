<?php
require_once '../includes/config.php';
$company=DB::setting('company_name','Billing Portal'); $error=''; $success='';
$token=trim(get_param('token')); if(!$token) redirect(BASE_URL.'/client/login.php');
$valid=DB::row("SELECT id FROM clients WHERE password_reset_token=? AND password_reset_expires>NOW()",'s',[$token]);
if(!$valid) $error='This reset link is invalid or has expired.';
if(is_post()&&csrf_verify()&&$valid){
    $pw=post('password'); $pw2=post('confirm');
    if(strlen($pw)<8){$error='Password must be at least 8 characters.';}
    elseif($pw!==$pw2){$error='Passwords do not match.';}
    else{Auth::resetPassword($token,$pw,'clients');$success='Password updated! You can now log in.';$valid=null;}
}
?><!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Reset Password — <?=h($company)?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?=BASE_URL?>/assets/css/style.css" rel="stylesheet">
<style>body{background:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:32px 16px}
.rc{background:#fff;border-radius:20px;box-shadow:0 4px 40px rgba(0,0,0,.09);padding:40px;max-width:420px;width:100%}
.rc h2{font-size:20px;font-weight:700;margin-bottom:6px}.rc p{color:#64748b;font-size:14px;margin-bottom:28px}
.form-label{font-size:13px;font-weight:600;color:#0f172a}.form-control{border:1.5px solid #e2e8f0;border-radius:10px;padding:11px 14px;font-size:14px}
.form-control:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
.bs{background:#0f172a;color:#fff;width:100%;padding:13px;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer}</style>
</head><body><div class="rc">
<h2>Set new password</h2><p>Choose a strong password for your account.</p>
<?php if($error):?><div class="alert-custom alert-danger mb-3"><span>✕</span> <?=h($error)?></div><?php endif?>
<?php if($success):?><div class="alert-custom alert-success mb-3"><span>✓</span> <?=h($success)?> <a href="login.php" style="color:#0f172a;font-weight:700">Login →</a></div><?php endif?>
<?php if($valid&&!$success):?>
<form method="POST"><?=csrf_input()?>
<div class="mb-3"><label class="form-label">New Password</label><input type="password" name="password" class="form-control" minlength="8" required></div>
<div class="mb-4"><label class="form-label">Confirm Password</label><input type="password" name="confirm" class="form-control" required></div>
<button type="submit" class="bs">Update Password</button></form>
<?php endif?>
<div style="text-align:center;margin-top:20px;font-size:13px"><a href="login.php" style="color:#3b82f6;text-decoration:none">← Back to login</a></div>
</div></body></html>
