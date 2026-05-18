<?php
require_once '../../includes/config.php';
$client=Auth::requireClient(); $company=DB::setting('company_name','Billing Portal'); $page_title='Open Ticket';
$error='';
if(is_post()&&csrf_verify()){
    $subject=trim(post('subject')); $dept=post('department','general');
    $priority=post('priority','medium'); $msg=trim(post('message'));
    if(!$subject||!$msg){$error='Subject and message are required.';}
    else{
        $num=generate_ticket_number();
        DB::execute("INSERT INTO tickets (client_id,ticket_number,subject,department,priority,status) VALUES (?,?,?,?,?,'open')",'issss',[$client['id'],$num,$subject,$dept,$priority]);
        $tid=DB::lastInsertId();
        DB::execute("INSERT INTO ticket_replies (ticket_id,author_type,author_id,message) VALUES (?,?,?,?)",'isid',[$tid,'client',$client['id'],$msg]);
        // Notify admin
        $ae=DB::setting('company_email');
        if($ae) Mailer::send($ae,'Support Team',"New Ticket #{$num}: {$subject}","<p>New support ticket from {$client['first_name']} {$client['last_name']}.</p><p><strong>Subject:</strong> {$subject}</p><p><strong>Message:</strong></p><p>".nl2br(htmlspecialchars($msg))."</p><p><a href='".BASE_URL."/admin/tickets/view.php?id={$tid}'>View Ticket</a></p>");
        log_activity('ticket_opened',"Ticket #{$num} opened",'client',$client['id']);
        redirect_with_flash(BASE_URL.'/client/tickets/view.php?id='.$tid,'success','Ticket opened! We will respond shortly.');
    }
}
$services=DB::rows("SELECT id,domain FROM services WHERE client_id=? AND status='active'",'i',[$client['id']]);
include dirname(dirname(__FILE__)).'/partials/header.php';
?>
<div class="bp-content">
<div class="d-flex align-items-center gap-3 mb-4"><a href="<?=BASE_URL?>/client/tickets.php" class="bp-btn bp-btn-outline bp-btn-sm">← Back</a><h1 class="bp-page-title" style="margin:0">Open Support Ticket</h1></div>
<?php if($error):?><div class="alert-custom alert-danger mb-3"><span>✕</span> <?=h($error)?></div><?php endif?>
<div class="row g-4">
  <div class="col-lg-7">
    <div class="bp-card"><div class="bp-card-body">
      <form method="POST">
        <?=csrf_input()?>
        <div class="bp-form-row bp-form-row-2">
          <div class="bp-form-group"><label class="bp-label">Department</label>
            <select name="department" class="bp-select">
              <?php foreach(['general'=>'General Support','billing'=>'Billing','technical'=>'Technical','sales'=>'Sales'] as $k=>$v):?><option value="<?=$k?>"><?=$v?></option><?php endforeach?>
            </select>
          </div>
          <div class="bp-form-group"><label class="bp-label">Priority</label>
            <select name="priority" class="bp-select">
              <?php foreach(['low'=>'Low','medium'=>'Medium','high'=>'High','urgent'=>'Urgent'] as $k=>$v):?><option value="<?=$k?>" <?=$k==='medium'?'selected':''?>><?=$v?></option><?php endforeach?>
            </select>
          </div>
        </div>
        <div class="bp-form-group"><label class="bp-label">Subject *</label><input type="text" name="subject" class="bp-input" placeholder="Brief description of your issue" value="<?=h(post('subject'))?>" required></div>
        <?php if($services):?>
        <div class="bp-form-group"><label class="bp-label">Related Service (optional)</label>
          <select name="service_id" class="bp-select"><option value="">Not service-specific</option>
            <?php foreach($services as $svc):?><option value="<?=$svc['id']?>" <?=get_param('service_id')==$svc['id']?'selected':''?>><?=h($svc['domain']??'Service #'.$svc['id'])?></option><?php endforeach?>
          </select>
        </div>
        <?php endif?>
        <div class="bp-form-group"><label class="bp-label">Message *</label><textarea name="message" class="bp-textarea" rows="8" placeholder="Please describe your issue in detail…" required><?=h(post('message'))?></textarea></div>
        <button type="submit" class="bp-btn bp-btn-primary" style="padding:13px 28px">Submit Ticket →</button>
      </form>
    </div></div>
  </div>
  <div class="col-lg-5">
    <div class="bp-card"><div class="bp-card-body">
      <div style="font-weight:600;margin-bottom:12px">💡 Before you submit</div>
      <div style="font-size:13px;color:#374151;line-height:1.8">
        <div style="margin-bottom:8px">🔍 Check your <a href="<?=BASE_URL?>/client/invoices.php" style="color:#3b82f6">invoices</a> for billing questions.</div>
        <div style="margin-bottom:8px">🖥 Check your <a href="<?=BASE_URL?>/client/services.php" style="color:#3b82f6">services</a> for hosting status.</div>
        <div style="margin-bottom:8px">📧 Include as much detail as possible — error messages, domain names, order numbers.</div>
        <div>⏰ We aim to respond within <strong>24 hours</strong>.</div>
      </div>
    </div></div>
  </div>
</div>
</div>
<?php include dirname(dirname(__FILE__)).'/partials/footer.php';?>
