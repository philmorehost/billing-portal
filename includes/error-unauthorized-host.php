<?php http_response_code(400); $host=$_SERVER['HTTP_HOST']??'this domain'; ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>400 — Domain Not Configured</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>*{box-sizing:border-box}body{margin:0;font-family:-apple-system,sans-serif;background:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:32px 16px}
.ec{background:#fff;border-radius:20px;box-shadow:0 8px 48px rgba(0,0,0,.1);max-width:560px;width:100%;overflow:hidden}
.eh{background:linear-gradient(135deg,#0f172a,#1e3a5f);padding:36px 40px;text-align:center}
.ec{font-size:72px;font-weight:900;color:transparent;background:linear-gradient(135deg,#fff,rgba(255,255,255,.4));-webkit-background-clip:text;background-clip:text;line-height:1;margin-bottom:8px}
.et{color:#fff;font-size:18px;font-weight:600;margin:0}
.eb{padding:36px 40px}
.ei{width:56px;height:56px;border-radius:16px;background:#fff1f2;display:flex;align-items:center;justify-content:center;font-size:26px;margin:0 auto 20px}
.em{text-align:center;color:#374151;font-size:15px;line-height:1.7;margin-bottom:24px}
.ed{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px;font-family:monospace;font-size:14px;color:#ef4444;text-align:center;margin-bottom:28px;word-break:break-all}
.bs{display:block;background:#0f172a;color:#fff;text-align:center;text-decoration:none;padding:13px 28px;border-radius:10px;font-size:15px;font-weight:700}
.bs:hover{background:#1e293b;color:#fff}
.ef{padding:16px 40px 24px;text-align:center;font-size:12px;color:#94a3b8;border-top:1px solid #f1f5f9}</style>
</head><body><div class="ec">
<div class="eh"><div class="ec">400</div><p class="et">Domain Not Configured</p></div>
<div class="eb">
  <div class="ei">⚠️</div>
  <p class="em">The domain you're trying to access has not been approved or configured on this platform.</p>
  <div class="ed"><?=htmlspecialchars($host,ENT_QUOTES|ENT_HTML5,'UTF-8')?></div>
  <p style="font-size:13px;font-weight:600;color:#0f172a;margin-bottom:12px">This can happen because:</p>
  <ul style="font-size:13px;color:#374151;padding-left:20px;margin-bottom:24px">
    <li style="margin-bottom:8px">Your domain's CNAME is pointing to this server but hasn't been registered in the reseller portal yet.</li>
    <li style="margin-bottom:8px">Your reseller account may be pending approval or suspended.</li>
    <li>The domain may have been removed or misconfigured.</li>
  </ul>
  <a href="mailto:support@example.com?subject=Domain+Not+Configured:+<?=urlencode($host)?>" class="bs">📧 Contact Support</a>
</div>
<div class="ef">If you are the reseller, log in to your portal and verify your custom domain settings.</div>
</div></body></html>
