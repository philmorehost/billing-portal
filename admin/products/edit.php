<?php
require_once '../../includes/config.php';
$admin=Auth::requireAdmin(); $company=DB::setting('company_name','Billing Portal'); $page_title='Edit Product';
$pid=(int)get_param('id'); $product=DB::row("SELECT * FROM products WHERE id=?",'i',[$pid]);
if(!$product) redirect(BASE_URL.'/admin/products.php');
$errors=[];

if(is_post()&&csrf_verify()){
    $name=trim(post('name')); $type=post('type'); $cur=post('currency');
    $slug=slug(trim(post('slug')));
    $gid = post('group_id') !== '' ? (int) post('group_id') : null; $wd=(float)post('wholesale_discount',0);
    $ext_id = trim(post('external_id'));
    if(!$name) $errors[]='Product name required.';
    if(!$slug) $errors[]='Slug (URL Friendly Name) required.';
    
    // Check if slug is unique
    $existing=DB::value("SELECT id FROM products WHERE slug=? AND id!=?",'si',[$slug, $pid]);
    if($existing) $errors[]='The slug is already used by another product.';
    
    if(empty($errors)){
        DB::execute("UPDATE products SET group_id=?,name=?,slug=?,description=?,type=?,price_monthly=?,price_quarterly=?,price_semi_annually=?,price_annually=?,price_biennially=?,setup_fee=?,currency=?,wholesale_discount=?,module=?,external_id=?,tax_enabled=?,auto_provision=?,visible=?,require_domain=?,compulsory_new_domain=? WHERE id=?",
            'issssddddddsdsisiiiiii',[
                $gid?:null,post('name'),$slug,post('description'),post('type'),
                (float)post('price_monthly')?:null,(float)post('price_quarterly')?:null,(float)post('price_semi_annually')?:null,
                (float)post('price_annually')?:null,(float)post('price_biennially')?:null,(float)post('setup_fee',0),
                post('currency'),post('wholesale_discount',0),post('module')?:null,
                $ext_id?:null,
                (int)!empty($_POST['tax_enabled']),(int)!empty($_POST['auto_provision']),(int)!empty($_POST['visible']),
                (int)!empty($_POST['require_domain']),(int)!empty($_POST['compulsory_new_domain']),
                $pid
            ]);
        redirect_with_flash(BASE_URL.'/admin/products.php','success','Product updated.');
    }
}

// Pre-fill with existing values if not POST
if(!is_post()) $_POST=array_merge($_POST,$product);
$groups=DB::rows("SELECT * FROM product_groups ORDER BY name");
include '../partials/header.php';
?>
<div class="bp-content">
<div class="d-flex align-items-center gap-3 mb-4"><a href="<?=BASE_URL?>/admin/products.php" class="bp-btn bp-btn-outline bp-btn-sm">← Back</a><h1 class="bp-page-title" style="margin:0">Edit: <?=h($product['name'])?></h1></div>
<?php if(!empty($errors)):?><div class="alert-custom alert-danger mb-3"><span>✕</span> <?=implode(', ',array_map('htmlspecialchars',$errors))?></div><?php endif?>
<form method="POST">
<?=csrf_input()?>
<div class="row g-4">
  <div class="col-lg-8">
    <div class="bp-card"><div class="bp-card-header"><h3 class="bp-card-title">Product Details</h3></div><div class="bp-card-body">
      <div class="bp-form-row bp-form-row-2">
        <div class="bp-form-group"><label class="bp-label">Product Name *</label><input type="text" name="name" class="bp-input" value="<?=h(post('name'))?>" required></div>
        <div class="bp-form-group"><label class="bp-label">Slug (URL Friendly Name) *</label><input type="text" name="slug" class="bp-input" value="<?=h(post('slug'))?>" required><div class="bp-input-hint" style="margin-top:2px;font-size:11px">Used for URL: /client/order.php?product=<span>[slug]</span></div></div>
      </div>
      <div class="bp-form-row bp-form-row-3">
        <div class="bp-form-group"><label class="bp-label">Type</label>
          <select name="type" class="bp-select"><?php foreach(['hosting','domain','vps','dedicated','other'] as $t):?><option value="<?=$t?>" <?=post('type')===$t?'selected':''?>><?=ucfirst($t)?></option><?php endforeach?></select>
        </div>
        <div class="bp-form-group"><label class="bp-label">Group</label>
          <select name="group_id" class="bp-select"><option value="">No Group</option><?php foreach($groups as $g):?><option value="<?=$g['id']?>" <?=post('group_id')==$g['id']?'selected':''?>><?=h($g['name'])?></option><?php endforeach?></select>
        </div>
        <div class="bp-form-group"><label class="bp-label">Currency</label>
          <select name="currency" class="bp-select"><?php foreach(['NGN','USD','GBP','EUR'] as $c):?><option value="<?=$c?>" <?=post('currency')===$c?'selected':''?>><?=$c?></option><?php endforeach?></select>
        </div>
      </div>
      <div class="bp-form-group"><label class="bp-label">Description</label><textarea name="description" class="bp-textarea" rows="3"><?=h(post('description'))?></textarea></div>
    </div></div>
    <div class="bp-card" style="margin-top:16px"><div class="bp-card-header"><h3 class="bp-card-title">Pricing</h3></div><div class="bp-card-body">
      <div class="bp-form-row bp-form-row-2">
        <?php foreach(['monthly'=>'Monthly','quarterly'=>'Quarterly','semi_annually'=>'Semi-Annual','annually'=>'Annual','biennially'=>'Biennial'] as $k=>$label):?>
        <div class="bp-form-group"><label class="bp-label"><?=$label?></label><input type="number" name="price_<?=$k?>" class="bp-input" step="0.01" min="0" value="<?=h(post('price_'.$k))?>"></div>
        <?php endforeach?>
        <div class="bp-form-group"><label class="bp-label">Setup Fee</label><input type="number" name="setup_fee" class="bp-input" step="0.01" min="0" value="<?=h(post('setup_fee'))?>"></div>
      </div>
    </div></div>
  </div>
  <div class="col-lg-4">
    <div class="bp-card"><div class="bp-card-header"><h3 class="bp-card-title">Settings</h3></div><div class="bp-card-body">
      <div class="bp-form-group"><label class="bp-label">Module</label>
        <select name="module" class="bp-select">
          <option value="">None</option>
          <?php foreach([
            'cpanel'          => 'cPanel/WHM',
            'resellerclub'    => 'ResellerClub Domains',
            'namecheap'       => 'Namecheap Domains',
            'connectreseller' => 'ConnectReseller Domains',
            'upperlink'       => 'Upperlink Domains',
            'nocix'           => 'NOCIX Dedicated',
            'time4vps'        => 'Time4VPS',
            'interserver'     => 'Interserver',
            'thesslstore'     => 'The SSL Store'
          ] as $m => $mname):?>
            <option value="<?=$m?>" <?=post('module')===$m?'selected':''?>><?=h($mname)?></option>
          <?php endforeach?>
        </select>
      </div>
      <div class="bp-form-group"><label class="bp-label">External / Plan ID</label><input type="text" name="external_id" class="bp-input" value="<?=h(post('external_id'))?>" placeholder="Module-specific ID"></div>
      <div class="bp-form-group"><label class="bp-label">Wholesale Discount (%)</label><input type="number" name="wholesale_discount" class="bp-input" step="0.1" min="0" max="100" value="<?=h(post('wholesale_discount'))?>"></div>
      <div style="display:flex;flex-direction:column;gap:10px">
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:13px"><input type="checkbox" name="tax_enabled" value="1" <?=post('tax_enabled')?'checked':''?>> Apply Tax</label>
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:13px"><input type="checkbox" name="auto_provision" value="1" <?=post('auto_provision')?'checked':''?>> Auto-Provision</label>
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:13px"><input type="checkbox" name="visible" value="1" <?=post('visible')?'checked':''?>> Visible</label>
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:13px"><input type="checkbox" name="require_domain" value="1" <?=post('require_domain')?'checked':''?>> Require Domain Name</label>
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:13px"><input type="checkbox" name="compulsory_new_domain" value="1" <?=post('compulsory_new_domain')?'checked':''?>> Registration is Compulsory (Must register a new domain)</label>
      </div>
    </div></div>
    <div class="bp-card" style="margin-top:16px"><div class="bp-card-body"><button type="submit" class="bp-btn bp-btn-primary" style="width:100%;justify-content:center;padding:13px">💾 Update Product</button></div></div>
  </div>
</div>
</form>
</div>
<?php include '../partials/footer.php';?>
