<?php
require_once '../includes/config.php';
$admin=Auth::requireAdmin(); $company=DB::setting('company_name','Billing Portal'); $page_title='Search';
$q=trim(get_param('q')); $currency=DB::setting('base_currency','NGN');

$clients=[]; $invoices=[]; $services=[]; $tickets=[];

if(strlen($q)>=2){
    $s="%{$q}%";
    $clients=DB::rows("SELECT id,first_name,last_name,email,status,account_type FROM clients WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR company LIKE ? ORDER BY id DESC LIMIT 10",'ssss',[$s,$s,$s,$s]);
    $invoices=DB::rows("SELECT i.*,c.first_name,c.last_name FROM invoices i JOIN clients c ON c.id=i.client_id WHERE i.invoice_number LIKE ? OR c.email LIKE ? OR c.first_name LIKE ? ORDER BY i.id DESC LIMIT 10",'sss',[$s,$s,$s]);
    $services=DB::rows("SELECT s.*,p.name AS pname,c.first_name,c.last_name FROM services s JOIN products p ON p.id=s.product_id JOIN clients c ON c.id=s.client_id WHERE s.domain LIKE ? OR s.username LIKE ? OR c.email LIKE ? ORDER BY s.id DESC LIMIT 10",'sss',[$s,$s,$s]);
    $tickets=DB::rows("SELECT t.*,c.first_name,c.last_name FROM tickets t JOIN clients c ON c.id=t.client_id WHERE t.ticket_number LIKE ? OR t.subject LIKE ? OR c.email LIKE ? ORDER BY t.id DESC LIMIT 8",'sss',[$s,$s,$s]);
}

$total=count($clients)+count($invoices)+count($services)+count($tickets);
include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title">Search Results</h1>

<form method="GET" class="d-flex gap-3 mb-4" style="max-width:560px">
  <input type="text" name="q" class="bp-input" value="<?=h($q)?>" placeholder="Search clients, invoices, domains, tickets…" autofocus style="flex:1">
  <button type="submit" class="bp-btn bp-btn-primary">Search</button>
</form>

<?php if($q&&strlen($q)<2):?>
<div class="alert-custom alert-warning mb-3"><span>⚠</span> Please enter at least 2 characters.</div>
<?php elseif($q&&$total===0):?>
<div class="bp-card"><div class="bp-empty"><div class="bp-empty-icon">🔍</div><div class="bp-empty-title">No results found</div><div class="bp-empty-text">No matches for "<?=h($q)?>".</div></div></div>
<?php elseif($q):?>
<p style="color:#64748b;font-size:14px;margin-bottom:20px"><?=$total?> result(s) for "<strong><?=h($q)?></strong>"</p>
<?php endif?>

<?php if($clients):?>
<div class="bp-card mb-4">
  <div class="bp-card-header"><h3 class="bp-card-title">👥 Clients (<?=count($clients)?>)</h3></div>
  <table class="bp-table"><thead><tr><th>Client</th><th>Type</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach($clients as $c):$sb=['active'=>'success','suspended'=>'danger','pending'=>'warning','inactive'=>'muted'];?>
  <tr>
    <td><div style="font-weight:600"><?=h($c['first_name'].' '.$c['last_name'])?></div><div style="font-size:12px;color:#64748b"><?=h($c['email'])?></div></td>
    <td><span class="bp-badge bp-badge-<?=$c['account_type']==='reseller'?'info':'muted'?>"><?=$c['account_type']?></span></td>
    <td><span class="bp-badge bp-badge-<?=$sb[$c['status']]??'muted'?>"><?=$c['status']?></span></td>
    <td><a href="clients/view.php?id=<?=$c['id']?>" class="bp-btn bp-btn-outline bp-btn-sm">View</a></td>
  </tr>
  <?php endforeach?></tbody></table>
</div>
<?php endif?>

<?php if($invoices):?>
<div class="bp-card mb-4">
  <div class="bp-card-header"><h3 class="bp-card-title">🧾 Invoices (<?=count($invoices)?>)</h3></div>
  <table class="bp-table"><thead><tr><th>Invoice</th><th>Client</th><th>Amount</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach($invoices as $inv):$sb=['paid'=>'success','unpaid'=>'warning','overdue'=>'danger','cancelled'=>'muted'];?>
  <tr>
    <td style="font-weight:600;font-family:monospace">#<?=h($inv['invoice_number'])?></td>
    <td style="font-size:13px"><?=h($inv['first_name'].' '.$inv['last_name'])?></td>
    <td style="font-weight:700"><?=format_currency($inv['total'],$inv['currency'])?></td>
    <td><span class="bp-badge bp-badge-<?=$sb[$inv['status']]??'muted'?>"><?=$inv['status']?></span></td>
    <td><a href="invoices/view.php?id=<?=$inv['id']?>" class="bp-btn bp-btn-outline bp-btn-sm">View</a></td>
  </tr>
  <?php endforeach?></tbody></table>
</div>
<?php endif?>

<?php if($services):?>
<div class="bp-card mb-4">
  <div class="bp-card-header"><h3 class="bp-card-title">🖥 Services (<?=count($services)?>)</h3></div>
  <table class="bp-table"><thead><tr><th>Service</th><th>Client</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach($services as $s):$sc=['active'=>'success','suspended'=>'danger','pending'=>'warning','terminated'=>'muted'];?>
  <tr>
    <td><div style="font-weight:600"><?=h($s['pname'])?></div><?php if($s['domain']):?><div style="font-size:12px;color:#64748b;font-family:monospace"><?=h($s['domain'])?></div><?php endif?></td>
    <td style="font-size:13px"><?=h($s['first_name'].' '.$s['last_name'])?></td>
    <td><span class="bp-badge bp-badge-<?=$sc[$s['status']]??'muted'?>"><?=$s['status']?></span></td>
    <td><a href="services/view.php?id=<?=$s['id']?>" class="bp-btn bp-btn-outline bp-btn-sm">View</a></td>
  </tr>
  <?php endforeach?></tbody></table>
</div>
<?php endif?>

<?php if($tickets):?>
<div class="bp-card mb-4">
  <div class="bp-card-header"><h3 class="bp-card-title">🎫 Tickets (<?=count($tickets)?>)</h3></div>
  <table class="bp-table"><thead><tr><th>Ticket</th><th>Client</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach($tickets as $t):$sb=['open'=>'danger','client_reply'=>'warning','answered'=>'success','closed'=>'muted'];?>
  <tr>
    <td><div style="font-weight:600">#<?=h($t['ticket_number'])?></div><div style="font-size:12px;color:#64748b"><?=h($t['subject'])?></div></td>
    <td style="font-size:13px"><?=h($t['first_name'].' '.$t['last_name'])?></td>
    <td><span class="bp-badge bp-badge-<?=$sb[$t['status']]??'muted'?>"><?=str_replace('_',' ',$t['status'])?></span></td>
    <td><a href="tickets/view.php?id=<?=$t['id']?>" class="bp-btn bp-btn-outline bp-btn-sm">View</a></td>
  </tr>
  <?php endforeach?></tbody></table>
</div>
<?php endif?>
</div>
<?php include 'partials/footer.php';?>
