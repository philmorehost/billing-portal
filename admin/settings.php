<?php
require_once '../includes/config.php';
$admin      = Auth::requireAdmin();
$company    = DB::setting('company_name', 'Billing Portal');
$page_title = 'Settings';

if (is_post() && csrf_verify()) {
    $group = post('group');
    $keys  = array_keys($_POST);
    $saved = 0;

    foreach ($keys as $key) {
        if (in_array($key, ['action','group','csrf_token'])) continue;
        $val = is_array($_POST[$key]) ? implode(',', $_POST[$key]) : trim($_POST[$key]);
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
          <div class="bp-form-group"><label class="bp-label">USD Exchange Rate (per USD)</label><input type="number" step="0.01" name="usd_exchange_rate" class="bp-input" value="<?= h($s('usd_exchange_rate','1600')) ?>"><div class="bp-input-hint">Used for NGN→USD conversion at checkout.</div></div>
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
        <h5 style="font-weight:700;margin-bottom:16px">Cryptocurrency</h5>
        <div class="bp-form-group">
          <label class="bp-label">Crypto Enabled</label>
          <select name="crypto_enabled" class="bp-select">
            <option value="1" <?= $s('crypto_enabled')==='1'?'selected':'' ?>>Enabled</option>
            <option value="0" <?= $s('crypto_enabled')==='0'?'selected':'' ?>>Disabled</option>
          </select>
        </div>
        <div class="bp-form-group"><label class="bp-label">Crypto Payment Details</label><textarea name="crypto_details" class="bp-textarea" rows="5" placeholder="BTC Address: ..."><?= h($s('crypto_details')) ?></textarea></div>

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
          <div class="bp-form-group"><label class="bp-label">Username</label><input type="text" name="module_connectreseller_username" class="bp-input" value="<?=h($s('module_connectreseller_username'))?>"></div>
          <div class="bp-form-group"><label class="bp-label">Password</label><input type="password" name="module_connectreseller_password" class="bp-input" placeholder="Leave blank to keep existing"></div>
        </div>
        <hr style="margin:24px 0;border-color:#f1f5f9">

        <!-- Upperlink .NG -->
        <h5 style="font-weight:700;margin-bottom:16px;color:#0f172a">🔌 Upperlink (.NG Domains)</h5>
        <div class="bp-form-group"><label class="bp-label">API Key</label><input type="password" name="module_upperlink_api_key" class="bp-input" placeholder="Leave blank to keep existing"></div>
        <hr style="margin:24px 0;border-color:#f1f5f9">

        <!-- NOCIX -->
        <h5 style="font-weight:700;margin-bottom:16px;color:#0f172a">🔌 NOCIX Dedicated Servers</h5>
        <div class="bp-form-row bp-form-row-2">
          <div class="bp-form-group"><label class="bp-label">API Key</label><input type="password" name="module_nocix_api_key" class="bp-input" placeholder="Leave blank to keep existing"></div>
          <div class="bp-form-group"><label class="bp-label">Default Location</label><input type="text" name="module_nocix_default_location" class="bp-input" value="<?=h($s('module_nocix_default_location','dallas'))?>" placeholder="dallas"></div>
        </div>
        <hr style="margin:24px 0;border-color:#f1f5f9">

        <!-- Time4VPS -->
        <h5 style="font-weight:700;margin-bottom:16px;color:#0f172a">🔌 Time4VPS</h5>
        <div class="bp-form-row bp-form-row-2">
          <div class="bp-form-group"><label class="bp-label">API Username (email)</label><input type="text" name="module_time4vps_username" class="bp-input" value="<?=h($s('module_time4vps_username'))?>"></div>
          <div class="bp-form-group"><label class="bp-label">API Password</label><input type="password" name="module_time4vps_password" class="bp-input" placeholder="Leave blank to keep existing"></div>
        </div>
        <div class="bp-form-group"><label class="bp-label">Default VPS Product ID</label><input type="text" name="module_time4vps_default_product_id" class="bp-input" value="<?=h($s('module_time4vps_default_product_id'))?>" placeholder="Product ID from Time4VPS panel"></div>

        <?php endif ?>

        <hr style="margin:24px 0;border-color:#f1f5f9">
        <button type="submit" name="action" value="save" class="bp-btn bp-btn-primary">💾 Save Settings</button>
      </form>
    </div>
  </div>
</div>

<?php include 'partials/footer.php'; ?>
