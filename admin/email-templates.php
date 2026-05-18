<?php
require_once '../includes/config.php';
$admin=Auth::requireAdmin(); $company=DB::setting('company_name','Billing Portal'); $page_title='Email Templates';

if(is_post()&&csrf_verify()){
    $action=post('action');
    if($action==='save'){
        $tid=(int)post('template_id');
        $subj=trim(post('subject')); $body=post('body_html');
        if($tid){
            DB::execute("UPDATE email_templates SET subject=?,body_html=? WHERE id=?",'ssi',[$subj,$body,$tid]);
            redirect_with_flash('email-templates.php','success','Template saved.');
        } else {
            $name=trim(post('name')); $slug_val=slug($name);
            DB::execute("INSERT INTO email_templates (name,slug,subject,body_html,status) VALUES (?,?,?,?,'active')",'ssss',[$name,$slug_val,$subj,$body]);
            redirect_with_flash('email-templates.php','success','Template created.');
        }
    }
    if($action==='test'){
        $tid=(int)post('template_id');
        $t=DB::row("SELECT * FROM email_templates WHERE id=?",'i',[$tid]);
        if($t){
            $to=DB::setting('company_email'); $name=$admin['name'];
            $vars=['client_name'=>$name,'company_name'=>$company,'site_url'=>BASE_URL,'login_url'=>BASE_URL.'/client/login.php','reset_url'=>BASE_URL.'/client/reset-password.php?token=TEST','invoice_number'=>'INV-TEST-001','invoice_total'=>'₦5,000.00','due_date'=>date('d M Y',strtotime('+7 days')),'invoice_url'=>BASE_URL.'/client/invoices/','amount'=>'₦5,000.00','domain'=>'example.com'];
            $subj=$t['subject']; $body=$t['body_html'];
            foreach($vars as $k=>$v){$subj=str_replace('{'.$k.'}',$v,$subj);$body=str_replace('{'.$k.'}',$v,$body);}
            Mailer::send($to,$name,$subj,$body);
            redirect_with_flash('email-templates.php?edit='.$tid,'success','Test email sent to '.$to);
        }
    }
    if($action==='toggle'){
        $tid=(int)post('template_id');
        $cur=DB::value("SELECT status FROM email_templates WHERE id=?",'i',[$tid]);
        DB::execute("UPDATE email_templates SET status=? WHERE id=?",'si',[$cur==='active'?'inactive':'active',$tid]);
        redirect_with_flash('email-templates.php','success','Template updated.');
    }
}

$edit_id=(int)get_param('edit');
$edit_template=$edit_id?DB::row("SELECT * FROM email_templates WHERE id=?",'i',[$edit_id]):null;
$templates=DB::rows("SELECT * FROM email_templates ORDER BY is_system DESC, name ASC");
include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title">Email Templates</h1>
<?=flash_html()?>

<div class="row g-4">
  <!-- Editor -->
  <div class="col-lg-7">
    <div class="bp-card">
      <div class="bp-card-header">
        <h3 class="bp-card-title"><?=$edit_template?'✏ Edit: '.h($edit_template['name']):'➕ New Template'?></h3>
        <?php if($edit_template):?><a href="email-templates.php" class="bp-btn bp-btn-outline bp-btn-sm">New</a><?php endif?>
      </div>
      <div class="bp-card-body">
        <form method="POST">
          <?=csrf_input()?>
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="template_id" value="<?=$edit_template?$edit_template['id']:0?>">
          <?php if(!$edit_template):?>
          <div class="bp-form-group"><label class="bp-label">Template Name *</label><input type="text" name="name" class="bp-input" placeholder="e.g. Service Renewal" required></div>
          <?php endif?>
          <div class="bp-form-group">
            <label class="bp-label">Subject Line *</label>
            <input type="text" name="subject" class="bp-input" value="<?=h($edit_template?$edit_template['subject']:'')?>" placeholder="Your subject with {variables}" required>
          </div>
          <div class="bp-form-group">
            <label class="bp-label">HTML Body</label>
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px;margin-bottom:8px;font-size:12px;color:#64748b">
              <strong>Available variables:</strong> {client_name} {company_name} {site_url} {login_url} {invoice_number} {invoice_total} {due_date} {invoice_url} {amount} {domain} {reset_url} {username} {password}
            </div>
            <textarea name="body_html" class="bp-textarea" rows="16" style="font-family:monospace;font-size:13px" required><?=h($edit_template?$edit_template['body_html']:'')?></textarea>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="bp-btn bp-btn-primary">💾 Save Template</button>
            <?php if($edit_template):?>
            <form method="POST" style="display:inline"><?=csrf_input()?><input type="hidden" name="action" value="test"><input type="hidden" name="template_id" value="<?=$edit_template['id']?>"><button type="submit" class="bp-btn bp-btn-outline">📧 Send Test</button></form>
            <?php endif?>
          </div>
        </form>
      </div>
    </div>

    <!-- Preview -->
    <?php if($edit_template): ?>
    <div class="bp-card" style="margin-top:16px">
      <div class="bp-card-header"><h3 class="bp-card-title">Preview</h3></div>
      <div style="padding:0;border-radius:0 0 16px 16px;overflow:hidden">
        <iframe srcdoc="<?=htmlspecialchars($edit_template['body_html'],ENT_QUOTES)?>" style="width:100%;height:400px;border:none"></iframe>
      </div>
    </div>
    <?php endif?>
  </div>

  <!-- Templates list -->
  <div class="col-lg-5">
    <div class="bp-card">
      <div class="bp-card-header"><h3 class="bp-card-title">All Templates (<?=count($templates)?>)</h3></div>
      <div style="max-height:700px;overflow-y:auto">
        <?php foreach($templates as $t): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:14px 20px;border-bottom:1px solid #f8fafc;<?=$edit_id===$t['id']?'background:#f0f9ff;':''?>">
          <div style="flex:1;min-width:0">
            <div style="font-weight:600;font-size:13px;display:flex;align-items:center;gap:6px">
              <?=h($t['name'])?>
              <?php if($t['is_system']):?><span style="font-size:10px;background:#e0e7ff;color:#3730a3;padding:1px 6px;border-radius:10px;font-weight:700">SYSTEM</span><?php endif?>
            </div>
            <div style="font-size:12px;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=h($t['subject'])?></div>
            <div style="font-size:11px;color:#94a3b8;font-family:monospace">{<?=h($t['slug'])?>}</div>
          </div>
          <div style="display:flex;gap:6px;flex-shrink:0">
            <a href="?edit=<?=$t['id']?>" class="bp-btn bp-btn-outline bp-btn-sm">Edit</a>
            <form method="POST" style="display:inline"><?=csrf_input()?><input type="hidden" name="action" value="toggle"><input type="hidden" name="template_id" value="<?=$t['id']?>"><button type="submit" class="bp-btn bp-btn-<?=$t['status']==='active'?'success':'outline'?> bp-btn-sm"><?=$t['status']==='active'?'On':'Off'?></button></form>
          </div>
        </div>
        <?php endforeach?>
      </div>
    </div>
  </div>
</div>
</div>
<?php include 'partials/footer.php';?>
