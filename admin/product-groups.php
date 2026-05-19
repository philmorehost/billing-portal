<?php
require_once '../includes/config.php';
$admin = Auth::requireAdmin();
$company = DB::setting('company_name', 'Billing Portal');
$page_title = 'Product Groups';
$success = '';
$error = '';

// 1. Add Product Group
if (is_post() && post('action') === 'add' && csrf_verify()) {
    $name = trim(post('name'));
    $slug_input = trim(post('slug'));
    $desc = trim(post('description'));
    $sort_order = (int)post('sort_order', 0);
    $visible = isset($_POST['visible']) ? 1 : 0;

    if (empty($name)) {
        $error = "Group name is required.";
    } else {
        $sl = !empty($slug_input) ? slug($slug_input) : slug($name);
        // Ensure unique slug
        $existing = DB::value("SELECT id FROM product_groups WHERE slug=?", 's', [$sl]);
        if ($existing) {
            $sl = $sl . '-' . rand(100, 999);
        }

        DB::execute(
            "INSERT INTO product_groups (name, slug, description, sort_order, visible) VALUES (?, ?, ?, ?, ?)",
            'sssii', [$name, $sl, $desc, $sort_order, $visible]
        );
        $success = "Product group '{$name}' created successfully!";
    }
}

// 2. Edit Product Group
if (is_post() && post('action') === 'edit' && csrf_verify()) {
    $id = (int)post('id');
    $name = trim(post('name'));
    $slug_input = trim(post('slug'));
    $desc = trim(post('description'));
    $sort_order = (int)post('sort_order', 0);
    $visible = isset($_POST['visible']) ? 1 : 0;

    if (empty($name)) {
        $error = "Group name is required.";
    } else {
        $sl = !empty($slug_input) ? slug($slug_input) : slug($name);
        // Ensure unique slug
        $existing = DB::value("SELECT id FROM product_groups WHERE slug=? AND id != ?", 'si', [$sl, $id]);
        if ($existing) {
            $sl = $sl . '-' . rand(100, 999);
        }

        DB::execute(
            "UPDATE product_groups SET name=?, slug=?, description=?, sort_order=?, visible=? WHERE id=?",
            'sssiii', [$name, $sl, $desc, $sort_order, $visible, $id]
        );
        $success = "Product group '{$name}' updated successfully!";
    }
}

// 3. Delete Product Group
if (is_post() && post('action') === 'delete' && csrf_verify()) {
    $id = (int)post('id');
    $products_count = DB::value("SELECT COUNT(*) FROM products WHERE group_id=?", 'i', [$id]);

    if ($products_count > 0) {
        $error = "Cannot delete product group: there are {$products_count} active product(s) assigned to this group.";
    } else {
        $name = DB::value("SELECT name FROM product_groups WHERE id=?", 'i', [$id]);
        DB::execute("DELETE FROM product_groups WHERE id=?", 'i', [$id]);
        $success = "Product group '{$name}' deleted successfully.";
    }
}

$groups = DB::rows("SELECT g.*, (SELECT COUNT(*) FROM products p WHERE p.group_id=g.id) AS products_count FROM product_groups g ORDER BY g.sort_order, g.name");
include 'partials/header.php';
?>
<div class="bp-content">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <a href="products.php" class="bp-btn bp-btn-outline bp-btn-sm" style="margin-bottom:8px">← Back to Products</a>
      <h1 class="bp-page-title" style="margin:0">📁 Product Groups</h1>
      <p class="bp-page-sub" style="margin:4px 0 0">Create and arrange categories to organize your customer products and plans.</p>
    </div>
    <button type="button" class="bp-btn bp-btn-primary" onclick="openAddModal()">
      ➕ Create New Group
    </button>
  </div>

  <?php if($error):?><div class="alert-custom alert-danger mb-3"><span>✕</span> <?=h($error)?></div><?php endif?>
  <?php if($success):?><div class="alert-custom alert-success mb-3"><span>✓</span> <?=h($success)?></div><?php endif?>

  <div class="bp-card">
    <?php if($groups):?>
      <table class="bp-table">
        <thead>
          <tr>
            <th style="width: 80px; text-align: center">Sort Order</th>
            <th>Group Name</th>
            <th>Slug</th>
            <th>Description</th>
            <th style="text-align: center">Total Products</th>
            <th style="text-align: center">Visible</th>
            <th style="text-align: right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($groups as $g):?>
            <tr>
              <td style="text-align: center; font-weight: 600; color: #64748b"><?=h($g['sort_order'])?></td>
              <td><strong style="color: #0f172a; font-size: 15px"><?=h($g['name'])?></strong></td>
              <td><span style="font-family: monospace; font-size: 13px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px"><?=h($g['slug'])?></span></td>
              <td style="color: #64748b; font-size: 13px"><?=h($g['description'] ?: '—')?></td>
              <td style="text-align: center; font-weight: 700; color: #3b82f6"><?=(int)$g['products_count']?></td>
              <td style="text-align: center">
                <span class="bp-badge bp-badge-<?=$g['visible'] ? 'success' : 'muted'?>">
                  <?=$g['visible'] ? 'Yes' : 'No'?>
                </span>
              </td>
              <td style="text-align: right">
                <div class="d-flex gap-1 justify-content-end">
                  <button type="button" class="bp-btn bp-btn-outline bp-btn-sm" onclick='openEditModal(<?=json_encode($g, JSON_HEX_APOS | JSON_HEX_QUOT)?>)'>
                    Edit
                  </button>
                  <form method="POST" style="display:inline" onsubmit="return confirm('Are you sure you want to delete this product group?')">
                    <?=csrf_input()?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?=$g['id']?>">
                    <button type="submit" class="bp-btn bp-btn-outline bp-btn-sm" style="color:#ef4444; border-color:#fecdd3" <?=$g['products_count'] > 0 ? 'disabled title="Cannot delete group with active products"' : ''?>>
                      Delete
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach?>
        </tbody>
      </table>
    <?php else:?>
      <div class="bp-empty">
        <div class="bp-empty-icon">📁</div>
        <div class="bp-empty-title">No product groups yet</div>
        <p style="color: #64748b; font-size: 14px; margin-top: 4px">Groups help you organize products on the order form and client store.</p>
        <button type="button" class="bp-btn bp-btn-primary bp-btn-sm" style="margin-top:12px" onclick="openAddModal()">
          Create First Group
        </button>
      </div>
    <?php endif?>
  </div>
</div>

<!-- Add/Edit Product Group Modal -->
<div id="group-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(15,23,42,0.4);backdrop-filter:blur(3px);z-index:999;align-items:center;justify-content:center">
  <div class="bp-card" style="width:500px;max-width:95%">
    <div class="bp-card-header">
      <h3 class="bp-card-title"><span id="modal-action-title">Create</span> Product Group</h3>
      <button type="button" class="bp-btn bp-btn-outline bp-btn-sm" onclick="closeModal()">✕</button>
    </div>
    <div class="bp-card-body">
      <form method="POST" id="modal-form">
        <?=csrf_input()?>
        <input type="hidden" name="action" id="modal-action" value="add">
        <input type="hidden" name="id" id="group-id">

        <div class="bp-form-group">
          <label class="bp-label">Group Name *</label>
          <input type="text" name="name" id="group-name" class="bp-input" required placeholder="e.g. Shared Hosting">
        </div>

        <div class="bp-form-group">
          <label class="bp-label">Custom Slug (optional)</label>
          <input type="text" name="slug" id="group-slug" class="bp-input" placeholder="e.g. shared-hosting">
          <div class="bp-input-hint">Leave blank to automatically generate slug from group name.</div>
        </div>

        <div class="bp-form-group">
          <label class="bp-label">Description (optional)</label>
          <textarea name="description" id="group-desc" class="bp-textarea" rows="3" placeholder="Briefly describe products in this category..."></textarea>
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="bp-form-group">
              <label class="bp-label">Sort Order</label>
              <input type="number" name="sort_order" id="group-sort" class="bp-input" value="0" required>
            </div>
          </div>
          <div class="col-md-6" style="display: flex; align-items: center; padding-top: 16px">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:13px; font-weight: 600">
              <input type="checkbox" name="visible" id="group-visible" value="1" checked> Visible to Clients
            </label>
          </div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:24px">
          <button type="button" class="bp-btn bp-btn-outline" onclick="closeModal()">Cancel</button>
          <button type="submit" class="bp-btn bp-btn-primary" id="modal-submit-btn">Save Category</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modal-action-title').textContent = 'Create';
    document.getElementById('modal-action').value = 'add';
    document.getElementById('group-id').value = '';
    document.getElementById('group-name').value = '';
    document.getElementById('group-slug').value = '';
    document.getElementById('group-desc').value = '';
    document.getElementById('group-sort').value = '0';
    document.getElementById('group-visible').checked = true;
    document.getElementById('modal-submit-btn').textContent = 'Create Category';
    document.getElementById('group-modal').style.display = 'flex';
}

function openEditModal(g) {
    document.getElementById('modal-action-title').textContent = 'Edit';
    document.getElementById('modal-action').value = 'edit';
    document.getElementById('group-id').value = g.id;
    document.getElementById('group-name').value = g.name;
    document.getElementById('group-slug').value = g.slug;
    document.getElementById('group-desc').value = g.description || '';
    document.getElementById('group-sort').value = g.sort_order;
    document.getElementById('group-visible').checked = parseInt(g.visible) === 1;
    document.getElementById('modal-submit-btn').textContent = 'Update Category';
    document.getElementById('group-modal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('group-modal').style.display = 'none';
}
</script>
<?php include 'partials/footer.php'; ?>
