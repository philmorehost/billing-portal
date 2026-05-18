<?php
require_once __DIR__ . '/includes/config.php';

// Allow admins through
if (!empty($_SESSION['admin_id'])) {
    redirect(BASE_URL . '/admin/');
}

$company  = DB::setting('company_name', 'Billing Portal');
$is_on    = DB::setting('maintenance_mode', '0') === '1';

if (!$is_on) {
    redirect(BASE_URL . '/client/');
}

http_response_code(503);
header('Retry-After: 3600');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Maintenance — <?= htmlspecialchars($company) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  background: #0f172a;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 32px 16px;
  color: #fff;
  overflow: hidden;
  position: relative;
}
/* Animated background */
body::before {
  content: '';
  position: fixed;
  inset: 0;
  background: radial-gradient(circle at 20% 50%, rgba(59,130,246,.15) 0%, transparent 50%),
              radial-gradient(circle at 80% 20%, rgba(6,182,212,.1) 0%, transparent 40%);
  pointer-events: none;
}
.card {
  position: relative;
  background: rgba(255,255,255,.05);
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 24px;
  padding: 60px 48px;
  text-align: center;
  max-width: 520px;
  width: 100%;
  backdrop-filter: blur(12px);
}
.icon-wrap {
  width: 96px; height: 96px;
  border-radius: 28px;
  background: linear-gradient(135deg, #3b82f6, #06b6d4);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 44px;
  margin: 0 auto 28px;
  animation: float 3s ease-in-out infinite;
}
@keyframes float {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-10px); }
}
h1 { font-size: 32px; font-weight: 800; margin-bottom: 12px; letter-spacing: -.5px; }
.subtitle {
  color: rgba(255,255,255,.6);
  font-size: 16px;
  line-height: 1.7;
  margin-bottom: 36px;
}
.status-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-bottom: 28px;
}
.status-dot {
  width: 10px; height: 10px;
  border-radius: 50%;
  background: #f59e0b;
  animation: pulse 1.5s ease-in-out infinite;
}
@keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(1.3)} }
.status-text { color: #f59e0b; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; }
.progress-wrap { background: rgba(255,255,255,.1); border-radius: 100px; height: 4px; overflow: hidden; margin-bottom: 36px; }
.progress-bar { height: 100%; background: linear-gradient(90deg, #3b82f6, #06b6d4); border-radius: 100px; animation: progress 2s ease-in-out infinite alternate; }
@keyframes progress { 0%{width:30%} 100%{width:90%} }
.contact {
  font-size: 13px;
  color: rgba(255,255,255,.4);
}
.contact a { color: rgba(255,255,255,.7); text-decoration: none; font-weight: 500; }
.contact a:hover { color: #fff; }
.brand { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 40px; }
.brand-icon { width: 36px; height: 36px; border-radius: 10px; background: linear-gradient(135deg, #3b82f6, #06b6d4); display: flex; align-items: center; justify-content: center; font-size: 18px; }
.brand-name { font-size: 18px; font-weight: 700; }
</style>
</head>
<body>
<div class="card">
  <div class="brand">
    <div class="brand-icon">⚡</div>
    <div class="brand-name"><?= htmlspecialchars($company) ?></div>
  </div>

  <div class="icon-wrap">🔧</div>
  <h1>We'll be back soon</h1>
  <p class="subtitle">
    We're currently performing scheduled maintenance to improve your experience.
    This won't take long — thank you for your patience.
  </p>

  <div class="status-row">
    <div class="status-dot"></div>
    <span class="status-text">Maintenance in progress</span>
  </div>

  <div class="progress-wrap">
    <div class="progress-bar"></div>
  </div>

  <div class="contact">
    Need urgent help?
    <a href="mailto:<?= htmlspecialchars(DB::setting('company_email', 'support@example.com')) ?>">
      <?= htmlspecialchars(DB::setting('company_email', 'Contact support')) ?>
    </a>
  </div>
</div>
</body>
</html>
