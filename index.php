<?php
/**
 * Public Entry Point, Reseller Domain Router & Premium Landing Page
 *
 * - Reseller custom domain  → serve reseller portal (white-labeled)
 * - Main domain             → serve premium hosting landing page
 * - Unknown host            → 400 error page
 */
require_once __DIR__ . '/includes/config.php';

$host      = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
$main_host = strtolower(explode(':', parse_url(BASE_URL, PHP_URL_HOST) ?? 'localhost')[0]);

// If it's a reseller domain, route it accordingly
if ($host !== $main_host && $host !== 'www.' . $main_host) {
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
}

// Ensure reseller session context is cleared on main domain
unset($_SESSION['reseller_domain_id']);
unset($_SESSION['reseller_host_brand']);

// Load parameters for dynamic landing page
$currency = DB::setting('base_currency', 'NGN');
$company  = DB::setting('company_name', 'Billing Portal');
$company_email = DB::setting('company_email', 'support@example.com');
$company_phone = DB::setting('company_phone', '');
$company_address = DB::setting('company_address', '');

// Load Landing Page CMS Content Settings
$hero_badge = DB::setting('landing_hero_badge', '⚡ Lightning Fast Hosting');
$hero_bg_image = DB::setting('landing_hero_bg_image', 'assets/images/hero-bg.png');
$hero_title = DB::setting('landing_hero_title', 'Premium Web Hosting Built for Performance & Scale');
$hero_sub = DB::setting('landing_hero_sub', 'Unmatched speed, reliable 99.9% uptime, and 24/7/365 customer support. Search your dream domain and launch your site in minutes.');

$domain_placeholder = DB::setting('landing_domain_placeholder', 'Search your dream domain name... e.g. mybrand');
$domain_btn_text = DB::setting('landing_domain_btn_text', 'Search Domain');

// Plan 1 (Starter Promo Card)
$plan1_title = DB::setting('landing_plan1_title', 'Starter Cloud');
$plan1_price = DB::setting('landing_plan1_price', '2500');
$plan1_cycle = DB::setting('landing_plan1_cycle', '/mo');
$plan1_desc = DB::setting('landing_plan1_desc', 'Perfect entry plan for new personal blogs, portfolios, and startup landing sites.');
$plan1_features = array_filter(array_map('trim', explode("\n", DB::setting('landing_plan1_features', "1 Website Allowed\n20 GB SSD Storage\nFree SSL & Domain\n24/7 Support Desk"))));
$plan1_slug = DB::setting('landing_plan1_product_id', '');
$plan1_link = $plan1_slug ? BASE_URL . "/client/order.php?product=" . urlencode($plan1_slug) : BASE_URL . "/client/register.php";

// Plan 2 (Business Pro Promo Card)
$plan2_title = DB::setting('landing_plan2_title', 'Business Pro');
$plan2_price = DB::setting('landing_plan2_price', '6000');
$plan2_cycle = DB::setting('landing_plan2_cycle', '/mo');
$plan2_desc = DB::setting('landing_plan2_desc', 'Optimized for growing online businesses, corporate hubs, and e-commerce setups.');
$plan2_features = array_filter(array_map('trim', explode("\n", DB::setting('landing_plan2_features', "Unlimited Websites\n100 GB NVMe Storage\nFree SSL & Backups\nPriority Tech Queue"))));
$plan2_slug = DB::setting('landing_plan2_product_id', '');
$plan2_link = $plan2_slug ? BASE_URL . "/client/order.php?product=" . urlencode($plan2_slug) : BASE_URL . "/client/register.php";

// Plan 3 (Enterprise Cloud Promo Card)
$plan3_title = DB::setting('landing_plan3_title', 'Enterprise Cloud');
$plan3_price = DB::setting('landing_plan3_price', '12000');
$plan3_cycle = DB::setting('landing_plan3_cycle', '/mo');
$plan3_desc = DB::setting('landing_plan3_desc', 'Dedicated server parameters tailored for massive user loads and enterprise traffic.');
$plan3_features = array_filter(array_map('trim', explode("\n", DB::setting('landing_plan3_features', "Unlimited Websites\nUnlimited SSD Storage\nFree SSL & Backups\nDedicated Manager Support"))));
$plan3_slug = DB::setting('landing_plan3_product_id', '');
$plan3_link = $plan3_slug ? BASE_URL . "/client/order.php?product=" . urlencode($plan3_slug) : BASE_URL . "/client/register.php";

// Platform Benefits (4 Cards)
$feat1_icon = DB::setting('landing_feat1_icon', '🚀');
$feat1_title = DB::setting('landing_feat1_title', 'Super Fast SSDs');
$feat1_desc = DB::setting('landing_feat1_desc', 'NVMe SSD storage arrays delivering 20x faster read-write operations for your applications.');

$feat2_icon = DB::setting('landing_feat2_icon', '🔒');
$feat2_title = DB::setting('landing_feat2_title', 'Ultimate Security');
$feat2_desc = DB::setting('landing_feat2_desc', 'Granular network firewalls, real-time threat scanning, and free Automated SSL certificates.');

$feat3_icon = DB::setting('landing_feat3_icon', '📦');
$feat3_title = DB::setting('landing_feat3_title', '1-Click Installers');
$feat3_desc = DB::setting('landing_feat3_desc', 'Deploy WordPress, Joomla, Drupal, and over 150 different scripts with a single mouse click.');

$feat4_icon = DB::setting('landing_feat4_icon', '🛡');
$feat4_title = DB::setting('landing_feat4_title', 'DDoS Protection');
$feat4_desc = DB::setting('landing_feat4_desc', 'Platform-wide traffic mitigation shields your servers and sites from sudden packet floods.');

// Datacenter Stats (4 counters)
$stat1_val = DB::setting('landing_stat1_val', '99.9%');
$stat1_lbl = DB::setting('landing_stat1_lbl', 'Uptime Guarantee');

$stat2_val = DB::setting('landing_stat2_val', '100ms');
$stat2_lbl = DB::setting('landing_stat2_lbl', 'Average Response Time');

$stat3_val = DB::setting('landing_stat3_val', '15,000+');
$stat3_lbl = DB::setting('landing_stat3_lbl', 'Clients Worldwide');

$stat4_val = DB::setting('landing_stat4_val', '24/7');
$stat4_lbl = DB::setting('landing_stat4_lbl', 'Expert Tech Support');

// Support Callout Banner Settings
$support_title = DB::setting('landing_support_title', 'Need help choosing the right plan?');
$support_desc = DB::setting('landing_support_desc', 'Our dedicated support technicians are available 24 hours a day, 7 days a week to guide your journey.');
$support_btn1_text = DB::setting('landing_support_btn1_text', 'Open Support Ticket');
$support_btn1_link = DB::setting('landing_support_btn1_link', '/client/tickets.php');
$support_btn2_text = DB::setting('landing_support_btn2_text', 'Browse Knowledgebase');
$support_btn2_link = DB::setting('landing_support_btn2_link', '/client/login.php');

// Fetch dynamic products and groups from database (tab layout below)
$groups = [];
$products = [];
try {
    $groups = DB::rows("SELECT * FROM product_groups WHERE visible=1 ORDER BY sort_order ASC") ?: [];
    $products = DB::rows("SELECT * FROM products WHERE visible=1 ORDER BY sort_order ASC") ?: [];
} catch (Exception $e) {}

// Organize products by group
$grouped_products = [];
foreach ($products as $p) {
    $grouped_products[$p['group_id']][] = $p;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Premium Web Hosting & Domains — <?= h($company) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #3b82f6;
      --primary-hover: #2563eb;
      --accent: #10b981;
      --accent-hover: #059669;
      --dark: #0f172a;
      --dark-card: #1e293b;
      --light: #f8fafc;
      --border: rgba(226, 232, 240, 0.8);
      --font-heading: 'Outfit', sans-serif;
      --font-body: 'Plus Jakarta Sans', sans-serif;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: var(--font-body);
      color: #334155;
      background-color: #fff;
      overflow-x: hidden;
      line-height: 1.6;
    }

    h1, h2, h3, h4, h5, h6 {
      font-family: var(--font-heading);
      color: var(--dark);
      font-weight: 700;
    }

    /* Premium Sticky Header */
    .hp-header {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 1000;
      background: rgba(255, 255, 255, 0.85);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border-bottom: 1px solid rgba(15, 23, 42, 0.06);
      transition: all 0.3s ease;
    }

    .hp-nav {
      display: flex;
      justify-content: space-between;
      align-items: center;
      max-width: 1280px;
      margin: 0 auto;
      padding: 16px 24px;
    }

    .hp-logo {
      display: flex;
      align-items: center;
      gap: 10px;
      font-family: var(--font-heading);
      font-size: 22px;
      font-weight: 800;
      color: var(--dark);
      text-decoration: none;
    }

    .hp-logo-icon {
      width: 38px;
      height: 38px;
      border-radius: 10px;
      background: linear-gradient(135deg, var(--primary), #60a5fa);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-size: 20px;
      font-weight: 700;
    }

    .hp-menu {
      display: flex;
      align-items: center;
      gap: 28px;
      list-style: none;
    }

    .hp-menu-link {
      font-size: 14px;
      font-weight: 600;
      color: #475569;
      text-decoration: none;
      transition: color 0.2s ease;
    }

    .hp-menu-link:hover {
      color: var(--primary);
    }

    .hp-nav-btns {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .hp-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 10px 20px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 700;
      text-decoration: none;
      transition: all 0.2s ease;
      cursor: pointer;
    }

    .hp-btn-outline {
      border: 1.5px solid var(--border);
      color: var(--dark);
      background: #fff;
    }

    .hp-btn-outline:hover {
      border-color: var(--dark);
      background: var(--light);
    }

    .hp-btn-primary {
      background: var(--dark);
      color: #fff;
      border: none;
    }

    .hp-btn-primary:hover {
      background: #1e293b;
      transform: translateY(-1px);
    }

    .hp-btn-accent {
      background: var(--primary);
      color: #fff;
      border: none;
    }

    .hp-btn-accent:hover {
      background: var(--primary-hover);
      transform: translateY(-1px);
    }

    .hp-menu-toggle {
      display: none;
      background: none;
      border: none;
      font-size: 24px;
      color: var(--dark);
      cursor: pointer;
    }

    /* Hero Section with Dark Replaceable Backdrop Image */
    .hp-hero {
      position: relative;
      background-image: linear-gradient(rgba(15, 23, 42, 0.88), rgba(15, 23, 42, 0.82)), url('<?= BASE_URL ?>/<?= h($hero_bg_image) ?>');
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      padding: 210px 24px 140px;
      text-align: center;
      color: #fff;
    }

    .hp-hero-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(255, 255, 255, 0.12);
      color: #93c5fd;
      padding: 6px 14px;
      border-radius: 50px;
      font-size: 12px;
      font-weight: 700;
      margin-bottom: 24px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .hp-hero-title {
      font-size: 54px;
      font-weight: 900;
      letter-spacing: -1.5px;
      line-height: 1.15;
      max-width: 880px;
      margin: 0 auto 20px;
      background: linear-gradient(135deg, #ffffff 60%, #93c5fd);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .hp-hero-sub {
      font-size: 17px;
      color: #cbd5e1;
      max-width: 650px;
      margin: 0 auto 36px;
      line-height: 1.7;
    }

    /* Interactive Domain Checker */
    .hp-domain-box {
      max-width: 720px;
      margin: 0 auto 40px;
      background: #fff;
      padding: 10px;
      border-radius: 16px;
      box-shadow: 0 10px 40px rgba(15, 23, 42, 0.08);
      border: 1px solid var(--border);
    }

    .hp-domain-form {
      display: flex;
      gap: 8px;
    }

    .hp-domain-input {
      flex: 1;
      border: none;
      padding: 12px 20px;
      font-size: 15px;
      font-weight: 600;
      color: var(--dark);
      outline: none;
    }

    .hp-domain-input::placeholder {
      color: #94a3b8;
    }

    .hp-domain-btn {
      background: var(--dark);
      color: #fff;
      border: none;
      padding: 14px 32px;
      font-size: 14px;
      font-weight: 700;
      border-radius: 10px;
      transition: all 0.2s ease;
    }

    .hp-domain-btn:hover {
      background: #1e293b;
      transform: scale(1.01);
    }

    .hp-tlds {
      display: flex;
      justify-content: center;
      flex-wrap: wrap;
      gap: 16px;
      font-size: 13px;
      font-weight: 600;
      color: #cbd5e1;
    }

    .hp-tld-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .hp-tld-badge strong {
      color: #fff;
    }

    /* Products Section */
    .hp-section {
      max-width: 1280px;
      margin: 0 auto;
      padding: 80px 24px;
    }

    .hp-section-header {
      text-align: center;
      margin-bottom: 50px;
    }

    .hp-section-title {
      font-size: 36px;
      font-weight: 800;
      letter-spacing: -0.5px;
      margin-bottom: 12px;
    }

    .hp-section-sub {
      color: #64748b;
      max-width: 580px;
      margin: 0 auto;
    }

    /* Tabs Layout */
    .hp-tabs {
      display: flex;
      justify-content: center;
      gap: 8px;
      margin-bottom: 40px;
      background: var(--light);
      padding: 6px;
      border-radius: 50px;
      width: max-content;
      margin-left: auto;
      margin-right: auto;
    }

    .hp-tab-btn {
      border: none;
      background: none;
      padding: 10px 24px;
      font-size: 13px;
      font-weight: 700;
      border-radius: 50px;
      color: #64748b;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .hp-tab-btn.active {
      background: #fff;
      color: var(--dark);
      box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
    }

    .hp-tab-content {
      display: none;
    }

    .hp-tab-content.active {
      display: block;
    }

    /* Grid layouts */
    .hp-plans-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 24px;
    }

    .hp-plan-card {
      background: #fff;
      border: 1.5px solid var(--border);
      border-radius: 20px;
      padding: 36px;
      position: relative;
      transition: all 0.3s ease;
      display: flex;
      flex-direction: column;
    }

    .hp-plan-card:hover {
      border-color: var(--primary);
      box-shadow: 0 16px 36px rgba(59, 130, 246, 0.05);
      transform: translateY(-4px);
    }

    .hp-plan-card.featured {
      border-color: var(--dark);
      background: var(--dark);
      color: #fff;
    }

    .hp-plan-card.featured h3,
    .hp-plan-card.featured .hp-plan-price,
    .hp-plan-card.featured .hp-plan-currency {
      color: #fff;
    }

    .hp-plan-badge {
      position: absolute;
      top: -12px;
      right: 24px;
      background: var(--accent);
      color: #fff;
      font-size: 10px;
      font-weight: 800;
      padding: 4px 12px;
      border-radius: 50px;
      text-transform: uppercase;
    }

    .hp-plan-name {
      font-size: 18px;
      font-weight: 700;
      margin-bottom: 12px;
    }

    .hp-plan-desc {
      font-size: 13px;
      color: #64748b;
      margin-bottom: 24px;
      min-height: 40px;
    }

    .hp-plan-card.featured .hp-plan-desc {
      color: #94a3b8;
    }

    .hp-plan-pricing {
      margin-bottom: 28px;
    }

    .hp-plan-price {
      font-size: 38px;
      font-weight: 800;
      color: var(--dark);
      letter-spacing: -1px;
    }

    .hp-plan-currency {
      font-size: 14px;
      font-weight: 600;
      color: #64748b;
    }

    .hp-plan-card.featured .hp-plan-currency {
      color: #94a3b8;
    }

    .hp-plan-features {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 12px;
      font-size: 13px;
      margin-bottom: 32px;
      flex: 1;
    }

    .hp-plan-feature-item {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .hp-plan-feature-check {
      color: var(--accent);
      font-weight: 700;
    }

    .hp-plan-card.featured .hp-plan-feature-check {
      color: #34d399;
    }

    .hp-plan-cta {
      width: 100%;
      text-align: center;
      padding: 12px 24px;
      font-size: 13px;
    }

    /* Benefits Section */
    .hp-benefits {
      background-color: var(--light);
    }

    .hp-benefits-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 24px;
    }

    .hp-benefit-card {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 32px 24px;
      text-align: center;
      transition: all 0.2s ease;
    }

    .hp-benefit-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
    }

    .hp-benefit-icon {
      font-size: 32px;
      margin-bottom: 16px;
    }

    .hp-benefit-title {
      font-size: 15px;
      font-weight: 700;
      margin-bottom: 8px;
    }

    .hp-benefit-desc {
      font-size: 12px;
      color: #64748b;
      line-height: 1.6;
    }

    /* Trust & Stats section */
    .hp-stats {
      background: linear-gradient(135deg, var(--dark), #1e293b);
      color: #fff;
      text-align: center;
      padding: 70px 24px;
    }

    .hp-stats-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      max-width: 1100px;
      margin: 0 auto;
      gap: 32px;
    }

    .hp-stat-val {
      font-size: 42px;
      font-weight: 900;
      margin-bottom: 4px;
      color: #fff;
      font-family: var(--font-heading);
    }

    .hp-stat-lbl {
      font-size: 13px;
      color: #94a3b8;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    /* Support Callout */
    .hp-support-box {
      background: linear-gradient(135deg, rgba(59, 130, 246, 0.06), rgba(16, 185, 129, 0.04));
      border: 1px solid var(--border);
      border-radius: 24px;
      padding: 60px 40px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 32px;
    }

    .hp-support-txt {
      max-width: 600px;
    }

    .hp-support-txt h2 {
      font-size: 28px;
      font-weight: 800;
      margin-bottom: 10px;
    }

    .hp-support-txt p {
      color: #64748b;
      font-size: 14px;
      margin: 0;
    }

    .hp-support-btns {
      display: flex;
      gap: 16px;
    }

    /* Footer */
    .hp-footer {
      background: #0f172a;
      color: #94a3b8;
      padding: 80px 24px 30px;
      border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .hp-footer-nav {
      max-width: 1280px;
      margin: 0 auto 50px;
      display: grid;
      grid-template-columns: 2fr 1fr 1fr 1.5fr;
      gap: 40px;
    }

    .hp-footer-logo-desc {
      font-size: 13px;
      margin-top: 16px;
      max-width: 320px;
      line-height: 1.7;
    }

    .hp-footer-logo {
      color: #fff;
    }

    .hp-footer-logo .hp-logo-icon {
      background: #fff;
      color: var(--dark);
    }

    .hp-footer-title {
      font-size: 14px;
      font-weight: 700;
      color: #fff;
      margin-bottom: 20px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .hp-footer-list {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 12px;
      font-size: 13px;
    }

    .hp-footer-link {
      color: #94a3b8;
      text-decoration: none;
      transition: color 0.2s ease;
    }

    .hp-footer-link:hover {
      color: #fff;
    }

    .hp-footer-bottom {
      max-width: 1280px;
      margin: 0 auto;
      padding-top: 30px;
      border-top: 1px solid rgba(255, 255, 255, 0.06);
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 12px;
    }

    /* Mobile Sidebar Panel */
    .hp-sidebar-panel {
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.6);
      backdrop-filter: blur(8px);
      z-index: 2000;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.3s ease;
    }

    .hp-sidebar-panel.open {
      opacity: 1;
      pointer-events: auto;
    }

    .hp-sidebar-body {
      position: absolute;
      top: 0;
      right: 0;
      bottom: 0;
      width: 280px;
      background: #fff;
      padding: 30px 24px;
      display: flex;
      flex-direction: column;
      gap: 30px;
      transform: translateX(100%);
      transition: transform 0.3s ease;
    }

    .hp-sidebar-panel.open .hp-sidebar-body {
      transform: translateX(0);
    }

    .hp-sidebar-close {
      align-self: flex-end;
      background: none;
      border: none;
      font-size: 20px;
      color: var(--dark);
      cursor: pointer;
    }

    .hp-sidebar-menu {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .hp-sidebar-link {
      font-family: var(--font-heading);
      font-size: 16px;
      font-weight: 700;
      color: var(--dark);
      text-decoration: none;
    }

    /* Mobile responsive queries */
    @media (max-width: 1024px) {
      .hp-plans-grid {
        grid-template-columns: repeat(2, 1fr);
      }
      .hp-benefits-grid {
        grid-template-columns: repeat(2, 1fr);
      }
      .hp-footer-nav {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    @media (max-width: 768px) {
      .hp-menu, .hp-nav-btns {
        display: none;
      }
      .hp-menu-toggle {
        display: block;
      }
      .hp-hero-title {
        font-size: 38px;
        letter-spacing: -0.5px;
      }
      .hp-hero-sub {
        font-size: 14px;
      }
      .hp-domain-form {
        flex-direction: column;
      }
      .hp-domain-btn {
        width: 100%;
      }
      .hp-plans-grid {
        grid-template-columns: 1fr;
      }
      .hp-benefits-grid {
        grid-template-columns: 1fr;
      }
      .hp-stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }
      .hp-support-box {
        flex-direction: column;
        text-align: center;
        padding: 40px 24px;
      }
      .hp-support-btns {
        width: 100%;
        flex-direction: column;
      }
      .hp-support-btns .hp-btn {
        width: 100%;
      }
      .hp-footer-nav {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>

  <!-- Premium Sticky Header -->
  <header class="hp-header">
    <div class="hp-nav">
      <a href="<?= BASE_URL ?>/" class="hp-logo">
        <div class="hp-logo-icon">⚡</div>
        <div><?= h($company) ?></div>
      </a>
      <ul class="hp-menu">
        <li><a href="#hero" class="hp-menu-link">Home</a></li>
        <li><a href="#hosting" class="hp-menu-link">Hosting Plans</a></li>
        <li><a href="#features" class="hp-menu-link">Features</a></li>
        <li><a href="#support" class="hp-menu-link">Support</a></li>
      </ul>
      <div class="hp-nav-btns">
        <a href="<?= BASE_URL ?>/client/login.php" class="hp-btn hp-btn-outline">Sign In</a>
        <a href="<?= BASE_URL ?>/client/register.php" class="hp-btn hp-btn-accent">Client Portal</a>
      </div>
      <button class="hp-menu-toggle" onclick="toggleMenu(true)">☰</button>
    </div>
  </header>

  <!-- Mobile Sidebar Menu -->
  <div class="hp-sidebar-panel" id="mobile-sidebar">
    <div class="hp-sidebar-body">
      <button class="hp-sidebar-close" onclick="toggleMenu(false)">✕</button>
      <ul class="hp-sidebar-menu">
        <li><a href="#hero" class="hp-sidebar-link" onclick="toggleMenu(false)">Home</a></li>
        <li><a href="#hosting" class="hp-sidebar-link" onclick="toggleMenu(false)">Hosting Plans</a></li>
        <li><a href="#features" class="hp-sidebar-link" onclick="toggleMenu(false)">Features</a></li>
        <li><a href="#support" class="hp-sidebar-link" onclick="toggleMenu(false)">Support</a></li>
        <hr style="border-color:#f1f5f9;margin:10px 0">
        <li><a href="<?= BASE_URL ?>/client/login.php" class="hp-sidebar-link">Sign In</a></li>
        <li><a href="<?= BASE_URL ?>/client/register.php" class="hp-sidebar-link">Client Register</a></li>
      </ul>
    </div>
  </div>

  <!-- Hero Banner (Replaceable Dynamic Settings Backdrop) -->
  <section class="hp-hero" id="hero">
    <div class="container">
      <div class="hp-hero-badge"><?= h($hero_badge) ?></div>
      <h1 class="hp-hero-title"><?= h($hero_title) ?></h1>
      <p class="hp-hero-sub"><?= h($hero_sub) ?></p>

      <!-- Domain Search Form -->
      <div class="hp-domain-box">
        <form class="hp-domain-form" action="<?= BASE_URL ?>/client/order.php" method="GET">
          <input type="hidden" name="type" value="domain">
          <input type="text" name="domain" class="hp-domain-input" placeholder="<?= h($domain_placeholder) ?>" required>
          <button type="submit" class="hp-domain-btn"><?= h($domain_btn_text) ?></button>
        </form>
      </div>

      <!-- Quick TLD list -->
      <div class="hp-tlds">
        <?php
        $active_tlds = DB::rows("SELECT * FROM domain_tlds WHERE status='active' ORDER BY retail_price_register ASC LIMIT 4");
        if (!empty($active_tlds)):
          require_once INC_PATH . '/modules/reseller.php';
          $reseller_id = !empty($_SESSION['reseller_domain_id']) ? (int)$_SESSION['reseller_domain_id'] : null;
          foreach ($active_tlds as $t):
            $d_pricing = Reseller::getDomainPricing('domain.' . $t['tld'], $reseller_id);
        ?>
          <span class="hp-tld-badge">.<?= h($t['tld']) ?> <strong><?= format_currency($d_pricing['register'], $currency) ?></strong></span>
        <?php endforeach; else: ?>
          <span class="hp-tld-badge">.com <strong><?= format_currency(15000, $currency) ?></strong></span>
          <span class="hp-tld-badge">.net <strong><?= format_currency(18000, $currency) ?></strong></span>
          <span class="hp-tld-badge">.org <strong><?= format_currency(20000, $currency) ?></strong></span>
          <span class="hp-tld-badge">.xyz <strong><?= format_currency(4500, $currency) ?></strong></span>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- Stats Banner -->
  <section class="hp-stats">
    <div class="container">
      <div class="hp-stats-grid">
        <div><div class="hp-stat-val"><?= h($stat1_val) ?></div><div class="hp-stat-lbl"><?= h($stat1_lbl) ?></div></div>
        <div><div class="hp-stat-val"><?= h($stat2_val) ?></div><div class="hp-stat-lbl"><?= h($stat2_lbl) ?></div></div>
        <div><div class="hp-stat-val"><?= h($stat3_val) ?></div><div class="hp-stat-lbl"><?= h($stat3_lbl) ?></div></div>
        <div><div class="hp-stat-val"><?= h($stat4_val) ?></div><div class="hp-stat-lbl"><?= h($stat4_lbl) ?></div></div>
      </div>
    </div>
  </section>

  <!-- Product Hosting Section -->
  <section class="hp-section" id="hosting">
    <div class="hp-section-header">
      <h2 class="hp-section-title">Flexible Hosting Plans for Everyone</h2>
      <p class="hp-section-sub">Choose a perfect hosting solution designed to get your brand online with ease.</p>
    </div>

    <?php if (!empty($groups)): ?>
      <!-- Dynamic Tabs -->
      <div class="hp-tabs">
        <?php foreach ($groups as $idx => $g): ?>
          <button class="hp-tab-btn <?= $idx === 0 ? 'active' : '' ?>" onclick="switchTab('tab-g-<?= $g['id'] ?>', this)">
            <?= h($g['name']) ?>
          </button>
        <?php endforeach; ?>
      </div>

      <!-- Tab Contents -->
      <?php foreach ($groups as $idx => $g):
        $plans = $grouped_products[$g['id']] ?? [];
      ?>
        <div class="hp-tab-content <?= $idx === 0 ? 'active' : '' ?>" id="tab-g-<?= $g['id'] ?>">
          <?php if (!empty($plans)): ?>
            <div class="hp-plans-grid">
              <?php foreach ($plans as $pIdx => $p):
                $isFeatured = $pIdx === 1;
              ?>
                <div class="hp-plan-card <?= $isFeatured ? 'featured' : '' ?>">
                  <?php if ($isFeatured): ?><div class="hp-plan-badge">Popular</div><?php endif; ?>
                  <h3 class="hp-plan-name"><?= h($p['name']) ?></h3>
                  <div class="hp-plan-desc"><?= h($p['description']) ?></div>
                  <div class="hp-plan-pricing">
                    <span class="hp-plan-price"><?= format_currency($p['price_monthly'] ?: $p['price_annually'], $currency) ?></span>
                    <span class="hp-plan-currency">/<?= $p['price_monthly'] ? 'mo' : 'yr' ?></span>
                  </div>
                  <ul class="hp-plan-features">
                    <li class="hp-plan-feature-item"><span class="hp-plan-feature-check">✓</span> High Performance CPU</li>
                    <li class="hp-plan-feature-item"><span class="hp-plan-feature-check">✓</span> Free SSL Certificate</li>
                    <li class="hp-plan-feature-item"><span class="hp-plan-feature-check">✓</span> Unlimited Bandwidth</li>
                    <li class="hp-plan-feature-item"><span class="hp-plan-feature-check">✓</span> 1-Click App Installer</li>
                  </ul>
                  <a href="<?= BASE_URL ?>/client/order.php?product=<?= $p['slug'] ?>" class="hp-btn <?= $isFeatured ? 'hp-btn-accent' : 'hp-btn-primary' ?> hp-plan-cta">
                    Order Plan Now &rarr;
                  </a>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="text-center py-5 text-muted">No plans added to this hosting group yet.</div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

    <?php else: ?>
      <!-- Dynamic Promo cards matching CMS configurations & Dynamic Order Button Link packages -->
      <div class="hp-plans-grid">
        <!-- Promo Card 1 -->
        <div class="hp-plan-card">
          <h3 class="hp-plan-name"><?= h($plan1_title) ?></h3>
          <div class="hp-plan-desc"><?= h($plan1_desc) ?></div>
          <div class="hp-plan-pricing">
            <span class="hp-plan-price"><?= is_numeric($plan1_price) ? format_currency($plan1_price, $currency) : h($plan1_price) ?></span>
            <span class="hp-plan-currency"><?= h($plan1_cycle) ?></span>
          </div>
          <ul class="hp-plan-features">
            <?php foreach ($plan1_features as $feat): ?>
            <li class="hp-plan-feature-item"><span class="hp-plan-feature-check">✓</span> <?= h($feat) ?></li>
            <?php endforeach; ?>
          </ul>
          <a href="<?= $plan1_link ?>" class="hp-btn hp-btn-primary hp-plan-cta">Get Started &rarr;</a>
        </div>

        <!-- Promo Card 2 (Featured) -->
        <div class="hp-plan-card featured">
          <div class="hp-plan-badge">Popular</div>
          <h3 class="hp-plan-name"><?= h($plan2_title) ?></h3>
          <div class="hp-plan-desc"><?= h($plan2_desc) ?></div>
          <div class="hp-plan-pricing">
            <span class="hp-plan-price"><?= is_numeric($plan2_price) ? format_currency($plan2_price, $currency) : h($plan2_price) ?></span>
            <span class="hp-plan-currency"><?= h($plan2_cycle) ?></span>
          </div>
          <ul class="hp-plan-features">
            <?php foreach ($plan2_features as $feat): ?>
            <li class="hp-plan-feature-item"><span class="hp-plan-feature-check">✓</span> <?= h($feat) ?></li>
            <?php endforeach; ?>
          </ul>
          <a href="<?= $plan2_link ?>" class="hp-btn hp-btn-accent hp-plan-cta">Choose Pro &rarr;</a>
        </div>

        <!-- Promo Card 3 -->
        <div class="hp-plan-card">
          <h3 class="hp-plan-name"><?= h($plan3_title) ?></h3>
          <div class="hp-plan-desc"><?= h($plan3_desc) ?></div>
          <div class="hp-plan-pricing">
            <span class="hp-plan-price"><?= is_numeric($plan3_price) ? format_currency($plan3_price, $currency) : h($plan3_price) ?></span>
            <span class="hp-plan-currency"><?= h($plan3_cycle) ?></span>
          </div>
          <ul class="hp-plan-features">
            <?php foreach ($plan3_features as $feat): ?>
            <li class="hp-plan-feature-item"><span class="hp-plan-feature-check">✓</span> <?= h($feat) ?></li>
            <?php endforeach; ?>
          </ul>
          <a href="<?= $plan3_link ?>" class="hp-btn hp-btn-primary hp-plan-cta">Get Enterprise &rarr;</a>
        </div>
      </div>
    <?php endif; ?>
  </section>

  <!-- Platform Features Section -->
  <section class="hp-section hp-benefits" id="features">
    <div class="hp-section-header">
      <h2 class="hp-section-title">Built-In Enterprise Features</h2>
      <p class="hp-section-sub">We offer everything you need to run, configure, and secure your online projects.</p>
    </div>
    <div class="hp-benefits-grid">
      <!-- Benefit 1 -->
      <div class="hp-benefit-card">
        <div class="hp-benefit-icon"><?= h($feat1_icon) ?></div>
        <h3 class="hp-benefit-title"><?= h($feat1_title) ?></h3>
        <p class="hp-benefit-desc"><?= h($feat1_desc) ?></p>
      </div>
      <!-- Benefit 2 -->
      <div class="hp-benefit-card">
        <div class="hp-benefit-icon"><?= h($feat2_icon) ?></div>
        <h3 class="hp-benefit-title"><?= h($feat2_title) ?></h3>
        <p class="hp-benefit-desc"><?= h($feat2_desc) ?></p>
      </div>
      <!-- Benefit 3 -->
      <div class="hp-benefit-card">
        <div class="hp-benefit-icon"><?= h($feat3_icon) ?></div>
        <h3 class="hp-benefit-title"><?= h($feat3_title) ?></h3>
        <p class="hp-benefit-desc"><?= h($feat3_desc) ?></p>
      </div>
      <!-- Benefit 4 -->
      <div class="hp-benefit-card">
        <div class="hp-benefit-icon"><?= h($feat4_icon) ?></div>
        <h3 class="hp-benefit-title"><?= h($feat4_title) ?></h3>
        <p class="hp-benefit-desc"><?= h($feat4_desc) ?></p>
      </div>
    </div>
  </section>

  <!-- Support CTA Section -->
  <section class="hp-section" id="support">
    <div class="hp-support-box">
      <div class="hp-support-txt">
        <h2><?= h($support_title) ?></h2>
        <p><= h($support_desc) ?></p>
      </div>
      <div class="hp-support-btns">
        <a href="<?= BASE_URL ?><?= h($support_btn1_link) ?>" class="hp-btn hp-btn-outline"><?= h($support_btn1_text) ?></a>
        <a href="<?= BASE_URL ?><?= h($support_btn2_link) ?>" class="hp-btn hp-btn-primary"><?= h($support_btn2_text) ?></a>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="hp-footer">
    <div class="hp-footer-nav">
      <div>
        <a href="<?= BASE_URL ?>/" class="hp-logo hp-footer-logo">
          <div class="hp-logo-icon">⚡</div>
          <div><?= h($company) ?></div>
        </a>
        <div class="hp-footer-logo-desc">Providing high performance web hosting services, VPS containers, dedicated parameters, and automated reseller modules.</div>
      </div>
      <div>
        <h4 class="hp-footer-title">Hosting</h4>
        <ul class="hp-footer-list">
          <li><a href="#hosting" class="hp-footer-link">Cloud Hosting</a></li>
          <li><a href="#hosting" class="hp-footer-link">VPS Servers</a></li>
          <li><a href="#hosting" class="hp-footer-link">Dedicated Servers</a></li>
        </ul>
      </div>
      <div>
        <h4 class="hp-footer-title">Support</h4>
        <ul class="hp-footer-list">
          <li><a href="<?= BASE_URL ?>/client/login.php" class="hp-footer-link">Support Desk</a></li>
          <li><a href="<?= BASE_URL ?>/client/tickets.php" class="hp-footer-link">Open Ticket</a></li>
          <li><a href="<?= BASE_URL ?>/client/login.php" class="hp-footer-link">Client Area</a></li>
        </ul>
      </div>
      <div>
        <h4 class="hp-footer-title">Contact</h4>
        <ul class="hp-footer-list" style="color: #64748b; font-size: 13px;">
          <?php if (!empty($company_address)): ?>
            <li>📍 <?= h($company_address) ?></li>
          <?php endif; ?>
          <li>✉ <?= h($company_email) ?></li>
          <?php if (!empty($company_phone)): ?>
            <li>📞 <?= h($company_phone) ?></li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
    <div class="hp-footer-bottom">
      <div>&copy; <?= date('Y') ?> <?= h($company) ?>. All rights reserved.</div>
      <div>Powered by Billing Portal</div>
    </div>
  </footer>

  <script>
    function toggleMenu(open) {
      const sb = document.getElementById('mobile-sidebar');
      if (open) {
        sb.classList.add('open');
      } else {
        sb.classList.remove('open');
      }
    }

    function switchTab(tabId, btn) {
      // Hide all tabs
      document.querySelectorAll('.hp-tab-content').forEach(el => el.classList.remove('active'));
      document.querySelectorAll('.hp-tab-btn').forEach(el => el.classList.remove('active'));

      // Show active tab
      document.getElementById(tabId).classList.add('active');
      btn.classList.add('active');
    }
  </script>
</body>
</html>