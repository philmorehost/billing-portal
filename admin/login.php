<?php
require_once '../includes/config.php';
if (Auth::isAdminLoggedIn()) redirect(BASE_URL.'/admin/');
$error=''; $company=DB::setting('company_name','Billing Portal');
if (!empty($_GET['token'])) {
    $a=DB::row("SELECT * FROM admins WHERE remember_token=? AND status='active'",'s',[$_GET['token']]);
    if ($a) { Auth::setAdminSession($a); redirect(BASE_URL.'/admin/'); }
}
if (is_post()&&csrf_verify()) {
    $r=Auth::adminLogin(trim(post('email')),post('password'),!empty($_POST['remember']));
    if ($r['success']) redirect(BASE_URL.'/admin/');
    elseif (!empty($r['require_2fa'])) redirect(BASE_URL.'/admin/2fa.php');
    else $error=$r['error'];
}
?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Admin Login — <?=h($company)?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?=BASE_URL?>/assets/css/style.css" rel="stylesheet">
<style>body{background:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:32px 16px}
.lw{width:100%;max-width:420px}.ll{text-align:center;margin-bottom:28px;display:flex;flex-direction:column;align-items:center;gap:10px}
.li{width:56px;height:56px;border-radius:16px;background:linear-gradient(135deg,#0f172a,#3b82f6);display:flex;align-items:center;justify-content:center;font-size:26px;color:#fff}
.ln{font-size:22px;font-weight:800;color:#0f172a}.ls{font-size:13px;color:#64748b;margin-top:-4px}
.lc{background:#fff;border-radius:20px;box-shadow:0 4px 40px rgba(0,0,0,.09);padding:36px}
.lc h2{font-size:20px;font-weight:700;margin-bottom:6px}.lc p{color:#64748b;font-size:14px;margin-bottom:28px}
.form-label{font-size:13px;font-weight:600;color:#0f172a}.form-control{border:1.5px solid #e2e8f0;border-radius:10px;padding:11px 14px;font-size:14px}
.form-control:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
.btn-l{background:#0f172a;color:#fff;width:100%;padding:13px;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer}
.btn-l:hover{background:#1e293b}.ie{position:relative}.ie input{padding-right:40px}.et{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94a3b8}</style>
</head><body><div class="lw">
<div class="ll"><div class="li">⚡</div><div class="ln"><?=h($company)?></div><div class="ls">Admin Panel</div></div>
<div class="lc"><h2>Welcome back</h2><p>Sign in to your admin account</p>
<?php if($error):?><div class="alert-custom alert-danger mb-3"><span>✕</span> <?=h($error)?></div><?php endif?>
<form method="POST"><?=csrf_input()?>
<div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?=h(post('email'))?>" required autofocus></div>
<div class="mb-3"><label class="form-label d-flex justify-content-between">Password <a href="forgot-password.php" style="color:#3b82f6;font-size:12px;text-decoration:none">Forgot?</a></label>
<div class="ie"><input type="password" name="password" id="pwd" class="form-control" required><button type="button" class="et" onclick="const i=document.getElementById('pwd');i.type=i.type==='password'?'text':'password'">👁</button></div></div>
<div class="form-check mb-4"><input type="checkbox" class="form-check-input" name="remember" id="rem"><label class="form-check-label" for="rem" style="font-size:13px">Remember me</label></div>
<button type="submit" class="btn-l">Sign In →</button></form></div>
<div style="text-align:center;margin-top:16px;font-size:13px;color:#64748b"><a href="<?=BASE_URL?>/client/login.php" style="color:#3b82f6;text-decoration:none">← Client Area</a></div>
</div></body></html>
