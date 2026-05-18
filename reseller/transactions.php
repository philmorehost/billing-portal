<?php
require_once '../includes/config.php';
require_once INC_PATH.'/modules/reseller.php';
if(empty($_SESSION['reseller_id'])) redirect(BASE_URL.'/reseller/login.php');
$reseller_id=$_SESSION['reseller_id'];
$reseller=DB::row("SELECT * FROM resellers WHERE id=?",'i',[$reseller_id]);
$reseller_client=DB::row("SELECT * FROM clients WHERE id=?",'i',[$reseller['client_id']]);
$currency=DB::setting('base_currency','NGN'); $company=DB::setting('company_name','Billing Portal');
$page_title='Transactions';
// Show reseller's own balance transactions from activity log
$logs=DB::rows("SELECT * FROM activity_log WHERE actor_type='system' AND actor_id=? AND action IN ('reseller_debit','reseller_credit') ORDER BY id DESC LIMIT 50",'i',[$reseller_id]);
// Also client transactions for reseller's clients
$client_txns=DB::rows("SELECT t.*,c.first_name,c.last_name FROM transactions t JOIN clients c ON c.id=t.client_id JOIN services s ON s.client_id=t.client_id WHERE s.reseller_id=? GROUP BY t.id ORDER BY t.id DESC LIMIT 30",'i',[$reseller_id]);
include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title">Transactions</h1>
<div class="row g-4">
  <div class="col-lg-5">
    <div class="bp-card"><div class="bp-card-header"><h3 class="bp-card-title">Balance History</h3></div>
    <?php if($logs):?>
    <table class="bp-table"><thead><tr><th>Action</th><th>Description</th><th>Time</th></tr></thead><tbody>
    <?php foreach($logs as $l):$is_credit=$l['action']==='reseller_credit';?>
    <tr>
      <td><span class="bp-badge bp-badge-<?=$is_credit?'success':'danger'?>"><?=$is_credit?'Credit':'Debit'?></span></td>
      <td style="font-size:12px;color:#374151"><?=h($l['description']??'')?></td>
      <td style="font-size:11px;color:#94a3b8;white-space:nowrap"><?=time_ago($l['created_at'])?></td>
    </tr>
    <?php endforeach?></tbody></table>
    <?php else:?><div class="bp-empty" style="padding:28px"><div class="bp-empty-title">No balance history</div></div><?php endif?>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="bp-card"><div class="bp-card-header"><h3 class="bp-card-title">Client Payment Transactions</h3></div>
    <?php if($client_txns):?>
    <table class="bp-table"><thead><tr><th>Client</th><th>Type</th><th>Amount</th><th>Gateway</th><th>Status</th><th>Date</th></tr></thead><tbody>
    <?php foreach($client_txns as $t):$sb=['completed'=>'success','pending'=>'warning','failed'=>'danger'];?>
    <tr>
      <td style="font-size:13px;font-weight:600"><?=h($t['first_name'].' '.$t['last_name'])?></td>
      <td><span class="bp-badge bp-badge-<?=$t['type']==='payment'?'success':($t['type']==='credit'?'info':'muted')?>" style="text-transform:capitalize"><?=$t['type']?></span></td>
      <td style="font-weight:700"><?=format_currency($t['amount'],$t['currency']??$currency)?></td>
      <td style="font-size:12px;color:#64748b"><?=h(str_replace('_',' ',ucfirst($t['gateway']??'')))?></td>
      <td><span class="bp-badge bp-badge-<?=$sb[$t['status']]??'muted'?>"><?=$t['status']?></span></td>
      <td style="font-size:11px;color:#94a3b8"><?=time_ago($t['created_at'])?></td>
    </tr>
    <?php endforeach?></tbody></table>
    <?php else:?><div class="bp-empty" style="padding:28px"><div class="bp-empty-title">No transactions</div></div><?php endif?>
    </div>
  </div>
</div>
</div>
<?php include 'partials/footer.php';?>
