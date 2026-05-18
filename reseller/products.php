<?php
require_once '../includes/config.php';
require_once INC_PATH.'/modules/reseller.php';
if(empty($_SESSION['reseller_id'])) redirect(BASE_URL.'/reseller/login.php');
$reseller_id=$_SESSION['reseller_id'];
$reseller=DB::row("SELECT * FROM resellers WHERE id=?",'i',[$reseller_id]);
$reseller_client=DB::row("SELECT * FROM clients WHERE id=?",'i',[$reseller['client_id']]);
$company=DB::setting('company_name','Billing Portal');
$currency=DB::setting('base_currency','NGN');
$page_title='Product Pricing';

$products=DB::rows("SELECT p.*,pg.name AS group_name FROM products p LEFT JOIN product_groups pg ON pg.id=p.group_id WHERE p.visible=1 ORDER BY pg.sort_order,p.sort_order,p.name");
$markup=(float)($reseller['markup_percentage']??20);
$cycles=['monthly'=>'Monthly','quarterly'=>'Quarterly','semi_annually'=>'Semi-Annual','annually'=>'Annual'];

include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title">Product Pricing</h1>
<p class="bp-page-sub">Your wholesale costs vs. what your clients pay at your <?=$markup?>% markup.</p>

<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:16px 20px;margin-bottom:24px;font-size:13px;color:#1e40af;display:flex;align-items:center;gap:10px">
  <span style="font-size:18px">💡</span>
  <div>You can change your retail markup % in <a href="settings.php" style="font-weight:700;color:#1e40af">Branding Settings</a>. All prices below update automatically.</div>
</div>

<div class="bp-card">
  <table class="bp-table">
    <thead><tr><th>Product</th><th>Type</th><th>Cycle</th><th>Wholesale (You Pay)</th><th>Retail (Client Pays)</th><th>Your Margin</th></tr></thead>
    <tbody>
    <?php
    $last_group='';
    foreach($products as $p):
      if($p['group_name']!==$last_group):$last_group=$p['group_name'];?>
      <tr style="background:#f8fafc"><td colspan="6" style="font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:#64748b;padding:10px 16px"><?=h($p['group_name']??'Other')?></td></tr>
      <?php endif?>
      <?php foreach($cycles as $cycle_key=>$cycle_label):
        $ws=Reseller::getWholesalePrice($p['id'],$cycle_key);
        if(!$ws) continue;
        $retail=Reseller::getRetailPrice($p['id'],$cycle_key,$reseller_id);
        $margin=$retail-$ws;
      ?>
      <tr>
        <td><?php if($cycle_key==='monthly'):?><div style="font-weight:600"><?=h($p['name'])?></div><?php if($p['description']):?><div style="font-size:12px;color:#64748b;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=h($p['description'])?></div><?php endif?><?php else:?><div style="padding-left:16px;color:#94a3b8;font-size:12px">↳</div><?php endif?></td>
        <td><?php if($cycle_key==='monthly'):?><span class="bp-badge bp-badge-info" style="text-transform:capitalize"><?=$p['type']?></span><?php endif?></td>
        <td style="font-size:13px;color:#64748b"><?=$cycle_label?></td>
        <td>
          <div style="display:flex;align-items:center;gap:6px">
            <span style="font-weight:600"><?=format_currency($ws,$currency)?></span>
            <?php $disc=(float)($p['wholesale_discount']?:DB::setting('reseller_default_discount',20));?>
            <span style="font-size:10px;background:#f0fdf4;color:#166534;padding:1px 6px;border-radius:10px;font-weight:700"><?=$disc?>% off retail</span>
          </div>
        </td>
        <td style="font-weight:700;color:#0f172a"><?=format_currency($retail,$currency)?></td>
        <td>
          <div style="font-weight:700;color:#10b981"><?=format_currency($margin,$currency)?></div>
          <div style="font-size:11px;color:#64748b"><?=round(($margin/$retail)*100,1)?>% margin</div>
        </td>
      </tr>
      <?php endforeach?>
    <?php endforeach?>
    </tbody>
  </table>
</div>
</div>
<?php include 'partials/footer.php';?>
