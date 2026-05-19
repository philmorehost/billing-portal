<?php
require_once '../includes/config.php';
$admin=Auth::requireAdmin(); $company=DB::setting('company_name','Billing Portal'); $page_title='Products';

if(is_post()&&csrf_verify()&&post('action')==='delete'){
    $pid=(int)post('product_id');
    $in_use=DB::value("SELECT COUNT(*) FROM services WHERE product_id=?",'i',[$pid]);
    if($in_use>0) redirect_with_flash('products.php','danger','Cannot delete: product has active services.');
    DB::execute("DELETE FROM products WHERE id=?",'i',[$pid]);
    redirect_with_flash('products.php','success','Product deleted.');
}

if(is_post()&&csrf_verify()&&post('action')==='duplicate'){
    $pid=(int)post('product_id');
    $p=DB::row("SELECT * FROM products WHERE id=?",'i',[$pid]);
    if(!$p) {
        redirect_with_flash('products.php','danger','Product not found.');
    } else {
        $newName = $p['name'] . ' (Copy)';
        $newSlug = $p['slug'] . '-copy-' . rand(100,999);
        
        DB::execute(
            "INSERT INTO products (group_id, name, slug, description, type, price_monthly, price_quarterly, price_semi_annually, price_annually, price_biennially, setup_fee, currency, wholesale_discount, module, tax_enabled, auto_provision, visible, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            'isssdddddddsdisiii',
            [
                $p['group_id'], $newName, $newSlug, $p['description'], $p['type'],
                $p['price_monthly'], $p['price_quarterly'], $p['price_semi_annually'],
                $p['price_annually'], $p['price_biennially'], $p['setup_fee'],
                $p['currency'], $p['wholesale_discount'], $p['module'],
                $p['tax_enabled'], $p['auto_provision'], $p['visible'], $p['sort_order']
            ]
        );
        $newId = DB::insertId();
        redirect_with_flash("products/edit.php?id={$newId}", 'success', 'Product duplicated successfully! You can now edit the duplicate.');
    }
}

$groups=DB::rows("SELECT * FROM product_groups ORDER BY sort_order,name");
$products=DB::rows("SELECT p.*,pg.name AS group_name,(SELECT COUNT(*) FROM services s WHERE s.product_id=p.id AND s.status='active') AS active_count FROM products p LEFT JOIN product_groups pg ON pg.id=p.group_id ORDER BY p.sort_order,p.name");
include 'partials/header.php';
?>
<div class="bp-content">
<div class="d-flex align-items-center justify-content-between mb-4">
  <div><h1 class="bp-page-title" style="margin:0">Products & Services</h1><p class="bp-page-sub" style="margin:4px 0 0"><?=count($products)?> products</p></div>
  <div class="d-flex gap-2">
    <a href="product-groups.php" class="bp-btn bp-btn-outline" style="border-color:#cbd5e1; color:#475569">📁 Manage Groups</a>
    <a href="products/add.php" class="bp-btn bp-btn-primary">➕ Add Product</a>
  </div>
</div>
<?=flash_html()?>
<div class="bp-card">
<?php if($products):?>
<table class="bp-table"><thead><tr><th>Product</th><th>Type</th><th>Group</th><th>Monthly Price</th><th>Active Services</th><th>Visible</th><th>Actions</th></tr></thead><tbody>
<?php foreach($products as $p):?>
<tr>
  <td><div style="font-weight:600"><?=h($p['name'])?></div><div style="font-size:12px;color:#64748b"><?=h($p['slug'])?></div></td>
  <td><span class="bp-badge bp-badge-info" style="text-transform:capitalize"><?=$p['type']?></span></td>
  <td style="font-size:13px;color:#64748b"><?=h($p['group_name']??'—')?></td>
  <td style="font-weight:600"><?=$p['price_monthly']?format_currency($p['price_monthly'],$p['currency']):'—'?></td>
  <td style="font-weight:600"><?=$p['active_count']?></td>
  <td><span class="bp-badge bp-badge-<?=$p['visible']?'success':'muted'?>"><?=$p['visible']?'Yes':'No'?></span></td>
  <td><div class="d-flex gap-1">
    <a href="products/edit.php?id=<?=$p['id']?>" class="bp-btn bp-btn-outline bp-btn-sm">Edit</a>
    <form method="POST" style="display:inline" onsubmit="return confirm('Duplicate this product?')">
      <?=csrf_input()?><input type="hidden" name="action" value="duplicate"><input type="hidden" name="product_id" value="<?=$p['id']?>">
      <button type="submit" class="bp-btn bp-btn-outline bp-btn-sm" style="color:#8b5cf6;border-color:#ddd6fe">Duplicate</button>
    </form>
    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this product?')">
      <?=csrf_input()?><input type="hidden" name="action" value="delete"><input type="hidden" name="product_id" value="<?=$p['id']?>">
      <button type="submit" class="bp-btn bp-btn-outline bp-btn-sm" style="color:#ef4444;border-color:#fecdd3">Delete</button>
    </form>
  </div></td>
</tr>
<?php endforeach?>
</tbody></table>
<?php else:?><div class="bp-empty"><div class="bp-empty-icon">📦</div><div class="bp-empty-title">No products yet</div><a href="products/add.php" class="bp-btn bp-btn-primary bp-btn-sm" style="margin-top:12px">Add First Product</a></div><?php endif?>
</div>
</div>
<?php include 'partials/footer.php';?>
