<?php
require_once '../includes/config.php';
$admin=Auth::requireAdmin(); $company=DB::setting('company_name','Billing Portal'); $page_title='Staff & Roles';

if(is_post()&&csrf_verify()){
    $action=post('action');
    if($action==='create_staff'){
        $name=trim(post('name')); $email=trim(post('email')); $pw=post('password'); $role_id=(int)post('role_id');
        if(!$name||!$email||!$pw){redirect_with_flash('staff.php','danger','All fields required.');}
        if(DB::value("SELECT id FROM admins WHERE email=?",'s',[$email])){redirect_with_flash('staff.php','danger','Email already exists.');}
        $hash=Auth::hashPassword($pw);
        DB::execute("INSERT INTO admins (name,email,password,role_id,status) VALUES (?,?,?,?,'active')",'sssi',[$name,$email,$hash,$role_id]);
        log_activity('staff_created',"Staff account created: {$email}",'admin',$admin['id']);
        redirect_with_flash('staff.php','success','Staff account created.');
    }
    if($action==='update_role'){
        $sid=(int)post('staff_id'); $role=(int)post('role_id');
        if($sid!==$admin['id']) DB::execute("UPDATE admins SET role_id=? WHERE id=?",'ii',[$role,$sid]);
        redirect_with_flash('staff.php','success','Role updated.');
    }
    if($action==='toggle_status'){
        $sid=(int)post('staff_id');
        if($sid!==$admin['id']){$cur=DB::value("SELECT status FROM admins WHERE id=?",'i',[$sid]);DB::execute("UPDATE admins SET status=? WHERE id=?",'si',[$cur==='active'?'inactive':'active',$sid]);}
        redirect_with_flash('staff.php','success','Status updated.');
    }
    if($action==='create_role'){
        $name=trim(post('role_name')); $perms=post('permissions',[]);
        $perm_map=[];
        foreach($perms as $p) $perm_map[$p]=true;
        DB::execute("INSERT INTO admin_roles (name,permissions) VALUES (?,?)",'ss',[$name,json_encode($perm_map)]);
        redirect_with_flash('staff.php','success','Role created.');
    }
}

$staff=DB::rows("SELECT a.*,r.name AS role_name FROM admins a LEFT JOIN admin_roles r ON r.id=a.role_id ORDER BY a.id ASC");
$roles=DB::rows("SELECT * FROM admin_roles ORDER BY id ASC");
$all_perms=['clients'=>'Manage Clients','invoices'=>'Manage Invoices','transactions'=>'View Transactions','products'=>'Manage Products','services'=>'Manage Services','tickets'=>'Manage Tickets','reports'=>'View Reports','settings'=>'Change Settings','staff'=>'Manage Staff','resellers'=>'Manage Resellers'];
include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title">Staff & Roles</h1>
<?=flash_html()?>
<div class="row g-4">
  <!-- Staff list -->
  <div class="col-lg-8">
    <div class="bp-card">
      <div class="bp-card-header"><h3 class="bp-card-title">Staff Accounts (<?=count($staff)?>)</h3></div>
      <table class="bp-table"><thead><tr><th>Staff Member</th><th>Role</th><th>Last Login</th><th>Status</th><th>Actions</th></tr></thead><tbody>
      <?php foreach($staff as $s):?>
      <tr>
        <td><div style="font-weight:600"><?=h($s['name'])?></div><div style="font-size:12px;color:#64748b"><?=h($s['email'])?></div></td>
        <td>
          <form method="POST" style="display:inline"><?=csrf_input()?><input type="hidden" name="action" value="update_role"><input type="hidden" name="staff_id" value="<?=$s['id']?>">
            <select name="role_id" class="bp-select" style="padding:5px 10px;font-size:12px" onchange="this.form.submit()" <?=$s['id']==$admin['id']?'disabled':''?>>
              <?php foreach($roles as $r):?><option value="<?=$r['id']?>" <?=$s['role_id']==$r['id']?'selected':''?>><?=h($r['name'])?></option><?php endforeach?>
            </select>
          </form>
        </td>
        <td style="font-size:12px;color:#64748b"><?=$s['last_login']?time_ago($s['last_login']):'Never'?></td>
        <td><span class="bp-badge bp-badge-<?=$s['status']==='active'?'success':'muted'?>"><?=$s['status']?></span></td>
        <td>
          <?php if($s['id']!=$admin['id']):?>
          <form method="POST" style="display:inline"><?=csrf_input()?><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="staff_id" value="<?=$s['id']?>"><button type="submit" class="bp-btn bp-btn-outline bp-btn-sm"><?=$s['status']==='active'?'Disable':'Enable'?></button></form>
          <?php else:?><span style="font-size:12px;color:#94a3b8">You</span><?php endif?>
        </td>
      </tr>
      <?php endforeach?></tbody></table>
    </div>

    <!-- Roles -->
    <div class="bp-card" style="margin-top:16px">
      <div class="bp-card-header"><h3 class="bp-card-title">Roles & Permissions</h3></div>
      <?php foreach($roles as $r):$perms=json_decode($r['permissions'],true)??[];?>
      <div style="padding:16px 20px;border-bottom:1px solid #f8fafc">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
          <div style="font-weight:700"><?=h($r['name'])?></div>
          <?php if(!empty($perms['all'])):?><span class="bp-badge bp-badge-success">Full Access</span><?php endif?>
        </div>
        <?php if(empty($perms['all'])):?>
        <div style="display:flex;flex-wrap:wrap;gap:6px">
          <?php foreach($all_perms as $k=>$label):?>
          <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;background:<?=!empty($perms[$k])?'#eff6ff':'#f8fafc'?>;color:<?=!empty($perms[$k])?'#1e40af':'#94a3b8'?>"><?=$label?></span>
          <?php endforeach?>
        </div>
        <?php endif?>
      </div>
      <?php endforeach?>
    </div>
  </div>

  <!-- Sidepanel: create staff + create role -->
  <div class="col-lg-4">
    <div class="bp-card"><div class="bp-card-header"><h3 class="bp-card-title">➕ Add Staff</h3></div><div class="bp-card-body">
      <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="create_staff">
        <div class="bp-form-group"><label class="bp-label">Full Name *</label><input type="text" name="name" class="bp-input" required></div>
        <div class="bp-form-group"><label class="bp-label">Email *</label><input type="email" name="email" class="bp-input" required></div>
        <div class="bp-form-group"><label class="bp-label">Password *</label><input type="password" name="password" class="bp-input" minlength="8" required></div>
        <div class="bp-form-group"><label class="bp-label">Role *</label>
          <select name="role_id" class="bp-select" required>
            <?php foreach($roles as $r):?><option value="<?=$r['id']?>"><?=h($r['name'])?></option><?php endforeach?>
          </select>
        </div>
        <button type="submit" class="bp-btn bp-btn-primary" style="width:100%;justify-content:center">Create Account</button>
      </form>
    </div></div>

    <div class="bp-card" style="margin-top:16px"><div class="bp-card-header"><h3 class="bp-card-title">➕ Create Role</h3></div><div class="bp-card-body">
      <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="create_role">
        <div class="bp-form-group"><label class="bp-label">Role Name *</label><input type="text" name="role_name" class="bp-input" required></div>
        <div class="bp-form-group"><label class="bp-label">Permissions</label>
          <div style="display:flex;flex-direction:column;gap:8px">
            <?php foreach($all_perms as $k=>$label):?>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer"><input type="checkbox" name="permissions[]" value="<?=$k?>"> <?=$label?></label>
            <?php endforeach?>
          </div>
        </div>
        <button type="submit" class="bp-btn bp-btn-primary" style="width:100%;justify-content:center;margin-top:8px">Create Role</button>
      </form>
    </div></div>
  </div>
</div>
</div>
<?php include 'partials/footer.php';?>
