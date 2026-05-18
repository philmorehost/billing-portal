<?php
if (!empty($_SESSION['reseller_host_brand'])) {
    $company = $_SESSION['reseller_host_brand']['name'];
} else {
    $company  = $company  ?? DB::setting('company_name','Billing Portal');
}
$currency = $currency ?? DB::setting('base_currency','NGN');
$page_title=$page_title??'Client Area';
?><!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?=h($page_title)?> — <?=h($company)?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?=BASE_URL?>/assets/css/style.css" rel="stylesheet">
<?php if (!empty($_SESSION['reseller_host_brand'])): ?>
<style>
:root {
  --bp-primary: <?= h($_SESSION['reseller_host_brand']['color']) ?>;
  --bp-accent: <?= h($_SESSION['reseller_host_brand']['color']) ?>;
}
</style>
<?php endif; ?>
</head><body>
<div class="bp-layout">
<aside class="bp-sidebar" id="sidebar"><div class="sidebar">
<a href="<?=BASE_URL?>/client/" class="sidebar-logo">
  <div class="sidebar-logo-icon">⚡</div>
  <div><div class="sidebar-logo-text"><?=h($company)?></div><div class="sidebar-logo-badge">Client Portal</div></div>
</a>
<nav class="sidebar-nav">
  <div class="nav-section"><div class="nav-section-label">Overview</div>
    <div class="nav-item"><a href="<?=BASE_URL?>/client/"><span class="nav-icon">🏠</span> Dashboard</a></div>
  </div>
  <div class="nav-section"><div class="nav-section-label">Services</div>
    <div class="nav-item"><a href="<?=BASE_URL?>/client/services.php"><span class="nav-icon">🖥</span> My Services</a></div>
    <div class="nav-item"><a href="<?=BASE_URL?>/client/domains.php"><span class="nav-icon">🌐</span> My Domains</a></div>
    <div class="nav-item"><a href="<?=BASE_URL?>/client/order.php"><span class="nav-icon">🛒</span> Order New</a></div>
  </div>
  <div class="nav-section"><div class="nav-section-label">Billing</div>
    <div class="nav-item"><a href="<?=BASE_URL?>/client/invoices.php"><span class="nav-icon">🧾</span> Invoices</a></div>
    <div class="nav-item"><a href="<?=BASE_URL?>/client/add-funds.php"><span class="nav-icon">💳</span> Add Funds</a></div>
  </div>
  <div class="nav-section"><div class="nav-section-label">Support</div>
    <div class="nav-item"><a href="<?=BASE_URL?>/client/tickets.php"><span class="nav-icon">🎫</span> My Tickets</a></div>
    <div class="nav-item"><a href="<?=BASE_URL?>/client/tickets/open.php"><span class="nav-icon">➕</span> Open Ticket</a></div>
  </div>
  <div class="nav-section"><div class="nav-section-label">Account</div>
    <div class="nav-item"><a href="<?=BASE_URL?>/client/profile.php"><span class="nav-icon">👤</span> My Profile</a></div>
    <div class="nav-item"><a href="<?=BASE_URL?>/client/security.php"><span class="nav-icon">🔐</span> Security (2FA)</a></div>
    <div class="nav-item"><a href="<?=BASE_URL?>/client/affiliate.php"><span class="nav-icon">🤝</span> Affiliates</a></div>
    <div class="nav-item"><a href="<?=BASE_URL?>/reseller/"><span class="nav-icon">🏪</span> Reseller Portal</a></div>
    <div class="nav-item"><a href="<?=BASE_URL?>/client/reseller-apply.php"><span class="nav-icon">➕</span> Become Reseller</a></div>
  </div>
</nav>
<div class="sidebar-footer">
  <?php if (!empty($client)): ?>
  <div class="sidebar-user">
    <div class="sidebar-avatar"><?=strtoupper(substr($client['first_name']??'U',0,1))?></div>
    <div><div class="sidebar-uname"><?=h(($client['first_name']??'').' '.($client['last_name']??''))?></div>
    <div class="sidebar-urole"><?=format_currency($client['credit_balance']??0,$currency)?> credit</div></div>
  </div>
  <a href="<?=BASE_URL?>/client/logout.php" style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;color:rgba(255,255,255,.5);font-size:12px;text-decoration:none;margin-top:4px"><span>⏏</span> Logout</a>
  <?php else: ?>
  <div class="sidebar-user">
    <div class="sidebar-avatar">G</div>
    <div><div class="sidebar-uname">Guest Customer</div>
    <div class="sidebar-urole">Welcome to our portal</div></div>
  </div>
  <a href="<?=BASE_URL?>/client/login.php" style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;color:rgba(255,255,255,.8);font-size:12px;text-decoration:none;margin-top:4px;background:rgba(255,255,255,0.06);font-weight:600"><span>🔑</span> Log In / Register</a>
  <?php endif; ?>
</div>
</div></aside>
<main class="bp-main">
<div class="bp-topbar"><div class="topbar">
  <button class="topbar-btn d-lg-none" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
  <div style="font-size:15px;font-weight:700;color:#0f172a"><?=h($page_title)?></div>
  <div class="topbar-right">
    <a href="<?=BASE_URL?>/client/add-funds.php" class="topbar-btn" title="Add Funds">💳</a>
    <a href="<?=BASE_URL?>/client/tickets.php" class="topbar-btn" title="Support">🎫</a>
    <a href="<?=BASE_URL?>/client/profile.php" class="topbar-btn" title="Profile">👤</a>
  </div>
</div></div>
