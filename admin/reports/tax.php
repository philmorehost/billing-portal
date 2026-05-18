<?php
require_once '../../includes/config.php';
$admin=Auth::requireAdmin(); $company=DB::setting('company_name','Billing Portal'); $page_title='Tax / VAT Report';
$currency=DB::setting('base_currency','NGN'); $tax_name=DB::setting('tax_name','VAT');
$year=(int)get_param('year',date('Y'));
$months_data=DB::rows("SELECT MONTH(paid_date) AS month,SUM(subtotal) AS net,SUM(tax_amount) AS tax,SUM(total) AS gross,COUNT(*) AS count FROM invoices WHERE status='paid' AND tax_amount>0 AND YEAR(paid_date)=? GROUP BY MONTH(paid_date) ORDER BY month",'i',[$year]);
$mmap=[];foreach($months_data as $m) $mmap[(int)$m['month']]=$m;
$totals=DB::row("SELECT SUM(subtotal) AS net,SUM(tax_amount) AS tax,SUM(total) AS gross,COUNT(*) AS count FROM invoices WHERE status='paid' AND tax_amount>0 AND YEAR(paid_date)=?",'i',[$year]);
$tax_rate=DB::setting('tax_rate','0');
$months=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
include '../partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title"><?=$tax_name?> / Tax Report — <?=$year?></h1>
<div class="stat-cards">
  <div class="stat-card"><div class="stat-card-icon blue">💰</div><div class="stat-card-value"><?=format_currency($totals['net']??0,$currency)?></div><div class="stat-card-label">Net Revenue</div></div>
  <div class="stat-card"><div class="stat-card-icon amber">🧾</div><div class="stat-card-value"><?=format_currency($totals['tax']??0,$currency)?></div><div class="stat-card-label">Total <?=$tax_name?> Collected</div></div>
  <div class="stat-card"><div class="stat-card-icon green">✓</div><div class="stat-card-value"><?=format_currency($totals['gross']??0,$currency)?></div><div class="stat-card-label">Gross Revenue</div></div>
  <div class="stat-card"><div class="stat-card-icon cyan">%</div><div class="stat-card-value"><?=$tax_rate?>%</div><div class="stat-card-label">Current Tax Rate</div></div>
</div>
<div class="bp-card">
  <div class="bp-card-header"><h3 class="bp-card-title">Monthly Tax Breakdown — <?=$year?></h3></div>
  <table class="bp-table"><thead><tr><th>Month</th><th>Invoices</th><th>Net Amount</th><th><?=$tax_name?> (<?=$tax_rate?>%)</th><th>Gross Total</th></tr></thead><tbody>
  <?php foreach(range(1,12) as $m):$d=$mmap[$m]??null;?>
  <tr style="<?=!$d?'opacity:.4':''?>">
    <td style="font-weight:600"><?=$months[$m-1].' '.$year?></td>
    <td><?=$d?$d['count']:0?></td>
    <td><?=$d?format_currency($d['net'],$currency):'—'?></td>
    <td style="font-weight:<?=$d?'700':'400'?>;color:<?=$d?'#f59e0b':'inherit'?>"><?=$d?format_currency($d['tax'],$currency):'—'?></td>
    <td style="font-weight:<?=$d?'700':'400'?>"><?=$d?format_currency($d['gross'],$currency):'—'?></td>
  </tr>
  <?php endforeach?>
  <tr style="background:#f8fafc">
    <td style="font-weight:800">Total <?=$year?></td>
    <td style="font-weight:800"><?=$totals['count']??0?></td>
    <td style="font-weight:800"><?=format_currency($totals['net']??0,$currency)?></td>
    <td style="font-weight:800;color:#f59e0b"><?=format_currency($totals['tax']??0,$currency)?></td>
    <td style="font-weight:800"><?=format_currency($totals['gross']??0,$currency)?></td>
  </tr>
  </tbody></table>
</div>
</div>
<?php include '../partials/footer.php';?>
