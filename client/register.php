<?php
require_once '../includes/config.php';
if(Auth::isClientLoggedIn()) redirect(BASE_URL.'/client/');
$company=DB::setting('company_name','Billing Portal'); $errors=[];
if(is_post()&&csrf_verify()){
    $first=trim(post('first_name')); $last=trim(post('last_name')); $email=strtolower(trim(post('email')));
    $pw=post('password'); $pw2=post('confirm_password'); $tos=!empty($_POST['tos_accept']);
    if(!$first||!$last) $errors[]='First and last name required.';
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)) $errors[]='Valid email required.';
    if(strlen($pw)<8) $errors[]='Password must be at least 8 characters.';
    if($pw!==$pw2) $errors[]='Passwords do not match.';
    if(!$tos) $errors[]='You must accept the Terms of Service and Privacy Policy.';
    if(empty($errors)&&DB::value("SELECT id FROM clients WHERE email=?",'s',[$email])) $errors[]='Email already registered.';
    if(empty($errors)){
        $hash=Auth::hashPassword($pw); $token=generate_token();
        $aff_id=null;
        if(!empty($_COOKIE['ref'])){$a=DB::row("SELECT id FROM affiliates WHERE referral_code=? AND status='active'",'s',[$_COOKIE['ref']]);if($a)$aff_id=$a['id'];}
        $r=DB::execute("INSERT INTO clients (first_name,last_name,email,password,phone,company,email_verify_token,tos_accepted,tos_accepted_at,affiliate_id,status) VALUES (?,?,?,?,?,?,?,1,NOW(),?,'active')",'sssssssi',[$first,$last,$email,$hash,trim(post('phone')),trim(post('company')),$token,$aff_id]);
        $cid=$r['insert_id'];
        log_activity('client_register',"New registration: {$email}",'client',$cid);
        Mailer::sendTemplate($email,"$first $last",'welcome',['client_name'=>$first,'login_url'=>BASE_URL.'/client/login.php']);
        $client=DB::row("SELECT * FROM clients WHERE id=?",'i',[$cid]);
        Auth::setClientSession($client);
        redirect(BASE_URL.'/client/?welcome=1');
    }
}
?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Create Account — <?=h($company)?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?=BASE_URL?>/assets/css/style.css" rel="stylesheet">
<style>body{background:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:32px 16px}
.rw{width:100%;max-width:520px}.rl{text-align:center;margin-bottom:24px}
.rl a{display:inline-flex;align-items:center;gap:10px;text-decoration:none}
.rli{width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#0f172a,#3b82f6);display:flex;align-items:center;justify-content:center;font-size:22px;color:#fff}
.rln{font-size:20px;font-weight:800;color:#0f172a}
.rc{background:#fff;border-radius:20px;box-shadow:0 4px 40px rgba(0,0,0,.09);padding:36px}
.rc h2{font-size:20px;font-weight:700;margin-bottom:4px}.rc p{color:#64748b;font-size:14px;margin-bottom:24px}
.form-label{font-size:13px;font-weight:600;color:#0f172a}.form-control{border:1.5px solid #e2e8f0;border-radius:10px;padding:10px 13px;font-size:14px}
.form-control:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
.btn-r{background:#0f172a;color:#fff;width:100%;padding:13px;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer}
.btn-r:hover{background:#1e293b}
.tos{background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:10px;padding:12px 16px;font-size:13px}
.tos a{color:#3b82f6;font-weight:500;text-decoration:none}
.pws{height:4px;border-radius:2px;margin-top:6px;background:#e2e8f0;transition:all .3s}
.pws.weak{background:#ef4444;width:33%}.pws.medium{background:#f59e0b;width:66%}.pws.strong{background:#10b981;width:100%}</style>
</head><body><div class="rw">
<div class="rl"><a href="<?=BASE_URL?>"><div class="rli">⚡</div><span class="rln"><?=h($company)?></span></a></div>
<div class="rc"><h2>Create an account</h2><p>Start managing your hosting and domains.</p>
<?php if(!empty($errors)):?><div class="alert-custom alert-danger mb-3"><span>✕</span><div><?=implode('<br>',array_map('htmlspecialchars',$errors))?></div></div><?php endif?>
<form method="POST"><?=csrf_input()?>
<div class="row g-3 mb-3">
  <div class="col-6"><label class="form-label">First Name *</label><input type="text" name="first_name" class="form-control" value="<?=h(post('first_name'))?>" required></div>
  <div class="col-6"><label class="form-label">Last Name *</label><input type="text" name="last_name" class="form-control" value="<?=h(post('last_name'))?>" required></div>
</div>
<div class="mb-3"><label class="form-label">Email Address *</label><input type="email" name="email" class="form-control" value="<?=h(post('email'))?>" placeholder="you@example.com" required></div>
<div class="mb-3"><label class="form-label">Phone</label><input type="tel" name="phone" class="form-control" value="<?=h(post('phone'))?>" placeholder="+234 800 000 0000"></div>
<div class="mb-3"><label class="form-label">Company (optional)</label><input type="text" name="company" class="form-control" value="<?=h(post('company'))?>"></div>
<div class="mb-3"><label class="form-label">Password *</label><input type="password" name="password" id="pw" class="form-control" placeholder="Min. 8 characters" minlength="8" required><div class="pws" id="pws"></div></div>
<div class="mb-4"><label class="form-label">Confirm Password *</label><input type="password" name="confirm_password" id="pw2" class="form-control" required></div>
<div class="tos mb-4"><div class="form-check"><input type="checkbox" name="tos_accept" id="tos" class="form-check-input" <?=!empty($_POST['tos_accept'])?'checked':''?> required>
<label class="form-check-label" for="tos">I agree to the <a href="<?=BASE_URL?>/terms.php" target="_blank">Terms of Service</a> and <a href="<?=BASE_URL?>/privacy.php" target="_blank">Privacy Policy</a>.</label></div></div>
<button type="submit" class="btn-r">Create Account →</button></form></div>
<div style="text-align:center;margin-top:16px;font-size:13px;color:#64748b">Already have an account? <a href="login.php" style="color:#3b82f6;font-weight:500;text-decoration:none">Sign in</a></div>
</div>
<script>
const pw=document.getElementById('pw'),pws=document.getElementById('pws');
pw.addEventListener('input',()=>{const v=pw.value;pws.className='pws '+(v.length<6?'weak':v.length<10||!/[A-Z]/.test(v)||!/[0-9]/.test(v)?'medium':'strong')});
document.getElementById('pw2').addEventListener('input',function(){this.style.borderColor=this.value===pw.value?'#10b981':'#ef4444'});
</script></body></html>
