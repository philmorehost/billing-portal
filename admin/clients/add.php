<?php
require_once '../../includes/config.php';
$admin=Auth::requireAdmin(); $company=DB::setting('company_name','Billing Portal'); $page_title='Add Client';
$errors=[];
if(is_post()&&csrf_verify()){
    $fn=trim(post('first_name')); $ln=trim(post('last_name')); $email=strtolower(trim(post('email'))); $pw=post('password');
    $ph=trim(post('phone')); $co=trim(post('company')); $status=post('status','active'); $type=post('account_type','client');
    if(!$fn||!$ln) $errors[]='Name required.';
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)) $errors[]='Valid email required.';
    if(strlen($pw)<8) $errors[]='Password min 8 characters.';
    if(DB::value("SELECT id FROM clients WHERE email=?",'s',[$email])) $errors[]='Email already registered.';
    if(empty($errors)){
        $hash=Auth::hashPassword($pw);
        DB::execute("INSERT INTO clients (first_name,last_name,email,password,phone,company,account_type,status,tos_accepted,tos_accepted_at) VALUES (?,?,?,?,?,?,?,?,1,NOW())",'ssssssss',[$fn,$ln,$email,$hash,$ph,$co,$type,$status]);
        $cid=DB::lastInsertId();
        log_activity('admin_create_client',"Client created: {$email}",'admin',$admin['id']);
        Mailer::sendTemplate($email,"$fn $ln",'welcome',['client_name'=>$fn,'login_url'=>BASE_URL.'/client/login.php']);
        redirect_with_flash(BASE_URL.'/admin/clients/view.php?id='.$cid,'success','Client created and welcome email sent.');
    }
}
include '../partials/header.php';
?>
<div class="bp-content">
<div class="d-flex align-items-center gap-3 mb-4"><a href="<?=BASE_URL?>/admin/clients.php" class="bp-btn bp-btn-outline bp-btn-sm">← Back</a><h1 class="bp-page-title" style="margin:0">Add Client</h1></div>
<?php if(!empty($errors)):?><div class="alert-custom alert-danger mb-3"><span>✕</span><div><?=implode('<br>',array_map('htmlspecialchars',$errors))?></div></div><?php endif?>
<div class="row g-4">
  <div class="col-lg-7">
    <div class="bp-card"><div class="bp-card-body">
      <form method="POST"><?=csrf_input()?>
        <div class="bp-form-row bp-form-row-2">
          <div class="bp-form-group"><label class="bp-label">First Name *</label><input type="text" name="first_name" class="bp-input" value="<?=h(post('first_name'))?>" required></div>
          <div class="bp-form-group"><label class="bp-label">Last Name *</label><input type="text" name="last_name" class="bp-input" value="<?=h(post('last_name'))?>" required></div>
          <div class="bp-form-group"><label class="bp-label">Email *</label><input type="email" name="email" class="bp-input" value="<?=h(post('email'))?>" required></div>
          <div class="bp-form-group"><label class="bp-label">Phone</label><input type="tel" name="phone" class="bp-input" value="<?=h(post('phone'))?>"></div>
          <div class="bp-form-group"><label class="bp-label">Password * (min 8)</label><input type="password" name="password" class="bp-input" minlength="8" required></div>
          <div class="bp-form-group"><label class="bp-label">Company</label><input type="text" name="company" class="bp-input" value="<?=h(post('company'))?>"></div>
          <div class="bp-form-group"><label class="bp-label">Account Type</label>
            <select name="account_type" class="bp-select"><option value="client">Client</option><option value="reseller">Reseller</option></select>
          </div>
          <div class="bp-form-group"><label class="bp-label">Status</label>
            <select name="status" class="bp-select"><option value="active">Active</option><option value="pending">Pending</option><option value="inactive">Inactive</option></select>
          </div>
        </div>
        <button type="submit" class="bp-btn bp-btn-primary" style="margin-top:8px">Create Client</button>
      </form>
    </div></div>
  </div>
</div>
</div>
<?php include '../partials/footer.php';?>
