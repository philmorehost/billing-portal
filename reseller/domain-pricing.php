<?php
require_once '../includes/config.php';
$client=Auth::requireClient();
$reseller=DB::row("SELECT * FROM resellers WHERE client_id=?", 'i', [$client['id']]);
if (!$reseller || $reseller['status'] !== 'active') {
    redirect(BASE_URL . '/client/reseller-apply.php');
}

$company = $reseller['branding_name'] ?: DB::setting('company_name', 'Billing Portal');
$page_title = 'Domain Markup Settings';
$currency = DB::setting('base_currency', 'NGN');
$success = ''; $error = '';

$default_discount = (float)DB::setting('reseller_default_discount', 20);

// Single TLD Update
if (is_post() && post('action') === 'update_reseller_markup' && csrf_verify()) {
    $tld_id = (int)post('tld_id');
    $markup_type = post('markup_type') === 'fixed' ? 'fixed' : 'percentage';
    $markup_val = (float)post('markup_value');

    $existing = DB::row("SELECT * FROM reseller_domain_prices WHERE reseller_id=? AND tld_id=?", 'ii', [$reseller['id'], $tld_id]);
    if ($existing) {
        DB::execute(
            "UPDATE reseller_domain_prices SET markup_type=?, markup_value=? WHERE reseller_id=? AND tld_id=?",
            'sdii', [$markup_type, $markup_val, $reseller['id'], $tld_id]
        );
    } else {
        DB::execute(
            "INSERT INTO reseller_domain_prices (reseller_id, tld_id, markup_type, markup_value) VALUES (?,?,?,?)",
            'iisd', [$reseller['id'], $tld_id, $markup_type, $markup_val]
        );
    }
    $success = "Your custom markup rules have been saved successfully.";
}

// Bulk Reseller Markup
if (is_post() && post('action') === 'bulk_reseller_markup' && csrf_verify()) {
    $markup_val = (float)post('bulk_markup_value');
    $markup_type = post('bulk_markup_type') === 'fixed' ? 'fixed' : 'percentage';

    $active_tlds = DB::rows("SELECT * FROM domain_tlds WHERE status='active'");
    foreach ($active_tlds as $t) {
        $existing = DB::row("SELECT * FROM reseller_domain_prices WHERE reseller_id=? AND tld_id=?", 'ii', [$reseller['id'], $t['id']]);
        if ($existing) {
            DB::execute(
                "UPDATE reseller_domain_prices SET markup_type=?, markup_value=? WHERE reseller_id=? AND tld_id=?",
                'sdii', [$markup_type, $markup_val, $reseller['id'], $t['id']]
            );
        } else {
            DB::execute(
                "INSERT INTO reseller_domain_prices (reseller_id, tld_id, markup_type, markup_value) VALUES (?,?,?,?)",
                'iisd', [$reseller['id'], $t['id'], $markup_type, $markup_val]
            );
        }
    }
    $success = "Successfully bulk-applied {$markup_val}" . ($markup_type==='percentage'?'%':'') . " markup to all domain extensions.";
}

// Fetch active TLDs and the reseller's custom overrides
$tlds = DB::rows("
    SELECT t.*, rdp.markup_type AS res_markup_type, rdp.markup_value AS res_markup_value 
    FROM domain_tlds t 
    LEFT JOIN reseller_domain_prices rdp ON rdp.tld_id = t.id AND rdp.reseller_id = ?
    WHERE t.status = 'active' 
    ORDER BY t.tld ASC
", 'i', [$reseller['id']]);

include 'partials/header.php';
?>
<div class="bp-content">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px">
    <div>
      <h1 class="bp-page-title" style="margin-bottom:4px">🌐 Reseller Domain Profit Manager</h1>
      <p class="bp-page-sub">Configure customized pricing markup margins for your domain extensions store.</p>
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
            <input type="hidden" name="action" value="bulk_reseller_markup">
            
            <div class="bp-form-group">
              <label class="bp-label">Markup Type</label>
              <select name="bulk_markup_type" class="bp-select">
                <option value="percentage">Percentage (%)</option>
                <option value="fixed">Fixed Flat Margin (<?=h($currency)?>)</option>
              </select>
            </div>
            
            <div class="bp-form-group">
              <label class="bp-label">Markup Profit Amount</label>
              <input type="number" name="bulk_markup_value" class="bp-input" step="0.01" min="0" placeholder="e.g. 15" required>
              <div class="bp-input-hint">Your profit margin will be added directly on top of your wholesale cost price.</div>
            </div>
            
            <button type="submit" class="bp-btn bp-btn-primary" style="width:100%;justify-content:center" onclick="return confirm('Bulk apply this markup to ALL extensions? Your existing custom margins will be overwritten.')">
              Apply Profit Margin
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Pricing Grid -->
    <div class="col-lg-8">
      <div class="bp-card">
        <div class="bp-card-header">
          <h3 class="bp-card-title">Domain Extensions & Wholesale Pricing</h3>
        </div>
        <div style="overflow-x:auto">
          <table class="bp-table">
            <thead>
              <tr>
                <th>TLD</th>
                <th>Your Cost Price</th>
                <th>Your Profit Markup</th>
                <th>Calculated Customer Price</th>
                <th style="text-align:right">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if(empty($tlds)):?>
                <tr>
                  <td colspan="5" style="text-align:center;padding:40px;color:#94a3b8">
                    <div style="font-size:32px;margin-bottom:8px">📡</div>
                    <strong>No active domain extensions.</strong><br>The main administrator has not synchronized any TLDs yet.
                  </td>
                </tr>
              <?php else: foreach($tlds as $t):
                // Reseller wholesale price = Admin retail price * (1 - default reseller discount)
                $wholes_reg = round((float)$t['retail_price_register'] * (1 - $default_discount / 100), 2);
                $wholes_ren = round((float)$t['retail_price_renew'] * (1 - $default_discount / 100), 2);

                // Use reseller custom override markup if set, otherwise fallback to reseller's global markup default (or 20%)
                $m_type = $t['res_markup_type'] ?? 'percentage';
                $m_val = $t['res_markup_value'] !== null ? (float)$t['res_markup_value'] : (float)$reseller['markup_percentage'];

                if ($m_type === 'percentage') {
                    $cust_reg = round($wholes_reg * (1 + $m_val / 100), 2);
                    $cust_ren = round($wholes_ren * (1 + $m_val / 100), 2);
                } else {
                    $cust_reg = round($wholes_reg + $m_val, 2);
                    $cust_ren = round($wholes_ren + $m_val, 2);
                }
              ?>
                <tr>
                  <td style="font-weight:700;color:#0f172a;font-family:monospace;font-size:15px">.<?=h($t['tld'])?></td>
                  <td>
                    <div style="font-weight:600;color:#64748b"><?=format_currency($wholes_reg, $currency)?></div>
                    <div style="font-size:11px;color:#94a3b8">Renew: <?=format_currency($wholes_ren, $currency)?></div>
                  </td>
                  <td>
                    <span class="bp-badge bp-badge-info" style="font-size:11px">
                      <?=h($m_val)?><?=$m_type==='percentage'?'%':' Flat'?>
                    </span>
                  </td>
                  <td>
                    <div style="font-weight:700;color:#10b981;font-size:13px"><?=format_currency($cust_reg, $currency)?></div>
                    <div style="font-size:11px;color:#64748b">Renew: <?=format_currency($cust_ren, $currency)?></div>
                  </td>
                  <td style="text-align:right">
                    <button class="bp-btn bp-btn-outline bp-btn-sm" onclick="editResellerTld(<?=h(json_encode($t))?>, <?=h(json_encode($reseller))?>)">
                      ⚙ Set Markup
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

<!-- Modal Dialog -->
<div id="reseller-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(15,23,42,0.4);backdrop-filter:blur(3px);z-index:999;align-items:center;justify-content:center">
  <div class="bp-card" style="width:420px;max-width:90%">
    <div class="bp-card-header">
      <h3 class="bp-card-title">Modify Custom Margin</h3>
      <button class="bp-btn bp-btn-outline bp-btn-sm" onclick="closeResellerModal()">✕</button>
    </div>
    <div class="bp-card-body">
      <form method="POST">
        <?=csrf_input()?>
        <input type="hidden" name="action" value="update_reseller_markup">
        <input type="hidden" name="tld_id" id="mr-tld-id">
        
        <div style="margin-bottom:16px;background:#f8fafc;padding:12px;border-radius:8px">
          <div style="font-size:11px;text-transform:uppercase;color:#64748b;font-weight:700">Domain Extension</div>
          <div id="mr-tld-name" style="font-size:20px;font-weight:800;font-family:monospace;color:#0f172a">.com</div>
        </div>

        <div class="bp-form-group">
          <label class="bp-label">Profit Markup Type</label>
          <select name="markup_type" id="mr-markup-type" class="bp-select">
            <option value="percentage">Percentage (%)</option>
            <option value="fixed">Fixed Flat Margin (<?=h($currency)?>)</option>
          </select>
        </div>

        <div class="bp-form-group">
          <label class="bp-label">Markup Profit Value</label>
          <input type="number" name="markup_value" id="mr-markup-value" class="bp-input" step="0.01" min="0" required>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:24px">
          <button type="button" class="bp-btn bp-btn-outline" onclick="closeResellerModal()">Cancel</button>
          <button type="submit" class="bp-btn bp-btn-primary">Save profit markup</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function editResellerTld(tld, reseller) {
    document.getElementById('mr-tld-id').value = tld.id;
    document.getElementById('mr-tld-name').textContent = '.' + tld.tld;
    
    // Default to existing reseller markup override if set, else reseller's global default
    const m_type = tld.res_markup_type || 'percentage';
    const m_val = tld.res_markup_value !== null ? tld.res_markup_value : reseller.markup_percentage;
    
    document.getElementById('mr-markup-type').value = m_type;
    document.getElementById('mr-markup-value').value = m_val;
    
    document.getElementById('reseller-modal').style.display = 'flex';
}

function closeResellerModal() {
    document.getElementById('reseller-modal').style.display = 'none';
}
</script>
<?php include 'partials/footer.php'; ?>
