<?php
require_once INC_PATH.'/modules/reseller.php';
$reseller_id=$_SESSION['reseller_id']??0;
$reseller=$reseller_id?DB::row("SELECT * FROM resellers WHERE id=?",'i',[$reseller_id]):null;
if(!$reseller){redirect(BASE_URL.'/reseller/login.php');}
$reseller_client=DB::row("SELECT * FROM clients WHERE id=?",'i',[$reseller['client_id']]);
$branding=Reseller::getBranding($reseller_id);
$currency=DB::setting('base_currency','NGN');
$page_title=$page_title??'Reseller Portal';
$pc=h($branding['color']);
?><!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?=h($page_title)?> — <?=h($branding['name'])?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?=BASE_URL?>/assets/css/style.css" rel="stylesheet">
<style>:root{--bp-primary:<?=$pc?>;--bp-primary-2:<?=$pc?>}.sidebar{background:<?=$pc?>}</style>
</head><body>
<div class="bp-layout">
<aside class="bp-sidebar" id="sidebar"><div class="sidebar">
  <a href="<?=BASE_URL?>/reseller/" class="sidebar-logo">
    <div class="sidebar-logo-icon"><?=$branding['logo']?'<img src="'.h($branding['logo']).'" style="width:26px;height:26px;object-fit:contain">':'🏪'?></div>
    <div><div class="sidebar-logo-text"><?=h($branding['name'])?></div><div class="sidebar-logo-badge">Reseller Portal</div></div>
  </a>
  <nav class="sidebar-nav">
    <div class="nav-section"><div class="nav-section-label">Overview</div>
      <div class="nav-item"><a href="<?=BASE_URL?>/reseller/"><span class="nav-icon">🏠</span> Dashboard</a></div>
    </div>
    <div class="nav-section"><div class="nav-section-label">Clients</div>
      <div class="nav-item"><a href="<?=BASE_URL?>/reseller/clients.php"><span class="nav-icon">👥</span> My Clients</a></div>
      <div class="nav-item"><a href="<?=BASE_URL?>/reseller/clients/add.php"><span class="nav-icon">➕</span> Add Client</a></div>
    </div>
    <div class="nav-section"><div class="nav-section-label">Billing</div>
      <div class="nav-item"><a href="<?=BASE_URL?>/reseller/invoices.php"><span class="nav-icon">🧾</span> Invoices</a></div>
      <div class="nav-item"><a href="<?=BASE_URL?>/reseller/transactions.php"><span class="nav-icon">💳</span> Transactions</a></div>
    </div>
    <div class="nav-section"><div class="nav-section-label">Services</div>
      <div class="nav-item"><a href="<?=BASE_URL?>/reseller/services.php"><span class="nav-icon">🖥</span> Services</a></div>
      <div class="nav-item"><a href="<?=BASE_URL?>/reseller/products.php"><span class="nav-icon">📦</span> Product Pricing</a></div>
      <div class="nav-item"><a href="<?=BASE_URL?>/reseller/domain-pricing.php"><span class="nav-icon">🌐</span> Domain Markup</a></div>
    </div>
    <div class="nav-section"><div class="nav-section-label">Support</div>
      <div class="nav-item"><a href="<?=BASE_URL?>/reseller/tickets.php"><span class="nav-icon">🎫</span> Tickets</a></div>
    </div>
    <div class="nav-section"><div class="nav-section-label">Account</div>
      <div class="nav-item"><a href="<?=BASE_URL?>/reseller/settings.php"><span class="nav-icon">⚙</span> Branding & Domain</a></div>
      <div class="nav-item"><a href="<?=BASE_URL?>/reseller/topup.php"><span class="nav-icon">💰</span> Top Up Balance</a></div>
    </div>
  </nav>
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="sidebar-avatar"><?=strtoupper(substr($reseller_client['first_name']??'R',0,1))?></div>
      <div><div class="sidebar-uname"><?=h(($reseller_client['first_name']??'').' '.($reseller_client['last_name']??''))?></div>
      <div class="sidebar-urole"><?=format_currency($reseller['balance'],$currency)?> balance</div></div>
    </div>
    <a href="<?=BASE_URL?>/reseller/logout.php" style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;color:rgba(255,255,255,.5);font-size:12px;text-decoration:none;margin-top:4px"><span>⏏</span> Logout</a>
  </div>
</div></aside>
<main class="bp-main">
<div class="bp-topbar"><div class="topbar">
  <button class="topbar-btn d-lg-none" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
  <div style="font-size:15px;font-weight:700;color:#0f172a"><?=h($page_title)?></div>
  <div class="topbar-right">
    <div style="background:<?=$pc?>;color:#fff;padding:6px 14px;border-radius:8px;font-size:13px;font-weight:700">💰 <?=format_currency($reseller['balance'],$currency)?></div>
    <a href="<?=BASE_URL?>/reseller/topup.php" class="topbar-btn" title="Top Up" style="font-weight:700;font-size:18px">+</a>
    <a href="<?=BASE_URL?>/reseller/settings.php" class="topbar-btn">⚙</a>
  </div>
</div></div>
