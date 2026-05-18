<?php
require_once '../includes/config.php';
$admin    = Auth::requireAdmin();
$company  = DB::setting('company_name', 'Billing Portal');
$page_title = 'Clients';

// Filters
$search   = trim(get_param('q'));
$status   = get_param('status');
$type     = get_param('type');
$page_num = max(1, (int)get_param('page', 1));
$per_page = 20;

$where  = ['1=1'];
$params = [];
$types  = '';

if ($search) {
    $where[]  = "(c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR c.company LIKE ?)";
    $s = "%{$search}%";
    $params   = array_merge($params, [$s, $s, $s, $s]);
    $types   .= 'ssss';
}
if ($status) {
    $where[]  = "c.status = ?";
    $params[] = $status;
    $types   .= 's';
}
if ($type) {
    $where[]  = "c.account_type = ?";
    $params[] = $type;
    $types   .= 's';
}

$where_sql = implode(' AND ', $where);
$total     = (int)DB::value("SELECT COUNT(*) FROM clients c WHERE {$where_sql}", $types, $params);
$pg        = paginate($total, $per_page, $page_num);
$clients   = DB::rows(
    "SELECT c.*, 
     (SELECT COUNT(*) FROM services s WHERE s.client_id=c.id AND s.status='active') AS active_services,
     (SELECT COUNT(*) FROM invoices i WHERE i.client_id=c.id AND i.status='unpaid') AS unpaid_invoices
     FROM clients c WHERE {$where_sql} ORDER BY c.id DESC LIMIT {$per_page} OFFSET {$pg['offset']}",
    $types, $params
);

include 'partials/header.php';
?>
<div class="bp-content">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h1 class="bp-page-title" style="margin:0">Clients</h1>
      <p class="bp-page-sub" style="margin:4px 0 0"><?= number_format($total) ?> total clients</p>
    </div>
    <a href="<?= BASE_URL ?>/admin/clients/add.php" class="bp-btn bp-btn-primary">➕ Add Client</a>
  </div>

  <?= flash_html() ?>

  <!-- Filters -->
  <div class="bp-card mb-4">
    <div class="bp-card-body" style="padding:16px 22px">
      <form method="GET" class="d-flex flex-wrap gap-3 align-items-end">
        <div style="flex:1;min-width:200px">
          <label class="bp-label">Search</label>
          <input type="text" name="q" class="bp-input" placeholder="Name, email, company…" value="<?= h($search) ?>">
        </div>
        <div>
          <label class="bp-label">Status</label>
          <select name="status" class="bp-select">
            <option value="">All Statuses</option>
            <?php foreach (['active','inactive','suspended','pending'] as $s): ?>
            <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div>
          <label class="bp-label">Type</label>
          <select name="type" class="bp-select">
            <option value="">All Types</option>
            <option value="client"   <?= $type==='client'?'selected':'' ?>>Client</option>
            <option value="reseller" <?= $type==='reseller'?'selected':'' ?>>Reseller</option>
          </select>
        </div>
        <div class="d-flex gap-2">
          <button type="submit" class="bp-btn bp-btn-primary">Filter</button>
          <?php if ($search||$status||$type): ?>
          <a href="clients.php" class="bp-btn bp-btn-outline">Clear</a>
          <?php endif ?>
        </div>
      </form>
    </div>
  </div>

  <!-- Table -->
  <div class="bp-card">
    <?php if ($clients): ?>
    <table class="bp-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Client</th>
          <th>Type</th>
          <th>Services</th>
          <th>Unpaid</th>
          <th>Credit</th>
          <th>Status</th>
          <th>Joined</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($clients as $c):
          $status_badge = ['active'=>'success','inactive'=>'muted','suspended'=>'danger','pending'=>'warning'];
        ?>
        <tr>
          <td style="color:#94a3b8;font-size:13px"><?= $c['id'] ?></td>
          <td>
            <div style="font-weight:600"><?= h($c['first_name'].' '.$c['last_name']) ?></div>
            <div style="font-size:12px;color:#64748b"><?= h($c['email']) ?></div>
            <?php if ($c['company']): ?><div style="font-size:11px;color:#94a3b8"><?= h($c['company']) ?></div><?php endif ?>
          </td>
          <td><span class="bp-badge bp-badge-<?= $c['account_type']==='reseller'?'info':'muted' ?>"><?= $c['account_type'] ?></span></td>
          <td style="font-weight:600"><?= $c['active_services'] ?></td>
          <td><?= $c['unpaid_invoices'] > 0 ? '<span style="color:#ef4444;font-weight:700">'.$c['unpaid_invoices'].'</span>' : '<span style="color:#94a3b8">0</span>' ?></td>
          <td><?= format_currency($c['credit_balance'], DB::setting('base_currency','NGN')) ?></td>
          <td><span class="bp-badge bp-badge-<?= $status_badge[$c['status']] ?? 'muted' ?>"><?= $c['status'] ?></span></td>
          <td style="font-size:13px;color:#64748b"><?= format_date($c['created_at']) ?></td>
          <td>
            <div class="d-flex gap-1">
              <a href="clients/view.php?id=<?= $c['id'] ?>" class="bp-btn bp-btn-outline bp-btn-sm">View</a>
              <a href="clients/edit.php?id=<?= $c['id'] ?>" class="bp-btn bp-btn-outline bp-btn-sm">Edit</a>
            </div>
          </td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="bp-empty">
      <div class="bp-empty-icon">👥</div>
      <div class="bp-empty-title">No clients found</div>
      <div class="bp-empty-text"><?= $search ? 'Try a different search.' : 'Add your first client to get started.' ?></div>
    </div>
    <?php endif ?>
  </div>

  <!-- Pagination -->
  <?php if ($pg['total_pages'] > 1): ?>
  <div class="bp-pagination">
    <?php if ($pg['has_prev']): ?><a href="?page=<?= $page_num-1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>" class="bp-page-btn">‹</a><?php endif ?>
    <?php for ($i = max(1,$page_num-2); $i <= min($pg['total_pages'],$page_num+2); $i++): ?>
    <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>" class="bp-page-btn <?= $i===$page_num?'active':'' ?>"><?= $i ?></a>
    <?php endfor ?>
    <?php if ($pg['has_next']): ?><a href="?page=<?= $page_num+1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>" class="bp-page-btn">›</a><?php endif ?>
  </div>
  <?php endif ?>
</div>

<?php include 'partials/footer.php'; ?>
