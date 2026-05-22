<?php
require 'includes/config.php';
echo "--- SETTINGS ---\n";
$res = DB::rows("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('base_currency', 'usd_to_ngn_markup_percent', 'live_rates_cache', 'live_rates_last_updated')");
foreach($res as $r) echo $r['setting_key'] . ": " . $r['setting_value'] . "\n";

echo "\n--- DOMAIN TLDS ---\n";
$res = DB::rows("SELECT * FROM domain_tlds");
foreach($res as $r) {
    echo "." . $r['tld'] . " | Base: " . $r['base_price_register'] . " | Retail: " . $r['retail_price_register'] . " | Registrar: " . $r['registrar'] . "\n";
}

echo "\n--- PRODUCTS ---\n";
$res = DB::rows("SELECT id, name, type, price_monthly, price_annually, currency FROM products WHERE id=8 OR name LIKE '%domain%'");
foreach($res as $r) {
    echo "ID: " . $r['id'] . " | Name: " . $r['name'] . " | Type: " . $r['type'] . " | Price(M): " . $r['price_monthly'] . " | Price(A): " . $r['price_annually'] . " | Currency: " . $r['currency'] . "\n";
}
