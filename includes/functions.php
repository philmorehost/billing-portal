<?php
// PHP 8.0 String Polyfills for backward compatibility with PHP 7.x
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || mb_strpos($haystack, $needle) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        return $needle === '' || $needle === mb_substr($haystack, -mb_strlen($needle));
    }
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrf_input(): string { return '<input type="hidden" name="csrf_token" value="'.csrf_token().'">'; }
function csrf_verify(): bool { return hash_equals(csrf_token(), $_POST['csrf_token'] ?? ''); }
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES|ENT_HTML5, 'UTF-8'); }
function generate_token(int $b=32): string { return bin2hex(random_bytes($b)); }
function redirect(string $url, int $code=302): void { header("Location: $url", true, $code); exit; }
function redirect_with_flash(string $url, string $type, string $msg): void {
    $_SESSION['flash'] = ['type'=>$type,'message'=>$msg]; redirect($url);
}
function get_flash(): ?array {
    if (!empty($_SESSION['flash'])) { $f=$_SESSION['flash']; unset($_SESSION['flash']); return $f; }
    return null;
}
function flash_html(): string {
    $f = get_flash(); if (!$f) return '';
    $icons = ['success'=>'✓','danger'=>'✕','warning'=>'⚠','info'=>'ℹ'];
    return sprintf('<div class="alert-custom alert-%s alert-dismissible fade show mb-3" role="alert"><span class="alert-icon">%s</span> %s<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>',
        h($f['type']), $icons[$f['type']]??'ℹ', h($f['message']));
}
function format_currency(float $a, string $c='NGN'): string {
    $sym=['NGN'=>'₦','USD'=>'$','GBP'=>'£','EUR'=>'€'];
    return ($sym[$c]??$c.' ').number_format($a,2);
}
function format_date(string $d, string $fmt='d M Y'): string {
    if (!$d||$d==='0000-00-00') return '—';
    return date($fmt, strtotime($d));
}
function time_ago(string $dt): string {
    $diff = time()-strtotime($dt);
    if ($diff<60) return 'just now';
    if ($diff<3600) return floor($diff/60).'m ago';
    if ($diff<86400) return floor($diff/3600).'h ago';
    return date('d M Y', strtotime($dt));
}
function generate_invoice_number(): string {
    $prefix = DB::setting('invoice_prefix','INV');
    $last   = DB::value("SELECT invoice_number FROM invoices ORDER BY id DESC LIMIT 1");
    preg_match('/(\d+)$/', $last??'', $m);
    $next = isset($m[1]) ? (int)$m[1]+1 : 1001;
    return $prefix.'-'.str_pad($next,5,'0',STR_PAD_LEFT);
}
function generate_order_number(): string { return 'ORD-'.strtoupper(substr(uniqid(),-8)); }
function generate_ticket_number(): string { return 'TKT-'.strtoupper(substr(uniqid(),-6)); }
function get_client_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) { $ip=trim(explode(',',$_SERVER[$k])[0]); if (filter_var($ip,FILTER_VALIDATE_IP)) return $ip; }
    }
    return '0.0.0.0';
}
function is_post(): bool { return $_SERVER['REQUEST_METHOD']==='POST'; }
function post(string $k, mixed $d=''): mixed { return $_POST[$k]??$d; }
function get_param(string $k, mixed $d=''): mixed { return $_GET[$k]??$d; }
function paginate(int $total, int $per, int $cur): array {
    $tp = max(1,(int)ceil($total/$per));
    return ['total'=>$total,'per_page'=>$per,'current'=>$cur,'total_pages'=>$tp,'offset'=>max(0,($cur-1)*$per),'has_prev'=>$cur>1,'has_next'=>$cur<$tp];
}
function log_activity(string $action, string $desc='', string $actor='system', int $id=0): void {
    try { DB::execute("INSERT INTO activity_log (actor_type,actor_id,action,description,ip_address) VALUES (?,?,?,?,?)",'sisss',[$actor,$id,$action,$desc,get_client_ip()]); } catch(Exception $e){}
}
function active_nav(string $path): string {
    return str_contains($_SERVER['REQUEST_URI']??'', $path) ? 'active' : '';
}
function json_response(array $data, int $code=200): void {
    http_response_code($code); header('Content-Type: application/json'); echo json_encode($data); exit;
}
function slug(string $s): string {
    return preg_replace('/-+/','-',preg_replace('/[^a-z0-9-]/','',strtolower(trim($s))));
}
