<?php
require_once '../includes/config.php';
require_once INC_PATH.'/modules/reseller.php';
if(empty($_SESSION['reseller_id'])) redirect(BASE_URL.'/reseller/login.php');
$reseller_id=$_SESSION['reseller_id'];
$reseller=DB::row("SELECT * FROM resellers WHERE id=?",'i',[$reseller_id]);
$reseller_client=DB::row("SELECT * FROM clients WHERE id=?",'i',[$reseller['client_id']]);
$currency=DB::setting('base_currency','NGN'); $company=DB::setting('company_name','Billing Portal');
$page_title='Support Tickets'; $error=''; $success='';

if(is_post()&&csrf_verify()){
    $action=post('action');
    if($action==='open'){
        $subj=trim(post('subject')); $msg=trim(post('message')); $dept=post('department','general');
        if(!$subj||!$msg){$error='Subject and message required.';}
        else{
            $num=generate_ticket_number();
            DB::execute("INSERT INTO tickets (client_id,ticket_number,subject,department,priority,status) VALUES (?,?,?,'reseller','high','open')",'iss',[$reseller['client_id'],$num,$subj]);
            $tid=DB::lastInsertId();
            DB::execute("INSERT INTO ticket_replies (ticket_id,author_type,author_id,message) VALUES (?,?,?,?)",'isid',[$tid,'client',$reseller['client_id'],$msg]);
            $ae=DB::setting('company_email');
            if($ae) Mailer::send($ae,'Support',"Reseller Ticket #{$num}: {$subj}","<p>Reseller #{$reseller_id} opened a ticket.</p><p><a href='".BASE_URL."/admin/tickets/view.php?id={$tid}'>View →</a></p>");
            $success='Ticket opened! We will respond shortly.';
        }
    }
    if($action==='reply'){
        $tid=(int)post('ticket_id'); $msg=trim(post('message'));
        $ticket=DB::row("SELECT * FROM tickets WHERE id=? AND client_id=?",'ii',[$tid,$reseller['client_id']]);
        if($ticket&&$msg){
            DB::execute("INSERT INTO ticket_replies (ticket_id,author_type,author_id,message) VALUES (?,?,?,?)",'isid',[$tid,'client',$reseller['client_id'],$msg]);
            DB::execute("UPDATE tickets SET status='client_reply',updated_at=NOW() WHERE id=?",'i',[$tid]);
            $success='Reply sent.';
        }
    }
}

$tickets=DB::rows("SELECT * FROM tickets WHERE client_id=? ORDER BY FIELD(status,'client_reply','open','answered','closed'),updated_at DESC",'i',[$reseller['client_id']]);
$view_id=(int)get_param('id');
$view_ticket=$view_id?DB::row("SELECT * FROM tickets WHERE id=? AND client_id=?",'ii',[$view_id,$reseller['client_id']]):null;
$replies=$view_ticket?DB::rows("SELECT r.*,IF(r.author_type='admin',(SELECT name FROM admins WHERE id=r.author_id),(SELECT first_name FROM clients WHERE id=r.author_id)) AS author_name FROM ticket_replies r WHERE r.ticket_id=? ORDER BY r.id ASC",'i',[$view_id]):[];
$sb_map=['open'=>'danger','client_reply'=>'warning','answered'=>'success','closed'=>'muted'];
include 'partials/header.php';
?>
<div class="bp-content">
<?php if($error):?><div class="alert-custom alert-danger mb-3"><span>✕</span> <?=h($error)?></div><?php endif?>
<?php if($success):?><div class="alert-custom alert-success mb-3"><span>✓</span> <?=h($success)?></div><?php endif?>

<?php if($view_ticket): ?>
<!-- Ticket thread view -->
<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
  <a href="tickets.php" class="bp-btn bp-btn-outline bp-btn-sm">← All Tickets</a>
  <h1 class="bp-page-title" style="margin:0">#<?=h($view_ticket['ticket_number'])?></h1>
  <span class="bp-badge bp-badge-<?=$sb_map[$view_ticket['status']]??'muted'?>"><?=str_replace('_',' ',$view_ticket['status'])?></span>
</div>
<div class="row g-4">
  <div class="col-lg-8">
    <div class="bp-card mb-3"><div class="bp-card-body"><h2 style="font-size:17px;font-weight:700;margin:0"><?=h($view_ticket['subject'])?></h2><div style="font-size:12px;color:#64748b;margin-top:4px">Opened <?=time_ago($view_ticket['created_at'])?></div></div></div>
    <?php foreach($replies as $r):$ia=$r['author_type']==='admin';?>
    <div style="display:flex;gap:12px;margin-bottom:14px;<?=$ia?'flex-direction:row-reverse':''?>">
      <div style="width:36px;height:36px;border-radius:50%;background:<?=$ia?'linear-gradient(135deg,#3b82f6,#06b6d4)':'linear-gradient(135deg,#0f172a,#1e3a5f)'?>;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:#fff;flex-shrink:0"><?=strtoupper(substr($r['author_name']??'?',0,1))?></div>
      <div style="flex:1;max-width:85%">
        <div style="background:<?=$ia?'#eff6ff':'#f8fafc'?>;border:1px solid <?=$ia?'#bfdbfe':'#e2e8f0'?>;border-radius:12px;padding:14px 16px">
          <div style="display:flex;justify-content:space-between;margin-bottom:8px"><span style="font-size:13px;font-weight:700"><?=h($r['author_name']??'?')?> <?=$ia?'<span style="font-size:10px;background:#3b82f6;color:#fff;padding:1px 6px;border-radius:10px">STAFF</span>':''?></span><span style="font-size:11px;color:#94a3b8"><?=time_ago($r['created_at'])?></span></div>
          <div style="font-size:14px;color:#374151;line-height:1.7;white-space:pre-wrap"><?=nl2br(h($r['message']))?></div>
        </div>
      </div>
    </div>
    <?php endforeach?>
    <?php if($view_ticket['status']!=='closed'):?>
    <div class="bp-card"><div class="bp-card-body">
      <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="reply"><input type="hidden" name="ticket_id" value="<?=$view_id?>">
        <textarea name="message" class="bp-textarea" rows="4" placeholder="Add your reply…" required></textarea>
        <button type="submit" class="bp-btn bp-btn-primary" style="margin-top:10px">Send Reply →</button>
      </form>
    </div></div>
    <?php endif?>
  </div>
  <div class="col-lg-4">
    <div class="bp-card"><div class="bp-card-body">
      <?php foreach([['#',$view_ticket['ticket_number']],['Priority',ucfirst($view_ticket['priority'])],['Opened',format_date($view_ticket['created_at'])],['Updated',time_ago($view_ticket['updated_at'])]] as [$l,$v]):?>
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f8fafc;font-size:13px"><span style="color:#64748b"><?=$l?></span><span style="font-weight:600"><?=$v?></span></div>
      <?php endforeach?>
    </div></div>
  </div>
</div>

<?php else: ?>
<!-- Ticket list + new ticket form -->
<h1 class="bp-page-title">Support Tickets</h1>
<div class="row g-4">
  <div class="col-lg-5">
    <div class="bp-card"><div class="bp-card-header"><h3 class="bp-card-title">Open New Ticket</h3></div><div class="bp-card-body">
      <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="open">
        <div class="bp-form-group"><label class="bp-label">Department</label>
          <select name="department" class="bp-select">
            <option value="reseller">Reseller Support</option>
            <option value="billing">Billing</option>
            <option value="technical">Technical</option>
          </select>
        </div>
        <div class="bp-form-group"><label class="bp-label">Subject *</label><input type="text" name="subject" class="bp-input" placeholder="Describe your issue briefly" required></div>
        <div class="bp-form-group"><label class="bp-label">Message *</label><textarea name="message" class="bp-textarea" rows="6" placeholder="Provide as much detail as possible…" required></textarea></div>
        <button type="submit" class="bp-btn bp-btn-primary" style="width:100%;justify-content:center">Submit Ticket →</button>
      </form>
    </div></div>
  </div>
  <div class="col-lg-7">
    <div class="bp-card">
      <div class="bp-card-header"><h3 class="bp-card-title">My Tickets (<?=count($tickets)?>)</h3></div>
      <?php if($tickets):?>
      <table class="bp-table"><thead><tr><th>Ticket</th><th>Status</th><th>Updated</th><th></th></tr></thead><tbody>
      <?php foreach($tickets as $t):$sb=['open'=>'danger','client_reply'=>'warning','answered'=>'success','closed'=>'muted'];?>
      <tr>
        <td><div style="font-weight:600">#<?=h($t['ticket_number'])?></div><div style="font-size:12px;color:#64748b"><?=h($t['subject'])?></div></td>
        <td><span class="bp-badge bp-badge-<?=$sb[$t['status']]??'muted'?>"><?=str_replace('_',' ',$t['status'])?></span></td>
        <td style="font-size:12px;color:#94a3b8"><?=time_ago($t['updated_at'])?></td>
        <td><a href="?id=<?=$t['id']?>" class="bp-btn bp-btn-outline bp-btn-sm"><?=$t['status']==='answered'?'View Reply':'View'?></a></td>
      </tr>
      <?php endforeach?></tbody></table>
      <?php else:?><div class="bp-empty"><div class="bp-empty-icon">🎫</div><div class="bp-empty-title">No tickets yet</div></div><?php endif?>
    </div>
  </div>
</div>
<?php endif?>
</div>
<?php include 'partials/footer.php';?>
