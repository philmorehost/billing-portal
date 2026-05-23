<?php
define('APP_DEBUG', false);
if (APP_DEBUG) { ini_set('display_errors',1); error_reporting(E_ALL); }
else { ini_set('display_errors',0); error_reporting(0); }

define('ROOT_PATH', realpath(__DIR__ . '/..'));
define('INC_PATH',  ROOT_PATH . '/includes');

function app_base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $root = str_replace('\\', '/', ROOT_PATH);
    $script_file = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    $suffix = '';
    if (strpos($script_file, $root) === 0) {
        $suffix = substr($script_file, strlen($root));
    }
    $web_root = '';
    if (!empty($suffix) && strpos($script_name, $suffix) !== false) {
        $web_root = substr($script_name, 0, strrpos($script_name, $suffix));
    } else {
        $dir = rtrim(dirname($script_name), '/\\');
        $parts = explode('/', trim($dir, '/'));
        $subs = ['admin','client','install','api','cron','reports','clients','services','invoices','products','servers','tickets','partials'];
        $filtered = array_filter($parts, fn($p) => !in_array($p, $subs));
        $web_root = '/' . implode('/', $filtered);
    }
    return $scheme . '://' . $host . rtrim($web_root, '/');
}
define('BASE_URL', rtrim(app_base_url(), '/'));

if (session_status() === PHP_SESSION_NONE) {
    session_name('BP_SESSION');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>isset($_SERVER['HTTPS']),'httponly'=>true,'samesite'=>'Strict']);
    session_start();
}

$config_file = ROOT_PATH . '/includes/db.config.php';
$is_installer = str_contains($_SERVER['SCRIPT_FILENAME'] ?? '', '/install/');

if (!file_exists($config_file) && !$is_installer) {
    header('Location: ' . BASE_URL . '/install/'); exit;
}
if (file_exists($config_file)) require_once $config_file;

foreach (['db','functions','auth','mailer'] as $f) {
    $p = INC_PATH . "/{$f}.php";
    if (file_exists($p)) require_once $p;
}

// Global Reseller Custom Domain Host Detector
if (class_exists('DB') && file_exists($config_file)) {
    require_once INC_PATH . '/modules/reseller.php';
    try {
        // Auto-migrate: Create domain_tlds table if not exists
        DB::execute("CREATE TABLE IF NOT EXISTS `domain_tlds` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `tld` VARCHAR(50) NOT NULL UNIQUE,
          `registrar` VARCHAR(50) DEFAULT 'none',
          `base_price_register` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
          `base_price_renew` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
          `base_price_transfer` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
          `markup_type` ENUM('percentage', 'fixed') DEFAULT 'percentage',
          `markup_value` DECIMAL(15,2) DEFAULT 20.00,
          `retail_price_register` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
          `retail_price_renew` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
          `retail_price_transfer` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
          `dns_management` INT DEFAULT 0,
          `email_forwarding` INT DEFAULT 0,
          `id_protection` INT DEFAULT 0,
          `epp_code` INT DEFAULT 1,
          `status` ENUM('active', 'inactive') DEFAULT 'active',
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Auto-migrate: check and add missing columns if they don't exist
        $existing_cols = [];
        $cols = DB::rows("SHOW COLUMNS FROM `domain_tlds`");
        if (is_array($cols)) {
            foreach ($cols as $c) {
                if (isset($c['Field'])) {
                    $existing_cols[] = strtolower($c['Field']);
                }
            }
        }
        
        if (!in_array('registrar', $existing_cols)) {
            DB::execute("ALTER TABLE `domain_tlds` ADD COLUMN `registrar` VARCHAR(50) DEFAULT 'none' AFTER `tld`");
        }
        if (!in_array('dns_management', $existing_cols)) {
            DB::execute("ALTER TABLE `domain_tlds` ADD COLUMN `dns_management` INT DEFAULT 0 AFTER `retail_price_transfer`");
        }
        if (!in_array('email_forwarding', $existing_cols)) {
            DB::execute("ALTER TABLE `domain_tlds` ADD COLUMN `email_forwarding` INT DEFAULT 0 AFTER `dns_management`");
        }
        if (!in_array('id_protection', $existing_cols)) {
            DB::execute("ALTER TABLE `domain_tlds` ADD COLUMN `id_protection` INT DEFAULT 0 AFTER `email_forwarding`");
        }
        if (!in_array('epp_code', $existing_cols)) {
            DB::execute("ALTER TABLE `domain_tlds` ADD COLUMN `epp_code` INT DEFAULT 1 AFTER `id_protection`");
        }

        // Auto-migrate: Create reseller_domain_prices table if not exists
        DB::execute("CREATE TABLE IF NOT EXISTS `reseller_domain_prices` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `reseller_id` INT NOT NULL,
          `tld_id` INT NOT NULL,
          `markup_type` ENUM('percentage', 'fixed') DEFAULT 'percentage',
          `markup_value` DECIMAL(15,2) DEFAULT 20.00,
          `retail_price_register` DECIMAL(15,2) DEFAULT NULL,
          `retail_price_renew` DECIMAL(15,2) DEFAULT NULL,
          `retail_price_transfer` DECIMAL(15,2) DEFAULT NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY `reseller_tld` (`reseller_id`, `tld_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Auto-migrate: check if resellers has bank_transfer_details column
        $cols = DB::rows("SHOW COLUMNS FROM resellers LIKE 'bank_transfer_details'");
        if (empty($cols)) {
            DB::execute("ALTER TABLE resellers ADD COLUMN bank_transfer_details TEXT DEFAULT NULL");
        }

        // Auto-migrate: check if affiliates has total_paid column
        $cols = DB::rows("SHOW COLUMNS FROM affiliates LIKE 'total_paid'");
        if (empty($cols)) {
            DB::execute("ALTER TABLE affiliates ADD COLUMN total_paid DECIMAL(15,2) DEFAULT 0.00 AFTER balance");
        }

        // Auto-migrate: check if servers has password column
        $cols = DB::rows("SHOW COLUMNS FROM servers LIKE 'password'");
        if (empty($cols)) {
            DB::execute("ALTER TABLE servers ADD COLUMN password VARCHAR(255) DEFAULT NULL AFTER username");
        }

        // Auto-migrate: add required columns for new features
        $c_cols = DB::rows("SHOW COLUMNS FROM clients LIKE 'google_id'");
        if (empty($c_cols)) DB::execute("ALTER TABLE clients ADD COLUMN google_id VARCHAR(255) DEFAULT NULL AFTER email_verify_token");

        $c_cols2 = DB::rows("SHOW COLUMNS FROM clients LIKE 'email_verified'");
        if (empty($c_cols2)) DB::execute("ALTER TABLE clients ADD COLUMN email_verified TINYINT(1) DEFAULT 0 AFTER google_id");

        $s_cols = DB::rows("SHOW COLUMNS FROM services LIKE 'module_data'");
        if (empty($s_cols)) DB::execute("ALTER TABLE services ADD COLUMN module_data JSON DEFAULT NULL AFTER termination_date");

        $s_cols2 = DB::rows("SHOW COLUMNS FROM services LIKE 'currency'");
        if (empty($s_cols2)) DB::execute("ALTER TABLE services ADD COLUMN currency VARCHAR(10) DEFAULT NULL AFTER price");

        // Auto-migrate products for external sync
        $p_cols = DB::rows("SHOW COLUMNS FROM products LIKE 'external_id'");
        if (empty($p_cols)) DB::execute("ALTER TABLE products ADD COLUMN external_id VARCHAR(100) DEFAULT NULL AFTER module");

        // Auto-migrate: check if affiliate_referrals table exists
        $tables = DB::rows("SHOW TABLES LIKE 'affiliate_referrals'");
        if (empty($tables)) {
            DB::execute("CREATE TABLE IF NOT EXISTS `affiliate_referrals` (
              `id` int NOT NULL AUTO_INCREMENT,
              `affiliate_id` int NOT NULL,
              `referred_client_id` int NOT NULL,
              `commission_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
              `status` enum('pending','approved','paid','cancelled') DEFAULT 'pending',
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `affiliate_id` (`affiliate_id`),
              KEY `referred_client_id` (`referred_client_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        // Auto-migrate email template: upgrade invoice_created to responsive summary layout
        $tmpl = DB::row("SELECT body_html FROM email_templates WHERE slug='invoice_created'");
        if ($tmpl && strpos($tmpl['body_html'], '{invoice_items}') === false) {
            $new_body = '<p>Dear {client_name},</p>
<p>This is a notice that invoice <strong>#{invoice_number}</strong> is now due on {due_date}. Please find the invoice summary below:</p>

<div style="overflow-x: auto; margin: 20px 0;">
  <table style="width: 100%; border-collapse: collapse; border: 1px solid #edf2f7; border-radius: 8px; font-size: 14px;">
    <thead>
      <tr style="background-color: #f7fafc;">
        <th style="padding: 12px; text-align: left; font-size: 11px; font-weight: bold; text-transform: uppercase; color: #718096; border-bottom: 2px solid #edf2f7;">Description</th>
        <th style="padding: 12px; text-align: center; font-size: 11px; font-weight: bold; text-transform: uppercase; color: #718096; border-bottom: 2px solid #edf2f7; width: 50px;">Qty</th>
        <th style="padding: 12px; text-align: right; font-size: 11px; font-weight: bold; text-transform: uppercase; color: #718096; border-bottom: 2px solid #edf2f7; width: 100px;">Price</th>
        <th style="padding: 12px; text-align: right; font-size: 11px; font-weight: bold; text-transform: uppercase; color: #718096; border-bottom: 2px solid #edf2f7; width: 100px;">Total</th>
      </tr>
    </thead>
    <tbody>
      {invoice_items}
    </tbody>
  </table>
</div>

<table align="right" style="width: 260px; border-collapse: collapse; margin-bottom: 20px; font-size: 14px;">
  <tr>
    <td style="padding: 6px 0; border-bottom: 1px solid #edf2f7; color: #718096;">Subtotal:</td>
    <td style="padding: 6px 0; border-bottom: 1px solid #edf2f7; text-align: right; color: #2d3748; font-weight: 500;">{subtotal}</td>
  </tr>
  <tr>
    <td style="padding: 6px 0; border-bottom: 1px solid #edf2f7; color: #718096;">Tax/VAT:</td>
    <td style="padding: 6px 0; border-bottom: 1px solid #edf2f7; text-align: right; color: #2d3748; font-weight: 500;">{tax_amount}</td>
  </tr>
  <tr>
    <td style="padding: 12px 0; font-size: 16px; font-weight: bold; color: #2d3748;">Total Due:</td>
    <td style="padding: 12px 0; font-size: 16px; font-weight: 800; text-align: right; color: #0f172a;">{invoice_total}</td>
  </tr>
</table>
<div style="clear: both;"></div>

{bank_details}

<p style="margin-top: 30px; text-align: center; clear: both;">
  <a href="{invoice_url}" class="btn" style="background-color: #0f172a; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: bold; display: inline-block; font-size: 14px;">Pay Invoice Online &rarr;</a>
</p>';
            DB::execute("UPDATE email_templates SET body_html=? WHERE slug='invoice_created'", 's', [$new_body]);
        }

        $reseller_host = Reseller::detectFromHost();
        if ($reseller_host) {
            $_SESSION['reseller_domain_id']  = $reseller_host['id'];
            $_SESSION['reseller_host_brand'] = [
                'name'  => $reseller_host['branding_name'] ?: DB::setting('company_name', 'Billing Portal'),
                'color' => $reseller_host['branding_color'] ?: '#0f172a',
            ];
        } else {
            unset($_SESSION['reseller_domain_id']);
            unset($_SESSION['reseller_host_brand']);
        }
    } catch (Exception $e) {}
}

// Maintenance mode — redirect clients if enabled
if (!$is_installer && file_exists($config_file)) {
    $is_admin   = str_contains($_SERVER['SCRIPT_FILENAME'] ?? '', DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR);
    $is_maint   = str_contains($_SERVER['SCRIPT_FILENAME'] ?? '', 'maintenance.php');
    $is_reseller= str_contains($_SERVER['SCRIPT_FILENAME'] ?? '', DIRECTORY_SEPARATOR . 'reseller' . DIRECTORY_SEPARATOR);
    if (!$is_admin && !$is_maint && !$is_reseller && empty($_SESSION['admin_id'])) {
        if (class_exists('DB') && DB::setting('maintenance_mode', '0') === '1') {
            header('Location: ' . BASE_URL . '/maintenance.php');
            exit;
        }
    }
}

