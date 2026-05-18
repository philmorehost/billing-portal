<?php
session_name('BP_INSTALL'); session_start();
$root = realpath(__DIR__.'/..');

if (file_exists($root.'/includes/db.config.php')) {
    require_once $root.'/includes/db.config.php';
    try {
        $chk = new mysqli(DB_HOST,DB_USER,DB_PASS,DB_NAME);
        $r = $chk->query("SELECT setting_value FROM settings WHERE setting_key='installer_complete'");
        if ($r && ($row=$r->fetch_assoc()) && $row['setting_value']==='1') { header('Location: ../admin/'); exit; }
    } catch(Exception $e){}
}

$step = max(1,min(4,(int)($_GET['step']??$_SESSION['install_step']??1)));
$scheme = (!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
$host   = $_SERVER['HTTP_HOST']??'localhost';
$dir    = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME']??'')),'/');
$base   = rtrim($scheme.'://'.$host.$dir,'/');

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action']??'';
    if ($action==='check_requirements') { $_SESSION['install_step']=2; header('Location: index.php?step=2'); exit; }
    if ($action==='save_database') {
        $dh=trim($_POST['db_host']??'localhost'); $dp=(int)($_POST['db_port']??3306);
        $dn=trim($_POST['db_name']??''); $du=trim($_POST['db_user']??''); $dw=$_POST['db_pass']??'';
        $conn=@new mysqli($dh,$du,$dw,'',$dp);
        if ($conn->connect_error) { $_SESSION['install_error']='Cannot connect: '.$conn->connect_error; header('Location: index.php?step=2'); exit; }
        $conn->query("CREATE DATABASE IF NOT EXISTS `".str_replace('`','``',$dn)."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $conn->select_db($dn);
        $schema = file_get_contents(__DIR__.'/schema.sql');
        foreach (array_filter(array_map('trim',explode(';',$schema))) as $sql) {
            if (empty($sql)||strpos(ltrim($sql),'--')===0) continue;
            if (!$conn->query($sql) && $conn->errno!==1050) {} // ignore table exists
        }
        file_put_contents($root.'/includes/db.config.php',"<?php\ndefine('DB_HOST',".var_export($dh,true).");\ndefine('DB_PORT',".var_export($dp,true).");\ndefine('DB_NAME',".var_export($dn,true).");\ndefine('DB_USER',".var_export($du,true).");\ndefine('DB_PASS',".var_export($dw,true).");\n");
        $_SESSION['install_step']=3; unset($_SESSION['install_error']); header('Location: index.php?step=3'); exit;
    }
    if ($action==='save_admin') {
        require_once $root.'/includes/db.config.php';
        $db=new mysqli(DB_HOST,DB_USER,DB_PASS,DB_NAME,DB_PORT);
        $name=trim($_POST['admin_name']??''); $email=trim($_POST['admin_email']??'');
        $pw=$_POST['admin_password']??''; $pw2=$_POST['admin_confirm']??'';
        $co=trim($_POST['company_name']??''); $url=rtrim(trim($_POST['site_url']??''),'/');
        if ($pw!==$pw2) { $_SESSION['install_error']='Passwords do not match.'; header('Location: index.php?step=3'); exit; }
        if (strlen($pw)<8) { $_SESSION['install_error']='Password must be at least 8 characters.'; header('Location: index.php?step=3'); exit; }
        $hash=password_hash($pw,PASSWORD_BCRYPT,['cost'=>12]);
        $s=$db->prepare("INSERT INTO admins (name,email,password,role_id,status) VALUES (?,?,?,1,'active') ON DUPLICATE KEY UPDATE name=VALUES(name),password=VALUES(password)");
        $s->bind_param('sss',$name,$email,$hash); $s->execute();
        foreach ([['company_name',$co],['company_email',$email],['site_url',$url]] as [$k,$v]) {
            $q=$db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
            $q->bind_param('ss',$k,$v); $q->execute();
        }
        $_SESSION['install_step']=4; unset($_SESSION['install_error']); header('Location: index.php?step=4'); exit;
    }
    if ($action==='finalize') {
        require_once $root.'/includes/db.config.php';
        $db=new mysqli(DB_HOST,DB_USER,DB_PASS,DB_NAME,DB_PORT);
        $db->query("UPDATE settings SET setting_value='1' WHERE setting_key='installer_complete'");
        $token=bin2hex(random_bytes(32));
        $db->query("UPDATE admins SET remember_token='".$db->real_escape_string($token)."' WHERE id=1 LIMIT 1");
        $_SESSION['install_token']=$token;
        header('Location: index.php?step=4&done=1'); exit;
    }
}

function check_reqs($root): array {
    return [
        ['PHP >= 8.0', version_compare(PHP_VERSION,'8.0','>='), PHP_VERSION],
        ['MySQLi',     extension_loaded('mysqli'),               extension_loaded('mysqli')?'Loaded':'Missing'],
        ['cURL',       extension_loaded('curl'),                 extension_loaded('curl')?'Loaded':'Missing'],
        ['OpenSSL',    extension_loaded('openssl'),              extension_loaded('openssl')?'Loaded':'Missing'],
        ['JSON',       extension_loaded('json'),                 extension_loaded('json')?'Loaded':'Missing'],
        ['MBString',   extension_loaded('mbstring'),             extension_loaded('mbstring')?'Loaded':'Missing'],
        ['Writable includes/', is_writable($root.'/includes'),   is_writable($root.'/includes')?'Writable':'Not writable'],
    ];
}
$reqs=$check=check_reqs($root); $all_pass=!in_array(false,array_column($reqs,1));
$err=$_SESSION['install_error']??null; unset($_SESSION['install_error']);
$done=!empty($_GET['done']);
?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Installer — Billing Portal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{--p:#0f172a;--a:#3b82f6;--s:#10b981;--d:#ef4444;--b:#e2e8f0}
*{box-sizing:border-box}body{background:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:32px 16px;font-family:-apple-system,sans-serif}
.iw{width:100%;max-width:680px}
.ih{text-align:center;margin-bottom:32px}
.ilo{display:inline-flex;align-items:center;gap:10px;font-size:22px;font-weight:700;color:var(--p);text-decoration:none;margin-bottom:20px}
.ili{width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,var(--p),var(--a));display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px}
.sb{display:flex;align-items:center;justify-content:center;gap:0;margin-bottom:32px}
.si{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:500;color:#64748b}
.sn{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;border:2px solid var(--b);background:#fff;color:#64748b;transition:all .3s}
.si.active .sn{border-color:var(--a);background:var(--a);color:#fff}.si.done .sn{border-color:var(--s);background:var(--s);color:#fff}.si.active .sl{color:var(--p)}
.sd{flex:1;height:2px;background:var(--b);margin:0 8px;max-width:60px}.sd.done{background:var(--s)}
.ic{background:#fff;border-radius:20px;box-shadow:0 4px 32px rgba(0,0,0,.08);overflow:hidden}
.ich{background:linear-gradient(135deg,var(--p),#1e3a5f);padding:28px 36px;color:#fff}
.ich h2{margin:0;font-size:20px;font-weight:700}.ich p{margin:6px 0 0;opacity:.65;font-size:14px}
.icb{padding:36px}
label{font-size:13px;font-weight:600;color:var(--p);margin-bottom:6px;display:block}
.fc{width:100%;padding:11px 14px;border:1.5px solid var(--b);border-radius:10px;font-size:14px;color:var(--p);transition:border-color .2s,box-shadow .2s}
.fc:focus{outline:none;border-color:var(--a);box-shadow:0 0 0 3px rgba(59,130,246,.12)}
.fh{font-size:12px;color:#64748b;margin-top:5px}
.fr{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.btn-a{background:var(--p);color:#fff;border:none;padding:12px 28px;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;transition:background .2s;display:inline-flex;align-items:center;gap:8px;text-decoration:none}
.btn-a:hover{background:#1e293b}
.btn-b{background:linear-gradient(135deg,var(--a),#06b6d4);color:#fff;border:none;padding:13px 32px;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;width:100%;transition:opacity .2s}
.btn-b:hover{opacity:.9}
.rl{list-style:none;padding:0;margin:0}
.ri{display:flex;align-items:center;justify-content:space-between;padding:11px 16px;border-radius:10px;background:#f8fafc;margin-bottom:8px;font-size:14px}
.rp{color:var(--s);font-weight:600}.rf{color:var(--d);font-weight:600}
.ae{background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px 16px;color:#991b1b;font-size:14px;margin-bottom:20px}
.si-ico{width:72px;height:72px;margin:0 auto 20px;background:linear-gradient(135deg,var(--s),#34d399);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:32px;color:#fff}
.sn-box{background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:16px 20px;margin:20px 0;font-size:13px;color:#92400e}
.sn-box strong{display:block;margin-bottom:6px;font-size:14px}
code{background:#f1f5f9;padding:2px 8px;border-radius:5px;font-size:13px;color:var(--p)}
</style></head><body>
<div class="iw">
<div class="ih">
  <div class="ilo"><div class="ili">⚡</div>Billing Portal</div>
  <div class="sb">
    <?php $labels=['Welcome','Database','Setup','Complete'];
    for($i=1;$i<=4;$i++){$cls=$i<$step?'done':($i===$step?'active':'');$sym=$i<$step?'✓':$i;if($i>1)echo '<div class="sd '.($i<=$step?'done':'').'"></div>';
    echo '<div class="si '.$cls.'"><div class="sn">'.$sym.'</div><span class="sl d-none d-sm-inline">'.$labels[$i-1].'</span></div>';}?>
  </div>
</div>

<div class="ic">
<?php if($step===1):?>
<div class="ich"><h2>Welcome to Billing Portal</h2><p>Let's verify your server requirements before we begin.</p></div>
<div class="icb">
  <ul class="rl">
    <?php foreach($reqs as [$label,$pass,$val]):?>
    <li class="ri"><div><span style="font-weight:500"><?=htmlspecialchars($label)?></span><div style="font-size:12px;color:#64748b"><?=htmlspecialchars($val)?></div></div><span class="<?=$pass?'rp':'rf'?>"><?=$pass?'✓ OK':'✕ Fail'?></span></li>
    <?php endforeach?>
  </ul>
  <?php if(!$all_pass):?><div class="ae">⚠ Some requirements not met. Please resolve above issues.</div>
  <?php else:?><form method="POST" style="margin-top:24px"><input type="hidden" name="action" value="check_requirements"><button type="submit" class="btn-b">Continue to Database Setup →</button></form><?php endif?>
</div>

<?php elseif($step===2):?>
<div class="ich"><h2>Database Configuration</h2><p>Enter your MySQL/MariaDB connection details.</p></div>
<div class="icb">
  <?php if($err):?><div class="ae"><?=htmlspecialchars($err)?></div><?php endif?>
  <form method="POST"><input type="hidden" name="action" value="save_database">
  <div class="fr" style="margin-bottom:16px"><div><label>Database Host</label><input type="text" name="db_host" class="fc" value="localhost" required></div><div><label>Port</label><input type="number" name="db_port" class="fc" value="3306" required></div></div>
  <div style="margin-bottom:16px"><label>Database Name</label><input type="text" name="db_name" class="fc" placeholder="billing_portal" required><div class="fh">Will be created if it doesn't exist.</div></div>
  <div class="fr" style="margin-bottom:24px"><div><label>Username</label><input type="text" name="db_user" class="fc" placeholder="root" required></div><div><label>Password</label><input type="password" name="db_pass" class="fc"></div></div>
  <button type="submit" class="btn-b">Create Tables & Continue →</button></form>
</div>

<?php elseif($step===3):?>
<div class="ich"><h2>Platform Setup</h2><p>Create your admin account and configure basic settings.</p></div>
<div class="icb">
  <?php if($err):?><div class="ae"><?=htmlspecialchars($err)?></div><?php endif?>
  <form method="POST"><input type="hidden" name="action" value="save_admin">
  <div style="margin-bottom:16px"><label>Company / Platform Name</label><input type="text" name="company_name" class="fc" placeholder="My Billing Portal" required></div>
  <div style="margin-bottom:24px"><label>Site URL</label><input type="url" name="site_url" class="fc" value="<?=htmlspecialchars($base)?>" required><div class="fh">Full URL with no trailing slash.</div></div>
  <hr style="margin:0 0 24px;border-color:#f1f5f9">
  <p style="font-weight:600;color:var(--p);margin-bottom:16px">Super Admin Account</p>
  <div style="margin-bottom:16px"><label>Full Name</label><input type="text" name="admin_name" class="fc" placeholder="John Doe" required></div>
  <div style="margin-bottom:16px"><label>Email Address</label><input type="email" name="admin_email" class="fc" placeholder="admin@yourdomain.com" required></div>
  <div class="fr" style="margin-bottom:24px">
    <div><label>Password</label><input type="password" name="admin_password" class="fc" minlength="8" required></div>
    <div><label>Confirm Password</label><input type="password" name="admin_confirm" id="pc" class="fc" required></div>
  </div>
  <button type="submit" class="btn-b">Save & Continue →</button></form>
</div>

<?php elseif($step===4):?>
<div class="ich"><h2>Installation Complete</h2><p>Your Billing Portal is ready.</p></div>
<div class="icb" style="text-align:center">
  <?php if(!$done):?>
  <div class="si-ico">✓</div>
  <h3 style="font-size:20px;font-weight:700;margin-bottom:8px">Almost there!</h3>
  <p style="color:#64748b;margin-bottom:24px">Click below to finalize installation.</p>
  <form method="POST"><input type="hidden" name="action" value="finalize"><button type="submit" class="btn-b">Complete Installation →</button></form>
  <?php else:?>
  <div class="si-ico">🚀</div>
  <h3 style="font-size:22px;font-weight:700;margin-bottom:8px;color:var(--p)">You're all set!</h3>
  <p style="color:#64748b">Billing Portal has been successfully installed.</p>
  <div class="sn-box"><strong>⚠ Security: Delete the installer now!</strong>Remove this directory from your server:<br><code><?=htmlspecialchars(__DIR__)?></code></div>
  <?php $login_url=!empty($_SESSION['install_token'])?$base.'/admin/auto-login.php?token='.$_SESSION['install_token']:$base.'/admin/login.php';?>
  <a href="<?=htmlspecialchars($login_url)?>" class="btn-b" style="display:block;text-decoration:none;margin-top:20px">Go to Admin Panel →</a>
  <a href="<?=htmlspecialchars($base)?>/client/login.php" class="btn-a" style="margin-top:12px;display:inline-block">Client Area</a>
  <?php endif?>
</div>
<?php endif?>
</div>

<p style="text-align:center;color:#64748b;font-size:12px;margin-top:20px">Billing Portal Installer &bull; Secure &bull; Self-hosted</p>
</div>
<script>
const p1=document.querySelector('[name="admin_password"]'),pc=document.getElementById('pc');
if(pc&&p1)pc.addEventListener('input',()=>{pc.style.borderColor=pc.value===p1.value?'#10b981':'#ef4444'});
</script></body></html>
