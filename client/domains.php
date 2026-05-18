<?php
require_once '../includes/config.php';
require_once INC_PATH.'/modules/provisioning/dispatcher.php';
$client=Auth::requireClient(); $company=DB::setting('company_name','Billing Portal');
$currency=DB::setting('base_currency','NGN'); $page_title='My Domains';
$action_result=null;

if(is_post()&&csrf_verify()){
    $action=post('action'); $sid=(int)post('service_id');
    // Verify service belongs to this client
    $svc=DB::row("SELECT s.*,p.module FROM services s JOIN products p ON p.id=s.product_id WHERE s.id=? AND s.client_id=? AND p.type='domain'",'ii',[$sid,$client['id']]);
    if($svc){
        if($action==='get_epp'){
            $mod=ProvisioningDispatcher::getModule($sid);
            if($mod&&method_exists($mod,'getEppCode')){
                $ref=$svc['domain']??$svc['username'];
                $action_result=['service_id'=>$sid,'type'=>'epp']+($ref?$mod->getEppCode($ref):['success'=>false,'error'=>'No domain reference.']);
            }
        }
        if($action==='update_ns'){
            $ns=array_filter(array_map('trim',[post('ns1'),post('ns2'),post('ns3'),post('ns4')]));
            if(count($ns)<2){$action_result=['service_id'=>$sid,'type'=>'ns','success'=>false,'error'=>'At least 2 nameservers required.'];}
            else{
                $mod=ProvisioningDispatcher::getModule($sid);
                $ref=$svc['domain']??$svc['username'];
                if($mod&&$ref&&method_exists($mod,'updateNameservers')){
                    $r=$mod->updateNameservers($ref,array_values($ns));
                    $action_result=['service_id'=>$sid,'type'=>'ns']+$r;
                }
            }
        }
    }
}

$domains=DB::rows("SELECT s.*,p.name AS pname,p.module FROM services s JOIN products p ON p.id=s.product_id WHERE s.client_id=? AND p.type='domain' AND s.status NOT IN ('terminated','cancelled') ORDER BY s.id DESC",'i',[$client['id']]);
include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title">My Domains</h1>
<?=flash_html()?>

<?php if($action_result): ?>
<div class="alert-custom alert-<?=$action_result['success']?'success':'danger'?> mb-4">
  <span><?=$action_result['success']?'✓':'✕'?></span>
  <div>
    <?php if($action_result['type']==='epp'&&!empty($action_result['epp_code'])):?>
      EPP / Auth Code retrieved successfully.<br>
      <strong style="font-size:16px;letter-spacing:2px;font-family:monospace"><?=h($action_result['epp_code'])?></strong><br>
      <span style="font-size:12px">Keep this code private. Use it to transfer your domain to another registrar.</span>
    <?php elseif(!$action_result['success']):?>
      <?=h($action_result['error']??'Action failed.')?>
    <?php else:?>
      <?=h($action_result['message']??'Done.')?>
    <?php endif?>
  </div>
</div>
<?php endif?>

<?php if($domains): foreach($domains as $d):
  $md=json_decode($d['module_data']??'{}',true)??[];
  $expiry=$d['next_due_date']?strtotime($d['next_due_date']):0;
  $days_left=$expiry?ceil(($expiry-time())/86400):null;
  $expiring=$days_left!==null&&$days_left<=30;

  $parts = explode('.', trim($d['domain'] ?? ''));
  $tld = strtolower(end($parts));
  $tld_row = DB::row("SELECT registrar FROM domain_tlds WHERE tld=?", 's', [$tld]);
  $registrar = $d['module'] ? ucfirst(h($d['module'])) : 'Manual';
  if ($tld_row && !empty($tld_row['registrar']) && $tld_row['registrar'] !== 'none') {
      $registrar = $tld_row['registrar'] === 'connectreseller' ? 'ConnectReseller' : ($tld_row['registrar'] === 'resellerclub' ? 'ResellerClub' : ucfirst(h($tld_row['registrar'])));
  }
?>
<div class="bp-card" style="margin-bottom:16px">
  <div style="background:linear-gradient(135deg,#0f172a,#1e3a5f);padding:20px 24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div style="display:flex;align-items:center;gap:12px">
      <div style="font-size:28px">🌐</div>
      <div>
        <div style="color:#fff;font-size:18px;font-weight:800;font-family:monospace"><?=h($d['domain']??'—')?></div>
        <div style="color:rgba(255,255,255,.5);font-size:12px"><?=h($d['pname'])?> · <?=$registrar?></div>
      </div>
    </div>
    <div style="text-align:right">
      <?php $sc=['active'=>['#10b981','Active'],'suspended'=>['#ef4444','Suspended'],'pending'=>['#f59e0b','Pending']];[$col,$lbl]=$sc[$d['status']]??['#64748b','Unknown'];?>
      <div style="color:<?=$col?>;font-weight:700;font-size:13px">● <?=$lbl?></div>
      <?php if($days_left!==null):?>
      <div style="color:<?=$expiring?'#fbbf24':'rgba(255,255,255,.5)'?>;font-size:12px;margin-top:2px">
        <?=$expiring?'⚠ ':''?>Expires <?=format_date($d['next_due_date'])?> (<?=$days_left>0?$days_left.' days':abs($days_left).' days ago'?>)
      </div>
      <?php endif?>
    </div>
  </div>

  <div class="bp-card-body">
    <div class="row g-4">
      <!-- Nameservers -->
      <div class="col-lg-6">
        <div style="font-weight:600;font-size:13px;margin-bottom:12px">🔧 Update Nameservers</div>
        <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="update_ns"><input type="hidden" name="service_id" value="<?=$d['id']?>">
          <?php
          $current_ns=$md['nameservers']??[];
          for($i=1;$i<=4;$i++):$placeholder=$i<=2?'Required':'Optional';$val=$current_ns[$i-1]??'';
          ?>
          <div class="bp-form-group"><label class="bp-label">Nameserver <?=$i?> <?=$i<=2?'*':''?></label>
            <input type="text" name="ns<?=$i?>" class="bp-input" value="<?=h($val)?>" placeholder="ns<?=$i?>.yourdns.com" <?=$i<=2?'required':''>></div>
          <?php endfor?>
          <button type="submit" class="bp-btn bp-btn-primary bp-btn-sm" <?=$d['module']?'':'disabled'?>>Update Nameservers</button>
          <?php if(!$d['module']):?><div class="bp-input-hint">Nameserver updates not available for manually managed domains.</div><?php endif?>
        </form>
      </div>

      <!-- Domain info + EPP -->
      <div class="col-lg-6">
        <div style="font-weight:600;font-size:13px;margin-bottom:12px">ℹ Domain Information</div>
        <?php foreach([['Domain',$d['domain']??'—'],['Registrar',$d['module']?ucfirst($d['module']):'Manual'],['Registration',format_date($d['created_at'])],['Expiry',$d['next_due_date']?format_date($d['next_due_date']):'—']] as [$l,$v]):?>
        <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid #f8fafc;font-size:13px"><span style="color:#64748b"><?=$l?></span><span style="font-weight:600"><?=$v?></span></div>
        <?php endforeach?>

        <?php if($d['status']==='active'&&$d['module']): ?>
        <div style="margin-top:16px">
          <div style="font-weight:600;font-size:13px;margin-bottom:8px">🔑 Transfer Away</div>
          <p style="font-size:13px;color:#64748b;margin-bottom:10px">Get your EPP/Authorization code to transfer this domain to another registrar.</p>
          <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="get_epp"><input type="hidden" name="service_id" value="<?=$d['id']?>">
            <button type="submit" class="bp-btn bp-btn-outline bp-btn-sm">Get EPP / Auth Code</button>
          </form>
        </div>
        <?php endif?>

        <?php if($expiring&&$d['status']==='active'):?>
        <div style="margin-top:16px;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px 14px;font-size:13px;color:#92400e">
          ⚠ <strong>This domain expires soon.</strong> <a href="<?=BASE_URL?>/client/invoices.php?status=unpaid" style="color:#92400e;font-weight:700">Pay renewal invoice →</a>
        </div>
        <?php endif?>
      </div>
    </div>
  </div>
</div>
<?php endforeach; else:?>
<div class="bp-card"><div class="bp-empty">
  <div class="bp-empty-icon">🌐</div>
  <div class="bp-empty-title">No domains yet</div>
  <div class="bp-empty-text">Register your first domain to get started.</div>
  <a href="order.php" class="bp-btn bp-btn-primary bp-btn-sm" style="margin-top:12px">Register a Domain</a>
</div></div>
<?php endif?>
</div>
<?php include 'partials/footer.php';?>
