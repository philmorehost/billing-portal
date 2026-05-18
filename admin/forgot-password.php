<?php
require_once '../includes/config.php';
$company = DB::setting('company_name', 'Billing Portal');
$error = ''; $success = '';

if (is_post() && csrf_verify()) {
    $email = strtolower(trim(post('email')));
    $token = Auth::initiatePasswordReset($email, 'admins');
    if ($token) {
        $reset_url = BASE_URL . '/admin/reset-password.php?token=' . $token;
        Mailer::sendTemplate($email, $email, 'password_reset', [
            'client_name' => $email,
            'reset_url'   => $reset_url,
        ]);
    }
    $success = 'If that email is registered, a reset link has been sent.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Forgot Password — <?= h($company) ?> Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
<style>
body{background:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:32px 16px;}
.fp-card{background:#fff;border-radius:20px;box-shadow:0 4px 40px rgba(0,0,0,.09);padding:40px;max-width:420px;width:100%;}
.fp-card h2{font-size:20px;font-weight:700;margin-bottom:6px;}
.fp-card p{color:#64748b;font-size:14px;margin-bottom:28px;}
.form-label{font-size:13px;font-weight:600;color:#0f172a;}
.form-control{border:1.5px solid #e2e8f0;border-radius:10px;padding:11px 14px;font-size:14px;}
.form-control:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1);}
.btn-send{background:#0f172a;color:#fff;width:100%;padding:13px;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;}
</style>
</head>
<body>
<div class="fp-card">
  <h2>Reset admin password</h2>
  <p>Enter your admin email to receive a reset link.</p>
  <?php if ($error): ?><div class="alert-custom alert-danger mb-3"><span class="alert-icon">✕</span> <?= h($error) ?></div><?php endif ?>
  <?php if ($success): ?><div class="alert-custom alert-success mb-3"><span class="alert-icon">✓</span> <?= h($success) ?></div><?php endif ?>
  <form method="POST">
    <?= csrf_input() ?>
    <div class="mb-4">
      <label class="form-label">Admin Email</label>
      <input type="email" name="email" class="form-control" placeholder="admin@yourdomain.com" required autofocus>
    </div>
    <button type="submit" class="btn-send">Send Reset Link</button>
  </form>
  <div style="text-align:center;margin-top:20px;font-size:13px">
    <a href="<?= BASE_URL ?>/admin/login.php" style="color:#3b82f6;text-decoration:none">← Back to login</a>
  </div>
</div>
</body>
</html>
