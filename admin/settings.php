<?php
require_once '../includes/config.php';
$admin      = Auth::requireAdmin();
$company    = DB::setting('company_name', 'Billing Portal');
$page_title = 'Settings';

if (is_post() && csrf_verify()) {
    $group = post('group');

    if (post('action') === 'refresh_rates') {
        require_once INC_PATH . '/modules/billing.php';
        if (Billing::refreshLiveRates()) {
            redirect_with_flash('settings.php?tab=billing', 'success', 'Exchange rates refreshed successfully.');
        } else {
            redirect_with_flash('settings.php?tab=billing', 'danger', 'Failed to refresh exchange rates. Please check your internet connection or API availability.');
        }
    }

    $keys  = array_keys($_POST);
    $saved = 0;

    foreach ($keys as $key) {
        if (in_array($key, ['action','group','csrf_token'])) continue;
        $val = is_array($_POST[$key]) ? implode(',', $_POST[$key]) : trim($_POST[$key]);
        
        // Skip empty sensitive settings to respect "Leave blank to keep existing"
        if ($val === '' && (strpos($key, 'password') !== false || strpos($key, 'api_key') !== false || strpos($key, 'secret') !== false || strpos($key, 'hash') !== false || strpos($key, 'key') !== false)) {
            continue;
        }
        
        DB::setSetting($key, $val);
        $saved++;
    }

    log_activity('settings_updated', "Settings group '{$group}' updated", 'admin', $admin['id']);
    redirect_with_flash('settings.php?tab=' . urlencode($group), 'success', 'Settings saved successfully.');
}

$tab = get_param('tab', 'general');

// Load all settings into a flat array for easy access in the form
$all_settings = [];
$rows = DB::rows("SELECT setting_key, setting_value FROM settings");
foreach ($rows as $r) $all_settings[$r['setting_key']] = $r['setting_value'];
$s = fn($k, $d='') => $all_settings[$k] ?? $d;

include 'partials/header.php';
?>
<div class="bp-content">
  <h1 class="bp-page-title">Settings</h1>
  <?= flash_html() ?>

  <!-- Tab nav -->
  <div class="bp-card mb-4">
    <div style="display:flex;flex-wrap:wrap;gap:4px;padding:12px 16px;border-bottom:1px solid #e2e8f0">
      <?php
      $tabs = [
        'general'  => '🏢 General',
        'billing'  => '💳 Billing',
        'email'    => '📧 Email / SMTP',
        'gateways' => '💰 Gateways',
        'security' => '🔐 Security',
        'legal'    => '📄 Legal',
        'reseller' => '🏪 Reseller',
        'modules'  => '🔌 API Modules',
        'landing'  => '🌐 Landing CMS',
      ];
      foreach ($tabs as $k => $label):
      ?>
      <a href="?tab=<?= $k ?>" class="bp-btn bp-btn-<?= $tab===$k?'primary':'outline' ?> bp-btn-sm"><?= $label ?></a>
      <?php endforeach ?>
    </div>

    <div class="bp-card-body">
      <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="group" value="<?= h($tab) ?>">

        <?php if ($tab === 'general'): ?>
        <div class="bp-form-row bp-form-row-2">
          <div class="bp-form-group"><label class="bp-label">Company Name</label><input type="text" name="company_name" class="bp-input" value="<?= h($s('company_name')) ?>" required></div>
          <div class="bp-form-group"><label class="bp-label">Company Email</label><input type="email" name="company_email" class="bp-input" value="<?= h($s('company_email')) ?>"></div>
          <div class="bp-form-group"><label class="bp-label">Phone Number</label><input type="text" name="company_phone" class="bp-input" value="<?= h($s('company_phone')) ?>"></div>
          <div class="bp-form-group"><label class="bp-label">Country Code</label><input type="text" name="company_country" class="bp-input" value="<?= h($s('company_country','NG')) ?>" maxlength="2"></div>
        </div>
        <div class="bp-form-group"><label class="bp-label">Company Address</label><textarea name="company_address" class="bp-textarea" rows="3"><?= h($s('company_address')) ?></textarea></div>
        <div class="bp-form-group">
          <label class="bp-label">Default Order Category</label>
          <select name="default_product_group" class="bp-select">
            <option value="" <?= $s('default_product_group')===''?'selected':'' ?>>Default (First Category with visible products)</option>
            <?php foreach (DB::rows("SELECT * FROM product_groups WHERE visible=1 ORDER BY sort_order, name") as $pg_opt): ?>
              <option value="<?= $pg_opt['id'] ?>" <?= $s('default_product_group')==$pg_opt['id']?'selected':'' ?>><?= h($pg_opt['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="bp-input-hint">Select the category that will show its products by default when a client opens the order page.</div>
        </div>
        <div class="bp-form-group">
          <label class="bp-label">Maintenance Mode</label>
          <select name="maintenance_mode" class="bp-select">
            <option value="0" <?= $s('maintenance_mode')==='0'?'selected':'' ?>>Disabled</option>
            <option value="1" <?= $s('maintenance_mode')==='1'?'selected':'' ?>>Enabled</option>
          </select>
          <div class="bp-input-hint">When enabled, the client area will show a maintenance page.</div>
        </div>

        <?php elseif ($tab === 'billing'): ?>
        <div class="bp-form-row bp-form-row-2">
          <div class="bp-form-group">
            <label class="bp-label">Base Currency</label>
            <select name="base_currency" class="bp-select">
              <?php foreach (['NGN','USD','GBP','EUR','GHS','KES','ZAR'] as $cur): ?>
              <option value="<?= $cur ?>" <?= $s('base_currency')===$cur?'selected':'' ?>><?= $cur ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="bp-form-group">
            <label class="bp-label">USD to NGN Markup Charge (%)</label>
            <input type="number" step="0.01" name="usd_to_ngn_markup_percent" class="bp-input" value="<?= h($s('usd_to_ngn_markup_percent','0')) ?>">
            <div class="bp-input-hint">Additional markup charge over live rate for NGN conversion only.</div>
          </div>
          <div class="bp-form-group" style="background:#f8fafc; padding:16px; border-radius:12px; border:1px solid #e2e8f0; grid-column: span 2">
            <label class="bp-label" style="font-size:12px; text-transform:uppercase; color:#64748b; margin-bottom:8px">📊 Live Exchange Rate Info</label>
            <div style="display:flex; justify-content:space-between; align-items:center">
              <div>
                <?php
                require_once INC_PATH . '/modules/billing.php';
                $rates = Billing::getLiveRates();
                $ngn_rate = $rates['NGN'] ?? 1600;
                $markup = (float)$s('usd_to_ngn_markup_percent','0');
                $effective_rate = round($ngn_rate * (1 + $markup/100), 2);
                $last_upd = (int)$s('live_rates_last_updated', 0);
                ?>
                <div style="font-size:18px; font-weight:800; color:#0f172a">1 USD = <?=number_format($effective_rate, 2)?> NGN</div>
                <div style="font-size:12px; color:#64748b; margin-top:4px">Base Rate: <?=number_format($ngn_rate, 2)?> | Last Sync: <?=$last_upd ? date('Y-m-d H:i:s', $last_upd) : 'Never'?></div>
                <details style="margin-top:8px; font-size:11px; color:#64748b">
                  <summary style="cursor:pointer">View Other Rates</summary>
                  <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:4px; margin-top:4px">
                    <?php foreach ($rates as $cur_code => $rate_val): if($cur_code === 'NGN') continue; ?>
                      <div><?=$cur_code?>: <?=number_format($rate_val, 2)?></div>
                    <?php endforeach; ?>
                  </div>
                </details>
              </div>
              <button type="submit" name="action" value="refresh_rates" class="bp-btn bp-btn-outline bp-btn-sm" style="border-radius:30px; padding:6px 16px">🔄 Force Refresh Rates</button>
            </div>
          </div>
          <div class="bp-form-group"><label class="bp-label">Invoice Prefix</label><input type="text" name="invoice_prefix" class="bp-input" value="<?= h($s('invoice_prefix','INV')) ?>"></div>
          <div class="bp-form-group"><label class="bp-label">Invoice Due Days</label><input type="number" name="invoice_due_days" class="bp-input" value="<?= h($s('invoice_due_days','7')) ?>"></div>
          <div class="bp-form-group"><label class="bp-label">Tax Name</label><input type="text" name="tax_name" class="bp-input" value="<?= h($s('tax_name','VAT')) ?>"></div>
          <div class="bp-form-group"><label class="bp-label">Tax Rate (%)</label><input type="number" step="0.01" name="tax_rate" class="bp-input" value="<?= h($s('tax_rate','7.5')) ?>"></div>
        </div>
        <div class="bp-form-group">
          <label class="bp-label">Tax Enabled</label>
          <select name="tax_enabled" class="bp-select">
            <option value="1" <?= $s('tax_enabled')==='1'?'selected':'' ?>>Yes</option>
            <option value="0" <?= $s('tax_enabled')==='0'?'selected':'' ?>>No</option>
          </select>
        </div>

        <?php elseif ($tab === 'email'): ?>
        <div class="bp-form-row bp-form-row-2">
          <div class="bp-form-group"><label class="bp-label">SMTP Host</label><input type="text" name="smtp_host" class="bp-input" value="<?= h($s('smtp_host')) ?>" placeholder="smtp.gmail.com"></div>
          <div class="bp-form-group"><label class="bp-label">SMTP Port</label><input type="number" name="smtp_port" class="bp-input" value="<?= h($s('smtp_port','587')) ?>"></div>
          <div class="bp-form-group"><label class="bp-label">SMTP Username</label><input type="text" name="smtp_user" class="bp-input" value="<?= h($s('smtp_user')) ?>" placeholder="you@gmail.com"></div>
          <div class="bp-form-group"><label class="bp-label">SMTP Password</label><input type="password" name="smtp_pass" class="bp-input" placeholder="Leave blank to keep existing"></div>
          <div class="bp-form-group"><label class="bp-label">From Name</label><input type="text" name="smtp_from_name" class="bp-input" value="<?= h($s('smtp_from_name')) ?>"></div>
          <div class="bp-form-group"><label class="bp-label">From Email</label><input type="email" name="smtp_from_email" class="bp-input" value="<?= h($s('smtp_from_email')) ?>"></div>
        </div>
        <div class="bp-form-group">
          <label class="bp-label">Encryption</label>
          <select name="smtp_encryption" class="bp-select">
            <option value="tls"  <?= $s('smtp_encryption')==='tls'?'selected':'' ?>>TLS (recommended, port 587)</option>
            <option value="ssl"  <?= $s('smtp_encryption')==='ssl'?'selected':'' ?>>SSL (port 465)</option>
            <option value="none" <?= $s('smtp_encryption')==='none'?'selected':'' ?>>None (port 25)</option>
          </select>
        </div>

        <?php elseif ($tab === 'gateways'): ?>
        <h5 style="font-weight:700;margin-bottom:16px">Paystack</h5>
        <div class="bp-form-row bp-form-row-2">
          <div class="bp-form-group"><label class="bp-label">Public Key</label><input type="text" name="paystack_public_key" class="bp-input" value="<?= h($s('paystack_public_key')) ?>" placeholder="pk_live_..."></div>
          <div class="bp-form-group"><label class="bp-label">Secret Key</label><input type="password" name="paystack_secret_key" class="bp-input" placeholder="Leave blank to keep existing"></div>
        </div>
        <div class="bp-form-group">
          <label class="bp-label">Paystack Enabled</label>
          <select name="paystack_enabled" class="bp-select">
            <option value="1" <?= $s('paystack_enabled')==='1'?'selected':'' ?>>Enabled</option>
            <option value="0" <?= $s('paystack_enabled')==='0'?'selected':'' ?>>Disabled</option>
          </select>
        </div>
        <hr style="margin:24px 0;border-color:#f1f5f9">
        <h5 style="font-weight:700;margin-bottom:16px">Bank Transfer</h5>
        <div class="bp-form-group">
          <label class="bp-label">Bank Transfer Enabled</label>
          <select name="bank_transfer_enabled" class="bp-select">
            <option value="1" <?= $s('bank_transfer_enabled')==='1'?'selected':'' ?>>Enabled</option>
            <option value="0" <?= $s('bank_transfer_enabled')==='0'?'selected':'' ?>>Disabled</option>
          </select>
        </div>
        <div class="bp-form-group"><label class="bp-label">Bank Details (shown to clients)</label><textarea name="bank_transfer_details" class="bp-textarea" rows="5"><?= h($s('bank_transfer_details')) ?></textarea></div>
        <hr style="margin:24px 0;border-color:#f1f5f9">
        <h5 style="font-weight:700;margin-bottom:16px">Cryptocurrency (Plisio Integration)</h5>
        <div class="bp-form-group">
          <label class="bp-label">Crypto Enabled</label>
          <select name="crypto_enabled" class="bp-select">
            <option value="1" <?= $s('crypto_enabled')==='1'?'selected':'' ?>>Enabled</option>
            <option value="0" <?= $s('crypto_enabled')==='0'?'selected':'' ?>>Disabled</option>
          </select>
        </div>
        <div class="bp-form-group">
          <label class="bp-label">Plisio Secret / API Key</label>
          <input type="password" name="crypto_plisio_api_key" class="bp-input" placeholder="Leave blank to keep existing">
        </div>
        <div class="bp-form-group">
          <label class="bp-label">Allowed Cryptocurrencies</label>
          <input type="text" name="crypto_plisio_allowed_coins" class="bp-input" value="<?= h($s('crypto_plisio_allowed_coins','BTC,LTC,USDT,ETH')) ?>" placeholder="BTC,LTC,USDT,ETH">
          <div class="bp-input-hint">Comma-separated list of coin codes that clients can pay with (e.g., BTC,LTC,USDT,ETH).</div>
        </div>

        <?php elseif ($tab === 'security'): ?>
        <div class="bp-form-row bp-form-row-2">
          <div class="bp-form-group"><label class="bp-label">Max Login Attempts</label><input type="number" name="login_max_attempts" class="bp-input" value="<?= h($s('login_max_attempts','5')) ?>"><div class="bp-input-hint">Attempts before account lock.</div></div>
          <div class="bp-form-group"><label class="bp-label">Lockout Duration (minutes)</label><input type="number" name="login_lockout_minutes" class="bp-input" value="<?= h($s('login_lockout_minutes','30')) ?>"></div>
          <div class="bp-form-group"><label class="bp-label">Session Lifetime (hours)</label><input type="number" name="session_lifetime_hours" class="bp-input" value="<?= h($s('session_lifetime_hours','24')) ?>"></div>
        </div>
        <div class="bp-form-group">
          <label class="bp-label">Require 2FA for All Admins</label>
          <select name="two_factor_required" class="bp-select">
            <option value="0" <?= $s('two_factor_required')==='0'?'selected':'' ?>>Optional</option>
            <option value="1" <?= $s('two_factor_required')==='1'?'selected':'' ?>>Required</option>
          </select>
        </div>
        <hr style="margin:24px 0;border-color:#f1f5f9">
        <h5 style="font-weight:700;margin-bottom:16px">Google OAuth Login</h5>
        <div class="bp-form-group">
          <label class="bp-label">Enable Google Login</label>
          <select name="google_auth_enabled" class="bp-select">
            <option value="1" <?= $s('google_auth_enabled')==='1'?'selected':'' ?>>Enabled</option>
            <option value="0" <?= $s('google_auth_enabled')==='0'?'selected':'' ?>>Disabled</option>
          </select>
        </div>
        <div class="bp-form-row bp-form-row-2">
          <div class="bp-form-group"><label class="bp-label">Client ID</label><input type="text" name="google_auth_client_id" class="bp-input" value="<?= h($s('google_auth_client_id')) ?>" placeholder="xxxxxxxx.apps.googleusercontent.com"></div>
          <div class="bp-form-group"><label class="bp-label">Client Secret</label><input type="password" name="google_auth_client_secret" class="bp-input" placeholder="Leave blank to keep existing"></div>
        </div>
        <div class="bp-input-hint">Redirect URI: <code><?= BASE_URL ?>/client/login-google.php</code></div>

        <?php elseif ($tab === 'legal'): ?>
        <div class="bp-form-group">
          <label class="bp-label">Terms of Service (HTML allowed)</label>
          <textarea name="tos_content" class="bp-textarea" rows="15"><?= h($s('tos_content')) ?></textarea>
        </div>
        <div class="bp-form-group">
          <label class="bp-label">Privacy Policy (HTML allowed)</label>
          <textarea name="privacy_content" class="bp-textarea" rows="15"><?= h($s('privacy_content')) ?></textarea>
        </div>

        <?php elseif ($tab === 'reseller'): ?>
        <div class="bp-form-group">
          <label class="bp-label">Reseller System Enabled</label>
          <select name="reseller_enabled" class="bp-select">
            <option value="1" <?= $s('reseller_enabled')==='1'?'selected':'' ?>>Enabled</option>
            <option value="0" <?= $s('reseller_enabled')==='0'?'selected':'' ?>>Disabled</option>
          </select>
        </div>
        <div class="bp-form-group">
          <label class="bp-label">Default Wholesale Discount (%)</label>
          <input type="number" step="0.1" name="reseller_default_discount" class="bp-input" value="<?= h($s('reseller_default_discount','20')) ?>">
          <div class="bp-input-hint">Percentage discount from retail price given to all resellers by default.</div>
        </div>

        <?php elseif ($tab === 'modules'): ?>
        <!-- ResellerClub -->
        <h5 style="font-weight:700;margin-bottom:16px;color:#0f172a">🔌 ResellerClub</h5>
        <div class="bp-form-row bp-form-row-2">
          <div class="bp-form-group"><label class="bp-label">Reseller ID</label><input type="text" name="module_resellerclub_reseller_id" class="bp-input" value="<?=h($s('module_resellerclub_reseller_id'))?>"></div>
          <div class="bp-form-group"><label class="bp-label">API Key</label><input type="password" name="module_resellerclub_api_key" class="bp-input" placeholder="Leave blank to keep existing"></div>
        </div>
        <div class="bp-form-group"><label class="bp-label">Test Mode</label>
          <select name="module_resellerclub_test_mode" class="bp-select"><option value="0" <?=$s('module_resellerclub_test_mode')==='0'?'selected':''?>>Live</option><option value="1" <?=$s('module_resellerclub_test_mode')==='1'?'selected':''?>>Test/Sandbox</option></select>
        </div>
        <hr style="margin:24px 0;border-color:#f1f5f9">

        <!-- Namecheap -->
        <h5 style="font-weight:700;margin-bottom:16px;color:#0f172a">🔌 Namecheap</h5>
        <div class="bp-form-row bp-form-row-2">
          <div class="bp-form-group"><label class="bp-label">API Username</label><input type="text" name="module_namecheap_api_user" class="bp-input" value="<?=h($s('module_namecheap_api_user'))?>"></div>
          <div class="bp-form-group"><label class="bp-label">API Key</label><input type="password" name="module_namecheap_api_key" class="bp-input" placeholder="Leave blank to keep existing"></div>
        </div>
        <div class="bp-form-group"><label class="bp-label">Sandbox Mode</label>
          <select name="module_namecheap_sandbox" class="bp-select"><option value="0" <?=$s('module_namecheap_sandbox')==='0'?'selected':''?>>Live</option><option value="1" <?=$s('module_namecheap_sandbox')==='1'?'selected':''?>>Sandbox</option></select>
        </div>
        <hr style="margin:24px 0;border-color:#f1f5f9">

        <!-- ConnectReseller -->
        <h5 style="font-weight:700;margin-bottom:16px;color:#0f172a">🔌 ConnectReseller</h5>
        <div class="bp-form-row bp-form-row-2">
          <div class="bp-form-group"><label class="bp-label">Brand ID</label><input type="text" name="module_connectreseller_brand_id" class="bp-input" value="<?=h($s('module_connectreseller_brand_id'))?>"></div>
          <div class="bp-form-group"><label class="bp-label">API Key</label><input type="password" name="module_connectreseller_api_key" class="bp-input" placeholder="Leave blank to keep existing"></div>
        </div>
        <hr style="margin:24px 0;border-color:#f1f5f9">

        <!-- Upperlink .NG -->
        <h5 style="font-weight:700;margin-bottom:16px;color:#0f172a">🔌 Upperlink (.NG Domains)</h5>
        <div class="bp-form-row bp-form-row-2">
          <div class="bp-form-group"><label class="bp-label">Reseller Email (Username)</label><input type="text" name="module_upperlink_username" class="bp-input" value="<?=h($s('module_upperlink_username'))?>" placeholder="e.g. email@example.com"></div>
          <div class="bp-form-group"><label class="bp-label">API Key</label><input type="password" name="module_upperlink_api_key" class="bp-input" placeholder="Leave blank to keep existing"></div>
        </div>
        <hr style="margin:24px 0;border-color:#f1f5f9">

        <!-- NOCIX -->
        <h5 style="font-weight:700;margin-bottom:16px;color:#0f172a">🔌 NOCIX Dedicated Servers</h5>
        <div class="bp-form-row bp-form-row-2">
          <div class="bp-form-group"><label class="bp-label">User ID (Email)</label><input type="text" name="module_nocix_username" class="bp-input" value="<?=h($s('module_nocix_username'))?>" placeholder="you@example.com"></div>
          <div class="bp-form-group"><label class="bp-label">API Key</label><input type="password" name="module_nocix_api_key" class="bp-input" placeholder="Leave blank to keep existing"></div>
        </div>
        <div class="bp-form-row bp-form-row-2">
          <div class="bp-form-group"><label class="bp-label">Default Location</label><input type="text" name="module_nocix_default_location" class="bp-input" value="<?=h($s('module_nocix_default_location','dallas'))?>" placeholder="dallas"></div>
          <div class="bp-form-group">
            <label class="bp-label">Auto-Sync Stock Status</label>
            <select name="module_nocix_sync_status" class="bp-select">
                <option value="1" <?= $s('module_nocix_sync_status')==='1'?'selected':'' ?>>Enabled (Hide out of stock)</option>
                <option value="0" <?= $s('module_nocix_sync_status')==='0'?'selected':'' ?>>Disabled</option>
            </select>
          </div>
        </div>
        <hr style="margin:24px 0;border-color:#f1f5f9">

        <!-- Time4VPS -->
        <h5 style="font-weight:700;margin-bottom:16px;color:#0f172a">🔌 Time4VPS</h5>
        <div class="bp-form-row bp-form-row-2">
          <div class="bp-form-group"><label class="bp-label">API Username (email)</label><input type="text" name="module_time4vps_username" class="bp-input" value="<?=h($s('module_time4vps_username'))?>"></div>
          <div class="bp-form-group"><label class="bp-label">API Password</label><input type="password" name="module_time4vps_password" class="bp-input" placeholder="Leave blank to keep existing"></div>
        </div>
        <div class="bp-form-group"><label class="bp-label">Default VPS Product ID</label><input type="text" name="module_time4vps_default_product_id" class="bp-input" value="<?=h($s('module_time4vps_default_product_id'))?>" placeholder="Product ID from Time4VPS panel"></div>
        <hr style="margin:24px 0;border-color:#f1f5f9">

        <!-- Interserver -->
        <h5 style="font-weight:700;margin-bottom:16px;color:#0f172a">🔌 Interserver</h5>
        <div class="bp-form-group">
          <label class="bp-label">API Key</label>
          <input type="password" name="module_interserver_api_key" class="bp-input" placeholder="Leave blank to keep existing">
        </div>
        <hr style="margin:24px 0;border-color:#f1f5f9">

        <!-- The SSL Store -->
        <h5 style="font-weight:700;margin-bottom:16px;color:#0f172a">🔌 The SSL Store</h5>
        <div class="bp-form-row bp-form-row-2">
          <div class="bp-form-group"><label class="bp-label">Partner Code</label><input type="text" name="module_thesslstore_partner_code" class="bp-input" value="<?=h($s('module_thesslstore_partner_code'))?>"></div>
          <div class="bp-form-group"><label class="bp-label">Auth Token</label><input type="password" name="module_thesslstore_auth_token" class="bp-input" placeholder="Leave blank to keep existing"></div>
        </div>
        <div class="bp-form-group">
          <label class="bp-label">Test Mode</label>
          <select name="module_thesslstore_test_mode" class="bp-select">
            <option value="0" <?=$s('module_thesslstore_test_mode')==='0'?'selected':''?>>Live</option>
            <option value="1" <?=$s('module_thesslstore_test_mode')==='1'?'selected':''?>>Test (Sandbox)</option>
          </select>
        </div>

        <?php elseif ($tab === 'landing'):
          $cms_products = [];
          try {
              $cms_products = DB::rows("SELECT id, name, slug FROM products ORDER BY name ASC") ?: [];
          } catch (Exception $e) {}
        ?>
        <h5 style="font-weight:800;margin-bottom:16px;color:var(--primary)">🖼 Hero Section & Backdrop</h5>
        <div class="bp-form-row bp-form-row-2">
          <div class="bp-form-group"><label class="bp-label">Hero Badge</label><input type="text" name="landing_hero_badge" class="bp-input" value="<?= h($s('landing_hero_badge','⚡ Lightning Fast Hosting')) ?>"></div>
          <div class="bp-form-group"><label class="bp-label">Hero Background Image Path / URL</label><input type="text" name="landing_hero_bg_image" class="bp-input" value="<?= h($s('landing_hero_bg_image','assets/images/hero-bg.png')) ?>"><div class="bp-input-hint">Default: assets/images/hero-bg.png (our generated premium high-tech banner)</div></div>
        </div>
        <div class="bp-form-group"><label class="bp-label">Hero Main Title</label><input type="text" name="landing_hero_title" class="bp-input" value="<?= h($s('landing_hero_title','Premium Web Hosting Built for Performance & Scale')) ?>"></div>
        <div class="bp-form-group"><label class="bp-label">Hero Subtitle</label><textarea name="landing_hero_sub" class="bp-textarea" rows="3"><?= h($s('landing_hero_sub','Unmatched speed, reliable 99.9% uptime, and 24/7/365 customer support. Search your dream domain and launch your site in minutes.')) ?></textarea></div>

        <hr style="margin:28px 0;border-color:#e2e8f0">
        <h5 style="font-weight:800;margin-bottom:16px;color:var(--primary)">🔍 Interactive Domain Search Box</h5>
        <div class="bp-form-row bp-form-row-2">
          <div class="bp-form-group"><label class="bp-label">Input Placeholder Text</label><input type="text" name="landing_domain_placeholder" class="bp-input" value="<?= h($s('landing_domain_placeholder','Search your dream domain name... e.g. mybrand')) ?>"></div>
          <div class="bp-form-group"><label class="bp-label">Search Button Text</label><input type="text" name="landing_domain_btn_text" class="bp-input" value="<?= h($s('landing_domain_btn_text','Search Domain')) ?>"></div>
        </div>

        <hr style="margin:28px 0;border-color:#e2e8f0">
        <h5 style="font-weight:800;margin-bottom:16px;color:var(--primary)">📦 Package Promo Cards & Package Selector Links</h5>
        <div class="bp-input-hint mb-3">Configure your three primary promotional cards below and bind their order buttons to any dynamic product package from your system!</div>
        
        <div class="row g-4">
          <!-- Card 1 -->
          <div class="col-md-4">
            <div style="background:#f8fafc;padding:16px;border-radius:12px;border:1px solid #e2e8f0">
              <h6 style="font-weight:700;margin-bottom:12px;color:var(--dark)">Promo Card 1 (Starter)</h6>
              <div class="bp-form-group"><label class="bp-label">Plan Title</label><input type="text" name="landing_plan1_title" class="bp-input" value="<?= h($s('landing_plan1_title','Starter Cloud')) ?>"></div>
              <div class="bp-form-group"><label class="bp-label">Price (Text)</label><input type="text" name="landing_plan1_price" class="bp-input" value="<?= h($s('landing_plan1_price','2500')) ?>"></div>
              <div class="bp-form-group"><label class="bp-label">Billing Cycle (e.g. /mo)</label><input type="text" name="landing_plan1_cycle" class="bp-input" value="<?= h($s('landing_plan1_cycle','/mo')) ?>"></div>
              <div class="bp-form-group"><label class="bp-label">Description</label><textarea name="landing_plan1_desc" class="bp-textarea" rows="2"><?= h($s('landing_plan1_desc','Perfect entry plan for new personal blogs, portfolios, and startup landing sites.')) ?></textarea></div>
              <div class="bp-form-group"><label class="bp-label">Features (one per line)</label><textarea name="landing_plan1_features" class="bp-textarea" rows="3"><?= h($s('landing_plan1_features',"1 Website Allowed\n20 GB SSD Storage\nFree SSL & Domain\n24/7 Support Desk")) ?></textarea></div>
              <div class="bp-form-group">
                <label class="bp-label">Target Package Order Link</label>
                <select name="landing_plan1_product_id" class="bp-select">
                  <option value="">-- Let client choose / Static Register --</option>
                  <?php foreach($cms_products as $cp): ?>
                  <option value="<?= h($cp['slug']) ?>" <?= $s('landing_plan1_product_id')===$cp['slug']?'selected':'' ?>><?= h($cp['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
          
          <!-- Card 2 -->
          <div class="col-md-4">
            <div style="background:rgba(59,130,246,0.03);padding:16px;border-radius:12px;border:1.5px solid var(--primary)">
              <h6 style="font-weight:700;margin-bottom:12px;color:var(--primary)">Promo Card 2 (Featured)</h6>
              <div class="bp-form-group"><label class="bp-label">Plan Title</label><input type="text" name="landing_plan2_title" class="bp-input" value="<?= h($s('landing_plan2_title','Business Pro')) ?>"></div>
              <div class="bp-form-group"><label class="bp-label">Price (Text)</label><input type="text" name="landing_plan2_price" class="bp-input" value="<?= h($s('landing_plan2_price','6000')) ?>"></div>
              <div class="bp-form-group"><label class="bp-label">Billing Cycle (e.g. /mo)</label><input type="text" name="landing_plan2_cycle" class="bp-input" value="<?= h($s('landing_plan2_cycle','/mo')) ?>"></div>
              <div class="bp-form-group"><label class="bp-label">Description</label><textarea name="landing_plan2_desc" class="bp-textarea" rows="2"><?= h($s('landing_plan2_desc','Optimized for growing online businesses, corporate hubs, and e-commerce setups.')) ?></textarea></div>
              <div class="bp-form-group"><label class="bp-label">Features (one per line)</label><textarea name="landing_plan2_features" class="bp-textarea" rows="3"><?= h($s('landing_plan2_features',"Unlimited Websites\n100 GB NVMe Storage\nFree SSL & Backups\nPriority Tech Queue")) ?></textarea></div>
              <div class="bp-form-group">
                <label class="bp-label">Target Package Order Link</label>
                <select name="landing_plan2_product_id" class="bp-select">
                  <option value="">-- Let client choose / Static Register --</option>
                  <?php foreach($cms_products as $cp): ?>
                  <option value="<?= h($cp['slug']) ?>" <?= $s('landing_plan2_product_id')===$cp['slug']?'selected':'' ?>><?= h($cp['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>

          <!-- Card 3 -->
          <div class="col-md-4">
            <div style="background:#f8fafc;padding:16px;border-radius:12px;border:1px solid #e2e8f0">
              <h6 style="font-weight:700;margin-bottom:12px;color:var(--dark)">Promo Card 3 (Enterprise)</h6>
              <div class="bp-form-group"><label class="bp-label">Plan Title</label><input type="text" name="landing_plan3_title" class="bp-input" value="<?= h($s('landing_plan3_title','Enterprise Cloud')) ?>"></div>
              <div class="bp-form-group"><label class="bp-label">Price (Text)</label><input type="text" name="landing_plan3_price" class="bp-input" value="<?= h($s('landing_plan3_price','12000')) ?>"></div>
              <div class="bp-form-group"><label class="bp-label">Billing Cycle (e.g. /mo)</label><input type="text" name="landing_plan3_cycle" class="bp-input" value="<?= h($s('landing_plan3_cycle','/mo')) ?>"></div>
              <div class="bp-form-group"><label class="bp-label">Description</label><textarea name="landing_plan3_desc" class="bp-textarea" rows="2"><?= h($s('landing_plan3_desc','Dedicated server parameters tailored for massive user loads and enterprise traffic.')) ?></textarea></div>
              <div class="bp-form-group"><label class="bp-label">Features (one per line)</label><textarea name="landing_plan3_features" class="bp-textarea" rows="3"><?= h($s('landing_plan3_features',"Unlimited Websites\nUnlimited SSD Storage\nFree SSL & Backups\nDedicated Manager Support")) ?></textarea></div>
              <div class="bp-form-group">
                <label class="bp-label">Target Package Order Link</label>
                <select name="landing_plan3_product_id" class="bp-select">
                  <option value="">-- Let client choose / Static Register --</option>
                  <?php foreach($cms_products as $cp): ?>
                  <option value="<?= h($cp['slug']) ?>" <?= $s('landing_plan3_product_id')===$cp['slug']?'selected':'' ?>><?= h($cp['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
        </div>

        <hr style="margin:28px 0;border-color:#e2e8f0">
        <h5 style="font-weight:800;margin-bottom:16px;color:var(--primary)">🔌 Platform Benefits Grid (4 Cards)</h5>
        <div class="row g-3">
          <!-- Card 1 -->
          <div class="col-md-6"><div style="background:#f8fafc;padding:12px;border-radius:8px">
            <div class="bp-form-row bp-form-row-2">
              <div class="bp-form-group"><label class="bp-label">Benefit 1 Icon / Emoji</label><input type="text" name="landing_feat1_icon" class="bp-input" value="<?= h($s('landing_feat1_icon','🚀')) ?>"></div>
              <div class="bp-form-group"><label class="bp-label">Benefit 1 Title</label><input type="text" name="landing_feat1_title" class="bp-input" value="<?= h($s('landing_feat1_title','Super Fast SSDs')) ?>"></div>
            </div>
            <div class="bp-form-group"><label class="bp-label">Benefit 1 Description</label><input type="text" name="landing_feat1_desc" class="bp-input" value="<?= h($s('landing_feat1_desc','NVMe SSD storage arrays delivering 20x faster read-write operations for your applications.')) ?>"></div>
          </div></div>
          
          <!-- Card 2 -->
          <div class="col-md-6"><div style="background:#f8fafc;padding:12px;border-radius:8px">
            <div class="bp-form-row bp-form-row-2">
              <div class="bp-form-group"><label class="bp-label">Benefit 2 Icon / Emoji</label><input type="text" name="landing_feat2_icon" class="bp-input" value="<?= h($s('landing_feat2_icon','🔒')) ?>"></div>
              <div class="bp-form-group"><label class="bp-label">Benefit 2 Title</label><input type="text" name="landing_feat2_title" class="bp-input" value="<?= h($s('landing_feat2_title','Ultimate Security')) ?>"></div>
            </div>
            <div class="bp-form-group"><label class="bp-label">Benefit 2 Description</label><input type="text" name="landing_feat2_desc" class="bp-input" value="<?= h($s('landing_feat2_desc','Granular network firewalls, real-time threat scanning, and free Automated SSL certificates.')) ?>"></div>
          </div></div>

          <!-- Card 3 -->
          <div class="col-md-6"><div style="background:#f8fafc;padding:12px;border-radius:8px">
            <div class="bp-form-row bp-form-row-2">
              <div class="bp-form-group"><label class="bp-label">Benefit 3 Icon / Emoji</label><input type="text" name="landing_feat3_icon" class="bp-input" value="<?= h($s('landing_feat3_icon','📦')) ?>"></div>
              <div class="bp-form-group"><label class="bp-label">Benefit 3 Title</label><input type="text" name="landing_feat3_title" class="bp-input" value="<?= h($s('landing_feat3_title','1-Click Installers')) ?>"></div>
            </div>
            <div class="bp-form-group"><label class="bp-label">Benefit 3 Description</label><input type="text" name="landing_feat3_desc" class="bp-input" value="<?= h($s('landing_feat3_desc','Deploy WordPress, Joomla, Drupal, and over 150 different scripts with a single mouse click.')) ?>"></div>
          </div></div>

          <!-- Card 4 -->
          <div class="col-md-6"><div style="background:#f8fafc;padding:12px;border-radius:8px">
            <div class="bp-form-row bp-form-row-2">
              <div class="bp-form-group"><label class="bp-label">Benefit 4 Icon / Emoji</label><input type="text" name="landing_feat4_icon" class="bp-input" value="<?= h($s('landing_feat4_icon','🛡')) ?>"></div>
              <div class="bp-form-group"><label class="bp-label">Benefit 4 Title</label><input type="text" name="landing_feat4_title" class="bp-input" value="<?= h($s('landing_feat4_title','DDoS Protection')) ?>"></div>
            </div>
            <div class="bp-form-group"><label class="bp-label">Benefit 4 Description</label><input type="text" name="landing_feat4_desc" class="bp-input" value="<?= h($s('landing_feat4_desc','Platform-wide traffic mitigation shields your servers and sites from sudden packet floods.')) ?>"></div>
          </div></div>
        </div>

        <hr style="margin:28px 0;border-color:#e2e8f0">
        <h5 style="font-weight:800;margin-bottom:16px;color:var(--primary)">📊 Datacenter Stats Banner</h5>
        <div class="bp-form-row bp-form-row-2" style="grid-template-columns:repeat(4,1fr)">
          <div class="bp-form-group"><label class="bp-label">Stat 1 Value</label><input type="text" name="landing_stat1_val" class="bp-input" value="<?= h($s('landing_stat1_val','99.9%')) ?>"></div>
          <div class="bp-form-group"><label class="bp-label">Stat 1 Label</label><input type="text" name="landing_stat1_lbl" class="bp-input" value="<?= h($s('landing_stat1_lbl','Uptime Guarantee')) ?>"></div>
          <div class="bp-form-group"><label class="bp-label">Stat 2 Value</label><input type="text" name="landing_stat2_val" class="bp-input" value="<?= h($s('landing_stat2_val','100ms')) ?>"></div>
          <div class="bp-form-group"><label class="bp-label">Stat 2 Label</label><input type="text" name="landing_stat2_lbl" class="bp-input" value="<?= h($s('landing_stat2_lbl','Average Response Time')) ?>"></div>
          <div class="bp-form-group"><label class="bp-label">Stat 3 Value</label><input type="text" name="landing_stat3_val" class="bp-input" value="<?= h($s('landing_stat3_val','15,000+')) ?>"></div>
          <div class="bp-form-group"><label class="bp-label">Stat 3 Label</label><input type="text" name="landing_stat3_lbl" class="bp-input" value="<?= h($s('landing_stat3_lbl','Clients Worldwide')) ?>"></div>
          <div class="bp-form-group"><label class="bp-label">Stat 4 Value</label><input type="text" name="landing_stat4_val" class="bp-input" value="<?= h($s('landing_stat4_val','24/7')) ?>"></div>
          <div class="bp-form-group"><label class="bp-label">Stat 4 Label</label><input type="text" name="landing_stat4_lbl" class="bp-input" value="<?= h($s('landing_stat4_lbl','Expert Tech Support')) ?>"></div>
        </div>

        <hr style="margin:28px 0;border-color:#e2e8f0">
        <h5 style="font-weight:800;margin-bottom:16px;color:var(--primary)">🎫 Support Banner (Call-to-Action)</h5>
        <div class="bp-form-group"><label class="bp-label">Support Heading</label><input type="text" name="landing_support_title" class="bp-input" value="<?= h($s('landing_support_title','Need help choosing the right plan?')) ?>"></div>
        <div class="bp-form-group"><label class="bp-label">Support Summary Text</label><input type="text" name="landing_support_desc" class="bp-input" value="<?= h($s('landing_support_desc','Our dedicated support technicians are available 24 hours a day, 7 days a week to guide your journey.')) ?>"></div>
        <div class="bp-form-row bp-form-row-2">
          <div class="bp-form-group"><label class="bp-label">Button 1 Label</label><input type="text" name="landing_support_btn1_text" class="bp-input" value="<?= h($s('landing_support_btn1_text','Open Support Ticket')) ?>"></div>
          <div class="bp-form-group"><label class="bp-label">Button 1 Link (e.g. /client/tickets.php)</label><input type="text" name="landing_support_btn1_link" class="bp-input" value="<?= h($s('landing_support_btn1_link','/client/tickets.php')) ?>"></div>
          <div class="bp-form-group"><label class="bp-label">Button 2 Label</label><input type="text" name="landing_support_btn2_text" class="bp-input" value="<?= h($s('landing_support_btn2_text','Browse Knowledgebase')) ?>"></div>
          <div class="bp-form-group"><label class="bp-label">Button 2 Link</label><input type="text" name="landing_support_btn2_link" class="bp-input" value="<?= h($s('landing_support_btn2_link','/client/login.php')) ?>"></div>
        </div>

        <?php endif ?>

        <hr style="margin:24px 0;border-color:#f1f5f9">
        <button type="submit" name="action" value="save" class="bp-btn bp-btn-primary">💾 Save Settings</button>
      </form>
    </div>
  </div>
</div>

<?php include 'partials/footer.php'; ?>
