<?php
require_once '../includes/config.php';
$admin=$admin??Auth::requireAdmin();
$company=DB::setting('company_name','Billing Portal');
$currency=DB::setting('base_currency','NGN');
$page_title='Dashboard';

$stats=[
    'total_clients'   =>DB::value("SELECT COUNT(*) FROM clients WHERE status!='pending'")??0,
    'active_services' =>DB::value("SELECT COUNT(*) FROM services WHERE status='active'")??0,
    'revenue_month'   =>DB::value("SELECT COALESCE(SUM(total),0) FROM invoices WHERE status='paid' AND MONTH(paid_date)=MONTH(NOW()) AND YEAR(paid_date)=YEAR(NOW())")??0,
    'unpaid_invoices' =>DB::value("SELECT COUNT(*) FROM invoices WHERE status IN ('unpaid','overdue')")??0,
    'open_tickets'    =>DB::value("SELECT COUNT(*) FROM tickets WHERE status IN ('open','client_reply')")??0,
    'pending_approvals'=>DB::value("SELECT COUNT(*) FROM transactions WHERE status='pending' AND gateway IN ('bank_transfer','crypto')")??0,
];
$recent_clients =DB::rows("SELECT id,first_name,last_name,email,status,created_at FROM clients ORDER BY id DESC LIMIT 6");
$recent_invoices=DB::rows("SELECT i.*,c.first_name,c.last_name FROM invoices i JOIN clients c ON c.id=i.client_id ORDER BY i.id DESC LIMIT 6");
include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title">Dashboard</h1>
<p class="bp-page-sub">Welcome back, <?=h($admin['name'])?>.</p>
<?=flash_html()?>
<div class="stat-cards">
  <div class="stat-card"><div class="stat-card-icon blue">👥</div><div class="stat-card-value"><?=number_format($stats['total_clients'])?></div><div class="stat-card-label">Total Clients</div></div>
  <div class="stat-card"><div class="stat-card-icon green">🖥</div><div class="stat-card-value"><?=number_format($stats['active_services'])?></div><div class="stat-card-label">Active Services</div></div>
  <div class="stat-card"><div class="stat-card-icon cyan">💰</div><div class="stat-card-value"><?=format_currency($stats['revenue_month'],$currency)?></div><div class="stat-card-label">Revenue This Month</div></div>
  <div class="stat-card"><div class="stat-card-icon amber">🧾</div><div class="stat-card-value"><?=number_format($stats['unpaid_invoices'])?></div><div class="stat-card-label">Unpaid Invoices</div></div>
  <div class="stat-card"><div class="stat-card-icon red">🎫</div><div class="stat-card-value"><?=number_format($stats['open_tickets'])?></div><div class="stat-card-label">Open Tickets</div></div>
  <div class="stat-card"><div class="stat-card-icon amber">⏳</div><div class="stat-card-value"><?=number_format($stats['pending_approvals'])?></div><div class="stat-card-label">Pending Approvals</div></div>
</div>
<div class="row g-4">
  <div class="col-lg-6">
    <div class="bp-card">
      <div class="bp-card-header"><h3 class="bp-card-title">Recent Clients</h3><a href="clients.php" class="bp-btn bp-btn-outline bp-btn-sm">View All</a></div>
      <?php if($recent_clients):?>
      <table class="bp-table"><thead><tr><th>Client</th><th>Status</th><th>Joined</th></tr></thead><tbody>
      <?php foreach($recent_clients as $c):$sb=['active'=>'success','suspended'=>'danger','pending'=>'warning','inactive'=>'muted'];?>
      <tr><td><a href="clients/view.php?id=<?=$c['id']?>" style="text-decoration:none"><div style="font-weight:600;color:#0f172a"><?=h($c['first_name'].' '.$c['last_name'])?></div><div style="font-size:12px;color:#64748b"><?=h($c['email'])?></div></a></td>
      <td><span class="bp-badge bp-badge-<?=$sb[$c['status']]??'muted'?>"><?=$c['status']?></span></td>
      <td style="color:#64748b;font-size:13px"><?=format_date($c['created_at'])?></td></tr>
      <?php endforeach?></tbody></table>
      <?php else:?><div class="bp-empty"><div class="bp-empty-icon">👥</div><div class="bp-empty-title">No clients yet</div></div><?php endif?>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="bp-card">
      <div class="bp-card-header"><h3 class="bp-card-title">Recent Invoices</h3><a href="invoices.php" class="bp-btn bp-btn-outline bp-btn-sm">View All</a></div>
      <?php if($recent_invoices):?>
      <table class="bp-table"><thead><tr><th>Invoice</th><th>Amount</th><th>Status</th></tr></thead><tbody>
      <?php foreach($recent_invoices as $inv):$sb=['paid'=>'success','unpaid'=>'warning','overdue'=>'danger','cancelled'=>'muted'];?>
      <tr><td><a href="invoices/view.php?id=<?=$inv['id']?>" style="text-decoration:none"><div style="font-weight:600;color:#0f172a">#<?=h($inv['invoice_number'])?></div><div style="font-size:12px;color:#64748b"><?=h($inv['first_name'].' '.$inv['last_name'])?></div></a></td>
      <td style="font-weight:600"><?=format_currency($inv['total'],$inv['currency'])?></td>
      <td><span class="bp-badge bp-badge-<?=$sb[$inv['status']]??'muted'?>"><?=$inv['status']?></span></td></tr>
      <?php endforeach?></tbody></table>
      <?php else:?><div class="bp-empty"><div class="bp-empty-icon">🧾</div><div class="bp-empty-title">No invoices yet</div></div><?php endif?>
    </div>
  </div>
</div>
</div>
<?php include 'partials/footer.php';?>
