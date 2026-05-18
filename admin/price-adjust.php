<?php
require_once '../includes/config.php';
require_once INC_PATH.'/modules/reseller.php';
$admin=Auth::requireAdmin(); $company=DB::setting('company_name','Billing Portal'); $page_title='Price Adjustment Tool';
$currency=DB::setting('base_currency','NGN'); $success=''; $error='';

if(is_post()&&csrf_verify()){
    $pct=(float)post('percentage'); $dir=post('direction','increase');
    $scope=post('scope','selected');
    $pids=$scope==='all'
        ?array_column(DB::rows("SELECT id FROM products"),'id')
        :(array)post('product_ids',[]);

    if($pct<=0||$pct>100){$error='Enter a percentage between 0.1 and 100.';}
    elseif(empty($pids)){$error='Select at least one product.';}
    else{
        $count=Reseller::adjustPrices($pids,$pct,$dir);
        log_activity('price_adjustment',"{$dir} {$pct}% on {$count} products",'admin',$admin['id']);
        $success="Price {$dir} of {$pct}% applied to {$count} product(s) successfully.";
    }
}

$groups=DB::rows("SELECT * FROM product_groups ORDER BY sort_order,name");
$products=DB::rows("SELECT p.*,pg.name AS group_name FROM products p LEFT JOIN product_groups pg ON pg.id=p.group_id ORDER BY pg.sort_order,p.sort_order,p.name");
include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title">Price Adjustment Tool</h1>
<p class="bp-page-sub">Apply bulk percentage price changes across products or categories.</p>
<?php if($error):?><div class="alert-custom alert-danger mb-3"><span>✕</span> <?=h($error)?></div><?php endif?>
<?php if($success):?><div class="alert-custom alert-success mb-3"><span>✓</span> <?=h($success)?></div><?php endif?>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="bp-card"><div class="bp-card-header"><h3 class="bp-card-title">⚙ Adjustment Settings</h3></div><div class="bp-card-body">
      <form method="POST" id="adjust-form">
        <?=csrf_input()?>
        <div class="bp-form-group">
          <label class="bp-label">Direction</label>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
            <label style="display:flex;align-items:center;gap:8px;padding:12px 14px;border:1.5px solid #e2e8f0;border-radius:9px;cursor:pointer;font-size:13px" class="dir-opt" onclick="this.style.borderColor='#10b981';document.querySelector('.dir-opt:not(this)').style.borderColor='#e2e8f0'">
              <input type="radio" name="direction" value="increase" checked style="accent-color:#10b981">
              <div><div style="font-weight:600">📈 Increase</div><div style="font-size:11px;color:#64748b">Raise prices</div></div>
            </label>
            <label style="display:flex;align-items:center;gap:8px;padding:12px 14px;border:1.5px solid #e2e8f0;border-radius:9px;cursor:pointer;font-size:13px" class="dir-opt">
              <input type="radio" name="direction" value="decrease" style="accent-color:#ef4444">
              <div><div style="font-weight:600">📉 Decrease</div><div style="font-size:11px;color:#64748b">Lower prices</div></div>
            </label>
          </div>
        </div>
        <div class="bp-form-group">
          <label class="bp-label">Percentage (%)</label>
          <input type="number" name="percentage" class="bp-input" step="0.1" min="0.1" max="100" placeholder="e.g. 10" required>
          <div class="bp-input-hint">Applied to all active billing cycles.</div>
        </div>
        <div class="bp-form-group">
          <label class="bp-label">Apply To</label>
          <select name="scope" class="bp-select" onchange="document.getElementById('product-select').style.display=this.value==='selected'?'block':'none'">
            <option value="selected">Selected Products</option>
            <option value="all">All Products</option>
          </select>
        </div>
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:10px;padding:12px 14px;margin-bottom:16px;font-size:13px;color:#856404">
          ⚠ <strong>Warning:</strong> This permanently modifies prices in the database. Consider exporting a backup first.
        </div>
        <button type="submit" class="bp-btn bp-btn-primary" style="width:100%;justify-content:center" onclick="return confirm('Apply this price adjustment? This cannot be undone.')">
          Apply Price Adjustment
        </button>
      </form>
    </div></div>
  </div>

  <div class="col-lg-8">
    <div class="bp-card" id="product-select">
      <div class="bp-card-header">
        <h3 class="bp-card-title">Select Products</h3>
        <div class="d-flex gap-2">
          <button type="button" class="bp-btn bp-btn-outline bp-btn-sm" onclick="document.querySelectorAll('.prod-cb').forEach(c=>c.checked=true)">Select All</button>
          <button type="button" class="bp-btn bp-btn-outline bp-btn-sm" onclick="document.querySelectorAll('.prod-cb').forEach(c=>c.checked=false)">Clear</button>
        </div>
      </div>
      <div style="max-height:500px;overflow-y:auto">
        <?php
        $last_grp='';
        foreach($products as $p):
          if($p['group_name']!==$last_grp):$last_grp=$p['group_name'];?>
          <div style="padding:8px 20px;background:#f8fafc;font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#64748b;border-bottom:1px solid #f1f5f9"><?=h($p['group_name']??'Other')?></div>
          <?php endif?>
          <label style="display:flex;align-items:center;gap:12px;padding:12px 20px;border-bottom:1px solid #f8fafc;cursor:pointer;transition:background .15s" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
            <input type="checkbox" name="product_ids[]" value="<?=$p['id']?>" class="prod-cb" form="adjust-form" style="width:16px;height:16px;flex-shrink:0">
            <div style="flex:1">
              <div style="font-weight:600;font-size:13px"><?=h($p['name'])?></div>
              <div style="font-size:11px;color:#94a3b8;text-transform:capitalize"><?=$p['type']?></div>
            </div>
            <div style="text-align:right;font-size:13px">
              <?php if($p['price_monthly']):?><div style="font-weight:600"><?=format_currency($p['price_monthly'],$currency)?>/mo</div><?php endif?>
              <?php if($p['price_annually']):?><div style="font-size:11px;color:#64748b"><?=format_currency($p['price_annually'],$currency)?>/yr</div><?php endif?>
            </div>
          </label>
        <?php endforeach?>
      </div>
    </div>
  </div>
</div>
</div>
<?php include 'partials/footer.php';?>
