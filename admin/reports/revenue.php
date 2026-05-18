<?php
require_once '../../includes/config.php';
$admin=Auth::requireAdmin(); $company=DB::setting('company_name','Billing Portal'); $page_title='Revenue Report';
$currency=DB::setting('base_currency','NGN');
$year=(int)get_param('year',date('Y'));
$years=DB::rows("SELECT DISTINCT YEAR(paid_date) AS yr FROM invoices WHERE status='paid' AND paid_date IS NOT NULL ORDER BY yr DESC");
$monthly=DB::rows("SELECT MONTH(paid_date) AS month,SUM(total) AS revenue,COUNT(*) AS count FROM invoices WHERE status='paid' AND YEAR(paid_date)=? GROUP BY MONTH(paid_date) ORDER BY month",'i',[$year]);
$monthly_map=[];foreach($monthly as $m) $monthly_map[(int)$m['month']]=$m;
$ytd=DB::value("SELECT COALESCE(SUM(total),0) FROM invoices WHERE status='paid' AND YEAR(paid_date)=?",'i',[$year])??0;
$mtd=DB::value("SELECT COALESCE(SUM(total),0) FROM invoices WHERE status='paid' AND YEAR(paid_date)=? AND MONTH(paid_date)=MONTH(NOW())",'i',[$year])??0;
$outstanding=DB::value("SELECT COALESCE(SUM(total),0) FROM invoices WHERE status IN ('unpaid','overdue')")??0;
$avg=DB::value("SELECT COALESCE(AVG(total),0) FROM invoices WHERE status='paid' AND YEAR(paid_date)=?",'i',[$year])??0;
$recent=DB::rows("SELECT i.*,c.first_name,c.last_name FROM invoices i JOIN clients c ON c.id=i.client_id WHERE i.status='paid' ORDER BY i.paid_date DESC LIMIT 10");
$months=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$chart=array_map(fn($i)=>(float)($monthly_map[$i]['revenue']??0),range(1,12));
$chart_max=max(max($chart),1);
include '../partials/header.php';
?>
<div class="bp-content">
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
  <h1 class="bp-page-title" style="margin:0">Revenue Report</h1>
  <form method="GET" class="d-flex gap-2 align-items-center">
    <select name="year" class="bp-select" onchange="this.form.submit()">
      <?php foreach($years as $y):?><option value="<?=$y['yr']?>" <?=$y['yr']==$year?'selected':''?>><?=$y['yr']?></option><?php endforeach?>
      <?php if(!in_array($year,array_column($years??[],'yr'))):?><option value="<?=$year?>" selected><?=$year?></option><?php endif?>
    </select>
  </form>
</div>
<div class="stat-cards">
  <div class="stat-card"><div class="stat-card-icon green">📅</div><div class="stat-card-value"><?=format_currency($ytd,$currency)?></div><div class="stat-card-label">Year <?=$year?></div></div>
  <div class="stat-card"><div class="stat-card-icon blue">📆</div><div class="stat-card-value"><?=format_currency($mtd,$currency)?></div><div class="stat-card-label">This Month</div></div>
  <div class="stat-card"><div class="stat-card-icon amber">⏳</div><div class="stat-card-value"><?=format_currency($outstanding,$currency)?></div><div class="stat-card-label">Outstanding</div></div>
  <div class="stat-card"><div class="stat-card-icon cyan">📊</div><div class="stat-card-value"><?=format_currency($avg,$currency)?></div><div class="stat-card-label">Avg Invoice</div></div>
</div>
<!-- Bar chart -->
<div class="bp-card mb-4"><div class="bp-card-header"><h3 class="bp-card-title">Monthly Revenue — <?=$year?></h3></div>
<div class="bp-card-body">
  <div style="display:flex;align-items:flex-end;gap:6px;height:180px;padding-bottom:28px;position:relative;margin:0 4px">
    <?php foreach(range(1,12) as $m):$rev=$chart[$m-1];$h=max(3,round(($rev/$chart_max)*160));?>
    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
      <?php if($rev>0):?><div style="font-size:9px;color:#64748b;font-weight:600;writing-mode:horizontal-tb"><?=number_format($rev/1000,0)?>k</div><?php endif?>
      <div style="width:100%;background:linear-gradient(180deg,#3b82f6,#06b6d4);border-radius:5px 5px 0 0;height:<?=$h?>px;min-height:3px" title="<?=$months[$m-1]?>: <?=format_currency($rev,$currency)?>"></div>
      <div style="font-size:10px;color:#94a3b8;position:absolute;bottom:4px"><?=$months[$m-1]?></div>
    </div>
    <?php endforeach?>
  </div>
</div></div>
<div class="row g-4">
  <div class="col-lg-5">
    <div class="bp-card"><div class="bp-card-header"><h3 class="bp-card-title">Monthly Breakdown</h3></div>
    <table class="bp-table"><thead><tr><th>Month</th><th>Invoices</th><th>Revenue</th></tr></thead><tbody>
    <?php foreach(range(1,12) as $m):$rev=(float)($monthly_map[$m]['revenue']??0);$cnt=$monthly_map[$m]['count']??0;?>
    <tr style="<?=$rev==0?'opacity:.5':''?>"><td style="font-weight:600"><?=$months[$m-1].' '.$year?></td><td><?=$cnt?></td><td style="font-weight:<?=$rev>0?'700':'400'?>"><?=$rev>0?format_currency($rev,$currency):'—'?></td></tr>
    <?php endforeach?>
    <tr style="background:#f8fafc"><td style="font-weight:800">Total</td><td style="font-weight:800"><?=array_sum(array_column($monthly,'count'))?></td><td style="font-weight:800"><?=format_currency($ytd,$currency)?></td></tr>
    </tbody></table></div>
  </div>
  <div class="col-lg-7">
    <div class="bp-card"><div class="bp-card-header"><h3 class="bp-card-title">Recent Payments</h3></div>
    <table class="bp-table"><thead><tr><th>Invoice</th><th>Client</th><th>Amount</th><th>Paid</th></tr></thead><tbody>
    <?php foreach($recent as $inv):?>
    <tr><td><a href="<?=BASE_URL?>/admin/invoices/view.php?id=<?=$inv['id']?>" style="color:#3b82f6;font-weight:600;text-decoration:none">#<?=h($inv['invoice_number'])?></a></td>
    <td style="font-size:13px"><?=h($inv['first_name'].' '.$inv['last_name'])?></td>
    <td style="font-weight:700"><?=format_currency($inv['total'],$currency)?></td>
    <td style="font-size:12px;color:#64748b"><?=format_date($inv['paid_date'])?></td></tr>
    <?php endforeach?></tbody></table></div>
  </div>
</div>
</div>
<?php include '../partials/footer.php';?>
