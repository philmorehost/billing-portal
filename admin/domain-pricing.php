<?php
require_once '../includes/config.php';
$admin=Auth::requireAdmin();
$company=DB::setting('company_name','Billing Portal');
$page_title='Domain Pricing Management';
$currency=DB::setting('base_currency','NGN');
$success=''; $error='';

// TLD Synchronization action
if(is_post() && post('action') === 'sync_tlds' && csrf_verify()) {
    $apiKey = DB::setting('module_connectreseller_api_key');
    if (empty($apiKey)) {
        $error = "Please configure your ConnectReseller API Key in Settings first.";
    } else {
        $url = "https://api.connectreseller.com/ConnectReseller/ESHOP/tldsync/?APIKey=" . urlencode($apiKey);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200 || !$response) {
            $error = "ConnectReseller TLD sync failed: HTTP code {$http_code} or no response.";
        } else {
            $data = json_decode($response, true);
            if (!is_array($data)) {
                $error = "Invalid API response payload.";
            } else {
                $synced = 0;
                foreach ($data as $item) {
                    $tld = strtolower(trim($item['tld'] ?? ''));
                    if (empty($tld)) continue;
                    $tld = ltrim($tld, '.');

                    $base_reg = (float)($item['registrationPrice'] ?? 0);
                    $base_ren = (float)($item['renewalPrice'] ?? 0);
                    $base_tr  = (float)($item['transferPrice'] ?? 0);

                    // Check if TLD already exists
                    $existing = DB::row("SELECT * FROM domain_tlds WHERE tld=?", 's', [$tld]);
                    if ($existing) {
                        $val = (float)$existing['markup_value'];
                        $type = $existing['markup_type'];
                        
                        if ($type === 'percentage') {
                            $retail_reg = round($base_reg * (1 + $val / 100), 2);
                            $retail_ren = round($base_ren * (1 + $val / 100), 2);
                            $retail_tr  = round($base_tr * (1 + $val / 100), 2);
                        } else {
                            $retail_reg = round($base_reg + $val, 2);
                            $retail_ren = round($base_ren + $val, 2);
                            $retail_tr  = round($base_tr + $val, 2);
                        }

                        DB::execute(
                            "UPDATE domain_tlds SET base_price_register=?, base_price_renew=?, base_price_transfer=?, retail_price_register=?, retail_price_renew=?, retail_price_transfer=? WHERE tld=?",
                            'dddddds', [$base_reg, $base_ren, $base_tr, $retail_reg, $retail_ren, $retail_tr, $tld]
                        );
                    } else {
                        // Default 20% markup
                        $val = 20.00;
                        $retail_reg = round($base_reg * 1.20, 2);
                        $retail_ren = round($base_ren * 1.20, 2);
                        $retail_tr  = round($base_tr * 1.20, 2);

                        DB::execute(
                            "INSERT INTO domain_tlds (tld, base_price_register, base_price_renew, base_price_transfer, markup_type, markup_value, retail_price_register, retail_price_renew, retail_price_transfer, status) VALUES (?,?,?,?,'percentage',?,?,?,?, 'active')",
                            'sdddddddd', [$tld, $base_reg, $base_ren, $base_tr, $val, $retail_reg, $retail_ren, $retail_tr]
                        );
                    }
                    $synced++;
                }
                $success = "Successfully synchronized {$synced} domain extension(s) from ConnectReseller!";
            }
        }
    }
}

// Update Single TLD Markup Config
if(is_post() && post('action') === 'update_markup' && csrf_verify()) {
    $tld_id = (int)post('tld_id');
    $markup_type = post('markup_type') === 'fixed' ? 'fixed' : 'percentage';
    $markup_val = (float)post('markup_value');
    $status = post('status') === 'inactive' ? 'inactive' : 'active';

    $row = DB::row("SELECT * FROM domain_tlds WHERE id=?", 'i', [$tld_id]);
    if ($row) {
        $base_reg = (float)$row['base_price_register'];
        $base_ren = (float)$row['base_price_renew'];
        $base_tr  = (float)$row['base_price_transfer'];

        if ($markup_type === 'percentage') {
            $retail_reg = round($base_reg * (1 + $markup_val / 100), 2);
            $retail_ren = round($base_ren * (1 + $markup_val / 100), 2);
            $retail_tr  = round($base_tr * (1 + $markup_val / 100), 2);
        } else {
            $retail_reg = round($base_reg + $markup_val, 2);
            $retail_ren = round($base_ren + $markup_val, 2);
            $retail_tr  = round($base_tr + $markup_val, 2);
        }

        DB::execute(
            "UPDATE domain_tlds SET markup_type=?, markup_value=?, retail_price_register=?, retail_price_renew=?, retail_price_transfer=?, status=? WHERE id=?",
            'sddddsi', [$markup_type, $markup_val, $retail_reg, $retail_ren, $retail_tr, $status, $tld_id]
        );
        $success = "TLD markup settings updated successfully.";
    }
}

// Bulk Apply Markup Action
if(is_post() && post('action') === 'bulk_markup' && csrf_verify()) {
    $markup_val = (float)post('bulk_markup_value');
    $markup_type = post('bulk_markup_type') === 'fixed' ? 'fixed' : 'percentage';
    
    $tlds = DB::rows("SELECT * FROM domain_tlds");
    $updated = 0;
    foreach ($tlds as $row) {
        $base_reg = (float)$row['base_price_register'];
        $base_ren = (float)$row['base_price_renew'];
        $base_tr  = (float)$row['base_price_transfer'];

        if ($markup_type === 'percentage') {
            $retail_reg = round($base_reg * (1 + $markup_val / 100), 2);
            $retail_ren = round($base_ren * (1 + $markup_val / 100), 2);
            $retail_tr  = round($base_tr * (1 + $markup_val / 100), 2);
        } else {
            $retail_reg = round($base_reg + $markup_val, 2);
            $retail_ren = round($base_ren + $markup_val, 2);
            $retail_tr  = round($base_tr + $markup_val, 2);
        }

        DB::execute(
            "UPDATE domain_tlds SET markup_type=?, markup_value=?, retail_price_register=?, retail_price_renew=?, retail_price_transfer=? WHERE id=?",
            'sddddi', [$markup_type, $markup_val, $retail_reg, $retail_ren, $retail_tr, $row['id']]
        );
        $updated++;
    }
    $success = "Successfully bulk-applied {$markup_val}" . ($markup_type==='percentage'?'%':'') . " markup to all {$updated} active TLD(s).";
}

$tlds = DB::rows("SELECT * FROM domain_tlds ORDER BY tld ASC");
include 'partials/header.php';
?>
<div class="bp-content">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px">
    <div>
      <h1 class="bp-page-title" style="margin-bottom:4px">🌐 Domain Pricing Sync Tool</h1>
      <p class="bp-page-sub">Manage your TLDs, wholesale prices from ConnectReseller, and profit markup settings.</p>
    </div>
    <div style="display:flex;gap:8px">
      <form method="POST">
        <?=csrf_input()?>
        <input type="hidden" name="action" value="sync_tlds">
        <button type="submit" class="bp-btn bp-btn-primary">
          🔄 Sync Extensions from ConnectReseller
        </button>
      </form>
    </div>
  </div>

  <?php if($error):?><div class="alert-custom alert-danger mb-3"><span>✕</span> <?=h($error)?></div><?php endif?>
  <?php if($success):?><div class="alert-custom alert-success mb-3"><span>✓</span> <?=h($success)?></div><?php endif?>

  <div class="row g-4">
    <!-- Bulk Markup Card -->
    <div class="col-lg-4">
      <div class="bp-card">
        <div class="bp-card-header"><h3 class="bp-card-title">📈 Bulk Apply Profit Markup</h3></div>
        <div class="bp-card-body">
          <form method="POST">
            <?=csrf_input()?>
            <input type="hidden" name="action" value="bulk_markup">
            
            <div class="bp-form-group">
              <label class="bp-label">Markup Method</label>
              <select name="bulk_markup_type" class="bp-select">
                <option value="percentage">Percentage (%)</option>
                <option value="fixed">Fixed Flat Margin (<?=h($currency)?>)</option>
              </select>
            </div>
            
            <div class="bp-form-group">
              <label class="bp-label">Profit Markup Amount</label>
              <input type="number" name="bulk_markup_value" class="bp-input" step="0.01" min="0" placeholder="e.g. 20" required>
              <div class="bp-input-hint">This percentage/amount will be applied on top of the wholesale cost for retail billing.</div>
            </div>
            
            <button type="submit" class="bp-btn bp-btn-accent" style="width:100%;justify-content:center" onclick="return confirm('Bulk apply this markup to ALL extensions? Existing configurations will be overwritten.')">
              Apply Profit Margin
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Active Extensions Table -->
    <div class="col-lg-8">
      <div class="bp-card">
        <div class="bp-card-header">
          <h3 class="bp-card-title">Domain Extensions & Profit Rules (<?=count($tlds)?>)</h3>
        </div>
        <div style="overflow-x:auto">
          <table class="bp-table">
            <thead>
              <tr>
                <th>TLD</th>
                <th>ConnectReseller Cost</th>
                <th>Profit Margin</th>
                <th>Retail Customer Price</th>
                <th>Status</th>
                <th style="text-align:right">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if(empty($tlds)):?>
                <tr>
                  <td colspan="6" style="text-align:center;padding:40px;color:#94a3b8">
                    <div style="font-size:32px;margin-bottom:8px">📡</div>
                    <strong>No domain extensions found.</strong><br>Click "Sync Extensions" at the top to import active TLDs and wholesale pricing from ConnectReseller.
                  </td>
                </tr>
              <?php else: foreach($tlds as $t):?>
                <tr>
                  <td style="font-weight:700;color:#0f172a;font-family:monospace;font-size:15px">.<?=h($t['tld'])?></td>
                  <td>
                    <div style="font-size:12px;color:#64748b">Reg: $<?=number_format($t['base_price_register'], 2)?></div>
                    <div style="font-size:12px;color:#64748b">Ren: $<?=number_format($t['base_price_renew'], 2)?></div>
                  </td>
                  <td>
                    <span class="bp-badge bp-badge-info" style="font-size:11px">
                      <?=h($t['markup_value'])?><?=$t['markup_type']==='percentage'?'%':' Flat'?>
                    </span>
                  </td>
                  <td>
                    <div style="font-weight:700;color:#10b981;font-size:13px"><?=format_currency($t['retail_price_register'],$currency)?></div>
                    <div style="font-size:11px;color:#64748b">Renew: <?=format_currency($t['retail_price_renew'],$currency)?></div>
                  </td>
                  <td>
                    <span class="bp-badge bp-badge-<?=$t['status']==='active'?'success':'danger'?>">
                      <?=ucfirst($t['status'])?>
                    </span>
                  </td>
                  <td style="text-align:right">
                    <button class="bp-btn bp-btn-outline bp-btn-sm" onclick="editTld(<?=h(json_encode($t))?>)">
                      ⚙ Edit Profit
                    </button>
                  </td>
                </tr>
              <?php endforeach; endif;?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Edit TLD Modal Dialog -->
<div id="edit-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(15,23,42,0.4);backdrop-filter:blur(3px);z-index:999;align-items:center;justify-content:center">
  <div class="bp-card" style="width:420px;max-width:90%">
    <div class="bp-card-header">
      <h3 class="bp-card-title">Modify TLD Markup</h3>
      <button class="bp-btn bp-btn-outline bp-btn-sm" onclick="closeModal()">✕</button>
    </div>
    <div class="bp-card-body">
      <form method="POST">
        <?=csrf_input()?>
        <input type="hidden" name="action" value="update_markup">
        <input type="hidden" name="tld_id" id="m-tld-id">
        
        <div style="margin-bottom:16px;background:#f8fafc;padding:12px;border-radius:8px">
          <div style="font-size:11px;text-transform:uppercase;color:#64748b;font-weight:700">Selected Extension</div>
          <div id="m-tld-name" style="font-size:20px;font-weight:800;font-family:monospace;color:#0f172a">.com</div>
        </div>

        <div class="bp-form-group">
          <label class="bp-label">Markup Type</label>
          <select name="markup_type" id="m-markup-type" class="bp-select">
            <option value="percentage">Percentage (%)</option>
            <option value="fixed">Fixed flat margin (<?=h($currency)?>)</option>
          </select>
        </div>

        <div class="bp-form-group">
          <label class="bp-label">Profit Markup Value</label>
          <input type="number" name="markup_value" id="m-markup-value" class="bp-input" step="0.01" min="0" required>
        </div>

        <div class="bp-form-group">
          <label class="bp-label">TLD Availability Status</label>
          <select name="status" id="m-status" class="bp-select">
            <option value="active">Active (available for purchase)</option>
            <option value="inactive">Inactive (hide from customer searches)</option>
          </select>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:24px">
          <button type="button" class="bp-btn bp-btn-outline" onclick="closeModal()">Cancel</button>
          <button type="submit" class="bp-btn bp-btn-primary">Save Markup Rules</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function editTld(tld) {
    document.getElementById('m-tld-id').value = tld.id;
    document.getElementById('m-tld-name').textContent = '.' + tld.tld;
    document.getElementById('m-markup-type').value = tld.markup_type;
    document.getElementById('m-markup-value').value = tld.markup_value;
    document.getElementById('m-status').value = tld.status;
    
    document.getElementById('edit-modal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('edit-modal').style.display = 'none';
}
</script>
<?php include 'partials/footer.php'; ?>
