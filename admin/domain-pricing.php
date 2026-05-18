<?php
require_once '../includes/config.php';
$admin=Auth::requireAdmin();
$company=DB::setting('company_name','Billing Portal');
$page_title='Domain Pricing Management';
$currency=DB::setting('base_currency','NGN');
$success=''; $error='';

// 1. Sync from ConnectReseller
if(is_post() && post('action') === 'sync_tlds' && csrf_verify()) {
    $apiKey = DB::setting('module_connectreseller_api_key');
    if (empty($apiKey)) {
        $error = "Please configure your ConnectReseller API Key in Settings first.";
    } else {
        $url = "https://api.connectreseller.com/ConnectReseller/ESHOP/tldsync/?APIKey=" . urlencode($apiKey);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200 || !$response) {
            $error = "ConnectReseller TLD sync failed: HTTP code {$http_code} or no response.";
        } else {
            $data = json_decode($response, true);
            $tldList = [];
            
            if (is_array($data)) {
                if (isset($data['responseData']) && is_array($data['responseData'])) {
                    $tldList = $data['responseData'];
                } elseif (isset($data['responseMsg']['statusCode']) && $data['responseMsg']['statusCode'] != 200) {
                    $error = "ConnectReseller API Error: " . ($data['responseMsg']['message'] ?? 'Unknown');
                } else {
                    $first = reset($data);
                    if (is_array($first) && (isset($first['tld']) || isset($first['registrationPrice']))) {
                        $tldList = $data;
                    } else {
                        $error = "ConnectReseller Response Error: " . ($data['responseMsg']['message'] ?? 'Invalid response format.');
                    }
                }
            } else {
                $error = "Invalid API response from ConnectReseller.";
            }

            if (empty($error) && !empty($tldList)) {
                $synced = 0;
                foreach ($tldList as $item) {
                    $tld = strtolower(trim($item['tld'] ?? ''));
                    if (empty($tld)) continue;
                    $tld = ltrim($tld, '.');

                    $base_reg = (float)($item['registrationPrice'] ?? 0);
                    $base_ren = (float)($item['renewalPrice'] ?? 0);
                    $base_tr  = (float)($item['transferPrice'] ?? 0);

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
                            "UPDATE domain_tlds SET registrar='connectreseller', base_price_register=?, base_price_renew=?, base_price_transfer=?, retail_price_register=?, retail_price_renew=?, retail_price_transfer=? WHERE tld=?",
                            'dddddds', [$base_reg, $base_ren, $base_tr, $retail_reg, $retail_ren, $retail_tr, $tld]
                        );
                    } else {
                        $val = 20.00;
                        $retail_reg = round($base_reg * 1.20, 2);
                        $retail_ren = round($base_ren * 1.20, 2);
                        $retail_tr  = round($base_tr * 1.20, 2);

                        DB::execute(
                            "INSERT INTO domain_tlds (tld, registrar, base_price_register, base_price_renew, base_price_transfer, markup_type, markup_value, retail_price_register, retail_price_renew, retail_price_transfer, status) VALUES (?, 'connectreseller', ?,?,?, 'percentage', ?,?,?,?, 'active')",
                            'sdddddddd', [$tld, $base_reg, $base_ren, $base_tr, $val, $retail_reg, $retail_ren, $retail_tr]
                        );
                    }
                    $synced++;
                }
                $success = "Successfully synchronized {$synced} domain extension(s) from ConnectReseller!";
            } elseif (empty($error)) {
                $error = "No active domain extensions found in the API response.";
            }
        }
    }
}

// 2. Sync from ResellerClub
if(is_post() && post('action') === 'sync_resellerclub' && csrf_verify()) {
    $resellerId = DB::setting('module_resellerclub_reseller_id');
    $apiKey = DB::setting('module_resellerclub_api_key');
    $testMode = DB::setting('module_resellerclub_test_mode') === '1';

    if (empty($resellerId) || empty($apiKey)) {
        $error = "Please configure your ResellerClub Reseller ID and API Key in Settings first.";
    } else {
        $base = $testMode ? 'https://test.httpapi.com/api' : 'https://httpapi.com/api';
        $url = "{$base}/products/reseller-cost-price.json?auth-userid=" . urlencode($resellerId) . "&api-key=" . urlencode($apiKey);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200 || !$response) {
            $error = "ResellerClub TLD sync failed: HTTP code {$http_code} or no response.";
        } else {
            $data = json_decode($response, true);
            if (!is_array($data)) {
                $error = "Invalid API response from ResellerClub.";
            } else {
                $synced = 0;
                foreach ($data as $key => $item) {
                    if (!str_starts_with($key, 'dom') || !isset($item['addnewdomain']['1'])) continue;
                    
                    $tld = strtolower(substr($key, 3));
                    if (empty($tld)) continue;
                    
                    $base_reg = (float)($item['addnewdomain']['1'] ?? 0);
                    $base_ren = (float)($item['renewdomain']['1'] ?? 0);
                    $base_tr  = (float)($item['addtransferdomain']['1'] ?? 0);
                    
                    if ($base_reg <= 0) continue;
                    
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
                            "UPDATE domain_tlds SET registrar='resellerclub', base_price_register=?, base_price_renew=?, base_price_transfer=?, retail_price_register=?, retail_price_renew=?, retail_price_transfer=? WHERE tld=?",
                            'dddddds', [$base_reg, $base_ren, $base_tr, $retail_reg, $retail_ren, $retail_tr, $tld]
                        );
                    } else {
                        $val = 20.00;
                        $retail_reg = round($base_reg * 1.20, 2);
                        $retail_ren = round($base_ren * 1.20, 2);
                        $retail_tr  = round($base_tr * 1.20, 2);
                        
                        DB::execute(
                            "INSERT INTO domain_tlds (tld, registrar, base_price_register, base_price_renew, base_price_transfer, markup_type, markup_value, retail_price_register, retail_price_renew, retail_price_transfer, status) VALUES (?, 'resellerclub', ?,?,?, 'percentage', ?,?,?,?, 'active')",
                            'sdddddddd', [$tld, $base_reg, $base_ren, $base_tr, $val, $retail_reg, $retail_ren, $retail_tr]
                        );
                    }
                    $synced++;
                }
                $success = "Successfully synchronized {$synced} domain extension(s) from ResellerClub!";
            }
        }
    }
}

// 3. Manually Create Domain Extension
if(is_post() && post('action') === 'add_tld' && csrf_verify()) {
    $tld = strtolower(trim(post('tld')));
    $tld = ltrim($tld, '.');
    
    if (empty($tld)) {
        $error = "Extension name cannot be empty.";
    } else {
        $existing = DB::row("SELECT id FROM domain_tlds WHERE tld=?", 's', [$tld]);
        if ($existing) {
            $error = "The extension .{$tld} already exists.";
        } else {
            $registrar = trim(post('registrar', 'none'));
            $base_reg = (float)post('base_price_register');
            $base_ren = (float)post('base_price_renew');
            $base_tr  = (float)post('base_price_transfer');
            $markup_type = post('markup_type') === 'fixed' ? 'fixed' : 'percentage';
            $markup_val = (float)post('markup_value');
            
            $retail_reg = (float)post('retail_price_register');
            $retail_ren = (float)post('retail_price_renew');
            $retail_tr  = (float)post('retail_price_transfer');
            
            if ($retail_reg <= 0) {
                $retail_reg = ($markup_type === 'percentage') ? round($base_reg * (1 + $markup_val / 100), 2) : round($base_reg + $markup_val, 2);
            }
            if ($retail_ren <= 0) {
                $retail_ren = ($markup_type === 'percentage') ? round($base_ren * (1 + $markup_val / 100), 2) : round($base_ren + $markup_val, 2);
            }
            if ($retail_tr <= 0) {
                $retail_tr = ($markup_type === 'percentage') ? round($base_tr * (1 + $markup_val / 100), 2) : round($base_tr + $markup_val, 2);
            }
            
            $status = post('status') === 'inactive' ? 'inactive' : 'active';
            
            DB::execute(
                "INSERT INTO domain_tlds (tld, registrar, base_price_register, base_price_renew, base_price_transfer, markup_type, markup_value, retail_price_register, retail_price_renew, retail_price_transfer, status) VALUES (?,?,?,?,?,?, ?,?,?,?, ?)",
                'ssdddsdddds', [$tld, $registrar, $base_reg, $base_ren, $base_tr, $markup_type, $markup_val, $retail_reg, $retail_ren, $retail_tr, $status]
            );
            $success = "Successfully added domain extension .{$tld}!";
        }
    }
}

// 4. Update Single TLD Markup Config / Manually adjust prices
if(is_post() && post('action') === 'update_tld' && csrf_verify()) {
    $tld_id = (int)post('tld_id');
    $registrar = trim(post('registrar', 'none'));
    $base_reg = (float)post('base_price_register');
    $base_ren = (float)post('base_price_renew');
    $base_tr  = (float)post('base_price_transfer');
    $markup_type = post('markup_type') === 'fixed' ? 'fixed' : 'percentage';
    $markup_val = (float)post('markup_value');
    
    $retail_reg = (float)post('retail_price_register');
    $retail_ren = (float)post('retail_price_renew');
    $retail_tr  = (float)post('retail_price_transfer');
    
    if ($retail_reg <= 0) {
        $retail_reg = ($markup_type === 'percentage') ? round($base_reg * (1 + $markup_val / 100), 2) : round($base_reg + $markup_val, 2);
    }
    if ($retail_ren <= 0) {
        $retail_ren = ($markup_type === 'percentage') ? round($base_ren * (1 + $markup_val / 100), 2) : round($base_ren + $markup_val, 2);
    }
    if ($retail_tr <= 0) {
        $retail_tr = ($markup_type === 'percentage') ? round($base_tr * (1 + $markup_val / 100), 2) : round($base_tr + $markup_val, 2);
    }
    
    $status = post('status') === 'inactive' ? 'inactive' : 'active';

    DB::execute(
        "UPDATE domain_tlds SET registrar=?, base_price_register=?, base_price_renew=?, base_price_transfer=?, markup_type=?, markup_value=?, retail_price_register=?, retail_price_renew=?, retail_price_transfer=?, status=? WHERE id=?",
        'sdddsddddsi', [$registrar, $base_reg, $base_ren, $base_tr, $markup_type, $markup_val, $retail_reg, $retail_ren, $retail_tr, $status, $tld_id]
    );
    $success = "Domain extension pricing and API routing updated successfully.";
}

// 5. Bulk Apply Markup Action
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
      <h1 class="bp-page-title" style="margin-bottom:4px">🌐 Domain Pricing Sync & Registrar Routing</h1>
      <p class="bp-page-sub">Manage your TLD extensions, define wholesale cost limits, calculate retail prices, and map them to their corresponding APIs.</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <button class="bp-btn bp-btn-success" onclick="openAddModal()">
        ➕ Add Custom TLD Manually
      </button>
      <form method="POST" style="display:inline-block">
        <?=csrf_input()?>
        <input type="hidden" name="action" value="sync_tlds">
        <button type="submit" class="bp-btn bp-btn-primary">
          🔄 Sync ConnectReseller
        </button>
      </form>
      <form method="POST" style="display:inline-block">
        <?=csrf_input()?>
        <input type="hidden" name="action" value="sync_resellerclub">
        <button type="submit" class="bp-btn bp-btn-accent">
          🔄 Sync ResellerClub
        </button>
      </form>
    </div>
  </div>

  <?php if($error):?><div class="alert-custom alert-danger mb-3"><span>✕</span> <?=h($error)?></div><?php endif?>
  <?php if($success):?><div class="alert-custom alert-success mb-3"><span>✓</span> <?=h($success)?></div><?php endif?>

  <div class="row g-4">
    <!-- Bulk Markup Card -->
    <div class="col-lg-3">
      <div class="bp-card">
        <div class="bp-card-header"><h3 class="bp-card-title">📈 Bulk Profit Markup</h3></div>
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
              <div class="bp-input-hint">This profit rule will bulk-apply over base costs across all extensions.</div>
            </div>
            
            <button type="submit" class="bp-btn bp-btn-accent" style="width:100%;justify-content:center" onclick="return confirm('Bulk apply this markup to ALL extensions? This will recalculate customer retail prices.')">
              Apply Profit Margin
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Active Extensions Table -->
    <div class="col-lg-9">
      <div class="bp-card">
        <div class="bp-card-header">
          <h3 class="bp-card-title">Domain Extensions & Registrar Settings (<?=count($tlds)?>)</h3>
        </div>
        <div style="overflow-x:auto">
          <table class="bp-table">
            <thead>
              <tr>
                <th>TLD</th>
                <th>API Registrar</th>
                <th>Wholesale Base Cost</th>
                <th>Profit Margin</th>
                <th>Retail Customer Price</th>
                <th>Status</th>
                <th style="text-align:right">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if(empty($tlds)):?>
                <tr>
                  <td colspan="7" style="text-align:center;padding:40px;color:#94a3b8">
                    <div style="font-size:32px;margin-bottom:8px">📡</div>
                    <strong>No domain extensions found.</strong><br>Use one of the "Sync" buttons above or click "Add Custom TLD Manually" to create your list.
                  </td>
                </tr>
              <?php else: foreach($tlds as $t):?>
                <tr>
                  <td style="font-weight:700;color:#0f172a;font-family:monospace;font-size:15px">.<?=h($t['tld'])?></td>
                  <td>
                    <span class="bp-badge bp-badge-<?=($t['registrar']??'none')!=='none'?'primary':'secondary'?>" style="font-size:11px">
                      <?=($t['registrar'] === 'connectreseller') ? 'ConnectReseller' : (($t['registrar'] === 'resellerclub') ? 'ResellerClub' : 'Manual')?>
                    </span>
                  </td>
                  <td>
                    <div style="font-size:12px;color:#64748b">Reg: $<?=number_format($t['base_price_register'], 2)?></div>
                    <div style="font-size:12px;color:#64748b">Ren: $<?=number_format($t['base_price_renew'], 2)?></div>
                    <div style="font-size:12px;color:#64748b">Tr: $<?=number_format($t['base_price_transfer'], 2)?></div>
                  </td>
                  <td>
                    <span class="bp-badge bp-badge-info" style="font-size:11px">
                      <?=h($t['markup_value'])?><?=$t['markup_type']==='percentage'?'%':' Flat'?>
                    </span>
                  </td>
                  <td>
                    <div style="font-weight:700;color:#10b981;font-size:13px"><?=format_currency($t['retail_price_register'],$currency)?></div>
                    <div style="font-size:11px;color:#64748b">Renew: <?=format_currency($t['retail_price_renew'],$currency)?></div>
                    <div style="font-size:11px;color:#64748b">Trans: <?=format_currency($t['retail_price_transfer'],$currency)?></div>
                  </td>
                  <td>
                    <span class="bp-badge bp-badge-<?=$t['status']==='active'?'success':'danger'?>">
                      <?=ucfirst($t['status'])?>
                    </span>
                  </td>
                  <td style="text-align:right">
                    <button class="bp-btn bp-btn-outline bp-btn-sm" onclick='editTld(<?=json_encode($t, JSON_HEX_APOS | JSON_HEX_QUOT)?>)'>
                      ⚙ Edit Pricing
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

<!-- Manual Add TLD Modal Dialog -->
<div id="add-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(15,23,42,0.4);backdrop-filter:blur(3px);z-index:999;align-items:center;justify-content:center">
  <div class="bp-card" style="width:520px;max-width:95%">
    <div class="bp-card-header">
      <h3 class="bp-card-title">Add Custom TLD Extension</h3>
      <button class="bp-btn bp-btn-outline bp-btn-sm" onclick="closeAddModal()">✕</button>
    </div>
    <div class="bp-card-body" style="max-height:80vh;overflow-y:auto">
      <form method="POST">
        <?=csrf_input()?>
        <input type="hidden" name="action" value="add_tld">
        
        <div class="row">
          <div class="col-md-6">
            <div class="bp-form-group">
              <label class="bp-label">Domain Extension (TLD)</label>
              <input type="text" name="tld" class="bp-input" placeholder="e.g. com" required>
              <div class="bp-input-hint">Do not include dot (e.g. use "com", "net.ng")</div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="bp-form-group">
              <label class="bp-label">Registrar API Routing</label>
              <select name="registrar" class="bp-select">
                <option value="none">Manual Processing (None)</option>
                <option value="connectreseller">ConnectReseller Module</option>
                <option value="resellerclub">ResellerClub Module</option>
              </select>
            </div>
          </div>
        </div>

        <div style="font-weight:700;font-size:12px;text-transform:uppercase;color:#64748b;margin-bottom:12px;border-bottom:1px solid #e2e8f0;padding-bottom:6px">💰 Wholesale Cost Prices (USD)</div>
        <div class="row">
          <div class="col-md-4">
            <div class="bp-form-group">
              <label class="bp-label">Register Cost ($)</label>
              <input type="number" name="base_price_register" class="bp-input" step="0.01" value="0.00" required>
            </div>
          </div>
          <div class="col-md-4">
            <div class="bp-form-group">
              <label class="bp-label">Renew Cost ($)</label>
              <input type="number" name="base_price_renew" class="bp-input" step="0.01" value="0.00" required>
            </div>
          </div>
          <div class="col-md-4">
            <div class="bp-form-group">
              <label class="bp-label">Transfer Cost ($)</label>
              <input type="number" name="base_price_transfer" class="bp-input" step="0.01" value="0.00" required>
            </div>
          </div>
        </div>

        <div style="font-weight:700;font-size:12px;text-transform:uppercase;color:#64748b;margin-bottom:12px;border-bottom:1px solid #e2e8f0;padding-bottom:6px">📈 Profit Rules & Markups</div>
        <div class="row">
          <div class="col-md-6">
            <div class="bp-form-group">
              <label class="bp-label">Markup Type</label>
              <select name="markup_type" class="bp-select">
                <option value="percentage">Percentage (%)</option>
                <option value="fixed">Fixed flat margin (<?=h($currency)?>)</option>
              </select>
            </div>
          </div>
          <div class="col-md-6">
            <div class="bp-form-group">
              <label class="bp-label">Profit Markup Value</label>
              <input type="number" name="markup_value" class="bp-input" step="0.01" value="20.00" required>
            </div>
          </div>
        </div>

        <div style="font-weight:700;font-size:12px;text-transform:uppercase;color:#64748b;margin-bottom:12px;border-bottom:1px solid #e2e8f0;padding-bottom:6px">🏷 Retail Selling Prices (<?=h($currency)?>)</div>
        <div class="row">
          <div class="col-md-4">
            <div class="bp-form-group">
              <label class="bp-label">Register Price</label>
              <input type="number" name="retail_price_register" class="bp-input" step="0.01" value="0.00" placeholder="Auto-calculated">
              <div class="bp-input-hint">Leave 0 to auto-apply profit rule</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="bp-form-group">
              <label class="bp-label">Renewal Price</label>
              <input type="number" name="retail_price_renew" class="bp-input" step="0.01" value="0.00" placeholder="Auto-calculated">
            </div>
          </div>
          <div class="col-md-4">
            <div class="bp-form-group">
              <label class="bp-label">Transfer Price</label>
              <input type="number" name="retail_price_transfer" class="bp-input" step="0.01" value="0.00" placeholder="Auto-calculated">
            </div>
          </div>
        </div>

        <div class="bp-form-group">
          <label class="bp-label">TLD Availability Status</label>
          <select name="status" class="bp-select">
            <option value="active">Active (available for purchase)</option>
            <option value="inactive">Inactive (hide from customer searches)</option>
          </select>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:24px">
          <button type="button" class="bp-btn bp-btn-outline" onclick="closeAddModal()">Cancel</button>
          <button type="submit" class="bp-btn bp-btn-primary">Add Extension</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit TLD Modal Dialog -->
<div id="edit-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(15,23,42,0.4);backdrop-filter:blur(3px);z-index:999;align-items:center;justify-content:center">
  <div class="bp-card" style="width:520px;max-width:95%">
    <div class="bp-card-header">
      <h3 class="bp-card-title">Modify TLD & Custom Pricing</h3>
      <button class="bp-btn bp-btn-outline bp-btn-sm" onclick="closeModal()">✕</button>
    </div>
    <div class="bp-card-body" style="max-height:80vh;overflow-y:auto">
      <form method="POST">
        <?=csrf_input()?>
        <input type="hidden" name="action" value="update_tld">
        <input type="hidden" name="tld_id" id="m-tld-id">
        
        <div style="margin-bottom:16px;background:#f8fafc;padding:12px;border-radius:8px;display:flex;justify-content:space-between;align-items:center">
          <div>
            <div style="font-size:11px;text-transform:uppercase;color:#64748b;font-weight:700">Selected Extension</div>
            <div id="m-tld-name" style="font-size:22px;font-weight:800;font-family:monospace;color:#0f172a">.com</div>
          </div>
          <div style="text-align:right">
            <label class="bp-label">Registrar API Routing</label>
            <select name="registrar" id="m-registrar" class="bp-select">
              <option value="none">Manual Processing (None)</option>
              <option value="connectreseller">ConnectReseller Module</option>
              <option value="resellerclub">ResellerClub Module</option>
            </select>
          </div>
        </div>

        <div style="font-weight:700;font-size:12px;text-transform:uppercase;color:#64748b;margin-bottom:12px;border-bottom:1px solid #e2e8f0;padding-bottom:6px">💰 Wholesale Cost Prices (USD)</div>
        <div class="row">
          <div class="col-md-4">
            <div class="bp-form-group">
              <label class="bp-label">Register Cost ($)</label>
              <input type="number" name="base_price_register" id="m-base-reg" class="bp-input" step="0.01" required>
            </div>
          </div>
          <div class="col-md-4">
            <div class="bp-form-group">
              <label class="bp-label">Renew Cost ($)</label>
              <input type="number" name="base_price_renew" id="m-base-ren" class="bp-input" step="0.01" required>
            </div>
          </div>
          <div class="col-md-4">
            <div class="bp-form-group">
              <label class="bp-label">Transfer Cost ($)</label>
              <input type="number" name="base_price_transfer" id="m-base-tr" class="bp-input" step="0.01" required>
            </div>
          </div>
        </div>

        <div style="font-weight:700;font-size:12px;text-transform:uppercase;color:#64748b;margin-bottom:12px;border-bottom:1px solid #e2e8f0;padding-bottom:6px">📈 Profit Rules & Markups</div>
        <div class="row">
          <div class="col-md-6">
            <div class="bp-form-group">
              <label class="bp-label">Markup Type</label>
              <select name="markup_type" id="m-markup-type" class="bp-select">
                <option value="percentage">Percentage (%)</option>
                <option value="fixed">Fixed flat margin (<?=h($currency)?>)</option>
              </select>
            </div>
          </div>
          <div class="col-md-6">
            <div class="bp-form-group">
              <label class="bp-label">Profit Markup Value</label>
              <input type="number" name="markup_value" id="m-markup-value" class="bp-input" step="0.01" required>
            </div>
          </div>
        </div>

        <div style="font-weight:700;font-size:12px;text-transform:uppercase;color:#64748b;margin-bottom:12px;border-bottom:1px solid #e2e8f0;padding-bottom:6px">🏷 Retail Selling Prices (<?=h($currency)?>)</div>
        <div class="row">
          <div class="col-md-4">
            <div class="bp-form-group">
              <label class="bp-label">Register Price</label>
              <input type="number" name="retail_price_register" id="m-retail-reg" class="bp-input" step="0.01" required>
              <div class="bp-input-hint">Enter custom price or set 0 to recalculate</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="bp-form-group">
              <label class="bp-label">Renewal Price</label>
              <input type="number" name="retail_price_renew" id="m-retail-ren" class="bp-input" step="0.01" required>
            </div>
          </div>
          <div class="col-md-4">
            <div class="bp-form-group">
              <label class="bp-label">Transfer Price</label>
              <input type="number" name="retail_price_transfer" id="m-retail-tr" class="bp-input" step="0.01" required>
            </div>
          </div>
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
          <button type="submit" class="bp-btn bp-btn-primary">Save Pricing Settings</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openAddModal() {
    document.getElementById('add-modal').style.display = 'flex';
}
function closeAddModal() {
    document.getElementById('add-modal').style.display = 'none';
}

function editTld(tld) {
    document.getElementById('m-tld-id').value = tld.id;
    document.getElementById('m-tld-name').textContent = '.' + tld.tld;
    document.getElementById('m-registrar').value = tld.registrar || 'none';
    
    document.getElementById('m-base-reg').value = tld.base_price_register;
    document.getElementById('m-base-ren').value = tld.base_price_renew;
    document.getElementById('m-base-tr').value = tld.base_price_transfer;
    
    document.getElementById('m-markup-type').value = tld.markup_type;
    document.getElementById('m-markup-value').value = tld.markup_value;
    
    document.getElementById('m-retail-reg').value = tld.retail_price_register;
    document.getElementById('m-retail-ren').value = tld.retail_price_renew;
    document.getElementById('m-retail-tr').value = tld.retail_price_transfer;
    
    document.getElementById('m-status').value = tld.status;
    
    document.getElementById('edit-modal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('edit-modal').style.display = 'none';
}
</script>
<?php include 'partials/footer.php'; ?>
