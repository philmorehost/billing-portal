<?php
require_once '../includes/config.php';
require_once INC_PATH . '/modules/provisioning/dispatcher.php';
$admin = Auth::requireAdmin();
$company = DB::setting('company_name', 'Billing Portal');
$page_title = 'NOCIX Product Sync';

$success = '';
$error = '';
$results = [];

if (is_post() && post('action') === 'sync' && csrf_verify()) {
    try {
        $module = ProvisioningDispatcher::buildModule('nocix');
        if (!$module) throw new Exception("NOCIX module could not be initialized.");

        // Fetch in-stock servers from NOCIX
        $nocix_products = $module->listProducts();
        $in_stock_ids = [];

        foreach ($nocix_products as $p) {
            if (!empty($p['id'])) {
                $in_stock_ids[] = (string)$p['id'];
            }
        }

        // Get all local NOCIX products
        $local_products = DB::rows("SELECT id, name, external_id, visible FROM products WHERE module='nocix'");

        $synced_count = 0;
        $hidden_count = 0;
        $shown_count = 0;

        foreach ($local_products as $lp) {
            $ext_id = (string)$lp['external_id'];
            if (empty($ext_id)) continue;

            $is_in_stock = in_array($ext_id, $in_stock_ids);

            if ($is_in_stock && !$lp['visible']) {
                DB::execute("UPDATE products SET visible=1 WHERE id=?", 'i', [$lp['id']]);
                $shown_count++;
            } elseif (!$is_in_stock && $lp['visible']) {
                DB::execute("UPDATE products SET visible=0 WHERE id=?", 'i', [$lp['id']]);
                $hidden_count++;
            }
            $synced_count++;
        }

        $success = "Sync complete! Processed {$synced_count} products. (Shown: {$shown_count}, Hidden/Out of Stock: {$hidden_count})";

        // Also prepare list of available NOCIX products that are NOT yet in local DB
        foreach ($nocix_products as $np) {
            $exists = false;
            foreach ($local_products as $lp) {
                if ((string)$lp['external_id'] === (string)$np['id']) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $results[] = $np;
            }
        }

    } catch (Exception $e) {
        $error = "NOCIX Sync Error: " . $e->getMessage();
    }
}

include 'partials/header.php';
?>
<div class="bp-content">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="bp-page-title" style="margin:0">🔄 NOCIX Product Sync</h1>
    <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="sync">
        <button type="submit" class="bp-btn bp-btn-primary">Sync Availability Now</button>
    </form>
  </div>

  <?php if ($success): ?><div class="alert-custom alert-success mb-3"><span>✓</span> <?= h($success) ?></div><?php endif ?>
  <?php if ($error): ?><div class="alert-custom alert-danger mb-3"><span>✕</span> <?= h($error) ?></div><?php endif ?>

  <div class="bp-card">
    <div class="bp-card-header"><h3 class="bp-card-title">Available NOCIX Inventory (Not in your local products)</h3></div>
    <div class="bp-card-body">
        <?php if ($results): ?>
        <table class="bp-table">
            <thead>
                <tr>
                    <th>NOCIX ID</th>
                    <th>Name / Description</th>
                    <th>Price (Monthly)</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $r): ?>
                <tr>
                    <td><code><?= h($r['id']) ?></code></td>
                    <td>
                        <div style="font-weight:600"><?= h($r['name']) ?></div>
                        <div style="font-size:12px; color:#64748b"><?= h($r['description'] ?? '') ?></div>
                    </td>
                    <td>$<?= number_format((float)($r['price'] ?? 0), 2) ?></td>
                    <td>
                        <a href="products/add.php?type=dedicated&module=nocix&external_id=<?=h($r['id'])?>&name=<?=urlencode($r['name'])?>&price=<?=h($r['price'])?>" class="bp-btn bp-btn-outline bp-btn-sm">Import</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p style="color:#64748b; text-align:center; padding:20px">No new products found or sync not yet performed.</p>
        <?php endif; ?>
    </div>
  </div>
</div>
<?php include 'partials/footer.php'; ?>
