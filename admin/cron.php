<?php
require_once '../includes/config.php';
$admin      = Auth::requireAdmin();
$company    = DB::setting('company_name', 'Billing Portal');
$page_title = 'Cron Jobs';

// Toggle enable/disable
if (is_post() && csrf_verify()) {
    $action = post('action');
    $slug   = post('slug');

    if ($action === 'toggle' && $slug) {
        $current = DB::value("SELECT enabled FROM cron_jobs WHERE slug=?", 's', [$slug]);
        DB::execute("UPDATE cron_jobs SET enabled=? WHERE slug=?", 'is', [(int)!$current, $slug]);
        redirect_with_flash('cron.php', 'success', 'Cron job updated.');
    }
    if ($action === 'run_now' && $slug) {
        redirect(BASE_URL . '/cron/run.php?key=' . urlencode(DB::setting('cron_key','')) . '&job=' . urlencode($slug));
    }
}

$jobs = DB::rows("SELECT * FROM cron_jobs ORDER BY id");
include 'partials/header.php';
?>
<div class="bp-content">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h1 class="bp-page-title" style="margin:0">Cron Jobs</h1>
      <p class="bp-page-sub" style="margin:4px 0 0">Automated scheduled tasks</p>
    </div>
  </div>
  <?= flash_html() ?>

  <!-- Crontab instructions -->
  <div class="bp-card mb-4" style="border-left:4px solid #3b82f6">
    <div class="bp-card-body">
      <strong style="font-size:14px">📋 Setup Instructions</strong>
      <p style="margin:8px 0 6px;font-size:13px;color:#374151">Add this single crontab entry to run all scheduled jobs automatically every minute:</p>
      <code style="display:block;background:#0f172a;color:#e2e8f0;padding:12px 16px;border-radius:8px;font-size:13px">
        * * * * * php <?= h(ROOT_PATH) ?>/cron/run.php >> /var/log/billing-cron.log 2>&1
      </code>
    </div>
  </div>

  <div class="bp-card">
    <table class="bp-table">
      <thead>
        <tr><th>Job</th><th>Frequency</th><th>Last Run</th><th>Last Status</th><th>Next Run</th><th>Enabled</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($jobs as $job):
          $status_badge = ['success'=>'success','failed'=>'danger','running'=>'warning'];
        ?>
        <tr>
          <td>
            <div style="font-weight:600"><?= h($job['name']) ?></div>
            <div style="font-size:12px;color:#64748b"><?= h($job['description'] ?? '') ?></div>
            <?php if ($job['last_output']): ?>
            <div style="font-size:11px;color:#94a3b8;margin-top:2px"><?= h(mb_strimwidth($job['last_output'], 0, 80, '…')) ?></div>
            <?php endif ?>
          </td>
          <td><span class="bp-badge bp-badge-info"><?= h($job['frequency']) ?></span></td>
          <td style="font-size:13px;color:#64748b"><?= $job['last_run'] ? time_ago($job['last_run']) : '—' ?></td>
          <td>
            <?php if ($job['last_status']): ?>
            <span class="bp-badge bp-badge-<?= $status_badge[$job['last_status']] ?? 'muted' ?>"><?= $job['last_status'] ?></span>
            <?php else: ?><span style="color:#94a3b8">—</span><?php endif ?>
          </td>
          <td style="font-size:13px;color:#64748b"><?= $job['next_run'] ? format_date($job['next_run'], 'd M Y H:i') : '—' ?></td>
          <td>
            <form method="POST" style="display:inline">
              <?= csrf_input() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="slug" value="<?= h($job['slug']) ?>">
              <button type="submit" class="bp-btn bp-btn-sm <?= $job['enabled'] ? 'bp-btn-success' : 'bp-btn-outline' ?>">
                <?= $job['enabled'] ? '✓ On' : '✕ Off' ?>
              </button>
            </form>
          </td>
          <td>
            <form method="POST" style="display:inline">
              <?= csrf_input() ?>
              <input type="hidden" name="action" value="run_now">
              <input type="hidden" name="slug" value="<?= h($job['slug']) ?>">
              <button type="submit" class="bp-btn bp-btn-outline bp-btn-sm">▶ Run Now</button>
            </form>
          </td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>
<?php include 'partials/footer.php'; ?>
