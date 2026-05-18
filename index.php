<?php
/**
 * Public Entry Point & Reseller Domain Router
 *
 * - Reseller custom domain  → serve reseller portal (white-labeled)
 * - Main domain             → redirect to client login
 * - Unknown host            → 400 error page
 */
require_once __DIR__ . '/includes/config.php';

$host      = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
$main_host = strtolower(explode(':', parse_url(BASE_URL, PHP_URL_HOST) ?? 'localhost')[0]);

// Main domain → client area
if ($host === $main_host || $host === 'www.' . $main_host) {
    redirect(BASE_URL . '/client/login.php');
}

// Check approved reseller domain
$reseller = DB::row(
    "SELECT r.*, c.first_name, c.last_name, c.email
     FROM resellers r
     JOIN clients c ON c.id = r.client_id
     WHERE r.custom_domain = ? AND r.status = 'active'",
    's', [$host]
);

if ($reseller) {
    // Store reseller context and redirect to white-labeled portal
    $_SESSION['reseller_domain_id']  = $reseller['id'];
    $_SESSION['reseller_host_brand'] = [
        'name'  => $reseller['branding_name'] ?: DB::setting('company_name', 'Billing Portal'),
        'color' => $reseller['branding_color'] ?: '#0f172a',
    ];

    // If user already logged in as this reseller → go to dashboard
    if (!empty($_SESSION['reseller_id']) && $_SESSION['reseller_id'] === $reseller['id']) {
        redirect(BASE_URL . '/reseller/');
    }

    // Otherwise go to branded login
    redirect(BASE_URL . '/reseller/login.php');
}

// Unknown / unapproved host → themed 400 error
include __DIR__ . '/includes/error-unauthorized-host.php';
exit;
