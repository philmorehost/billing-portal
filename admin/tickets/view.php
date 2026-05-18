<?php
require_once '../../includes/config.php';
$admin=Auth::requireAdmin(); $company=DB::setting('company_name','Billing Portal');
$tid=(int)get_param('id');
$ticket=DB::row("SELECT t.*,c.first_name,c.last_name,c.email,c.id AS cid FROM tickets t JOIN clients c ON c.id=t.client_id WHERE t.id=?",'i',[$tid]);
if(!$ticket) redirect(BASE_URL.'/admin/tickets.php');
$page_title='Ticket #'.h($ticket['ticket_number']);

if(is_post()&&csrf_verify()){
    $action=post('action');
    if($action==='reply'&&trim(post('message'))){
        $msg=trim(post('message'));
        DB::execute("INSERT INTO ticket_replies (ticket_id,author_type,author_id,message) VALUES (?,?,?,?)",'isid',[$tid,'admin',$admin['id'],$msg]);
        DB::execute("UPDATE tickets SET status='answered',updated_at=NOW() WHERE id=?",'i',[$tid]);
        $c=DB::row("SELECT email,first_name FROM clients WHERE id=?",'i',[$ticket['cid']]);
        if($c) Mailer::send($c['email'],$c['first_name'],"Re: [{$ticket['ticket_number']}] {$ticket['subject']}","<p>Dear {$c['first_name']},</p><p>A staff member replied to your ticket:</p><blockquote style='border-left:3px solid #e2e8f0;padding-left:16px;color:#374151'>".nl2br(htmlspecialchars($msg))."</blockquote><p><a href='".BASE_URL."/client/tickets/view.php?id={$tid}'>View Ticket →</a></p>");
        redirect_with_flash("view.php?id={$tid}",'success','Reply sent.');
    }
    if($action==='close'){DB::execute("UPDATE tickets SET status='closed' WHERE id=?",'i',[$tid]);redirect_with_flash("view.php?id={$tid}",'success','Ticket closed.');}
    if($action==='reopen'){DB::execute("UPDATE tickets SET status='open' WHERE id=?",'i',[$tid]);redirect_with_flash("view.php?id={$tid}",'success','Ticket reopened.');}
}

$replies=DB::rows("SELECT r.*,IF(r.author_type='admin',(SELECT name FROM admins WHERE id=r.author_id),(SELECT first_name FROM clients WHERE id=r.author_id)) AS author_name FROM ticket_replies r WHERE r.ticket_id=? ORDER BY r.id ASC",'i',[$tid]);
$ticket=DB::row("SELECT t.*,c.first_name,c.last_name,c.email,c.id AS cid FROM tickets t JOIN clients c ON c.id=t.client_id WHERE t.id=?",'i',[$tid]);
$pb=['urgent'=>'danger','high'=>'warning','medium'=>'info','low'=>'muted'];
$sb=['open'=>'danger','client_reply'=>'warning','answered'=>'success','closed'=>'muted'];
include '../partials/header.php';
?>
<div class="bp-content">
<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
  <a href="<?=BASE_URL?>/admin/tickets.php" class="bp-btn bp-btn-outline bp-btn-sm">← Back</a>
  <h1 class="bp-page-title" style="margin:0">#<?=h($ticket['ticket_number'])?></h1>
  <span class="bp-badge bp-badge-<?=$sb[$ticket['status']]??'muted'?>"><?=str_replace('_',' ',$ticket['status'])?></span>
  <span class="bp-badge bp-badge-<?=$pb[$ticket['priority']]??'muted'?>"><?=$ticket['priority']?></span>
  <div class="ms-auto d-flex gap-2">
    <?php if($ticket['status']!=='closed'):?>
    <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="close"><button type="submit" class="bp-btn bp-btn-outline bp-btn-sm">✕ Close</button></form>
    <?php else:?>
    <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="reopen"><button type="submit" class="bp-btn bp-btn-outline bp-btn-sm">↺ Reopen</button></form>
    <?php endif?>
  </div>
</div>
<?=flash_html()?>
<div class="row g-4">
  <div class="col-lg-8">
    <div class="bp-card mb-3"><div class="bp-card-body"><h2 style="font-size:17px;font-weight:700;margin:0"><?=h($ticket['subject'])?></h2><div style="font-size:12px;color:#64748b;margin-top:4px">Opened <?=time_ago($ticket['created_at'])?> · <?=ucfirst($ticket['department'])?></div></div></div>
    <?php foreach($replies as $r): $ia=$r['author_type']==='admin'; ?>
    <div style="display:flex;gap:12px;margin-bottom:14px;<?=$ia?'flex-direction:row-reverse':''?>">
      <div style="width:38px;height:38px;border-radius:50%;background:<?=$ia?'linear-gradient(135deg,#3b82f6,#06b6d4)':'linear-gradient(135deg,#0f172a,#1e3a5f)'?>;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:#fff;flex-shrink:0"><?=strtoupper(substr($r['author_name']??'?',0,1))?></div>
      <div style="flex:1;max-width:85%">
        <div style="background:<?=$ia?'#eff6ff':'#f8fafc'?>;border:1px solid <?=$ia?'#bfdbfe':'#e2e8f0'?>;border-radius:12px;padding:14px 16px">
          <div style="display:flex;justify-content:space-between;margin-bottom:8px"><span style="font-size:13px;font-weight:700"><?=h($r['author_name']??'?')?> <?=$ia?'<span style="font-size:10px;background:#3b82f6;color:#fff;padding:1px 6px;border-radius:10px">STAFF</span>':''?></span><span style="font-size:11px;color:#94a3b8"><?=time_ago($r['created_at'])?></span></div>
          <div style="font-size:14px;color:#374151;line-height:1.7;white-space:pre-wrap"><?=nl2br(h($r['message']))?></div>
        </div>
      </div>
    </div>
    <?php endforeach?>
    <?php if($ticket['status']!=='closed'):?>
    <div class="bp-card"><div class="bp-card-body">
      <div style="font-weight:600;margin-bottom:12px">✏ Reply</div>
      <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="reply">
        <textarea name="message" class="bp-textarea" rows="5" placeholder="Type your reply…" required></textarea>
        <button type="submit" class="bp-btn bp-btn-primary" style="margin-top:12px">Send Reply →</button>
      </form>
    </div></div>
    <?php endif?>
  </div>
  <div class="col-lg-4">
    <div class="bp-card"><div class="bp-card-body">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:12px">Client</div>
      <a href="<?=BASE_URL?>/admin/clients/view.php?id=<?=$ticket['cid']?>" style="display:flex;align-items:center;gap:10px;text-decoration:none;margin-bottom:16px">
        <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#06b6d4);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700"><?=strtoupper(substr($ticket['first_name'],0,1))?></div>
        <div><div style="font-weight:600;color:#0f172a"><?=h($ticket['first_name'].' '.$ticket['last_name'])?></div><div style="font-size:12px;color:#3b82f6"><?=h($ticket['email'])?></div></div>
      </a>
      <?php foreach([['#',$ticket['ticket_number']],['Dept',ucfirst($ticket['department'])],['Priority',ucfirst($ticket['priority'])],['Opened',format_date($ticket['created_at'])],['Updated',time_ago($ticket['updated_at'])]] as [$l,$v]):?>
      <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid #f8fafc;font-size:13px"><span style="color:#64748b"><?=$l?></span><span style="font-weight:600"><?=$v?></span></div>
      <?php endforeach?>
    </div></div>
  </div>
</div>
</div>
<?php include '../partials/footer.php';?>
