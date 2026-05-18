<?php
require_once '../../includes/config.php';
require_once INC_PATH.'/modules/provisioning/dispatcher.php';
$admin=Auth::requireAdmin();
$sid=(int)get_param('id');
$server=DB::row("SELECT * FROM servers WHERE id=?",'i',[$sid]);
if(!$server) redirect(BASE_URL.'/admin/servers.php');

$result=['tested'=>false,'message'=>'No test available for this server type.'];

if($server['type']==='cpanel'){
    $module=ProvisioningDispatcher::buildModule('cpanel',$sid);
    if($module){
        try{
            $pkgs=$module->listPackages();
            $result=['tested'=>true,'success'=>true,'message'=>'WHM connection successful.','details'=>'Packages found: '.count($pkgs)];
        }catch(Exception $e){
            $result=['tested'=>true,'success'=>false,'message'=>'Connection failed: '.$e->getMessage()];
        }
    }
}

$company=DB::setting('company_name','Billing Portal');
$page_title='Server Test';
include '../partials/header.php';
?>
<div class="bp-content">
<div class="d-flex align-items-center gap-3 mb-4">
  <a href="<?=BASE_URL?>/admin/servers.php" class="bp-btn bp-btn-outline bp-btn-sm">← Back</a>
  <h1 class="bp-page-title" style="margin:0">Test: <?=h($server['name'])?></h1>
</div>
<div class="bp-card" style="max-width:600px">
  <div class="bp-card-body">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
      <div style="width:48px;height:48px;border-radius:12px;background:<?=isset($result['success'])&&$result['success']?'#f0fdf4':'#fff1f2'?>;display:flex;align-items:center;justify-content:center;font-size:22px">
        <?=isset($result['success'])&&$result['success']?'✅':'❌'?>
      </div>
      <div>
        <div style="font-weight:700;font-size:16px"><?=h($server['name'])?></div>
        <div style="font-size:13px;color:#64748b"><?=h($server['hostname'])?> : <?=$server['port']?></div>
      </div>
    </div>
    <div style="background:<?=isset($result['success'])&&$result['success']?'#f0fdf4':'#fff1f2'?>;border-radius:10px;padding:16px;font-size:14px">
      <strong><?=$result['message']?></strong>
      <?php if(!empty($result['details'])):?><div style="margin-top:6px;font-size:13px;color:#374151"><?=h($result['details'])?></div><?php endif?>
    </div>
    <?php foreach([['Type',ucfirst($server['type'])],['Hostname',h($server['hostname'])],['Port',$server['port']],['Username',h($server['username']??'—')],['API Key',$server['api_key']?'✓ Configured':'Not set'],['Status',ucfirst($server['status'])]] as [$l,$v]):?>
    <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f8fafc;font-size:13px"><span style="color:#64748b"><?=$l?></span><span style="font-weight:600"><?=$v?></span></div>
    <?php endforeach?>
  </div>
</div>
</div>
<?php include '../partials/footer.php';?>
