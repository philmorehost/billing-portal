<?php
$company    = $company    ?? DB::setting('company_name','Billing Portal');
$page_title = $page_title ?? 'Dashboard';
?><!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?=h($page_title)?> — <?=h($company)?> Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?=BASE_URL?>/assets/css/style.css" rel="stylesheet">
</head><body>
<div class="bp-layout">
<aside class="bp-sidebar" id="sidebar"><div class="sidebar">
<a href="<?=BASE_URL?>/admin/" class="sidebar-logo">
  <div class="sidebar-logo-icon">⚡</div>
  <div><div class="sidebar-logo-text"><?=h($company)?></div><div class="sidebar-logo-badge">Admin Panel</div></div>
</a>
<nav class="sidebar-nav">
  <div class="nav-section"><div class="nav-section-label">Overview</div>
    <div class="nav-item"><a href="<?=BASE_URL?>/admin/" class="<?=active_nav('/admin/index')?>"><span class="nav-icon">🏠</span> Dashboard</a></div>
  </div>
  <div class="nav-section"><div class="nav-section-label">Clients</div>
    <div class="nav-item"><a href="<?=BASE_URL?>/admin/clients.php"><span class="nav-icon">👥</span> All Clients</a></div>
    <div class="nav-item"><a href="<?=BASE_URL?>/admin/clients/add.php"><span class="nav-icon">➕</span> Add Client</a></div>
    <div class="nav-item"><a href="<?=BASE_URL?>/admin/resellers.php"><span class="nav-icon">🏪</span> Resellers</a></div>
    <div class="nav-item"><a href="<?=BASE_URL?>/admin/price-adjust.php"><span class="nav-icon">💹</span> Price Adjust</a></div>
  </div>
  <div class="nav-section"><div class="nav-section-label">Billing</div>
    <div class="nav-item"><a href="<?=BASE_URL?>/admin/invoices.php"><span class="nav-icon">🧾</span> Invoices</a></div>
    <div class="nav-item"><a href="<?=BASE_URL?>/admin/transactions.php"><span class="nav-icon">💳</span> Transactions</a></div>
    <div class="nav-item"><a href="<?=BASE_URL?>/admin/approvals.php"><span class="nav-icon">⏳</span> Approvals
      <?php $pa=DB::value("SELECT COUNT(*) FROM transactions WHERE status='pending' AND gateway IN ('bank_transfer','crypto')"); if($pa>0) echo '<span class="nav-badge">'.$pa.'</span>'; ?></a></div>
  </div>
  <div class="nav-section"><div class="nav-section-label">Services</div>
    <div class="nav-item"><a href="<?=BASE_URL?>/admin/services.php"><span class="nav-icon">🖥</span> Services</a></div>
    <div class="nav-item"><a href="<?=BASE_URL?>/admin/products.php"><span class="nav-icon">📦</span> Products</a></div>
    <div class="nav-item"><a href="<?=BASE_URL?>/admin/domain-pricing.php"><span class="nav-icon">🌐</span> Domain Pricing</a></div>
    <div class="nav-item"><a href="<?=BASE_URL?>/admin/orders.php"><span class="nav-icon">🛒</span> Orders</a></div>
  </div>
  <div class="nav-section"><div class="nav-section-label">Support</div>
    <div class="nav-item"><a href="<?=BASE_URL?>/admin/tickets.php"><span class="nav-icon">🎫</span> Tickets
      <?php $ot=DB::value("SELECT COUNT(*) FROM tickets WHERE status IN ('open','client_reply')"); if($ot>0) echo '<span class="nav-badge">'.$ot.'</span>'; ?></a></div>
  </div>
  <div class="nav-section"><div class="nav-section-label">Marketing</div>
    <div class="nav-item"><a href="<?=BASE_URL?>/admin/coupons.php"><span class="nav-icon">🏷</span> Coupons</a></div>
    <div class="nav-item"><a href="<?=BASE_URL?>/admin/affiliates.php"><span class="nav-icon">🤝</span> Affiliates</a></div>
    <div class="nav-item"><a href="<?=BASE_URL?>/admin/email-templates.php"><span class="nav-icon">✉</span> Email Templates</a></div>
    <div class="nav-item"><a href="<?=BASE_URL?>/admin/campaigns.php"><span class="nav-icon">📧</span> Email Campaigns</a></div>
  </div>
  <div class="nav-section"><div class="nav-section-label">Reports</div>
    <div class="nav-item"><a href="<?=BASE_URL?>/admin/reports/revenue.php"><span class="nav-icon">📊</span> Revenue</a></div>
    <div class="nav-item"><a href="<?=BASE_URL?>/admin/reports/tax.php"><span class="nav-icon">📋</span> Tax / VAT</a></div>
  </div>
  <div class="nav-section"><div class="nav-section-label">System</div>
    <div class="nav-item"><a href="<?=BASE_URL?>/admin/staff.php"><span class="nav-icon">👤</span> Staff & Roles</a></div>
    <div class="nav-item"><a href="<?=BASE_URL?>/admin/servers.php"><span class="nav-icon">🖥</span> Servers</a></div>
    <div class="nav-item"><a href="<?=BASE_URL?>/admin/cron.php"><span class="nav-icon">⏰</span> Cron Jobs</a></div>
    <div class="nav-item"><a href="<?=BASE_URL?>/admin/activity.php"><span class="nav-icon">📜</span> Activity Log</a></div>
    <div class="nav-item"><a href="<?=BASE_URL?>/admin/settings.php"><span class="nav-icon">⚙</span> Settings</a></div>
  </div>
</nav>
<div class="sidebar-footer">
  <a href="<?=BASE_URL?>/admin/profile.php" class="sidebar-user" style="text-decoration:none">
    <div class="sidebar-avatar"><?=strtoupper(substr($admin['name']??'A',0,1))?></div>
    <div><div class="sidebar-uname"><?=h($admin['name']??'Admin')?></div><div class="sidebar-urole"><?=h($admin['email']??'')?></div></div>
  </a>
  <a href="<?=BASE_URL?>/admin/logout.php" style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;color:rgba(255,255,255,.5);font-size:12px;text-decoration:none;margin-top:4px"><span>⏏</span> Logout</a>
</div>
</div></aside>
<main class="bp-main">
<div class="bp-topbar"><div class="topbar">
  <button class="topbar-btn d-lg-none" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
  <div class="topbar-search"><span class="topbar-search-icon">🔍</span><input type="text" placeholder="Search clients, invoices…" id="gsearch"></div>
  <div class="topbar-right">
    <a href="<?=BASE_URL?>/admin/tickets.php" class="topbar-btn" title="Tickets">🎫</a>
    <a href="<?=BASE_URL?>/admin/approvals.php" class="topbar-btn" title="Approvals">⏳</a>
    <a href="<?=BASE_URL?>/admin/settings.php" class="topbar-btn" title="Settings">⚙</a>
  </div>
</div></div>
