<?php
require_once '../includes/config.php';
$admin=Auth::requireAdmin(); $company=DB::setting('company_name','Billing Portal'); $page_title='Servers';

if(is_post()&&csrf_verify()){
    $action=post('action');
    if($action==='create'){
        $name=trim(post('name')); $type=post('type'); $host=trim(post('hostname'));
        $port=(int)post('port',2087);
        if($type==='nocix'||$type==='time4vps') $port=443;
        $user=trim(post('username')); $api=trim(post('api_key'));
        $pw=post('password');
        if(!$name||!$host){redirect_with_flash('servers.php','danger','Name and hostname required.');}
        DB::execute("INSERT INTO servers (name,type,hostname,port,username,password,api_key,status) VALUES (?,?,?,?,?,?,?,'active')",'sssisss',[$name,$type,$host,$port,$user,$pw,$api]);
        redirect_with_flash('servers.php','success','Server added.');
    }
    if($action==='delete'){
        DB::execute("DELETE FROM servers WHERE id=?",'i',[(int)post('server_id')]);
        redirect_with_flash('servers.php','success','Server removed.');
    }
    if($action==='toggle'){
        $sid=(int)post('server_id');
        $cur=DB::value("SELECT status FROM servers WHERE id=?",'i',[$sid]);
        DB::execute("UPDATE servers SET status=? WHERE id=?",'si',[$cur==='active'?'maintenance':'active',$sid]);
        redirect_with_flash('servers.php','success','Server status updated.');
    }
}

$servers=DB::rows("SELECT * FROM servers ORDER BY type,name");
include 'partials/header.php';
?>
<div class="bp-content">
<h1 class="bp-page-title">Servers</h1>
<?=flash_html()?>
<div class="row g-4">
  <div class="col-lg-8">
    <div class="bp-card">
      <div class="bp-card-header"><h3 class="bp-card-title">Configured Servers (<?=count($servers)?>)</h3></div>
      <?php if($servers):?>
      <table class="bp-table">
        <thead><tr><th>Name</th><th>Type</th><th>Hostname</th><th>Port</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach($servers as $s):$sb=['active'=>'success','inactive'=>'muted','maintenance'=>'warning'];?>
        <tr>
          <td style="font-weight:600"><?=h($s['name'])?></td>
          <td><span class="bp-badge bp-badge-info" style="text-transform:capitalize"><?=h($s['type'])?></span></td>
          <td style="font-family:monospace;font-size:13px"><?=h($s['hostname'])?></td>
          <td style="font-size:13px;color:#64748b"><?=in_array($s['type'],['nocix','time4vps'])?'N/A':$s['port']?></td>
          <td><span class="bp-badge bp-badge-<?=$sb[$s['status']]??'muted'?>"><?=$s['status']?></span></td>
          <td>
            <div class="d-flex gap-1">
              <a href="servers/test.php?id=<?=$s['id']?>" class="bp-btn bp-btn-outline bp-btn-sm">Test</a>
              <form method="POST" style="display:inline"><?=csrf_input()?><input type="hidden" name="action" value="toggle"><input type="hidden" name="server_id" value="<?=$s['id']?>">
                <button type="submit" class="bp-btn bp-btn-outline bp-btn-sm"><?=$s['status']==='active'?'Pause':'Activate'?></button>
              </form>
              <form method="POST" style="display:inline" onsubmit="return confirm('Remove this server?')"><?=csrf_input()?><input type="hidden" name="action" value="delete"><input type="hidden" name="server_id" value="<?=$s['id']?>">
                <button type="submit" class="bp-btn bp-btn-outline bp-btn-sm" style="color:#ef4444;border-color:#fecdd3">Remove</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach?>
        </tbody>
      </table>
      <?php else:?><div class="bp-empty"><div class="bp-empty-icon">🖥</div><div class="bp-empty-title">No servers configured</div></div><?php endif?>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="bp-card">
      <div class="bp-card-header"><h3 class="bp-card-title">➕ Add Server</h3></div>
      <div class="bp-card-body">
        <form method="POST"><?=csrf_input()?><input type="hidden" name="action" value="create">
          <div class="bp-form-group"><label class="bp-label">Server Name *</label><input type="text" name="name" class="bp-input" placeholder="e.g. cPanel US-1" required></div>
          <div class="bp-form-group"><label class="bp-label">Type</label>
            <select name="type" class="bp-select" onchange="updateFormForType(this.value)">
              <option value="cpanel">cPanel/WHM</option>
              <option value="nocix">NOCIX Dedicated</option>
              <option value="time4vps">Time4VPS VPS</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="bp-form-group"><label class="bp-label" id="lbl-hostname">Hostname / IP *</label><input type="text" name="hostname" id="input-hostname" class="bp-input" placeholder="server.yourdomain.com" required></div>
          <div class="bp-form-row bp-form-row-2">
            <div class="bp-form-group" id="group-port"><label class="bp-label">Port</label><input type="number" name="port" id="port-in" class="bp-input" value="2087"></div>
            <div class="bp-form-group" id="group-username"><label class="bp-label" id="lbl-username">Username</label><input type="text" name="username" id="input-username" class="bp-input" placeholder="root"></div>
          </div>
          <div class="bp-form-group" id="group-apikey"><label class="bp-label" id="lbl-apikey">API Key</label><input type="password" name="api_key" id="input-apikey" class="bp-input" placeholder="Preferred over password"></div>
          <div class="bp-form-group" id="group-password"><label class="bp-label" id="lbl-password">Password (if no API key)</label><input type="password" name="password" id="input-password" class="bp-input"></div>
          <button type="submit" class="bp-btn bp-btn-primary" style="width:100%;justify-content:center">Add Server</button>
        </form>
      </div>
    </div>

    <!-- Module API settings shortcut -->
    <div class="bp-card" style="margin-top:16px">
      <div class="bp-card-header"><h3 class="bp-card-title">🔑 API Credentials</h3></div>
      <div class="bp-card-body" style="font-size:13px;color:#374151;line-height:1.8">
        Configure domain registrar API keys in <a href="settings.php?tab=modules" style="color:#3b82f6;font-weight:600">Settings → Modules</a>.
        <div style="margin-top:12px;display:flex;flex-direction:column;gap:8px">
          <?php foreach(['ResellerClub','Namecheap','ConnectReseller','Upperlink (.NG)','NOCIX','Time4VPS'] as $api):?>
          <div style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:#f8fafc;border-radius:8px">
            <span style="font-size:14px">🔌</span>
            <span style="font-weight:500"><?=h($api)?></span>
            <a href="settings.php?tab=modules" style="margin-left:auto;font-size:12px;color:#3b82f6">Configure →</a>
          </div>
          <?php endforeach?>
        </div>
      </div>
    </div>
  </div>
</div>
</div>
<script>
function updateFormForType(t) {
    const lblHost = document.getElementById('lbl-hostname');
    const inputHost = document.getElementById('input-hostname');
    const grpPort = document.getElementById('group-port');
    const lblUser = document.getElementById('lbl-username');
    const inputUser = document.getElementById('input-username');
    const lblApiKey = document.getElementById('lbl-apikey');
    const inputApiKey = document.getElementById('input-apikey');
    const grpPassword = document.getElementById('group-password');

    // Reset default cPanel layout
    lblHost.innerHTML = "Hostname / IP *";
    inputHost.placeholder = "server.yourdomain.com";
    grpPort.style.display = "block";
    lblUser.innerHTML = "Username";
    inputUser.placeholder = "root";
    lblApiKey.innerHTML = "API Key";
    inputApiKey.placeholder = "Preferred over password";
    grpPassword.style.display = "block";

    if (t === 'cpanel') {
        document.getElementById('port-in').value = 2087;
    } else if (t === 'nocix') {
        lblHost.innerHTML = "API Base URL *";
        inputHost.placeholder = "https://my.nocix.net/api/";
        grpPort.style.display = "none";
        lblUser.innerHTML = "User ID *";
        inputUser.placeholder = "Nocix Account ID / Email";
        lblApiKey.innerHTML = "API Key / Auth Token *";
        inputApiKey.placeholder = "Nocix API Token";
        grpPassword.style.display = "none";
    } else if (t === 'time4vps') {
        lblHost.innerHTML = "API Base URL *";
        inputHost.placeholder = "https://billing.time4vps.com/api/";
        grpPort.style.display = "none";
        lblUser.innerHTML = "API Username *";
        inputUser.placeholder = "Time4VPS Client Email";
        lblApiKey.innerHTML = "API Password *";
        inputApiKey.placeholder = "Time4VPS API Password";
        grpPassword.style.display = "none";
    } else {
        document.getElementById('port-in').value = 22;
    }
}
document.addEventListener('DOMContentLoaded', () => {
    updateFormForType(document.querySelector('select[name="type"]').value);
});
</script>
<?php include 'partials/footer.php';?>
