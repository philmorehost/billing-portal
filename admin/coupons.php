<?php
require_once '../includes/config.php';
$admin=Auth::requireAdmin(); $company=DB::setting('company_name','Billing Portal'); $page_title='Coupons';

if(is_post()&&csrf_verify()){
    $action=post('action');
    if($action==='create'){
        $code=strtoupper(trim(post('code'))); $type=post('type','percentage'); $val=(float)post('value');
        $max_uses=post('max_uses')?:(null); $vf=post('valid_from')?:null; $vu=post('valid_until')?:null;
        if(!$code||!$val){redirect_with_flash('coupons.php','danger','Code and value required.');}
        $existing=DB::value("SELECT id FROM coupons WHERE code=?",'s',[$code]);
        if($existing){redirect_with_flash('coupons.php','danger','Coupon code already exists.');}
        DB::execute("INSERT INTO coupons (code,type,value,max_uses,valid_from,valid_until,status) VALUES (?,?,?,?,?,?,'active')",'ssdiss',[$code,$type,$val,$max_uses?:(null),$vf,$vu]);
        redirect_with_flash('coupons.php','success','Coupon created.');
    }
    if($action==='toggle'){
        $cid=(int)post('coupon_id');
        $cur=DB::value("SELECT status FROM coupons WHERE id=?",'i',[$cid]);
        DB::execute("UPDATE coupons SET status=? WHERE id=?",'si',[$cur==='active'?'inactive':'active',$cid]);
        redirect_with_flash('coupons.php','success','Coupon updated.');
    }
    if($action==='delete'){
        DB::execute("DELETE FROM coupons WHERE id=?",'i',[(int)post('coupon_id')]);
        redirect_with_flash('coupons.php','success','Coupon deleted.');
    }
}
$coupons=DB::rows("SELECT * FROM coupons ORDER BY id DESC");
include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title">Coupons & Promotions</h1>
<?=flash_html()?>
<div class="row g-4">
  <div class="col-lg-4">
    <div class="bp-card"><div class="bp-card-header"><h3 class="bp-card-title">Create Coupon</h3></div><div class="bp-card-body">
      <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="create">
        <div class="bp-form-group"><label class="bp-label">Coupon Code *</label><input type="text" name="code" class="bp-input" placeholder="SAVE20" style="text-transform:uppercase" required></div>
        <div class="bp-form-row bp-form-row-2">
          <div class="bp-form-group"><label class="bp-label">Type</label>
            <select name="type" class="bp-select"><option value="percentage">Percentage (%)</option><option value="fixed">Fixed Amount</option></select>
          </div>
          <div class="bp-form-group"><label class="bp-label">Value *</label><input type="number" name="value" class="bp-input" step="0.01" min="0" placeholder="e.g. 20" required></div>
        </div>
        <div class="bp-form-group"><label class="bp-label">Max Uses</label><input type="number" name="max_uses" class="bp-input" min="1" placeholder="Unlimited"></div>
        <div class="bp-form-row bp-form-row-2">
          <div class="bp-form-group"><label class="bp-label">Valid From</label><input type="date" name="valid_from" class="bp-input"></div>
          <div class="bp-form-group"><label class="bp-label">Valid Until</label><input type="date" name="valid_until" class="bp-input"></div>
        </div>
        <button type="submit" class="bp-btn bp-btn-primary" style="width:100%;justify-content:center">Create Coupon</button>
      </form>
    </div></div>
  </div>
  <div class="col-lg-8">
    <div class="bp-card">
      <div class="bp-card-header"><h3 class="bp-card-title">All Coupons</h3></div>
      <?php if($coupons):?>
      <table class="bp-table"><thead><tr><th>Code</th><th>Type / Value</th><th>Uses</th><th>Valid Until</th><th>Status</th><th>Actions</th></tr></thead><tbody>
      <?php foreach($coupons as $c):?>
      <tr>
        <td style="font-weight:700;font-family:monospace;letter-spacing:1px"><?=h($c['code'])?></td>
        <td><?=$c['type']==='percentage'?$c['value'].'%':format_currency($c['value'],DB::setting('base_currency','NGN'))?></td>
        <td><?=$c['uses_count']?'/'.($c['max_uses']??'∞')?></td>
        <td style="font-size:13px;color:#64748b"><?=$c['valid_until']?format_date($c['valid_until']):'No limit'?></td>
        <td><span class="bp-badge bp-badge-<?=$c['status']==='active'?'success':'muted'?>"><?=$c['status']?></span></td>
        <td><div class="d-flex gap-1">
          <form method="POST" style="display:inline"><?=csrf_input()?><input type="hidden" name="action" value="toggle"><input type="hidden" name="coupon_id" value="<?=$c['id']?>"><button type="submit" class="bp-btn bp-btn-outline bp-btn-sm"><?=$c['status']==='active'?'Disable':'Enable'?></button></form>
          <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')"><?=csrf_input()?><input type="hidden" name="action" value="delete"><input type="hidden" name="coupon_id" value="<?=$c['id']?>"><button type="submit" class="bp-btn bp-btn-outline bp-btn-sm" style="color:#ef4444;border-color:#fecdd3">Delete</button></form>
        </div></td>
      </tr>
      <?php endforeach?>
      </tbody></table>
      <?php else:?><div class="bp-empty"><div class="bp-empty-icon">🏷</div><div class="bp-empty-title">No coupons yet</div></div><?php endif?>
    </div>
  </div>
</div>
</div>
<?php include 'partials/footer.php';?>
