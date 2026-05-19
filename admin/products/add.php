<?php
require_once '../../includes/config.php';
$admin=Auth::requireAdmin(); $company=DB::setting('company_name','Billing Portal'); $page_title='Add Product';
$errors=[];

if(is_post()&&csrf_verify()){
    $name=trim(post('name')); $type=post('type','other'); $cur=post('currency','NGN');
    $pm=post('price_monthly'); $pq=post('price_quarterly'); $psa=post('price_semi_annually');
    $pa=post('price_annually'); $pb=post('price_biennially'); $sf=post('setup_fee',0);
    $gid=(int)post('group_id')||null; $desc=trim(post('description')); $module=trim(post('module'));
    $tax=(int)!empty($_POST['tax_enabled']); $visible=(int)!empty($_POST['visible']);
    $auto=(int)!empty($_POST['auto_provision']); $wd=(float)post('wholesale_discount',0);
    if(!$name) $errors[]='Product name required.';
    if(empty($errors)){
        $sl=slug($name);
        // Ensure unique slug
        $existing=DB::value("SELECT id FROM products WHERE slug=?",'s',[$sl]);
        if($existing) $sl=$sl.'-'.time();
        DB::execute("INSERT INTO products (group_id,name,slug,description,type,price_monthly,price_quarterly,price_semi_annually,price_annually,price_biennially,setup_fee,currency,wholesale_discount,module,tax_enabled,auto_provision,visible) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            'issssddddddsdsiii',[$gid?:null,$name,$sl,$desc,$type,(float)$pm?:null,(float)$pq?:null,(float)$psa?:null,(float)$pa?:null,(float)$pb?:null,(float)$sf,$cur,$wd,$module?:null,$tax,$auto,$visible]);
        redirect_with_flash(BASE_URL.'/admin/products.php','success','Product created successfully.');
    }
}
$groups=DB::rows("SELECT * FROM product_groups ORDER BY name");
include '../partials/header.php';
?>
<div class="bp-content">
<div class="d-flex align-items-center gap-3 mb-4"><a href="<?=BASE_URL?>/admin/products.php" class="bp-btn bp-btn-outline bp-btn-sm">← Back</a><h1 class="bp-page-title" style="margin:0">Add Product</h1></div>
<?php if(!empty($errors)):?><div class="alert-custom alert-danger mb-3"><span>✕</span><div><?=implode('<br>',array_map('htmlspecialchars',$errors))?></div></div><?php endif?>
<form method="POST">
<?=csrf_input()?>
<div class="row g-4">
  <div class="col-lg-8">
    <div class="bp-card"><div class="bp-card-header"><h3 class="bp-card-title">Product Details</h3></div><div class="bp-card-body">
      <div class="bp-form-row bp-form-row-2">
        <div class="bp-form-group"><label class="bp-label">Product Name *</label><input type="text" name="name" class="bp-input" value="<?=h(post('name'))?>" required></div>
        <div class="bp-form-group"><label class="bp-label">Type</label>
          <select name="type" class="bp-select">
            <?php foreach(['hosting','domain','vps','dedicated','other'] as $t):?><option value="<?=$t?>" <?=post('type')===$t?'selected':''?>><?=ucfirst($t)?></option><?php endforeach?>
          </select>
        </div>
      </div>
      <div class="bp-form-row bp-form-row-2">
        <div class="bp-form-group"><label class="bp-label">Product Group</label>
          <select name="group_id" class="bp-select"><option value="">No Group</option>
            <?php foreach($groups as $g):?><option value="<?=$g['id']?>" <?=post('group_id')==$g['id']?'selected':''?>><?=h($g['name'])?></option><?php endforeach?>
          </select>
        </div>
        <div class="bp-form-group"><label class="bp-label">Currency</label>
          <select name="currency" class="bp-select">
            <?php foreach(['NGN','USD','GBP','EUR'] as $c):?><option value="<?=$c?>" <?=post('currency')===$c||(post('currency')===''&&$c===DB::setting('base_currency','NGN'))?'selected':''?>><?=$c?></option><?php endforeach?>
          </select>
        </div>
      </div>
      <div class="bp-form-group"><label class="bp-label">Description</label><textarea name="description" class="bp-textarea" rows="3"><?=h(post('description'))?></textarea></div>
    </div></div>

    <div class="bp-card" style="margin-top:16px"><div class="bp-card-header"><h3 class="bp-card-title">Pricing (leave blank if not offered)</h3></div><div class="bp-card-body">
      <div class="bp-form-row bp-form-row-2">
        <?php foreach(['monthly'=>'Monthly','quarterly'=>'Quarterly','semi_annually'=>'Semi-Annual','annually'=>'Annual','biennially'=>'Biennial'] as $k=>$label):?>
        <div class="bp-form-group"><label class="bp-label"><?=$label?></label><input type="number" name="price_<?=$k?>" class="bp-input" step="0.01" min="0" placeholder="0.00" value="<?=h(post('price_'.$k))?>"></div>
        <?php endforeach?>
        <div class="bp-form-group"><label class="bp-label">Setup Fee</label><input type="number" name="setup_fee" class="bp-input" step="0.01" min="0" placeholder="0.00" value="<?=h(post('setup_fee','0'))?>"></div>
      </div>
    </div></div>
  </div>

  <div class="col-lg-4">
    <div class="bp-card"><div class="bp-card-header"><h3 class="bp-card-title">Settings</h3></div><div class="bp-card-body">
      <div class="bp-form-group"><label class="bp-label">Provisioning Module</label>
        <select name="module" class="bp-select">
          <option value="">None (manual)</option>
          <?php foreach([
            'cpanel'          => 'cPanel/WHM',
            'resellerclub'    => 'ResellerClub Domains',
            'namecheap'       => 'Namecheap Domains',
            'connectreseller' => 'ConnectReseller Domains',
            'upperlink'       => 'Upperlink Domains',
            'nocix'           => 'NOCIX Dedicated',
            'time4vps'        => 'Time4VPS'
          ] as $m => $mname):?>
            <option value="<?=$m?>" <?=post('module')===$m?'selected':''?>><?=h($mname)?></option>
          <?php endforeach?>
        </select>
      </div>
      <div class="bp-form-group"><label class="bp-label">Reseller Discount (%)</label><input type="number" name="wholesale_discount" class="bp-input" step="0.1" min="0" max="100" value="<?=h(post('wholesale_discount','0'))?>"><div class="bp-input-hint">% discount given to resellers on this product.</div></div>
      <div style="display:flex;flex-direction:column;gap:10px">
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:13px"><input type="checkbox" name="tax_enabled" value="1" <?=post('tax_enabled','1')?'checked':''?>> Apply Tax</label>
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:13px"><input type="checkbox" name="auto_provision" value="1" <?=post('auto_provision','1')?'checked':''?>> Auto-Provision on Payment</label>
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:13px"><input type="checkbox" name="visible" value="1" <?=post('visible','1')?'checked':''?>> Visible to Clients</label>
      </div>
    </div></div>

    <div class="bp-card" style="margin-top:16px"><div class="bp-card-body">
      <button type="submit" class="bp-btn bp-btn-primary" style="width:100%;justify-content:center;padding:13px">💾 Create Product</button>
    </div></div>
  </div>
</div>
</form>
</div>
<?php include '../partials/footer.php';?>
