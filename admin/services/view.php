<?php
require_once '../../includes/config.php';
require_once INC_PATH.'/modules/provisioning/dispatcher.php';
$admin=Auth::requireAdmin(); $company=DB::setting('company_name','Billing Portal');
$sid=(int)get_param('id');
$service=DB::row("SELECT s.*,p.name AS pname,p.module,p.type AS ptype,c.first_name,c.last_name,c.email FROM services s JOIN products p ON p.id=s.product_id JOIN clients c ON c.id=s.client_id WHERE s.id=?",'i',[$sid]);
if(!$service) redirect(BASE_URL.'/admin/services.php');
$page_title='Service #'.$sid;
$module_data=json_decode($service['module_data']??'{}',true)??[];
$api_status=null; $action_result=null;

if(is_post()&&csrf_verify()){
    $action=post('action');
    if($action==='provision') $action_result=ProvisioningDispatcher::provision($sid);
    if($action==='suspend'){ProvisioningDispatcher::suspend($sid);redirect_with_flash("view.php?id={$sid}",'success','Service suspended.');}
    if($action==='unsuspend'){ProvisioningDispatcher::unsuspend($sid);redirect_with_flash("view.php?id={$sid}",'success','Service unsuspended.');}
    if($action==='terminate'){ProvisioningDispatcher::terminate($sid);redirect_with_flash(BASE_URL.'/admin/services.php','success','Service terminated.');}
    if($action==='check_status'){
        $mod=ProvisioningDispatcher::getModule($sid);
        $ref=$service['username']??($module_data['server_id']??($module_data['service_id']??$service['domain']));
        if($mod&&$ref) $api_status=$mod->getStatus($ref);
    }
    if($action==='reboot'){
        $mod=ProvisioningDispatcher::getModule($sid);
        $ref=$service['username']??($module_data['server_id']??($module_data['service_id']??null));
        if($mod&&$ref&&method_exists($mod,'reboot')) $action_result=$mod->reboot($ref);
    }
    if($action==='get_epp'){
        $mod=ProvisioningDispatcher::getModule($sid);
        $ref=$service['domain']??$service['username'];
        if($mod&&$ref&&method_exists($mod,'getEppCode')) $action_result=$mod->getEppCode($ref);
    }
    $service=DB::row("SELECT s.*,p.name AS pname,p.module,p.type AS ptype,c.first_name,c.last_name,c.email FROM services s JOIN products p ON p.id=s.product_id JOIN clients c ON c.id=s.client_id WHERE s.id=?",'i',[$sid]);
    $module_data=json_decode($service['module_data']??'{}',true)??[];
}

$invoices=DB::rows("SELECT i.* FROM invoices i JOIN invoice_items ii ON ii.invoice_id=i.id WHERE ii.service_id=? ORDER BY i.id DESC LIMIT 5",'i',[$sid]);
$sb_s=['active'=>'success','suspended'=>'danger','pending'=>'warning','terminated'=>'muted','cancelled'=>'muted'];
include '../partials/header.php';
?>
<div class="bp-content">
<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
  <a href="<?=BASE_URL?>/admin/services.php" class="bp-btn bp-btn-outline bp-btn-sm">← Back</a>
  <h1 class="bp-page-title" style="margin:0">Service #<?=$sid?></h1>
  <span class="bp-badge bp-badge-<?=$sb_s[$service['status']]??'muted'?>"><?=$service['status']?></span>
  <?php if($service['module']):?><span class="bp-badge bp-badge-info"><?=h($service['module'])?></span><?php endif?>
</div>
<?=flash_html()?>

<?php if($action_result): ?>
<div class="alert-custom alert-<?=$action_result['success']?'success':'danger'?> mb-3">
  <span><?=$action_result['success']?'✓':'✕'?></span>
  <div><?=h($action_result['message']??($action_result['error']??'Done.'))?>
    <?php if(!empty($action_result['epp_code'])):?><br><strong>EPP Code: <code><?=h($action_result['epp_code'])?></code></strong><?php endif?>
    <?php if(!empty($action_result['username'])):?><br>Username: <strong><?=h($action_result['username'])?></strong><?php endif?>
    <?php if(!empty($action_result['password'])):?><br>Password: <strong><?=h($action_result['password'])?></strong><?php endif?>
    <?php if(!empty($action_result['ip_address'])):?><br>IP: <strong><?=h($action_result['ip_address'])?></strong><?php endif?>
  </div>
</div>
<?php endif?>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="bp-card"><div class="bp-card-body">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:12px">Service Details</div>
      <?php foreach([['Product',h($service['pname'])],['Type',ucfirst($service['ptype'])],['Domain',$service['domain']?h($service['domain']):'—'],['Username',$service['username']?h($service['username']):'—'],['Cycle',ucfirst(str_replace('_',' ',$service['billing_cycle']))],['Price',format_currency($service['price'],DB::setting('base_currency','NGN'))],['Next Due',$service['next_due_date']?format_date($service['next_due_date']):'—'],['Module',$service['module']?h($service['module']):'None']] as [$l,$v]):?>
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f8fafc;font-size:13px"><span style="color:#64748b"><?=$l?></span><span style="font-weight:600"><?=$v?></span></div>
      <?php endforeach?>
      <div style="margin-top:16px">
        <a href="<?=BASE_URL?>/admin/clients/view.php?id=<?=$service['client_id']?>" style="text-decoration:none;display:flex;align-items:center;gap:10px">
          <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#06b6d4);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:14px"><?=strtoupper(substr($service['first_name'],0,1))?></div>
          <div><div style="font-weight:600;color:#0f172a"><?=h($service['first_name'].' '.$service['last_name'])?></div><div style="font-size:12px;color:#3b82f6"><?=h($service['email'])?></div></div>
        </a>
      </div>
    </div></div>

    <?php if($module_data):?>
    <div class="bp-card" style="margin-top:16px"><div class="bp-card-header"><h3 class="bp-card-title">Module Data</h3></div><div class="bp-card-body">
      <?php foreach($module_data as $k=>$v):if(is_array($v))$v=json_encode($v);?>
      <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f8fafc;font-size:12px"><span style="color:#64748b;font-family:monospace"><?=h($k)?></span><span style="font-weight:600;font-family:monospace;word-break:break-all;max-width:60%;text-align:right"><?=h((string)$v)?></span></div>
      <?php endforeach?>
    </div></div>
    <?php endif?>
  </div>

  <div class="col-lg-7">
    <div class="bp-card"><div class="bp-card-header"><h3 class="bp-card-title">⚡ Actions</h3></div><div class="bp-card-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <?php if($service['status']==='pending'&&$service['module']):?>
        <form method="POST" style="grid-column:1/-1"><?=csrf_input()?><input type="hidden" name="action" value="provision">
          <button type="submit" class="bp-btn bp-btn-success" style="width:100%;justify-content:center;padding:13px" onclick="return confirm('Provision now?')">🚀 Provision Now</button>
        </form>
        <?php endif?>
        <?php if($service['status']==='active'):?>
        <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="suspend"><button type="submit" class="bp-btn bp-btn-outline" style="width:100%;justify-content:center;color:#ef4444;border-color:#fecdd3" onclick="return confirm('Suspend?')">⏸ Suspend</button></form>
        <?php elseif($service['status']==='suspended'):?>
        <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="unsuspend"><button type="submit" class="bp-btn bp-btn-success" style="width:100%;justify-content:center" onclick="return confirm('Unsuspend?')">▶ Unsuspend</button></form>
        <?php endif?>
        <?php if(!in_array($service['status'],['terminated','cancelled'])):?>
        <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="terminate"><button type="submit" class="bp-btn bp-btn-danger" style="width:100%;justify-content:center" onclick="return confirm('PERMANENTLY terminate? Cannot be undone.')">🗑 Terminate</button></form>
        <?php endif?>
        <?php if($service['module']&&!in_array($service['status'],['terminated','cancelled'])):?>
        <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="check_status"><button type="submit" class="bp-btn bp-btn-outline" style="width:100%;justify-content:center">📡 Check Status</button></form>
        <?php endif?>
        <?php if(in_array($service['ptype'],['vps','dedicated'])&&$service['status']==='active'):?>
        <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="reboot"><button type="submit" class="bp-btn bp-btn-outline" style="width:100%;justify-content:center" onclick="return confirm('Reboot?')">🔄 Reboot</button></form>
        <?php endif?>
        <?php if($service['ptype']==='domain'):?>
        <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="get_epp"><button type="submit" class="bp-btn bp-btn-outline" style="width:100%;justify-content:center">🔑 Get EPP Code</button></form>
        <?php endif?>
      </div>
    </div></div>

    <?php if($api_status):?>
    <div class="bp-card" style="margin-top:16px"><div class="bp-card-header"><h3 class="bp-card-title">📡 Live API Status</h3></div><div class="bp-card-body">
      <?php if($api_status['success']):?>
      <div style="display:flex;flex-wrap:wrap;gap:10px">
        <?php foreach($api_status as $k=>$v):if($k==='success'||is_array($v))continue;?>
        <div style="flex:1;min-width:140px;background:#f8fafc;border-radius:8px;padding:11px">
          <div style="font-size:10px;color:#94a3b8;text-transform:uppercase;font-weight:700;margin-bottom:3px"><?=h(str_replace('_',' ',$k))?></div>
          <div style="font-size:13px;font-weight:600"><?=h((string)$v)?></div>
        </div>
        <?php endforeach?>
      </div>
      <?php else:?><div class="alert-custom alert-danger"><span>✕</span> <?=h($api_status['error']??'Status check failed.')?></div><?php endif?>
    </div></div>
    <?php endif?>

    <div class="bp-card" style="margin-top:16px"><div class="bp-card-header"><h3 class="bp-card-title">Invoices</h3></div>
      <?php if($invoices):?>
      <table class="bp-table"><thead><tr><th>Invoice</th><th>Amount</th><th>Due</th><th>Status</th></tr></thead><tbody>
      <?php foreach($invoices as $inv):$sb=['paid'=>'success','unpaid'=>'warning','overdue'=>'danger','cancelled'=>'muted'];?>
      <tr><td><a href="<?=BASE_URL?>/admin/invoices/view.php?id=<?=$inv['id']?>" style="color:#3b82f6;font-weight:600;text-decoration:none">#<?=h($inv['invoice_number'])?></a></td>
      <td style="font-weight:700"><?=format_currency($inv['total'],$inv['currency'])?></td>
      <td style="font-size:13px;color:#64748b"><?=format_date($inv['due_date'])?></td>
      <td><span class="bp-badge bp-badge-<?=$sb[$inv['status']]??'muted'?>"><?=$inv['status']?></span></td></tr>
      <?php endforeach?></tbody></table>
      <?php else:?><div class="bp-empty" style="padding:20px"><div class="bp-empty-title">No invoices</div></div><?php endif?>
    </div>
  </div>
</div>
</div>
<?php include '../partials/footer.php';?>
