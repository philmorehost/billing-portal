<?php
require_once '../includes/config.php';
if(Auth::isClientLoggedIn()) redirect(BASE_URL.'/client/');
if (!empty($_SESSION['reseller_host_brand'])) {
    $company = $_SESSION['reseller_host_brand']['name'];
} else {
    $company=DB::setting('company_name','Billing Portal');
}
$error='';
if(is_post()&&csrf_verify()){
    $r=Auth::clientLogin(trim(post('email')),post('password'),!empty($_POST['remember']));
    if($r['success']) redirect(BASE_URL.'/client/');
    elseif(!empty($r['require_2fa'])) redirect(BASE_URL.'/client/2fa.php');
    else $error=$r['error'];
}
?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Login — <?=h($company)?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?=BASE_URL?>/assets/css/style.css" rel="stylesheet">
<?php if (!empty($_SESSION['reseller_host_brand'])): ?>
<style>
:root {
  --bp-primary: <?= h($_SESSION['reseller_host_brand']['color']) ?>;
  --bp-accent: <?= h($_SESSION['reseller_host_brand']['color']) ?>;
}
.al {
  background: linear-gradient(145deg, <?= h($_SESSION['reseller_host_brand']['color']) ?> 0%, #1e3a5f 100%) !important;
}
</style>
<?php endif; ?>
<style>body{background:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:32px 16px}
.aw{width:100%;max-width:880px;display:grid;grid-template-columns:1fr 1fr;gap:0;border-radius:20px;overflow:hidden;box-shadow:0 8px 60px rgba(0,0,0,.12)}
.al{background:linear-gradient(145deg,#0f172a 0%,#1e3a5f 60%,#0e4f8a 100%);padding:48px 40px;display:flex;flex-direction:column;justify-content:space-between}
.ab{display:flex;align-items:center;gap:12px;text-decoration:none}
.abi{width:44px;height:44px;border-radius:12px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:22px}
.abn{color:#fff;font-size:20px;font-weight:800}
.ah{margin-top:48px}.ah h2{color:#fff;font-size:28px;font-weight:800;line-height:1.3;margin-bottom:16px}.ah p{color:rgba(255,255,255,.6);font-size:14px;line-height:1.7}
.af{margin-top:32px}.afi{display:flex;align-items:center;gap:12px;margin-bottom:14px}
.afic{width:32px;height:32px;border-radius:8px;background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.afit{color:rgba(255,255,255,.75);font-size:13px}
.ar{background:#fff;padding:48px 40px;display:flex;flex-direction:column;justify-content:center}
.ar h2{font-size:22px;font-weight:800;color:#0f172a;margin-bottom:6px}.ar p{color:#64748b;font-size:14px;margin-bottom:32px}
.form-label{font-size:13px;font-weight:600;color:#0f172a}.form-control{border:1.5px solid #e2e8f0;border-radius:10px;padding:11px 14px;font-size:14px}
.form-control:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
.btn-si{background:#0f172a;color:#fff;width:100%;padding:13px;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer}
.btn-si:hover{background:#1e293b}
@media(max-width:640px){.aw{grid-template-columns:1fr}.al{display:none}}</style>
</head><body><div class="aw">
<div class="al">
  <a href="<?=BASE_URL?>" class="ab"><div class="abi">⚡</div><span class="abn"><?=h($company)?></span></a>
  <div>
    <div class="ah"><h2>Manage your hosting & domains in one place</h2><p>View invoices, manage services, open tickets and more.</p></div>
    <div class="af">
      <div class="afi"><div class="afic">🧾</div><div class="afit">View and pay invoices instantly</div></div>
      <div class="afi"><div class="afic">🖥</div><div class="afit">Manage all your hosting services</div></div>
      <div class="afi"><div class="afic">🌐</div><div class="afit">Domain registration & management</div></div>
      <div class="afi"><div class="afic">🎫</div><div class="afit">24/7 support ticket system</div></div>
    </div>
  </div>
</div>
<div class="ar">
  <h2>Sign in</h2><p>Enter your credentials to access your account.</p>
  <?php if($error):?><div class="alert-custom alert-danger mb-3"><span>✕</span> <?=h($error)?></div><?php endif?>
  <form method="POST"><?=csrf_input()?>
  <div class="mb-3"><label class="form-label">Email Address</label><input type="email" name="email" class="form-control" value="<?=h(post('email'))?>" placeholder="you@example.com" required autofocus></div>
  <div class="mb-3"><label class="form-label d-flex justify-content-between">Password <a href="forgot-password.php" style="color:#3b82f6;font-size:12px;text-decoration:none">Forgot?</a></label><input type="password" name="password" class="form-control" placeholder="••••••••" required></div>
  <div class="form-check mb-4"><input type="checkbox" name="remember" class="form-check-input" id="rem"><label class="form-check-label" for="rem" style="font-size:13px">Keep me signed in for 30 days</label></div>
  <button type="submit" class="btn-si">Sign In →</button></form>
  <div style="text-align:center;margin-top:20px;font-size:13px;color:#64748b">Don't have an account? <a href="register.php" style="color:#3b82f6;font-weight:500;text-decoration:none">Create one</a></div>
</div>
</div></body></html>
