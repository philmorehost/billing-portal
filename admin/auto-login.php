<?php
require_once '../includes/config.php';
$token = trim(get_param('token'));
if ($token) {
    $admin = DB::row("SELECT * FROM admins WHERE remember_token=? AND status='active'",'s',[$token]);
    if ($admin) { Auth::setAdminSession($admin); redirect(BASE_URL.'/admin/'); }
}
redirect(BASE_URL.'/admin/login.php');
