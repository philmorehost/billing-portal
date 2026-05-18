<?php
require_once '../includes/config.php';
$client=Auth::requireClient(); $company=DB::setting('company_name','Billing Portal');
$currency=DB::setting('base_currency','NGN'); $page_title='Affiliate Program';

// Auto-enroll if not yet an affiliate
$affiliate=DB::row("SELECT * FROM affiliates WHERE client_id=?",'i',[$client['id']]);

if(is_post()&&csrf_verify()&&post('action')==='join'){
    if(!$affiliate){
        $code=strtoupper(substr(md5($client['id'].time()),0,8));
        while(DB::value("SELECT id FROM affiliates WHERE referral_code=?",'s',[$code])) $code=strtoupper(substr(md5(rand()),0,8));
        DB::execute("INSERT INTO affiliates (client_id,referral_code,commission_type,commission_value,status) VALUES (?,?,'percentage',10,'active')",'is',[$client['id'],$code]);
        $affiliate=DB::row("SELECT * FROM affiliates WHERE client_id=?",'i',[$client['id']]);
        redirect_with_flash('affiliate.php','success','You have joined the affiliate program!');
    }
}

$ref_url=$affiliate?BASE_URL.'/client/register.php?ref='.urlencode($affiliate['referral_code']):'';
$referrals=$affiliate?DB::rows("SELECT ar.*,c.first_name,c.last_name,c.email FROM affiliate_referrals ar JOIN clients c ON c.id=ar.referred_client_id WHERE ar.affiliate_id=? ORDER BY ar.id DESC LIMIT 20",'i',[$affiliate['id']]):[];
include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title">Affiliate Program</h1>
<?=flash_html()?>

<?php if(!$affiliate): ?>
<!-- Join CTA -->
<div class="bp-card" style="border:2px solid #3b82f6">
  <div class="bp-card-body" style="text-align:center;padding:48px 32px">
    <div style="font-size:56px;margin-bottom:16px">🤝</div>
    <h2 style="font-size:24px;font-weight:800;margin-bottom:10px">Earn by Referring Friends</h2>
    <p style="color:#64748b;font-size:15px;max-width:460px;margin:0 auto 28px;line-height:1.7">Join our affiliate program and earn commission for every successful referral. Get a unique link to share with friends and colleagues.</p>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;max-width:480px;margin:0 auto 32px">
      <div style="background:#f0fdf4;border-radius:12px;padding:16px"><div style="font-size:24px;margin-bottom:8px">🔗</div><div style="font-size:13px;font-weight:600">Share your link</div></div>
      <div style="background:#eff6ff;border-radius:12px;padding:16px"><div style="font-size:24px;margin-bottom:8px">👤</div><div style="font-size:13px;font-weight:600">Friend signs up</div></div>
      <div style="background:#fffbeb;border-radius:12px;padding:16px"><div style="font-size:24px;margin-bottom:8px">💰</div><div style="font-size:13px;font-weight:600">You earn commission</div></div>
    </div>
    <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="join">
      <button type="submit" class="bp-btn bp-btn-accent" style="padding:14px 36px;font-size:16px;justify-content:center">Join Affiliate Program →</button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- Stats -->
<div class="stat-cards" style="grid-template-columns:repeat(4,1fr)">
  <div class="stat-card"><div class="stat-card-icon blue">💰</div><div class="stat-card-value"><?=format_currency($affiliate['balance'],$currency)?></div><div class="stat-card-label">Available Balance</div></div>
  <div class="stat-card"><div class="stat-card-icon green">✓</div><div class="stat-card-value"><?=format_currency($affiliate['total_earned'],$currency)?></div><div class="stat-card-label">Total Earned</div></div>
  <div class="stat-card"><div class="stat-card-icon cyan">✓</div><div class="stat-card-value"><?=format_currency($affiliate['total_paid'],$currency)?></div><div class="stat-card-label">Total Paid Out</div></div>
  <div class="stat-card"><div class="stat-card-icon amber">👥</div><div class="stat-card-value"><?=count($referrals)?></div><div class="stat-card-label">Total Referrals</div></div>
</div>

<div class="row g-4" style="margin-top:4px">
  <div class="col-lg-5">
    <!-- Referral link -->
    <div class="bp-card">
      <div class="bp-card-header"><h3 class="bp-card-title">Your Referral Link</h3></div>
      <div class="bp-card-body">
        <div style="background:linear-gradient(135deg,#0f172a,#1e3a5f);border-radius:12px;padding:20px;margin-bottom:16px">
          <div style="color:rgba(255,255,255,.5);font-size:11px;font-weight:700;text-transform:uppercase;margin-bottom:6px">Your Unique Code</div>
          <div style="color:#fff;font-size:28px;font-weight:900;letter-spacing:3px;font-family:monospace"><?=h($affiliate['referral_code'])?></div>
        </div>
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:9px;padding:10px 14px;font-size:12px;word-break:break-all;margin-bottom:12px;font-family:monospace" id="ref-url"><?=h($ref_url)?></div>
        <button onclick="navigator.clipboard.writeText('<?=h($ref_url)?>').then(()=>{this.textContent='✓ Copied!';setTimeout(()=>this.textContent='📋 Copy Link',2000)})" class="bp-btn bp-btn-primary" style="width:100%;justify-content:center">📋 Copy Link</button>
        <div style="margin-top:16px;padding:14px;background:#f0fdf4;border-radius:10px;font-size:13px;color:#166534">
          <strong>Commission Rate:</strong> <?=$affiliate['commission_type']==='percentage'?$affiliate['commission_value'].'% of each referral\'s first payment':format_currency($affiliate['commission_value'],$currency).' per referral'?>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="bp-card">
      <div class="bp-card-header"><h3 class="bp-card-title">Referral History</h3></div>
      <?php if($referrals):?>
      <table class="bp-table"><thead><tr><th>Client</th><th>Commission</th><th>Status</th><th>Date</th></tr></thead><tbody>
      <?php foreach($referrals as $r):$sb=['pending'=>'warning','approved'=>'info','paid'=>'success'];?>
      <tr>
        <td><div style="font-weight:600"><?=h($r['first_name'].' '.$r['last_name'])?></div><div style="font-size:12px;color:#64748b"><?=h($r['email'])?></div></td>
        <td style="font-weight:600"><?=$r['commission_amount']>0?format_currency($r['commission_amount'],$currency):'—'?></td>
        <td><span class="bp-badge bp-badge-<?=$sb[$r['status']]??'muted'?>"><?=$r['status']?></span></td>
        <td style="font-size:12px;color:#64748b"><?=time_ago($r['created_at'])?></td>
      </tr>
      <?php endforeach?>
      </tbody></table>
      <?php else:?><div class="bp-empty"><div class="bp-empty-icon">👥</div><div class="bp-empty-title">No referrals yet</div><div class="bp-empty-text">Share your referral link to start earning.</div></div><?php endif?>
    </div>
  </div>
</div>
<?php endif?>
</div>
<?php include 'partials/footer.php';?>
