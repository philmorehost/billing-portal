<?php
require_once '../includes/config.php';
$admin=Auth::requireAdmin(); $company=DB::setting('company_name','Billing Portal'); $page_title='Email Campaigns';

if(is_post()&&csrf_verify()){
    $action=post('action');

    if($action==='create'){
        $name=trim(post('name')); $subj=trim(post('subject')); $body=post('body_html'); $target=post('target_group','all');
        if(!$name||!$subj||!$body){redirect_with_flash('campaigns.php','danger','All fields required.');}
        DB::execute("INSERT INTO email_campaigns (name,subject,body_html,target_group,status) VALUES (?,?,?,'draft','draft')",'ssss',[$name,$subj,$body]);
        redirect_with_flash('campaigns.php','success','Campaign saved as draft.');
    }

    if($action==='send'){
        $cid=(int)post('campaign_id');
        $camp=DB::row("SELECT * FROM email_campaigns WHERE id=? AND status='draft'",'i',[$cid]);
        if(!$camp){redirect_with_flash('campaigns.php','danger','Campaign not found or already sent.');}

        // Get target clients
        $target=$camp['target_group'];
        $where_map=['all'=>"status='active'",'active'=>"status='active' AND (SELECT COUNT(*) FROM services s WHERE s.client_id=clients.id AND s.status='active')>0",'inactive'=>"status='inactive'",'resellers'=>"account_type='reseller' AND status='active'"];
        $where=$where_map[$target]?? "status='active'";
        $clients=DB::rows("SELECT email,first_name,last_name FROM clients WHERE {$where}");

        DB::execute("UPDATE email_campaigns SET status='sending' WHERE id=?",'i',[$cid]);
        $sent=0; $vars=['company_name'=>$company,'site_url'=>BASE_URL,'unsubscribe_url'=>BASE_URL.'/client/unsubscribe.php'];
        foreach($clients as $c){
            $vars['client_name']=$c['first_name'];
            $subj=$camp['subject']; $body=$camp['body_html'];
            foreach($vars as $k=>$v){$subj=str_replace('{'.$k.'}',$v,$subj);$body=str_replace('{'.$k.'}',$v,$body);}
            if(Mailer::send($c['email'],$c['first_name'].' '.$c['last_name'],$subj,$body)) $sent++;
            usleep(100000); // 100ms throttle
        }
        DB::execute("UPDATE email_campaigns SET status='sent',total_sent=?,sent_at=NOW() WHERE id=?",'ii',[$sent,$cid]);
        log_activity('campaign_sent',"Campaign '{$camp['name']}' sent to {$sent} recipients",'admin',$admin['id']);
        redirect_with_flash('campaigns.php','success',"Campaign sent to {$sent} recipients.");
    }

    if($action==='delete'){
        $cid=(int)post('campaign_id');
        DB::execute("DELETE FROM email_campaigns WHERE id=? AND status='draft'",'i',[$cid]);
        redirect_with_flash('campaigns.php','success','Campaign deleted.');
    }
}

$edit_id=(int)get_param('edit');
$edit_camp=$edit_id?DB::row("SELECT * FROM email_campaigns WHERE id=?",'i',[$edit_id]):null;
$campaigns=DB::rows("SELECT * FROM email_campaigns ORDER BY id DESC");

// Count targets for display
$counts=['all'=>DB::value("SELECT COUNT(*) FROM clients WHERE status='active'"),'active'=>DB::value("SELECT COUNT(*) FROM clients WHERE status='active' AND (SELECT COUNT(*) FROM services s WHERE s.client_id=clients.id AND s.status='active')>0"),'inactive'=>DB::value("SELECT COUNT(*) FROM clients WHERE status='inactive'"),'resellers'=>DB::value("SELECT COUNT(*) FROM clients WHERE account_type='reseller' AND status='active'")];
include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title">Email Campaigns</h1>
<?=flash_html()?>
<div class="row g-4">
  <div class="col-lg-7">
    <div class="bp-card">
      <div class="bp-card-header">
        <h3 class="bp-card-title"><?=$edit_camp?'✏ Edit Campaign':'➕ New Campaign'?></h3>
        <?php if($edit_camp):?><a href="campaigns.php" class="bp-btn bp-btn-outline bp-btn-sm">New</a><?php endif?>
      </div>
      <div class="bp-card-body">
        <form method="POST">
          <?=csrf_input()?>
          <input type="hidden" name="action" value="create">
          <?php if($edit_camp):?><input type="hidden" name="campaign_id" value="<?=$edit_camp['id']?>"><?php endif?>
          <div class="bp-form-group"><label class="bp-label">Campaign Name *</label><input type="text" name="name" class="bp-input" value="<?=h($edit_camp?$edit_camp['name']:'')?>" placeholder="e.g. November Newsletter" required></div>
          <div class="bp-form-group"><label class="bp-label">Target Audience</label>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
              <?php foreach(['all'=>'All Active Clients','active'=>'Clients w/ Active Services','inactive'=>'Inactive Clients','resellers'=>'Resellers Only'] as $k=>$label):?>
              <label style="display:flex;align-items:center;gap:8px;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:9px;cursor:pointer;font-size:13px" class="target-opt">
                <input type="radio" name="target_group" value="<?=$k?>" <?=($edit_camp?$edit_camp['target_group']:post('target_group','all'))===$k?'checked':''?>>
                <div><div style="font-weight:600"><?=$label?></div><div style="font-size:11px;color:#64748b"><?=number_format($counts[$k])?> recipients</div></div>
              </label>
              <?php endforeach?>
            </div>
          </div>
          <div class="bp-form-group"><label class="bp-label">Subject Line *</label><input type="text" name="subject" class="bp-input" value="<?=h($edit_camp?$edit_camp['subject']:'')?>" placeholder="Your email subject" required></div>
          <div class="bp-form-group">
            <label class="bp-label">Email Body (HTML) *</label>
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px;margin-bottom:8px;font-size:12px;color:#64748b">
              Variables: {client_name} {company_name} {site_url} {unsubscribe_url}
            </div>
            <textarea name="body_html" class="bp-textarea" rows="14" style="font-family:monospace;font-size:13px" required><?=h($edit_camp?$edit_camp['body_html']:'')?></textarea>
          </div>
          <button type="submit" class="bp-btn bp-btn-primary">💾 Save Draft</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="bp-card">
      <div class="bp-card-header"><h3 class="bp-card-title">Campaigns (<?=count($campaigns)?>)</h3></div>
      <?php if($campaigns): foreach($campaigns as $c):
        $sb=['draft'=>'warning','sending'=>'info','sent'=>'success','cancelled'=>'muted'];
      ?>
      <div style="padding:16px 20px;border-bottom:1px solid #f8fafc">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">
          <div>
            <div style="font-weight:600;font-size:14px"><?=h($c['name'])?></div>
            <div style="font-size:12px;color:#64748b"><?=h($c['subject'])?></div>
          </div>
          <span class="bp-badge bp-badge-<?=$sb[$c['status']]?>"><?=$c['status']?></span>
        </div>
        <div style="display:flex;align-items:center;gap:12px;font-size:12px;color:#94a3b8;margin-bottom:10px">
          <span>👥 <?=ucfirst($c['target_group'])?></span>
          <?php if($c['status']==='sent'):?><span>📧 <?=number_format($c['total_sent'])?> sent</span><span>📅 <?=format_date($c['sent_at'])?></span><?php endif?>
        </div>
        <?php if($c['status']==='draft'):?>
        <div class="d-flex gap-2">
          <a href="?edit=<?=$c['id']?>" class="bp-btn bp-btn-outline bp-btn-sm">Edit</a>
          <form method="POST" style="display:inline" onsubmit="return confirm('Send to all target recipients now?')"><?=csrf_input()?><input type="hidden" name="action" value="send"><input type="hidden" name="campaign_id" value="<?=$c['id']?>"><button type="submit" class="bp-btn bp-btn-accent bp-btn-sm">📧 Send Now</button></form>
          <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')"><?=csrf_input()?><input type="hidden" name="action" value="delete"><input type="hidden" name="campaign_id" value="<?=$c['id']?>"><button type="submit" class="bp-btn bp-btn-outline bp-btn-sm" style="color:#ef4444;border-color:#fecdd3">Delete</button></form>
        </div>
        <?php endif?>
      </div>
      <?php endforeach; else:?>
      <div class="bp-empty"><div class="bp-empty-icon">📧</div><div class="bp-empty-title">No campaigns yet</div></div>
      <?php endif?>
    </div>
  </div>
</div>
</div>
<?php include 'partials/footer.php';?>
