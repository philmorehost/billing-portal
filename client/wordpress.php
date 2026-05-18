<?php
require_once '../includes/config.php';
require_once INC_PATH.'/modules/wordpress.php';
$client=Auth::requireClient(); $company=DB::setting('company_name','Billing Portal');
$page_title='WordPress Manager'; $action_result=null; $active_tab='overview';

$sid=(int)get_param('service_id');
$service=DB::row("SELECT s.*,p.module FROM services s JOIN products p ON p.id=s.product_id WHERE s.id=? AND s.client_id=? AND p.type='hosting' AND s.status='active'",'ii',[$sid,$client['id']]);
if(!$service||$service['module']!=='cpanel'){redirect(BASE_URL.'/client/services.php');}

$wp=WordPressManager::fromService($sid);
$installations=$wp?$wp->findInstallations():[];

$install_path=get_param('path')?:($installations[0]['install_path']??'');
$active_install=null;
foreach($installations as $inst){if($inst['install_path']===$install_path){$active_install=$inst;break;}}
if(!$active_install&&!empty($installations)){$active_install=$installations[0];$install_path=$active_install['install_path'];}

$plugins=[]; $themes=[]; $core_info=[]; $in_maintenance=false;
if($wp&&$install_path){
    $active_tab=get_param('tab','overview');
    if($active_tab==='plugins') $plugins=$wp->getPlugins($install_path)['plugins']??[];
    if($active_tab==='themes') $themes=$wp->getThemes($install_path)['themes']??[];
    if($active_tab==='overview'){$core_info=$wp->getCoreInfo($install_path);$in_maintenance=$wp->getMaintenanceStatus($install_path);}
}

if(is_post()&&csrf_verify()&&$wp&&$install_path){
    $action=post('action');
    if($action==='login'){$action_result=$wp->getLoginUrl($install_path);if($action_result['success']&&!empty($action_result['login_url'])){redirect($action_result['login_url']);}}
    if($action==='update_core'){$action_result=$wp->updateCore($install_path);$active_tab='overview';}
    if($action==='activate_plugin'){$action_result=$wp->activatePlugin($install_path,post('plugin'));$active_tab='plugins';$plugins=$wp->getPlugins($install_path)['plugins']??[];}
    if($action==='deactivate_plugin'){$action_result=$wp->deactivatePlugin($install_path,post('plugin'));$active_tab='plugins';$plugins=$wp->getPlugins($install_path)['plugins']??[];}
    if($action==='delete_plugin'){$action_result=$wp->deletePlugin($install_path,post('plugin'));$active_tab='plugins';$plugins=$wp->getPlugins($install_path)['plugins']??[];}
    if($action==='activate_theme'){$action_result=$wp->activateTheme($install_path,post('theme'));$active_tab='themes';$themes=$wp->getThemes($install_path)['themes']??[];}
    if($action==='delete_theme'){$action_result=$wp->deleteTheme($install_path,post('theme'));$active_tab='themes';$themes=$wp->getThemes($install_path)['themes']??[];}
    if($action==='maintenance_on'){$action_result=$wp->enableMaintenance($install_path);$in_maintenance=true;}
    if($action==='maintenance_off'){$action_result=$wp->disableMaintenance($install_path);$in_maintenance=false;}
}
include 'partials/header.php';
?>
<div class="bp-content">
<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
  <a href="<?=BASE_URL?>/client/services.php" class="bp-btn bp-btn-outline bp-btn-sm">← Back</a>
  <h1 class="bp-page-title" style="margin:0">WordPress Manager</h1>
  <span style="font-size:13px;color:#64748b"><?=h($service['domain']??'')?></span>
</div>
<?=flash_html()?>

<?php if($action_result):?>
<div class="alert-custom alert-<?=$action_result['success']?'success':'danger'?> mb-3">
  <span><?=$action_result['success']?'✓':'✕'?></span>
  <div><?=h($action_result['output']??$action_result['error']??($action_result['success']?'Done.':'Failed.'))?></div>
</div>
<?php endif?>

<?php if(!$wp):?>
<div class="bp-card"><div class="bp-empty"><div class="bp-empty-icon">🔧</div><div class="bp-empty-title">WordPress Manager Unavailable</div><div class="bp-empty-text">This service is not linked to a configured cPanel server. Please contact support.</div></div></div>
<?php elseif(empty($installations)):?>
<div class="bp-card"><div class="bp-empty"><div class="bp-empty-icon">🔍</div><div class="bp-empty-title">No WordPress Installations Found</div><div class="bp-empty-text">No WordPress installations were detected on this account. Install WordPress via your cPanel to manage it here.</div><a href="https://<?=h($service['domain']??'')?>/cpanel" target="_blank" class="bp-btn bp-btn-primary bp-btn-sm" style="margin-top:12px">Open cPanel</a></div></div>
<?php else:?>

<!-- Installation selector (if multiple) -->
<?php if(count($installations)>1):?>
<div class="bp-card mb-4"><div class="bp-card-body" style="padding:14px 20px">
  <label class="bp-label" style="margin-bottom:8px;display:block">WordPress Installation</label>
  <div style="display:flex;flex-wrap:wrap;gap:8px">
    <?php foreach($installations as $inst):?>
    <a href="?service_id=<?=$sid?>&path=<?=urlencode($inst['install_path'])?>&tab=<?=h($active_tab)?>" class="bp-btn bp-btn-<?=$inst['install_path']===$install_path?'primary':'outline'?> bp-btn-sm">
      🌐 <?=h($inst['site_url'])?> <span style="opacity:.7;font-size:11px">v<?=h($inst['wp_version'])?></span>
    </a>
    <?php endforeach?>
  </div>
</div></div>
<?php endif?>

<!-- Tab nav -->
<div class="d-flex gap-2 mb-4 flex-wrap">
  <?php foreach(['overview'=>'📊 Overview','plugins'=>'🧩 Plugins','themes'=>'🎨 Themes'] as $tab=>$label):?>
  <a href="?service_id=<?=$sid?>&path=<?=urlencode($install_path)?>&tab=<?=$tab?>" class="bp-btn bp-btn-<?=$active_tab===$tab?'primary':'outline'?>"><?=$label?></a>
  <?php endforeach?>
  <!-- One-click login button always visible -->
  <form method="POST" style="margin-left:auto"><?=csrf_input()?><input type="hidden" name="action" value="login"><input type="hidden" name="install_path" value="<?=h($install_path)?>">
    <button type="submit" class="bp-btn bp-btn-accent">⚡ One-Click WP Login</button>
  </form>
</div>

<!-- Overview Tab -->
<?php if($active_tab==='overview'):?>
<div class="row g-4">
  <div class="col-lg-6">
    <div class="bp-card"><div class="bp-card-header"><h3 class="bp-card-title">WordPress Core</h3></div><div class="bp-card-body">
      <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px">
        <div style="width:64px;height:64px;border-radius:16px;background:#eff6ff;display:flex;align-items:center;justify-content:center;font-size:32px">🟦</div>
        <div>
          <div style="font-size:24px;font-weight:800"><?=h($core_info['current_version']??$active_install['wp_version']??'—')?></div>
          <div style="font-size:13px;color:#64748b">Current Version</div>
        </div>
        <?php if(!empty($core_info['has_update'])):?>
        <div style="margin-left:auto;text-align:right">
          <span class="bp-badge bp-badge-warning">Update Available</span>
          <div style="font-size:12px;color:#64748b;margin-top:4px">→ v<?=h($core_info['latest_version']??'')?></div>
        </div>
        <?php else:?>
        <span class="bp-badge bp-badge-success" style="margin-left:auto">Up to Date</span>
        <?php endif?>
      </div>
      <?php if(!empty($core_info['has_update'])):?>
      <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="update_core">
        <button type="submit" class="bp-btn bp-btn-primary" style="width:100%;justify-content:center" onclick="return confirm('Update WordPress core to the latest version?')">
          ⬆ Update to v<?=h($core_info['latest_version']?:'Latest')?>
        </button>
      </form>
      <?php endif?>
    </div></div>
  </div>

  <div class="col-lg-6">
    <div class="bp-card"><div class="bp-card-header"><h3 class="bp-card-title">Site Settings</h3></div><div class="bp-card-body">
      <?php foreach([['Site URL',h($active_install['site_url']??'')],['Install Path',h($active_install['path']??'/')],['Domain',h($active_install['domain']??$service['domain']??'')]] as [$l,$v]):?>
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f8fafc;font-size:13px"><span style="color:#64748b"><?=$l?></span><span style="font-weight:600;font-family:monospace"><?=$v?></span></div>
      <?php endforeach?>
    </div></div>
    <div class="bp-card" style="margin-top:12px"><div class="bp-card-body">
      <div style="font-weight:600;font-size:13px;margin-bottom:12px">🔧 Maintenance Mode
        <span class="bp-badge bp-badge-<?=$in_maintenance?'danger':'success'?>" style="margin-left:8px"><?=$in_maintenance?'ACTIVE':'Off'?></span>
      </div>
      <p style="font-size:13px;color:#64748b;margin-bottom:12px">
        <?=$in_maintenance?'Your site is in maintenance mode. Visitors will see a "coming soon" page.':'Your site is live and accessible to visitors.'?>
      </p>
      <?php if($in_maintenance):?>
      <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="maintenance_off">
        <button type="submit" class="bp-btn bp-btn-success" style="width:100%;justify-content:center">▶ Disable Maintenance Mode</button>
      </form>
      <?php else:?>
      <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="maintenance_on">
        <button type="submit" class="bp-btn bp-btn-outline" style="width:100%;justify-content:center;color:#f59e0b;border-color:#fde68a" onclick="return confirm('Enable maintenance mode? Visitors will see a maintenance page.')">⏸ Enable Maintenance Mode</button>
      </form>
      <?php endif?>
    </div></div>
  </div>
</div>

<!-- Plugins Tab -->
<?php elseif($active_tab==='plugins'):?>
<div class="bp-card"><div class="bp-card-header"><h3 class="bp-card-title">Plugins (<?=count($plugins)?>)</h3></div>
<?php if($plugins):?>
<table class="bp-table">
  <thead><tr><th>Plugin</th><th>Version</th><th>Status</th><th>Update?</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach($plugins as $p):
    $active=$p['status']==='active';
    $has_update=!empty($p['update'])&&$p['update']==='available';
  ?>
  <tr>
    <td><div style="font-weight:600"><?=h($p['name']??$p['plugin']??'?')?></div><div style="font-size:11px;color:#94a3b8;font-family:monospace"><?=h($p['plugin']??'')?></div></td>
    <td style="font-size:13px"><?=h($p['version']??'—')?></td>
    <td><span class="bp-badge bp-badge-<?=$active?'success':'muted'?>"><?=$active?'Active':'Inactive'?></span></td>
    <td><?=$has_update?'<span class="bp-badge bp-badge-warning">Update</span>':'<span style="color:#94a3b8;font-size:12px">—</span>'?></td>
    <td>
      <div class="d-flex gap-1 flex-wrap">
        <?php $slug=$p['plugin']??'';?>
        <?php if($active):?>
        <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="deactivate_plugin"><input type="hidden" name="plugin" value="<?=h($slug)?>"><button type="submit" class="bp-btn bp-btn-outline bp-btn-sm">Deactivate</button></form>
        <?php else:?>
        <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="activate_plugin"><input type="hidden" name="plugin" value="<?=h($slug)?>"><button type="submit" class="bp-btn bp-btn-success bp-btn-sm">Activate</button></form>
        <form method="POST" onsubmit="return confirm('Delete this plugin?')"><?=csrf_input()?><input type="hidden" name="action" value="delete_plugin"><input type="hidden" name="plugin" value="<?=h($slug)?>"><button type="submit" class="bp-btn bp-btn-outline bp-btn-sm" style="color:#ef4444;border-color:#fecdd3">Delete</button></form>
        <?php endif?>
      </div>
    </td>
  </tr>
  <?php endforeach?>
  </tbody>
</table>
<?php else:?><div class="bp-empty"><div class="bp-empty-icon">🧩</div><div class="bp-empty-title">No plugins found</div></div><?php endif?>
</div>

<!-- Themes Tab -->
<?php elseif($active_tab==='themes'):?>
<div class="bp-card"><div class="bp-card-header"><h3 class="bp-card-title">Themes (<?=count($themes)?>)</h3></div>
<?php if($themes):?>
<table class="bp-table">
  <thead><tr><th>Theme</th><th>Version</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach($themes as $t):
    $active_theme=$t['status']==='active';
  ?>
  <tr>
    <td><div style="font-weight:600"><?=h($t['name']??$t['theme']??'?')?></div><div style="font-size:11px;color:#94a3b8;font-family:monospace"><?=h($t['theme']??'')?></div></td>
    <td style="font-size:13px"><?=h($t['version']??'—')?></td>
    <td><span class="bp-badge bp-badge-<?=$active_theme?'success':'muted'?>"><?=$active_theme?'Active':'Inactive'?></span></td>
    <td>
      <div class="d-flex gap-1">
        <?php $tslug=$t['theme']??'';?>
        <?php if(!$active_theme):?>
        <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="activate_theme"><input type="hidden" name="theme" value="<?=h($tslug)?>"><button type="submit" class="bp-btn bp-btn-success bp-btn-sm">Activate</button></form>
        <form method="POST" onsubmit="return confirm('Delete this theme?')"><?=csrf_input()?><input type="hidden" name="action" value="delete_theme"><input type="hidden" name="theme" value="<?=h($tslug)?>"><button type="submit" class="bp-btn bp-btn-outline bp-btn-sm" style="color:#ef4444;border-color:#fecdd3">Delete</button></form>
        <?php else:?>
        <span style="font-size:12px;color:#10b981;font-weight:600">✓ In Use</span>
        <?php endif?>
      </div>
    </td>
  </tr>
  <?php endforeach?>
  </tbody>
</table>
<?php else:?><div class="bp-empty"><div class="bp-empty-icon">🎨</div><div class="bp-empty-title">No themes found</div></div><?php endif?>
</div>
<?php endif?>

<?php endif?>
</div>
<?php include 'partials/footer.php';?>
