<?php
require_once '../includes/config.php';
$client=Auth::requireClient(); $company=DB::setting('company_name','Billing Portal'); $page_title='My Profile';
$error=''; $success='';

if(is_post()&&csrf_verify()){
    $action=post('action');
    if($action==='update_profile'){
        $fn=trim(post('first_name')); $ln=trim(post('last_name'));
        $ph=trim(post('phone')); $co=trim(post('company'));
        $ci=trim(post('city')); $st=trim(post('state')); $pc=trim(post('postcode'));
        if(!$fn||!$ln){$error='Name is required.';}
        else{
            DB::execute("UPDATE clients SET first_name=?,last_name=?,phone=?,company=?,city=?,state=?,postcode=? WHERE id=?",'sssssssi',[$fn,$ln,$ph,$co,$ci,$st,$pc,$client['id']]);
            $success='Profile updated successfully.';
            $client=DB::row("SELECT * FROM clients WHERE id=?",'i',[$client['id']]);
        }
    }
    if($action==='change_password'){
        $cur=post('current_password'); $new=post('new_password'); $conf=post('confirm_password');
        if(!password_verify($cur,$client['password'])){$error='Current password is incorrect.';}
        elseif(strlen($new)<8){$error='New password must be at least 8 characters.';}
        elseif($new!==$conf){$error='New passwords do not match.';}
        else{
            DB::execute("UPDATE clients SET password=? WHERE id=?",'si',[Auth::hashPassword($new),$client['id']]);
            $success='Password changed successfully.';
        }
    }
}
$currency=DB::setting('base_currency','NGN');
if(!is_post()) $_POST=array_merge($_POST,(array)$client);
include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title">My Profile</h1>
<?php if($error):?><div class="alert-custom alert-danger mb-3"><span>✕</span> <?=h($error)?></div><?php endif?>
<?php if($success):?><div class="alert-custom alert-success mb-3"><span>✓</span> <?=h($success)?></div><?php endif?>
<?=flash_html()?>
<div class="row g-4">
  <div class="col-lg-8">
    <!-- Profile form -->
    <div class="bp-card"><div class="bp-card-header"><h3 class="bp-card-title">Personal Information</h3></div><div class="bp-card-body">
      <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="update_profile">
        <div class="bp-form-row bp-form-row-2">
          <div class="bp-form-group"><label class="bp-label">First Name *</label><input type="text" name="first_name" class="bp-input" value="<?=h(post('first_name'))?>" required></div>
          <div class="bp-form-group"><label class="bp-label">Last Name *</label><input type="text" name="last_name" class="bp-input" value="<?=h(post('last_name'))?>" required></div>
          <div class="bp-form-group"><label class="bp-label">Phone</label><input type="tel" name="phone" class="bp-input" value="<?=h(post('phone'))?>"></div>
          <div class="bp-form-group"><label class="bp-label">Company</label><input type="text" name="company" class="bp-input" value="<?=h(post('company'))?>"></div>
          <div class="bp-form-group"><label class="bp-label">City</label><input type="text" name="city" class="bp-input" value="<?=h(post('city'))?>"></div>
          <div class="bp-form-group"><label class="bp-label">State / Region</label><input type="text" name="state" class="bp-input" value="<?=h(post('state'))?>"></div>
          <div class="bp-form-group"><label class="bp-label">Postcode</label><input type="text" name="postcode" class="bp-input" value="<?=h(post('postcode'))?>"></div>
        </div>
        <div class="bp-form-group"><label class="bp-label">Email Address</label><input type="email" class="bp-input" value="<?=h($client['email'])?>" disabled style="opacity:.6"><div class="bp-input-hint">Contact support to change your email address.</div></div>
        <button type="submit" class="bp-btn bp-btn-primary">💾 Save Profile</button>
      </form>
    </div></div>

    <!-- Change password -->
    <div class="bp-card" style="margin-top:16px"><div class="bp-card-header"><h3 class="bp-card-title">Change Password</h3></div><div class="bp-card-body">
      <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="change_password">
        <div class="bp-form-row bp-form-row-2">
          <div class="bp-form-group" style="grid-column:1/-1"><label class="bp-label">Current Password *</label><input type="password" name="current_password" class="bp-input" required></div>
          <div class="bp-form-group"><label class="bp-label">New Password *</label><input type="password" name="new_password" class="bp-input" minlength="8" required></div>
          <div class="bp-form-group"><label class="bp-label">Confirm New Password *</label><input type="password" name="confirm_password" class="bp-input" required></div>
        </div>
        <button type="submit" class="bp-btn bp-btn-primary">🔑 Change Password</button>
      </form>
    </div></div>
  </div>

  <div class="col-lg-4">
    <!-- Account summary card -->
    <div class="bp-card"><div class="bp-card-body" style="text-align:center;padding:32px 20px">
      <div style="width:70px;height:70px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#06b6d4);display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;color:#fff;margin:0 auto 16px"><?=strtoupper(substr($client['first_name'],0,1))?></div>
      <div style="font-size:18px;font-weight:700"><?=h($client['first_name'].' '.$client['last_name'])?></div>
      <div style="font-size:13px;color:#64748b;margin-top:4px"><?=h($client['email'])?></div>
      <div style="margin-top:20px;padding-top:20px;border-top:1px solid #f1f5f9">
        <?php foreach([['Account Type',ucfirst($client['account_type'])],['Status',ucfirst($client['status'])],['Credit Balance',format_currency($client['credit_balance'],$currency)],['Member Since',format_date($client['created_at'])]] as [$l,$v]):?>
        <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid #f8fafc;font-size:13px"><span style="color:#64748b"><?=$l?></span><span style="font-weight:600"><?=$v?></span></div>
        <?php endforeach?>
      </div>
    </div></div>
    <div class="bp-card" style="margin-top:12px"><div class="bp-card-body">
      <a href="security.php" class="bp-btn bp-btn-outline" style="width:100%;justify-content:center;margin-bottom:8px">🔐 Manage 2FA</a>
      <a href="affiliate.php" class="bp-btn bp-btn-outline" style="width:100%;justify-content:center">🤝 Affiliate Program</a>
    </div></div>
  </div>
</div>
</div>
<?php include 'partials/footer.php';?>
