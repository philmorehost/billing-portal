<?php
require_once '../includes/config.php';
$client=Auth::requireClient();
$company=DB::setting('company_name','Billing Portal');
$currency=DB::setting('base_currency','NGN');
$page_title='Dashboard';

$services=DB::rows("SELECT s.*,p.name AS product_name FROM services s JOIN products p ON p.id=s.product_id WHERE s.client_id=? AND s.status NOT IN ('terminated','cancelled') ORDER BY s.id DESC LIMIT 5",'i',[$client['id']]);
$invoices=DB::rows("SELECT * FROM invoices WHERE client_id=? ORDER BY id DESC LIMIT 5",'i',[$client['id']]);
$unpaid=DB::value("SELECT COUNT(*) FROM invoices WHERE client_id=? AND status IN ('unpaid','overdue')",'i',[$client['id']])??0;
$active_svc=DB::value("SELECT COUNT(*) FROM services WHERE client_id=? AND status='active'",'i',[$client['id']])??0;
$open_tkt=DB::value("SELECT COUNT(*) FROM tickets WHERE client_id=? AND status IN ('open','answered')",'i',[$client['id']])??0;
include 'partials/header.php';
?>
<div class="bp-content">
<?php if(!empty($_GET['welcome'])):?><div class="alert-custom alert-success mb-4"><span>✓</span> Welcome to <?=h($company)?>! Your account is ready.</div><?php endif?>
<?=flash_html()?>
<h1 class="bp-page-title">Welcome back, <?=h($client['first_name'])?> 👋</h1>
<div class="stat-cards">
  <div class="stat-card"><div class="stat-card-icon green">🖥</div><div class="stat-card-value"><?=$active_svc?></div><div class="stat-card-label">Active Services</div></div>
  <div class="stat-card"><div class="stat-card-icon amber">🧾</div><div class="stat-card-value"><?=$unpaid?></div><div class="stat-card-label">Unpaid Invoices</div></div>
  <div class="stat-card"><div class="stat-card-icon blue">💳</div><div class="stat-card-value"><?=format_currency($client['credit_balance'],$currency)?></div><div class="stat-card-label">Account Credit</div></div>
  <div class="stat-card"><div class="stat-card-icon red">🎫</div><div class="stat-card-value"><?=$open_tkt?></div><div class="stat-card-label">Open Tickets</div></div>
</div>
<div class="row g-4">
  <div class="col-lg-7">
    <div class="bp-card">
      <div class="bp-card-header"><h3 class="bp-card-title">My Services</h3><a href="services.php" class="bp-btn bp-btn-outline bp-btn-sm">View All</a></div>
      <?php if($services):?>
      <table class="bp-table"><thead><tr><th>Service</th><th>Status</th><th>Next Due</th></tr></thead><tbody>
      <?php foreach($services as $s):$sc=['active'=>'success','suspended'=>'danger','pending'=>'warning'];?>
      <tr><td><div style="font-weight:600"><?=h($s['product_name'])?></div><div style="font-size:12px;color:#64748b"><?=h($s['domain']??'')?></div></td>
      <td><span class="bp-badge bp-badge-<?=$sc[$s['status']]??'muted'?>"><?=$s['status']?></span></td>
      <td style="font-size:13px;color:#64748b"><?=$s['next_due_date']?format_date($s['next_due_date']):'—'?></td></tr>
      <?php endforeach?></tbody></table>
      <?php else:?><div class="bp-empty"><div class="bp-empty-icon">🖥</div><div class="bp-empty-title">No services yet</div><a href="order.php" class="bp-btn bp-btn-primary bp-btn-sm" style="margin-top:12px">Order Now</a></div><?php endif?>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="bp-card">
      <div class="bp-card-header"><h3 class="bp-card-title">Recent Invoices</h3><a href="invoices.php" class="bp-btn bp-btn-outline bp-btn-sm">View All</a></div>
      <?php if($invoices):?>
      <table class="bp-table"><thead><tr><th>Invoice</th><th>Amount</th><th>Status</th></tr></thead><tbody>
      <?php foreach($invoices as $inv):$sc=['paid'=>'success','unpaid'=>'warning','overdue'=>'danger','cancelled'=>'muted'];?>
      <tr><td><a href="invoices/view.php?id=<?=$inv['id']?>" style="color:#3b82f6;font-weight:600;text-decoration:none">#<?=h($inv['invoice_number'])?></a><div style="font-size:12px;color:#64748b"><?=format_date($inv['due_date'])?></div></td>
      <td style="font-weight:600"><?=format_currency($inv['total'],$inv['currency'])?></td>
      <td><span class="bp-badge bp-badge-<?=$sc[$inv['status']]??'muted'?>"><?=$inv['status']?></span></td></tr>
      <?php endforeach?></tbody></table>
      <?php else:?><div class="bp-empty"><div class="bp-empty-icon">🧾</div><div class="bp-empty-title">No invoices yet</div></div><?php endif?>
    </div>
    <div class="bp-card" style="margin-top:16px">
      <div class="bp-card-header"><h3 class="bp-card-title">Quick Actions</h3></div>
      <div class="bp-card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <a href="order.php" class="bp-btn bp-btn-primary" style="justify-content:center">🛒 Order Hosting</a>
        <a href="order.php?type=domain" class="bp-btn bp-btn-primary" style="justify-content:center;background:#2563eb;border-color:#2563eb">🌐 Register Domain</a>
        <a href="add-funds.php" class="bp-btn bp-btn-outline" style="justify-content:center">💳 Add Funds</a>
        <a href="tickets/open.php" class="bp-btn bp-btn-outline" style="justify-content:center">🎫 Support</a>
      </div>
    </div>
  </div>
</div>
</div>
<?php include 'partials/footer.php';?>
