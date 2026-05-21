<?php
class Auth {
    public static function clientLogin(string $email, string $password, bool $remember=false): array {
        $c = DB::row("SELECT * FROM clients WHERE email=?",'s',[$email]);
        if (!$c) return ['success'=>false,'error'=>'Invalid email or password.'];
        if ($c['locked_until'] && strtotime($c['locked_until'])>time()) {
            $m=ceil((strtotime($c['locked_until'])-time())/60);
            return ['success'=>false,'error'=>"Account locked. Try again in {$m} minute(s)."];
        }
        if (!password_verify($password, $c['password'])) { self::incrementAttempts('clients',$c['id']); return ['success'=>false,'error'=>'Invalid email or password.']; }
        if ($c['status']==='suspended') return ['success'=>false,'error'=>'Account suspended. Contact support.'];
        if ($c['two_factor_enabled']) { $_SESSION['2fa_pending_client']=$c['id']; return ['success'=>false,'require_2fa'=>true]; }
        self::setClientSession($c,$remember);
        return ['success'=>true];
    }
    public static function setClientSession(array $c, bool $remember=false): void {
        DB::execute("UPDATE clients SET login_attempts=0,locked_until=NULL,last_login=NOW(),last_login_ip=? WHERE id=?",'si',[get_client_ip(),$c['id']]);
        $_SESSION['client_id']=$c['id']; $_SESSION['client_name']=$c['first_name'].' '.$c['last_name']; $_SESSION['client_email']=$c['email'];
        session_regenerate_id(true);
        if ($remember) { $t=generate_token(); DB::execute("UPDATE clients SET remember_token=? WHERE id=?",'si',[$t,$c['id']]);
            setcookie('client_remember',$c['id'].':'.$t,['expires'=>time()+2592000,'path'=>'/','secure'=>isset($_SERVER['HTTPS']),'httponly'=>true,'samesite'=>'Strict']); }
    }
    public static function requireClient(): array {
        if (!empty($_SESSION['client_id'])) {
            $c=DB::row("SELECT * FROM clients WHERE id=? AND status='active'",'i',[$_SESSION['client_id']]);
            if ($c) return $c;
        }
        if (!empty($_COOKIE['client_remember'])) {
            [$id,$token]=explode(':',$_COOKIE['client_remember'],2)+['',''];
            $c=DB::row("SELECT * FROM clients WHERE id=? AND remember_token=? AND status='active'",'is',[(int)$id,$token]);
            if ($c) { self::setClientSession($c,true); return $c; }
        }
        redirect(BASE_URL.'/client/login.php');
    }
    public static function client(): ?array {
        if (!empty($_SESSION['client_id'])) {
            $c=DB::row("SELECT * FROM clients WHERE id=? AND status='active'",'i',[$_SESSION['client_id']]);
            if ($c) return $c;
        }
        if (!empty($_COOKIE['client_remember'])) {
            [$id,$token]=explode(':',$_COOKIE['client_remember'],2)+['',''];
            $c=DB::row("SELECT * FROM clients WHERE id=? AND remember_token=? AND status='active'",'is',[(int)$id,$token]);
            if ($c) { self::setClientSession($c,true); return $c; }
        }
        return null;
    }
    public static function clientLogout(): void {
        if (!empty($_SESSION['client_id'])) DB::execute("UPDATE clients SET remember_token=NULL WHERE id=?",'i',[$_SESSION['client_id']]);
        setcookie('client_remember','',['expires'=>1,'path'=>'/']);
        session_destroy(); redirect(BASE_URL.'/client/login.php');
    }
    public static function adminLogin(string $email, string $password, bool $remember=false): array {
        $a=DB::row("SELECT * FROM admins WHERE email=?",'s',[$email]);
        if (!$a) return ['success'=>false,'error'=>'Invalid credentials.'];
        if ($a['locked_until'] && strtotime($a['locked_until'])>time()) {
            $m=ceil((strtotime($a['locked_until'])-time())/60);
            return ['success'=>false,'error'=>"Account locked. Try again in {$m} minute(s)."];
        }
        if (!password_verify($password,$a['password'])) { self::incrementAttempts('admins',$a['id']); return ['success'=>false,'error'=>'Invalid credentials.']; }
        if ($a['status']!=='active') return ['success'=>false,'error'=>'Admin account is not active.'];
        if ($a['two_factor_enabled']) { $_SESSION['2fa_pending_admin']=$a['id']; return ['success'=>false,'require_2fa'=>true]; }
        self::setAdminSession($a,$remember);
        return ['success'=>true];
    }
    public static function setAdminSession(array $a, bool $remember=false): void {
        DB::execute("UPDATE admins SET login_attempts=0,locked_until=NULL,last_login=NOW(),last_login_ip=? WHERE id=?",'si',[get_client_ip(),$a['id']]);
        $_SESSION['admin_id']=$a['id']; $_SESSION['admin_name']=$a['name']; $_SESSION['admin_email']=$a['email']; $_SESSION['admin_role']=$a['role_id'];
        session_regenerate_id(true);
        if ($remember) { $t=generate_token(); DB::execute("UPDATE admins SET remember_token=? WHERE id=?",'si',[$t,$a['id']]);
            setcookie('admin_remember',$a['id'].':'.$t,['expires'=>time()+2592000,'path'=>'/admin','secure'=>isset($_SERVER['HTTPS']),'httponly'=>true,'samesite'=>'Strict']); }
    }
    public static function requireAdmin(?string $perm=null): array {
        if (!empty($_SESSION['admin_id'])) {
            $a=DB::row("SELECT * FROM admins WHERE id=? AND status='active'",'i',[$_SESSION['admin_id']]);
            if ($a) { if ($perm && !self::hasPermission($a,$perm)) { http_response_code(403); die('Access denied.'); } return $a; }
        }
        if (!empty($_COOKIE['admin_remember'])) {
            [$id,$token]=explode(':',$_COOKIE['admin_remember'],2)+['',''];
            $a=DB::row("SELECT * FROM admins WHERE id=? AND remember_token=? AND status='active'",'is',[(int)$id,$token]);
            if ($a) { self::setAdminSession($a,true); return $a; }
        }
        redirect(BASE_URL.'/admin/login.php');
    }
    public static function adminLogout(): void {
        if (!empty($_SESSION['admin_id'])) { DB::execute("UPDATE admins SET remember_token=NULL WHERE id=?",'i',[$_SESSION['admin_id']]); log_activity('admin_logout','Admin logged out','admin',$_SESSION['admin_id']); }
        setcookie('admin_remember','',['expires'=>1,'path'=>'/admin']); session_destroy(); redirect(BASE_URL.'/admin/login.php');
    }
    public static function hasPermission(array $admin, string $perm): bool {
        if (!$admin['role_id']) return false;
        $role=DB::row("SELECT permissions FROM admin_roles WHERE id=?",'i',[$admin['role_id']]);
        if (!$role) return false;
        $p=json_decode($role['permissions'],true)??[];
        return !empty($p['all'])||!empty($p[$perm]);
    }
    private static function incrementAttempts(string $table, int $id): void {
        $max=(int)DB::setting('login_max_attempts',5); $lock=(int)DB::setting('login_lockout_minutes',30);
        DB::execute("UPDATE {$table} SET login_attempts=login_attempts+1 WHERE id=?",'i',[$id]);
        $attempts=(int)DB::value("SELECT login_attempts FROM {$table} WHERE id=?",'i',[$id]);
        if ($attempts>=$max) { $until=date('Y-m-d H:i:s',time()+$lock*60); DB::execute("UPDATE {$table} SET locked_until=? WHERE id=?",'si',[$until,$id]); }
    }
    public static function verify2FA(string $secret, string $code): bool {
        $code=preg_replace('/\s/','',$code);
        for ($i=-1;$i<=1;$i++) { if (self::computeTotp($secret,floor(time()/30)+$i)===$code) return true; }
        return false;
    }
    private static function computeTotp(string $secret, int $time): string {
        $key=self::base32Decode($secret);
        $msg=pack('N*',0).pack('N*',$time);
        $hash=hash_hmac('sha1',$msg,$key,true);
        $off=ord($hash[19])&0x0F;
        $code=((ord($hash[$off])&0x7F)<<24)|((ord($hash[$off+1])&0xFF)<<16)|((ord($hash[$off+2])&0xFF)<<8)|(ord($hash[$off+3])&0xFF);
        return str_pad((string)($code%1000000),6,'0',STR_PAD_LEFT);
    }
    public static function generate2FASecret(): string {
        $chars='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $s='';
        for ($i=0;$i<16;$i++) $s.=$chars[random_int(0,31)];
        return $s;
    }
    private static function base32Decode(string $input): string {
        $map='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $input=strtoupper($input); $buf=0; $bits=0; $out='';
        for ($i=0;$i<strlen($input);$i++) { $v=strpos($map,$input[$i]); if ($v===false) continue; $buf=($buf<<5)|$v; $bits+=5; if ($bits>=8) { $bits-=8; $out.=chr(($buf>>$bits)&0xFF); } }
        return $out;
    }
    public static function initiatePasswordReset(string $email, string $table='clients'): mixed {
        $row=DB::row("SELECT id FROM {$table} WHERE email=?",'s',[$email]);
        if (!$row) return false;
        $token=generate_token(); $expires=date('Y-m-d H:i:s',time()+3600);
        DB::execute("UPDATE {$table} SET password_reset_token=?,password_reset_expires=? WHERE id=?",'ssi',[$token,$expires,$row['id']]);
        return $token;
    }
    public static function resetPassword(string $token, string $pw, string $table='clients'): bool {
        $row=DB::row("SELECT id FROM {$table} WHERE password_reset_token=? AND password_reset_expires>NOW()",'s',[$token]);
        if (!$row) return false;
        $hash=password_hash($pw,PASSWORD_BCRYPT,['cost'=>12]);
        DB::execute("UPDATE {$table} SET password=?,password_reset_token=NULL,password_reset_expires=NULL WHERE id=?",'si',[$hash,$row['id']]);
        return true;
    }
    public static function hashPassword(string $pw): string { return password_hash($pw,PASSWORD_BCRYPT,['cost'=>12]); }
    public static function isAdminLoggedIn(): bool { return !empty($_SESSION['admin_id']); }
    public static function isClientLoggedIn(): bool { return !empty($_SESSION['client_id']); }

    public static function googleLogin(array $data): array {
        $google_id = $data['id'];
        $email = strtolower(trim($data['email']));
        $first_name = $data['given_name'] ?? 'Google';
        $last_name = $data['family_name'] ?? 'User';

        // 1. Try finding by Google ID
        $c = DB::row("SELECT * FROM clients WHERE google_id=?", 's', [$google_id]);

        // 2. If not found, try finding by email
        if (!$c) {
            $c = DB::row("SELECT * FROM clients WHERE email=?", 's', [$email]);
            if ($c) {
                // Link account
                DB::execute("UPDATE clients SET google_id=? WHERE id=?", 'si', [$google_id, $c['id']]);
            }
        }

        // 3. If still not found, create new account
        if (!$c) {
            $dummy_pass = password_hash(generate_token(16), PASSWORD_BCRYPT);
            DB::execute(
                "INSERT INTO clients (first_name, last_name, email, password, google_id, status, email_verified) VALUES (?, ?, ?, ?, ?, 'active', 1)",
                'sssss', [$first_name, $last_name, $email, $dummy_pass, $google_id]
            );
            $new_id = DB::lastInsertId();
            $c = DB::row("SELECT * FROM clients WHERE id=?", 'i', [$new_id]);
        }

        if ($c['status'] === 'suspended') {
            return ['success' => false, 'error' => 'Account suspended. Contact support.'];
        }

        self::setClientSession($c);
        log_activity('client_login_google', "Logged in via Google", 'client', $c['id']);
        return ['success' => true];
    }
}
