<?php
require_once '../../includes/config.php';
$admin = Auth::requireAdmin();
$company = DB::setting('company_name', 'Billing Portal');
$cid = (int)get_param('id');
$client = DB::row("SELECT * FROM clients WHERE id=?", 'i', [$cid]);

if (!$client) redirect(BASE_URL . '/admin/clients.php');

$page_title = 'Edit Client: ' . h($client['first_name'] . ' ' . $client['last_name']);
$errors = [];
$success = '';

if (is_post() && csrf_verify()) {
    $fn = trim(post('first_name'));
    $ln = trim(post('last_name'));
    $email = strtolower(trim(post('email')));
    $pw = post('password');
    $ph = trim(post('phone'));
    $co = trim(post('company'));
    $addr1 = trim(post('address1'));
    $city = trim(post('city'));
    $state = trim(post('state'));
    $postcode = trim(post('postcode'));
    $country = trim(post('country'));
    $status = post('status');
    $type = post('account_type');

    if (!$fn || !$ln) $errors[] = 'Name required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';

    // Check if email is already used by another client
    $existing = DB::row("SELECT id FROM clients WHERE email=? AND id != ?", 'si', [$email, $cid]);
    if ($existing) $errors[] = 'Email already registered by another client.';

    if (empty($errors)) {
        if (!empty($pw)) {
            if (strlen($pw) < 8) {
                $errors[] = 'Password must be at least 8 characters.';
            } else {
                $hash = Auth::hashPassword($pw);
                DB::execute("UPDATE clients SET password=? WHERE id=?", 'si', [$hash, $cid]);
            }
        }

        if (empty($errors)) {
            DB::execute(
                "UPDATE clients SET first_name=?, last_name=?, email=?, phone=?, company=?, address1=?, city=?, state=?, postcode=?, country=?, status=?, account_type=? WHERE id=?",
                'ssssssssssssi',
                [$fn, $ln, $email, $ph, $co, $addr1, $city, $state, $postcode, $country, $status, $type, $cid]
            );
            log_activity('admin_edit_client', "Client updated: {$email}", 'admin', $admin['id']);
            $success = 'Client updated successfully.';
            // Refresh client data
            $client = DB::row("SELECT * FROM clients WHERE id=?", 'i', [$cid]);
        }
    }
}

include '../partials/header.php';
?>
<div class="bp-content">
  <div class="d-flex align-items-center gap-3 mb-4">
    <a href="<?= BASE_URL ?>/admin/clients/view.php?id=<?= $cid ?>" class="bp-btn bp-btn-outline bp-btn-sm">← Back to Profile</a>
    <h1 class="bp-page-title" style="margin:0">Edit Client</h1>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert-custom alert-danger mb-3"><span>✕</span>
      <div><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
    </div>
  <?php endif ?>
  <?php if ($success): ?>
    <div class="alert-custom alert-success mb-3"><span>✓</span> <?= h($success) ?></div>
  <?php endif ?>

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="bp-card">
        <div class="bp-card-body">
          <form method="POST">
            <?= csrf_input() ?>
            <div style="font-size:14px; font-weight:700; color:#0f172a; margin-bottom:16px; border-bottom:1px solid #f1f5f9; padding-bottom:8px">Basic Information</div>
            <div class="bp-form-row bp-form-row-2">
              <div class="bp-form-group"><label class="bp-label">First Name *</label><input type="text" name="first_name" class="bp-input" value="<?= h($client['first_name']) ?>" required></div>
              <div class="bp-form-group"><label class="bp-label">Last Name *</label><input type="text" name="last_name" class="bp-input" value="<?= h($client['last_name']) ?>" required></div>
              <div class="bp-form-group"><label class="bp-label">Email *</label><input type="email" name="email" class="bp-input" value="<?= h($client['email']) ?>" required></div>
              <div class="bp-form-group"><label class="bp-label">Phone</label><input type="tel" name="phone" class="bp-input" value="<?= h($client['phone']) ?>"></div>
              <div class="bp-form-group"><label class="bp-label">Company</label><input type="text" name="company" class="bp-input" value="<?= h($client['company']) ?>"></div>
              <div class="bp-form-group"><label class="bp-label">Status</label>
                <select name="status" class="bp-select">
                  <?php foreach (['active', 'pending', 'inactive', 'suspended'] as $st): ?>
                    <option value="<?= $st ?>" <?= $client['status'] === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                  <?php endforeach ?>
                </select>
              </div>
              <div class="bp-form-group"><label class="bp-label">Account Type</label>
                <select name="account_type" class="bp-select">
                  <option value="client" <?= $client['account_type'] === 'client' ? 'selected' : '' ?>>Client</option>
                  <option value="reseller" <?= $client['account_type'] === 'reseller' ? 'selected' : '' ?>>Reseller</option>
                </select>
              </div>
            </div>

            <div style="font-size:14px; font-weight:700; color:#0f172a; margin-top:24px; margin-bottom:16px; border-bottom:1px solid #f1f5f9; padding-bottom:8px">Address Details</div>
            <div class="bp-form-group"><label class="bp-label">Address 1</label><input type="text" name="address1" class="bp-input" value="<?= h($client['address1'] ?? '') ?>"></div>
            <div class="bp-form-row bp-form-row-2">
              <div class="bp-form-group"><label class="bp-label">City</label><input type="text" name="city" class="bp-input" value="<?= h($client['city'] ?? '') ?>"></div>
              <div class="bp-form-group"><label class="bp-label">State</label><input type="text" name="state" class="bp-input" value="<?= h($client['state'] ?? '') ?>"></div>
              <div class="bp-form-group"><label class="bp-label">Postcode</label><input type="text" name="postcode" class="bp-input" value="<?= h($client['postcode'] ?? '') ?>"></div>
              <div class="bp-form-group"><label class="bp-label">Country Code (2 chars)</label><input type="text" name="country" class="bp-input" maxlength="2" value="<?= h($client['country'] ?? 'NG') ?>"></div>
            </div>

            <div style="font-size:14px; font-weight:700; color:#0f172a; margin-top:24px; margin-bottom:16px; border-bottom:1px solid #f1f5f9; padding-bottom:8px">Security</div>
            <div class="bp-form-group">
              <label class="bp-label">Update Password</label>
              <input type="password" name="password" class="bp-input" placeholder="Leave blank to keep existing password">
              <div class="bp-input-hint">Minimum 8 characters.</div>
            </div>

            <button type="submit" class="bp-btn bp-btn-primary" style="margin-top:16px">Update Client Profile</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include '../partials/footer.php'; ?>
