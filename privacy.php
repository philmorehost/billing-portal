<?php
require_once __DIR__.'/includes/config.php';
$company=DB::setting('company_name','Billing Portal');
$content=DB::setting('privacy_content','<h2>Privacy Policy</h2><p>Please check back soon.</p>');
?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Privacy Policy — <?=h($company)?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?=BASE_URL?>/assets/css/style.css" rel="stylesheet">
<style>body{background:#f1f5f9;padding:32px 16px}.pw{max-width:800px;margin:0 auto}.ph{background:linear-gradient(135deg,#0f172a,#1e3a5f);border-radius:16px 16px 0 0;padding:32px 40px;color:#fff}.ph h1{margin:0;font-size:26px;font-weight:800}.pb{background:#fff;border-radius:0 0 16px 16px;padding:40px;box-shadow:0 4px 32px rgba(0,0,0,.08)}.pb p{color:#374151;line-height:1.8;font-size:15px}</style>
</head><body><div class="pw">
<div class="ph"><h1>Privacy Policy</h1><p><?=h($company)?></p></div>
<div class="pb"><?=$content?><div style="margin-top:32px;padding-top:20px;border-top:1px solid #f1f5f9;font-size:13px;color:#94a3b8">Last updated: <?=date('d F Y')?></div></div>
<div style="text-align:center;margin-top:20px;font-size:13px"><a href="<?=BASE_URL?>/client/" style="color:#3b82f6;text-decoration:none">← Back to Client Area</a></div>
</div></body></html>
