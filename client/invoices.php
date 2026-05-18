<?php
require_once '../includes/config.php';
$client   = Auth::requireClient();
$company  = DB::setting('company_name', 'Billing Portal');
$currency = DB::setting('base_currency', 'NGN');
$page_title = 'My Invoices';

$status   = get_param('status');
$page_num = max(1, (int) get_param('page', 1));
$per_page = 15;

$where  = ['client_id = ?'];
$params = [$client['id']];
$types  = 'i';

if ($status) { $where[] = 'status = ?'; $params[] = $status; $types .= 's'; }

$where_sql = implode(' AND ', $where);
$total     = (int) DB::value("SELECT COUNT(*) FROM invoices WHERE {$where_sql}", $types, $params);
$pg        = paginate($total, $per_page, $page_num);
$invoices  = DB::rows(
    "SELECT * FROM invoices WHERE {$where_sql} ORDER BY id DESC LIMIT {$per_page} OFFSET {$pg['offset']}",
    $types, $params
);

include 'partials/header.php';
?>
<div class="bp-content">
  <h1 class="bp-page-title">My Invoices</h1>
  <?= flash_html() ?>

  <!-- Filter tabs -->
  <div class="d-flex gap-2 mb-4 flex-wrap">
    <a href="invoices.php" class="bp-btn bp-btn-<?= !$status ? 'primary' : 'outline' ?> bp-btn-sm">All</a>
    <?php foreach (['unpaid'=>'⚠ Unpaid','overdue'=>'🔴 Overdue','paid'=>'✓ Paid','cancelled'=>'Cancelled'] as $k=>$label): ?>
    <a href="?status=<?= $k ?>" class="bp-btn bp-btn-<?= $status===$k?'primary':'outline' ?> bp-btn-sm"><?= $label ?></a>
    <?php endforeach ?>
  </div>

  <div class="bp-card">
    <?php if ($invoices): ?>
    <table class="bp-table">
      <thead>
        <tr><th>Invoice</th><th>Amount</th><th>Due Date</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($invoices as $inv):
          $sb = ['paid'=>'success','unpaid'=>'warning','overdue'=>'danger','cancelled'=>'muted','refunded'=>'info'];
        ?>
        <tr>
          <td>
            <div style="font-weight:600;color:#0f172a">#<?= h($inv['invoice_number']) ?></div>
            <div style="font-size:12px;color:#64748b"><?= format_date($inv['created_at']) ?></div>
          </td>
          <td style="font-weight:700;font-size:15px"><?= format_currency($inv['total'], $inv['currency']) ?></td>
          <td style="font-size:13px;color:<?= $inv['status']==='overdue'?'#ef4444':'#64748b' ?>;font-weight:<?= $inv['status']==='overdue'?'600':'400' ?>">
            <?= format_date($inv['due_date']) ?>
          </td>
          <td><span class="bp-badge bp-badge-<?= $sb[$inv['status']] ?? 'muted' ?>"><?= $inv['status'] ?></span></td>
          <td>
            <div class="d-flex gap-1">
              <a href="invoices/view.php?id=<?= $inv['id'] ?>" class="bp-btn bp-btn-outline bp-btn-sm">View</a>
              <?php if (in_array($inv['status'], ['unpaid','overdue'])): ?>
              <a href="invoices/pay.php?id=<?= $inv['id'] ?>" class="bp-btn bp-btn-accent bp-btn-sm">Pay Now</a>
              <?php endif ?>
              <a href="invoices/print.php?id=<?= $inv['id'] ?>" class="bp-btn bp-btn-outline bp-btn-sm" target="_blank">PDF</a>
            </div>
          </td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="bp-empty">
      <div class="bp-empty-icon">🧾</div>
      <div class="bp-empty-title">No invoices found</div>
      <div class="bp-empty-text"><?= $status ? 'No ' . $status . ' invoices.' : 'Your invoices will appear here.' ?></div>
    </div>
    <?php endif ?>
  </div>

  <?php if ($pg['total_pages'] > 1): ?>
  <div class="bp-pagination">
    <?php if ($pg['has_prev']): ?><a href="?page=<?= $page_num-1 ?>&status=<?= urlencode($status) ?>" class="bp-page-btn">‹</a><?php endif ?>
    <?php for ($i = max(1,$page_num-2); $i <= min($pg['total_pages'],$page_num+2); $i++): ?>
    <a href="?page=<?= $i ?>&status=<?= urlencode($status) ?>" class="bp-page-btn <?= $i===$page_num?'active':'' ?>"><?= $i ?></a>
    <?php endfor ?>
    <?php if ($pg['has_next']): ?><a href="?page=<?= $page_num+1 ?>&status=<?= urlencode($status) ?>" class="bp-page-btn">›</a><?php endif ?>
  </div>
  <?php endif ?>
</div>
<?php include 'partials/footer.php'; ?>
