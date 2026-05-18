<?php
require_once '../../includes/config.php';
require_once INC_PATH.'/modules/reseller.php';
if(empty($_SESSION['reseller_id'])) redirect(BASE_URL.'/reseller/login.php');
$reseller_id=$_SESSION['reseller_id'];
$reseller=DB::row("SELECT * FROM resellers WHERE id=?",'i',[$reseller_id]);
$reseller_client=DB::row("SELECT * FROM clients WHERE id=?",'i',[$reseller['client_id']]);
$company=DB::setting('company_name','Billing Portal');
$page_title='Add Client'; $errors=[];

if(is_post()&&csrf_verify()){
    $fn=trim(post('first_name')); $ln=trim(post('last_name')); $email=strtolower(trim(post('email'))); $pw=post('password');
    $ph=trim(post('phone')); $co=trim(post('company'));
    if(!$fn||!$ln) $errors[]='Name required.';
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)) $errors[]='Valid email required.';
    if(strlen($pw)<8) $errors[]='Password must be at least 8 characters.';
    if(DB::value("SELECT id FROM clients WHERE email=?",'s',[$email])) $errors[]='Email already registered.';
    if(empty($errors)){
        $hash=Auth::hashPassword($pw);
        DB::execute("INSERT INTO clients (first_name,last_name,email,password,phone,company,status,tos_accepted,tos_accepted_at) VALUES (?,?,?,?,?,?,'active',1,NOW())",'ssssss',[$fn,$ln,$email,$hash,$ph,$co]);
        $cid=DB::lastInsertId();
        $aff=DB::row("SELECT id FROM affiliates WHERE client_id=?",'i',[$reseller['client_id']]);
        if($aff) DB::execute("UPDATE clients SET affiliate_id=? WHERE id=?",'ii',[$aff['id'],$cid]);
        $branding=Reseller::getBranding($reseller_id);
        Mailer::sendTemplate($email,"$fn $ln",'welcome',['client_name'=>$fn,'company_name'=>$branding['name'],'login_url'=>BASE_URL.'/client/login.php']);
        redirect_with_flash(BASE_URL.'/reseller/clients/view.php?id='.$cid,'success','Client created.');
    }
}
include '../partials/header.php';
?>
<div class="bp-content">
<div class="d-flex align-items-center gap-3 mb-4"><a href="<?=BASE_URL?>/reseller/clients.php" class="bp-btn bp-btn-outline bp-btn-sm">← Back</a><h1 class="bp-page-title" style="margin:0">Add Client</h1></div>
<?php if(!empty($errors)):?><div class="alert-custom alert-danger mb-3"><span>✕</span><div><?=implode('<br>',array_map('htmlspecialchars',$errors))?></div></div><?php endif?>
<div class="row"><div class="col-lg-6">
<div class="bp-card"><div class="bp-card-body">
  <form method="POST"><?=csrf_input()?>
    <div class="bp-form-row bp-form-row-2">
      <div class="bp-form-group"><label class="bp-label">First Name *</label><input type="text" name="first_name" class="bp-input" value="<?=h(post('first_name'))?>" required></div>
      <div class="bp-form-group"><label class="bp-label">Last Name *</label><input type="text" name="last_name" class="bp-input" value="<?=h(post('last_name'))?>" required></div>
      <div class="bp-form-group"><label class="bp-label">Email *</label><input type="email" name="email" class="bp-input" value="<?=h(post('email'))?>" required></div>
      <div class="bp-form-group"><label class="bp-label">Phone</label><input type="tel" name="phone" class="bp-input" value="<?=h(post('phone'))?>"></div>
      <div class="bp-form-group"><label class="bp-label">Password * (min 8)</label><input type="password" name="password" class="bp-input" minlength="8" required></div>
      <div class="bp-form-group"><label class="bp-label">Company</label><input type="text" name="company" class="bp-input" value="<?=h(post('company'))?>"></div>
    </div>
    <button type="submit" class="bp-btn bp-btn-primary" style="margin-top:8px">Create Client</button>
  </form>
</div></div>
</div></div>
</div>
<?php include '../partials/footer.php';?>
