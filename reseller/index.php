<?php
require_once '../includes/config.php';
require_once INC_PATH.'/modules/reseller.php';
if(empty($_SESSION['reseller_id'])){
    if(!empty($_SESSION['client_id'])){$r=DB::row("SELECT id FROM resellers WHERE client_id=? AND status='active'",'i',[$_SESSION['client_id']]);if($r){$_SESSION['reseller_id']=$r['id'];}else redirect(BASE_URL.'/reseller/login.php');}
    else redirect(BASE_URL.'/reseller/login.php');
}
$reseller_id=$_SESSION['reseller_id'];
$reseller=DB::row("SELECT * FROM resellers WHERE id=?",'i',[$reseller_id]);
$reseller_client=DB::row("SELECT * FROM clients WHERE id=?",'i',[$reseller['client_id']]);
$company=DB::setting('company_name','Billing Portal');
$currency=DB::setting('base_currency','NGN');
$page_title='Dashboard';

$stats=[
    'total_clients'  =>DB::value("SELECT COUNT(*) FROM clients WHERE affiliate_id=(SELECT id FROM affiliates WHERE client_id=?) OR id IN (SELECT client_id FROM services WHERE reseller_id=?)",'ii',[$reseller['client_id'],$reseller_id])??0,
    'active_services'=>DB::value("SELECT COUNT(*) FROM services WHERE reseller_id=? AND status='active'",'i',[$reseller_id])??0,
    'revenue_month'  =>DB::value("SELECT COALESCE(SUM(total),0) FROM invoices WHERE client_id IN (SELECT DISTINCT client_id FROM services WHERE reseller_id=?) AND status='paid' AND MONTH(paid_date)=MONTH(NOW())",'i',[$reseller_id])??0,
    'unpaid_invoices'=>DB::value("SELECT COUNT(*) FROM invoices WHERE client_id IN (SELECT DISTINCT client_id FROM services WHERE reseller_id=?) AND status IN ('unpaid','overdue')",'i',[$reseller_id])??0,
];
$recent_clients=DB::rows("SELECT c.* FROM clients c JOIN services s ON s.client_id=c.id WHERE s.reseller_id=? GROUP BY c.id ORDER BY c.id DESC LIMIT 5",'i',[$reseller_id]);
$recent_invoices=DB::rows("SELECT i.*,c.first_name,c.last_name FROM invoices i JOIN clients c ON c.id=i.client_id JOIN services s ON s.client_id=i.client_id WHERE s.reseller_id=? GROUP BY i.id ORDER BY i.id DESC LIMIT 5",'i',[$reseller_id]);

include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title">Dashboard</h1>
<p class="bp-page-sub">Welcome, <?=h($reseller_client['first_name'])?>. Your reseller overview.</p>
<?=flash_html()?>

<!-- Balance card -->
<div style="background:linear-gradient(135deg,<?=$pc??'#0f172a'?>,<?=$pc??'#1e3a5f'?>22);border:1px solid <?=$pc??'#e2e8f0'?>;border-radius:16px;padding:24px 28px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px">
  <div>
    <div style="font-size:12px;font-weight:700;text-transform:uppercase;color:#64748b;margin-bottom:4px">Pre-paid Balance</div>
    <div style="font-size:36px;font-weight:900;color:#0f172a"><?=format_currency($reseller['balance'],$currency)?></div>
    <div style="font-size:13px;color:#64748b;margin-top:4px">Wholesale costs are deducted instantly when clients order.</div>
  </div>
  <a href="topup.php" class="bp-btn bp-btn-primary" style="padding:13px 28px;font-size:15px">+ Top Up Balance</a>
</div>

<div class="stat-cards">
  <div class="stat-card"><div class="stat-card-icon blue">👥</div><div class="stat-card-value"><?=$stats['total_clients']?></div><div class="stat-card-label">Total Clients</div></div>
  <div class="stat-card"><div class="stat-card-icon green">🖥</div><div class="stat-card-value"><?=$stats['active_services']?></div><div class="stat-card-label">Active Services</div></div>
  <div class="stat-card"><div class="stat-card-icon cyan">💰</div><div class="stat-card-value"><?=format_currency($stats['revenue_month'],$currency)?></div><div class="stat-card-label">Retail Revenue (MTD)</div></div>
  <div class="stat-card"><div class="stat-card-icon amber">🧾</div><div class="stat-card-value"><?=$stats['unpaid_invoices']?></div><div class="stat-card-label">Unpaid Invoices</div></div>
</div>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="bp-card">
      <div class="bp-card-header"><h3 class="bp-card-title">Recent Clients</h3><a href="clients.php" class="bp-btn bp-btn-outline bp-btn-sm">View All</a></div>
      <?php if($recent_clients):?>
      <table class="bp-table"><thead><tr><th>Client</th><th>Status</th><th>Joined</th></tr></thead><tbody>
      <?php foreach($recent_clients as $c):$sb=['active'=>'success','suspended'=>'danger','pending'=>'warning'];?>
      <tr><td><a href="clients/view.php?id=<?=$c['id']?>" style="text-decoration:none"><div style="font-weight:600;color:#0f172a"><?=h($c['first_name'].' '.$c['last_name'])?></div><div style="font-size:12px;color:#64748b"><?=h($c['email'])?></div></a></td>
      <td><span class="bp-badge bp-badge-<?=$sb[$c['status']]??'muted'?>"><?=$c['status']?></span></td>
      <td style="font-size:12px;color:#64748b"><?=time_ago($c['created_at'])?></td></tr>
      <?php endforeach?></tbody></table>
      <?php else:?><div class="bp-empty"><div class="bp-empty-icon">👥</div><div class="bp-empty-title">No clients yet</div><a href="clients/add.php" class="bp-btn bp-btn-primary bp-btn-sm" style="margin-top:8px">Add First Client</a></div><?php endif?>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="bp-card">
      <div class="bp-card-header"><h3 class="bp-card-title">Recent Invoices</h3><a href="invoices.php" class="bp-btn bp-btn-outline bp-btn-sm">View All</a></div>
      <?php if($recent_invoices):?>
      <table class="bp-table"><thead><tr><th>Invoice</th><th>Amount</th><th>Status</th></tr></thead><tbody>
      <?php foreach($recent_invoices as $inv):$sb=['paid'=>'success','unpaid'=>'warning','overdue'=>'danger'];?>
      <tr><td><a href="invoices/view.php?id=<?=$inv['id']?>" style="color:#3b82f6;font-weight:600;text-decoration:none">#<?=h($inv['invoice_number'])?></a><div style="font-size:12px;color:#64748b"><?=h($inv['first_name'].' '.$inv['last_name'])?></div></td>
      <td style="font-weight:600"><?=format_currency($inv['total'],$inv['currency'])?></td>
      <td><span class="bp-badge bp-badge-<?=$sb[$inv['status']]??'muted'?>"><?=$inv['status']?></span></td></tr>
      <?php endforeach?></tbody></table>
      <?php else:?><div class="bp-empty"><div class="bp-empty-icon">🧾</div><div class="bp-empty-title">No invoices yet</div></div><?php endif?>
    </div>
  </div>
</div>
</div>
<?php include 'partials/footer.php';?>
