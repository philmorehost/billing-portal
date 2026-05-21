<?php
require_once '../includes/config.php';

if (DB::setting('google_auth_enabled') !== '1') {
    redirect(BASE_URL . '/client/login.php');
}

$client_id     = DB::setting('google_auth_client_id');
$client_secret = DB::setting('google_auth_client_secret');
$redirect_uri  = BASE_URL . '/client/login-google.php';

// 1. Initial Redirect to Google
if (!isset($_GET['code'])) {
    $state = generate_token(16);
    $_SESSION['google_oauth_state'] = $state;

    $params = [
        'client_id'     => $client_id,
        'redirect_uri'  => $redirect_uri,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'access_type'   => 'online',
        'prompt'        => 'select_account',
        'state'         => $state
    ];
    redirect('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
}

// 2. Handle Callback
$code = $_GET['code'];
$state = $_GET['state'] ?? '';

if (empty($state) || empty($_SESSION['google_oauth_state']) || $state !== $_SESSION['google_oauth_state']) {
    unset($_SESSION['google_oauth_state']);
    redirect_with_flash(BASE_URL . '/client/login.php', 'danger', 'Google login failed: Invalid OAuth state.');
}
unset($_SESSION['google_oauth_state']);

// Exchange code for Access Token
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'client_id'     => $client_id,
    'client_secret' => $client_secret,
    'redirect_uri'  => $redirect_uri,
    'code'          => $code,
    'grant_type'    => 'authorization_code',
]));
$resp = curl_exec($ch);
curl_close($ch);

$token_data = json_decode($resp, true);
if (empty($token_data['access_token'])) {
    redirect_with_flash(BASE_URL . '/client/login.php', 'danger', 'Google login failed: Invalid response from Google.');
}

// Fetch User Info
$ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token_data['access_token']]);
$resp = curl_exec($ch);
curl_close($ch);

$user_data = json_decode($resp, true);
if (empty($user_data['id']) || empty($user_data['email'])) {
    redirect_with_flash(BASE_URL . '/client/login.php', 'danger', 'Google login failed: Could not retrieve user profile.');
}

// 3. Authenticate
$res = Auth::googleLogin($user_data);

if ($res['success']) {
    redirect(BASE_URL . '/client/');
} else {
    redirect_with_flash(BASE_URL . '/client/login.php', 'danger', $res['error'] ?? 'Google login failed.');
}
