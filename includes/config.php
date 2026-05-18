<?php
define('APP_DEBUG', false);
if (APP_DEBUG) { ini_set('display_errors',1); error_reporting(E_ALL); }
else { ini_set('display_errors',0); error_reporting(0); }

define('ROOT_PATH', realpath(__DIR__ . '/..'));
define('INC_PATH',  ROOT_PATH . '/includes');

function app_base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir    = rtrim(dirname($script), '/\\');
    $parts  = explode('/', trim($dir, '/'));
    $subs   = ['admin','client','install','api','cron'];
    $filtered = array_filter($parts, fn($p) => !in_array($p, $subs));
    return $scheme . '://' . $host . '/' . implode('/', $filtered);
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

