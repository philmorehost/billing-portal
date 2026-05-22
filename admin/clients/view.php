<?php
require_once '../../includes/config.php';
require_once INC_PATH.'/modules/billing.php';
$admin=Auth::requireAdmin(); $company=DB::setting('company_name','Billing Portal');
$cid=(int)get_param('id'); $client=DB::row("SELECT * FROM clients WHERE id=?",'i',[$cid]);
if(!$client) redirect(BASE_URL.'/admin/clients.php');
$page_title=h($client['first_name'].' '.$client['last_name']);

if(is_post()&&csrf_verify()){
    $action=post('action');
    if($action==='update_status'){
        DB::execute("UPDATE clients SET status=? WHERE id=?",'si',[post('status'),$cid]);
        redirect_with_flash("view.php?id={$cid}",'success','Client status updated.');
    }
    if($action==='add_credit'&&(float)post('amount')>0){
        Billing::addCredit($cid,(float)post('amount'),trim(post('credit_note','Admin credit')),'admin');
        redirect_with_flash("view.php?id={$cid}",'success','Credit added.');
    }
    if($action==='add_note'){
        DB::execute("UPDATE clients SET notes=? WHERE id=?",'si',[trim(post('notes')),$cid]);
        redirect_with_flash("view.php?id={$cid}",'success','Notes updated.');
    }
}
$services=DB::rows("SELECT s.*,p.name AS pname FROM services s JOIN products p ON p.id=s.product_id WHERE s.client_id=? ORDER BY s.id DESC",'i',[$cid]);
$invoices=DB::rows("SELECT * FROM invoices WHERE client_id=? ORDER BY id DESC LIMIT 10",'i',[$cid]);
$tickets=DB::rows("SELECT * FROM tickets WHERE client_id=? ORDER BY id DESC LIMIT 5",'i',[$cid]);
$transactions=DB::rows("SELECT * FROM transactions WHERE client_id=? ORDER BY id DESC LIMIT 10",'i',[$cid]);
$currency=DB::setting('base_currency','NGN');
include '../partials/header.php';
?>
<div class="bp-content">
<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
  <a href="<?=BASE_URL?>/admin/clients.php" class="bp-btn bp-btn-outline bp-btn-sm">← Back</a>
  <h1 class="bp-page-title" style="margin:0"><?=h($client['first_name'].' '.$client['last_name'])?></h1>
  <span class="bp-badge bp-badge-<?=['active'=>'success','suspended'=>'danger','pending'=>'warning','inactive'=>'muted'][$client['status']]??'muted'?>"><?=$client['status']?></span>
  <?php if($client['account_type']==='reseller'):?><span class="bp-badge bp-badge-info">Reseller</span><?php endif?>
  <a href="<?=BASE_URL?>/admin/clients/edit.php?id=<?=$cid?>" class="bp-btn bp-btn-outline bp-btn-sm ms-auto">✏ Edit</a>
</div>
<?=flash_html()?>

<div class="row g-4">
  <!-- Left: info + actions -->
  <div class="col-lg-4">
    <div class="bp-card"><div class="bp-card-body">
      <div style="text-align:center;padding:20px 0 16px">
        <div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#06b6d4);display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:700;color:#fff;margin:0 auto 12px"><?=strtoupper(substr($client['first_name'],0,1))?></div>
        <div style="font-size:18px;font-weight:700"><?=h($client['first_name'].' '.$client['last_name'])?></div>
        <div style="font-size:13px;color:#64748b;margin-top:2px"><?=h($client['email'])?></div>
        <?php if($client['company']):?><div style="font-size:13px;color:#64748b"><?=h($client['company'])?></div><?php endif?>
      </div>
      <div style="border-top:1px solid #f1f5f9;padding-top:16px">
        <?php $info=[['📞',$client['phone']??'—'],['📍',$client['city']?h($client['city'].', '.$client['country']):h($client['country']??'')],['📅','Joined '.format_date($client['created_at'])],['🔐','Last login: '.($client['last_login']?time_ago($client['last_login']):'Never')]];
        foreach($info as [$icon,$val]):?>
        <div style="display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid #f8fafc;font-size:13px"><span><?=$icon?></span><span style="color:#374151"><?=$val?></span></div>
        <?php endforeach?>
      </div>
      <!-- Credit balance -->
      <div style="background:linear-gradient(135deg,#0f172a,#1e3a5f);border-radius:12px;padding:16px;margin-top:16px;text-align:center">
        <div style="color:rgba(255,255,255,.5);font-size:11px;text-transform:uppercase;font-weight:700">Credit Balance</div>
        <div style="color:#fff;font-size:24px;font-weight:900"><?=format_currency($client['credit_balance'],$currency)?></div>
      </div>
    </div></div>

    <!-- Status -->
    <div class="bp-card" style="margin-top:12px"><div class="bp-card-body">
      <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="update_status">
        <div class="bp-form-group"><label class="bp-label">Account Status</label>
          <select name="status" class="bp-select">
            <?php foreach(['active','inactive','suspended','pending'] as $s):?><option value="<?=$s?>" <?=$client['status']===$s?'selected':''?>><?=ucfirst($s)?></option><?php endforeach?>
          </select>
        </div>
        <button type="submit" class="bp-btn bp-btn-primary bp-btn-sm">Update Status</button>
      </form>
    </div></div>

    <!-- Add credit -->
    <div class="bp-card" style="margin-top:12px"><div class="bp-card-body">
      <div style="font-size:13px;font-weight:600;margin-bottom:12px">Add Credit</div>
      <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="add_credit">
        <div class="bp-form-group"><input type="number" name="amount" class="bp-input" step="100" min="0" placeholder="Amount" required></div>
        <div class="bp-form-group"><input type="text" name="credit_note" class="bp-input" placeholder="Reason (optional)"></div>
        <button type="submit" class="bp-btn bp-btn-success bp-btn-sm">Add Credit</button>
      </form>
    </div></div>

    <!-- Notes -->
    <div class="bp-card" style="margin-top:12px"><div class="bp-card-body">
      <div style="font-size:13px;font-weight:600;margin-bottom:12px">Admin Notes</div>
      <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="add_note">
        <textarea name="notes" class="bp-textarea" rows="4" placeholder="Internal notes…"><?=h($client['notes']??'')?></textarea>
        <button type="submit" class="bp-btn bp-btn-outline bp-btn-sm" style="margin-top:8px">Save Notes</button>
      </form>
    </div></div>
  </div>

  <!-- Right: services, invoices, tickets -->
  <div class="col-lg-8">
    <!-- Services -->
    <div class="bp-card"><div class="bp-card-header"><h3 class="bp-card-title">Services (<?=count($services)?>)</h3></div>
      <?php if($services):?>
      <table class="bp-table"><thead><tr><th>Service</th><th>Cycle / Price</th><th>Next Due</th><th>Status</th></tr></thead><tbody>
      <?php foreach($services as $s):$sb=['active'=>'success','suspended'=>'danger','pending'=>'warning','terminated'=>'muted'];
      $s_cur = $s['currency'] ?: $currency;
      $price_str = format_currency($s['price'], $s_cur);
      if ($s_cur !== $currency) {
          $conv = Billing::convertCurrency($s['price'], $s_cur, $currency);
          $price_str .= ' ('.format_currency($conv, $currency).')';
      }
      ?>
      <tr><td><div style="font-weight:600"><?=h($s['pname'])?></div><?php if($s['domain']):?><div style="font-size:12px;color:#64748b"><?=h($s['domain'])?></div><?php endif?></td>
      <td style="font-size:13px"><?=ucfirst(str_replace('_',' ',$s['billing_cycle']))?> · <?=$price_str?></td>
      <td style="font-size:13px;color:#64748b"><?=$s['next_due_date']?format_date($s['next_due_date']):'—'?></td>
      <td><span class="bp-badge bp-badge-<?=$sb[$s['status']]??'muted'?>"><?=$s['status']?></span></td></tr>
      <?php endforeach?>
      </tbody></table>
      <?php else:?><div class="bp-empty" style="padding:30px"><div class="bp-empty-title">No services</div></div><?php endif?>
    </div>

    <!-- Invoices -->
    <div class="bp-card" style="margin-top:16px"><div class="bp-card-header"><h3 class="bp-card-title">Recent Invoices</h3><a href="<?=BASE_URL?>/admin/invoices.php?q=<?=urlencode($client['email'])?>" class="bp-btn bp-btn-outline bp-btn-sm">View All</a></div>
      <?php if($invoices):?>
      <table class="bp-table"><thead><tr><th>Invoice</th><th>Amount</th><th>Due</th><th>Status</th></tr></thead><tbody>
      <?php foreach($invoices as $inv):$sb=['paid'=>'success','unpaid'=>'warning','overdue'=>'danger','cancelled'=>'muted'];?>
      <tr><td><a href="<?=BASE_URL?>/admin/invoices/view.php?id=<?=$inv['id']?>" style="color:#3b82f6;font-weight:600;text-decoration:none">#<?=h($inv['invoice_number'])?></a></td>
      <td style="font-weight:600"><?=format_currency($inv['total'],$inv['currency'])?></td>
      <td style="font-size:13px;color:#64748b"><?=format_date($inv['due_date'])?></td>
      <td><span class="bp-badge bp-badge-<?=$sb[$inv['status']]??'muted'?>"><?=$inv['status']?></span></td></tr>
      <?php endforeach?></tbody></table>
      <?php else:?><div class="bp-empty" style="padding:30px"><div class="bp-empty-title">No invoices</div></div><?php endif?>
    </div>

    <!-- Tickets -->
    <?php if($tickets):?>
    <div class="bp-card" style="margin-top:16px"><div class="bp-card-header"><h3 class="bp-card-title">Support Tickets</h3></div>
      <table class="bp-table"><thead><tr><th>Ticket</th><th>Priority</th><th>Status</th><th>Date</th></tr></thead><tbody>
      <?php foreach($tickets as $t):$sb=['open'=>'danger','answered'=>'success','client_reply'=>'warning','closed'=>'muted'];?>
      <tr><td><div style="font-weight:600">#<?=h($t['ticket_number'])?></div><div style="font-size:12px;color:#64748b"><?=h($t['subject'])?></div></td>
      <td><span class="bp-badge bp-badge-<?=['urgent'=>'danger','high'=>'warning','medium'=>'info','low'=>'muted'][$t['priority']]??"muted"?>"><?=$t['priority']?></span></td>
      <td><span class="bp-badge bp-badge-<?=$sb[$t['status']]??'muted'?>"><?=str_replace('_',' ',$t['status'])?></span></td>
      <td style="font-size:12px;color:#64748b"><?=time_ago($t['created_at'])?></td></tr>
      <?php endforeach?></tbody></table>
    </div>
    <?php endif?>
  </div>
</div>
</div>
<?php include '../partials/footer.php';?>
