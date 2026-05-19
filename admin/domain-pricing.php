<?php
require_once '../includes/config.php';
$admin=Auth::requireAdmin();
$company=DB::setting('company_name','Billing Portal');
$page_title='Domain Pricing Management';
$currency=DB::setting('base_currency','NGN');
$success=''; $error='';

// 1. Sync from ConnectReseller (Fully corrected parsing structure for responseMsg list)
if(is_post() && post('action') === 'sync_tlds' && csrf_verify()) {
    $apiKey = DB::setting('module_connectreseller_api_key');
    if (empty($apiKey)) {
        $error = "Please configure your ConnectReseller API Key in Settings first.";
    } else {
        $url = "https://api.connectreseller.com/ConnectReseller/ESHOP/tldsync/?APIKey=" . urlencode($apiKey);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200 || !$response) {
            $error = "ConnectReseller TLD sync failed: HTTP code {$http_code} or no response.";
        } else {
            $data = json_decode($response, true);
            $tldList = [];
            
            if (is_array($data)) {
                // ConnectReseller standard: TLD list is array in the 'responseMsg' key
                if (isset($data['responseMsg']) && is_array($data['responseMsg'])) {
                    $first = reset($data['responseMsg']);
                    if (is_array($first) && (isset($first['tld']) || isset($first['registrationPrice']))) {
                        $tldList = $data['responseMsg'];
                    } else if (isset($data['responseMsg']['statusCode']) && $data['responseMsg']['statusCode'] != 200) {
                        $error = "ConnectReseller API Error: " . ($data['responseMsg']['message'] ?? 'Unknown');
                    }
                }
                
                // Fallback to 'responseData' wrapper
                if (empty($tldList) && empty($error) && isset($data['responseData']) && is_array($data['responseData'])) {
                    $tldList = $data['responseData'];
                }
                
                // Fallback to root-level array
                if (empty($tldList) && empty($error)) {
                    $first = reset($data);
                    if (is_array($first) && (isset($first['tld']) || isset($first['registrationPrice']))) {
                        $tldList = $data;
                    }
                }
            } else {
                $error = "Invalid API response from ConnectReseller.";
            }

            if (empty($error) && !empty($tldList)) {
                $synced = 0;
                foreach ($tldList as $item) {
                    $tld = strtolower(trim($item['tld'] ?? ''));
                    if (empty($tld)) continue;
                    $tld = ltrim($tld, '.');

                    $base_reg = (float)($item['registrationPrice'] ?? 0);
                    $base_ren = (float)($item['renewalPrice'] ?? 0);
                    $base_tr  = (float)($item['transferPrice'] ?? 0);

                    $existing = DB::row("SELECT * FROM domain_tlds WHERE tld=?", 's', [$tld]);
                    if ($existing) {
                        $val = (float)$existing['markup_value'];
                        $type = $existing['markup_type'];
                        
                        if ($type === 'percentage') {
                            $retail_reg = round($base_reg * (1 + $val / 100), 2);
                            $retail_ren = round($base_ren * (1 + $val / 100), 2);
                            $retail_tr  = round($base_tr * (1 + $val / 100), 2);
                        } else {
                            $retail_reg = round($base_reg + $val, 2);
                            $retail_ren = round($base_ren + $val, 2);
                            $retail_tr  = round($base_tr + $val, 2);
                        }

                        DB::execute(
                            "UPDATE domain_tlds SET registrar='connectreseller', base_price_register=?, base_price_renew=?, base_price_transfer=?, retail_price_register=?, retail_price_renew=?, retail_price_transfer=? WHERE tld=?",
                            'dddddds', [$base_reg, $base_ren, $base_tr, $retail_reg, $retail_ren, $retail_tr, $tld]
                        );
                    } else {
                        $val = 20.00;
                        $retail_reg = round($base_reg * 1.20, 2);
                        $retail_ren = round($base_ren * 1.20, 2);
                        $retail_tr  = round($base_tr * 1.20, 2);

                        DB::execute(
                            "INSERT INTO domain_tlds (tld, registrar, base_price_register, base_price_renew, base_price_transfer, markup_type, markup_value, retail_price_register, retail_price_renew, retail_price_transfer, status) VALUES (?, 'connectreseller', ?,?,?, 'percentage', ?,?,?,?, 'active')",
                            'sddddddd', [$tld, $base_reg, $base_ren, $base_tr, $val, $retail_reg, $retail_ren, $retail_tr]
                        );
                    }
                    $synced++;
                }
                $success = "Successfully synchronized {$synced} domain extension(s) from ConnectReseller!";
            } else if (empty($error)) {
                $error = "No active domain extensions found in the API response.";
            }
        }
    }
}

// 2. Sync from ResellerClub
if(is_post() && post('action') === 'sync_resellerclub' && csrf_verify()) {
    $resellerId = DB::setting('module_resellerclub_reseller_id');
    $apiKey = DB::setting('module_resellerclub_api_key');
    $testMode = DB::setting('module_resellerclub_test_mode') === '1';

    if (empty($resellerId) || empty($apiKey)) {
        $error = "Please configure your ResellerClub Reseller ID and API Key in Settings first.";
    } else {
        $base = $testMode ? 'https://test.httpapi.com/api' : 'https://httpapi.com/api';
        $url = "{$base}/products/reseller-cost-price.json?auth-userid=" . urlencode($resellerId) . "&api-key=" . urlencode($apiKey);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200 || !$response) {
            $error = "ResellerClub TLD sync failed: HTTP code {$http_code} or no response.";
        } else {
            $data = json_decode($response, true);
            if (!is_array($data)) {
                $error = "Invalid API response from ResellerClub.";
            } else {
                $synced = 0;
                foreach ($data as $key => $item) {
                    if (!str_starts_with($key, 'dom') || !isset($item['addnewdomain']['1'])) continue;
                    
                    $tld = strtolower(substr($key, 3));
                    if (empty($tld)) continue;
                    
                    $base_reg = (float)($item['addnewdomain']['1'] ?? 0);
                    $base_ren = (float)($item['renewdomain']['1'] ?? 0);
                    $base_tr  = (float)($item['addtransferdomain']['1'] ?? 0);
                    
                    if ($base_reg <= 0) continue;
                    
                    $existing = DB::row("SELECT * FROM domain_tlds WHERE tld=?", 's', [$tld]);
                    if ($existing) {
                        $val = (float)$existing['markup_value'];
                        $type = $existing['markup_type'];
                        
                        if ($type === 'percentage') {
                            $retail_reg = round($base_reg * (1 + $val / 100), 2);
                            $retail_ren = round($base_ren * (1 + $val / 100), 2);
                            $retail_tr  = round($base_tr * (1 + $val / 100), 2);
                        } else {
                            $retail_reg = round($base_reg + $val, 2);
                            $retail_ren = round($base_ren + $val, 2);
                            $retail_tr  = round($base_tr + $val, 2);
                        }
                        
                        DB::execute(
                            "UPDATE domain_tlds SET registrar='resellerclub', base_price_register=?, base_price_renew=?, base_price_transfer=?, retail_price_register=?, retail_price_renew=?, retail_price_transfer=? WHERE tld=?",
                            'dddddds', [$base_reg, $base_ren, $base_tr, $retail_reg, $retail_ren, $retail_tr, $tld]
                        );
                    } else {
                        $val = 20.00;
                        $retail_reg = round($base_reg * 1.20, 2);
                        $retail_ren = round($base_ren * 1.20, 2);
                        $retail_tr  = round($base_tr * 1.20, 2);
                        
                        DB::execute(
                            "INSERT INTO domain_tlds (tld, registrar, base_price_register, base_price_renew, base_price_transfer, markup_type, markup_value, retail_price_register, retail_price_renew, retail_price_transfer, status) VALUES (?, 'resellerclub', ?,?,?, 'percentage', ?,?,?,?, 'active')",
                            'sddddddd', [$tld, $base_reg, $base_ren, $base_tr, $val, $retail_reg, $retail_ren, $retail_tr]
                        );
                    }
                    $synced++;
                }
                $success = "Successfully synchronized {$synced} domain extension(s) from ResellerClub!";
            }
        }
    }
}

// 2b. Sync from Upperlink
if(is_post() && post('action') === 'sync_upperlink' && csrf_verify()) {
    $apiKey = DB::setting('module_upperlink_api_key');
    if (empty($apiKey)) {
        $error = "Please configure your Upperlink API Key in Settings first.";
    } else {
        $username = DB::setting('module_upperlink_username');
        if (empty($username)) {
            $username = DB::setting('company_email', '');
        }
        
        $timeStr = gmdate("y-m-d H");
        $hash = hash_hmac("sha256", $apiKey, "{$username}:{$timeStr}");
        $token = base64_encode($hash);
        
        $url = 'https://client.upperlink.ng/clients/modules/addons/DomainsReseller/api/index.php/tlds/pricing';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "username: {$username}",
            "token: {$token}"
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200 || !$response) {
            $error = "Upperlink TLD sync failed: HTTP code {$http_code} or no response.";
        } else {
            $data = json_decode($response, true);
            $tldList = [];
            
            if (is_array($data)) {
                if (isset($data['tlds']) && is_array($data['tlds'])) {
                    foreach ($data['tlds'] as $tldName => $prices) {
                        $tldList[] = [
                            'tld' => $tldName,
                            'register' => $prices['register'] ?? ($prices['registration'] ?? 0),
                            'renew' => $prices['renew'] ?? ($prices['renewal'] ?? 0),
                            'transfer' => $prices['transfer'] ?? 0
                        ];
                    }
                } elseif (isset($data['success']) && isset($data['data']) && is_array($data['data'])) {
                    foreach ($data['data'] as $row) {
                        if (isset($row['tld'])) {
                            $tldList[] = [
                                'tld' => $row['tld'],
                                'register' => $row['register'] ?? ($row['registrationPrice'] ?? 0),
                                'renew' => $row['renew'] ?? ($row['renewalPrice'] ?? 0),
                                'transfer' => $row['transfer'] ?? ($row['transferPrice'] ?? 0)
                            ];
                        }
                    }
                } else {
                    $first = reset($data);
                    if (is_array($first) && (isset($first['tld']) || isset($first['register']))) {
                        foreach ($data as $row) {
                            $tldList[] = [
                                'tld' => $row['tld'] ?? '',
                                'register' => $row['register'] ?? ($row['registration'] ?? 0),
                                'renew' => $row['renew'] ?? ($row['renewal'] ?? 0),
                                'transfer' => $row['transfer'] ?? 0
                            ];
                        }
                    }
                }
            }
            
            if (empty($tldList)) {
                $error = "Could not parse TLD list from Upperlink response.";
            } else {
                $synced = 0;
                foreach ($tldList as $item) {
                    $tld = strtolower(trim($item['tld'] ?? ''));
                    if (empty($tld)) continue;
                    $tld = ltrim($tld, '.');
                    
                    $base_reg = (float)($item['register'] ?? 0);
                    $base_ren = (float)($item['renew'] ?? 0);
                    $base_tr  = (float)($item['transfer'] ?? 0);
                    
                    if ($base_reg <= 0) continue;
                    
                    $existing = DB::row("SELECT * FROM domain_tlds WHERE tld=?", 's', [$tld]);
                    if ($existing) {
                        $val = (float)$existing['markup_value'];
                        $type = $existing['markup_type'];
                        
                        if ($type === 'percentage') {
                            $retail_reg = round($base_reg * (1 + $val / 100), 2);
                            $retail_ren = round($base_ren * (1 + $val / 100), 2);
                            $retail_tr  = round($base_tr * (1 + $val / 100), 2);
                        } else {
                            $retail_reg = round($base_reg + $val, 2);
                            $retail_ren = round($base_ren + $val, 2);
                            $retail_tr  = round($base_tr + $val, 2);
                        }
                        
                        DB::execute(
                            "UPDATE domain_tlds SET registrar='upperlink', base_price_register=?, base_price_renew=?, base_price_transfer=?, retail_price_register=?, retail_price_renew=?, retail_price_transfer=? WHERE tld=?",
                            'dddddds', [$base_reg, $base_ren, $base_tr, $retail_reg, $retail_ren, $retail_tr, $tld]
                        );
                    } else {
                        $val = 20.00;
                        $retail_reg = round($base_reg * 1.20, 2);
                        $retail_ren = round($base_ren * 1.20, 2);
                        $retail_tr  = round($base_tr * 1.20, 2);
                        
                        DB::execute(
                            "INSERT INTO domain_tlds (tld, registrar, base_price_register, base_price_renew, base_price_transfer, markup_type, markup_value, retail_price_register, retail_price_renew, retail_price_transfer, status) VALUES (?, 'upperlink', ?,?,?, 'percentage', ?,?,?,?, 'active')",
                            'sddddddd', [$tld, $base_reg, $base_ren, $base_tr, $val, $retail_reg, $retail_ren, $retail_tr]
                        );
                    }
                    $synced++;
                }
                $success = "Successfully synchronized {$synced} domain extension(s) from Upperlink!";
            }
        }
    }
}

// 3. Save All Inline Features & Registrars (WHMCS Style bulk update)
if(is_post() && post('action') === 'save_all_tlds' && csrf_verify()) {
    $tld_data = $_POST['tlds'] ?? [];
    if (is_array($tld_data)) {
        foreach ($tld_data as $id => $data) {
            $id = (int)$id;
            $registrar = trim($data['registrar'] ?? 'none');
            $dns = isset($data['dns_management']) ? 1 : 0;
            $email = isset($data['email_forwarding']) ? 1 : 0;
            $id_prot = isset($data['id_protection']) ? 1 : 0;
            $epp = isset($data['epp_code']) ? 1 : 0;
            $status = isset($data['status']) ? 'active' : 'inactive';

            DB::execute(
                "UPDATE domain_tlds SET registrar=?, dns_management=?, email_forwarding=?, id_protection=?, epp_code=?, status=? WHERE id=?",
                'siiiisi', [$registrar, $dns, $email, $id_prot, $epp, $status, $id]
            );
        }
        $success = "All domain features, routings, and statuses saved successfully!";
    }
}

// 4. Save Single TLD Pricing Modal Config
if(is_post() && post('action') === 'save_tld_pricing' && csrf_verify()) {
    $tld_id = (int)post('tld_id');
    $base_reg = (float)post('base_price_register');
    $base_ren = (float)post('base_price_renew');
    $base_tr  = (float)post('base_price_transfer');
    
    $markup_type = post('markup_type') === 'fixed' ? 'fixed' : 'percentage';
    $markup_val = (float)post('markup_value');
    
    $retail_reg = (float)post('retail_price_register');
    $retail_ren = (float)post('retail_price_renew');
    $retail_tr  = (float)post('retail_price_transfer');
    
    if ($retail_reg <= 0) {
        $retail_reg = ($markup_type === 'percentage') ? round($base_reg * (1 + $markup_val / 100), 2) : round($base_reg + $markup_val, 2);
    }
    if ($retail_ren <= 0) {
        $retail_ren = ($markup_type === 'percentage') ? round($base_ren * (1 + $markup_val / 100), 2) : round($base_ren + $markup_val, 2);
    }
    if ($retail_tr <= 0) {
        $retail_tr = ($markup_type === 'percentage') ? round($base_tr * (1 + $markup_val / 100), 2) : round($base_tr + $markup_val, 2);
    }

    DB::execute(
        "UPDATE domain_tlds SET base_price_register=?, base_price_renew=?, base_price_transfer=?, markup_type=?, markup_value=?, retail_price_register=?, retail_price_renew=?, retail_price_transfer=? WHERE id=?",
        'ddddsdddi', [$base_reg, $base_ren, $base_tr, $markup_type, $markup_val, $retail_reg, $retail_ren, $retail_tr, $tld_id]
    );
    $success = "Pricing structures updated successfully.";
}

// 5. Add Custom TLD Inline
if(is_post() && post('action') === 'add_tld_inline' && csrf_verify()) {
    $tld = strtolower(trim(post('tld')));
    $tld = ltrim($tld, '.');
    
    if (empty($tld)) {
        $error = "Domain extension name cannot be empty.";
    } else {
        $existing = DB::row("SELECT id FROM domain_tlds WHERE tld=?", 's', [$tld]);
        if ($existing) {
            $error = "The extension .{$tld} already exists.";
        } else {
            $registrar = trim(post('registrar', 'none'));
            $dns = isset($_POST['dns_management']) ? 1 : 0;
            $email = isset($_POST['email_forwarding']) ? 1 : 0;
            $id_prot = isset($_POST['id_protection']) ? 1 : 0;
            $epp = isset($_POST['epp_code']) ? 1 : 0;
            
            DB::execute(
                "INSERT INTO domain_tlds (tld, registrar, base_price_register, base_price_renew, base_price_transfer, markup_type, markup_value, retail_price_register, retail_price_renew, retail_price_transfer, dns_management, email_forwarding, id_protection, epp_code, status) VALUES (?,?,0.00,0.00,0.00,'percentage',20.00,0.00,0.00,0.00,?,?,?,?, 'active')",
                'ssiiii', [$tld, $registrar, $dns, $email, $id_prot, $epp]
            );
            $success = "Successfully added domain extension .{$tld}!";
        }
    }
}

// 6. Delete TLD Extension
if(is_post() && post('action') === 'delete_tld' && csrf_verify()) {
    $tld_id = (int)post('tld_id');
    DB::execute("DELETE FROM domain_tlds WHERE id=?", 'i', [$tld_id]);
    $success = "Domain extension deleted successfully.";
}

// 6b. Duplicate TLD Extension
if(is_post() && post('action') === 'duplicate_tld' && csrf_verify()) {
    $new_tld = strtolower(trim(post('new_tld')));
    $new_tld = ltrim($new_tld, '.');
    
    if (empty($new_tld)) {
        $error = "New domain extension name cannot be empty.";
    } else {
        $existing = DB::row("SELECT id FROM domain_tlds WHERE tld=?", 's', [$new_tld]);
        if ($existing) {
            $error = "The extension .{$new_tld} already exists.";
        } else {
            $source_id = (int)post('source_tld_id');
            $source = DB::row("SELECT * FROM domain_tlds WHERE id=?", 'i', [$source_id]);
            
            if (!$source) {
                $error = "Source TLD not found.";
            } else {
                $registrar = $source['registrar'];
                $dns = (int)$source['dns_management'];
                $email = (int)$source['email_forwarding'];
                $id_prot = (int)$source['id_protection'];
                $epp = (int)$source['epp_code'];
                
                $base_reg = (float)post('base_price_register');
                $base_ren = (float)post('base_price_renew');
                $base_tr  = (float)post('base_price_transfer');
                
                $markup_type = post('markup_type') === 'fixed' ? 'fixed' : 'percentage';
                $markup_val = (float)post('markup_value');
                
                $retail_reg = (float)post('retail_price_register');
                $retail_ren = (float)post('retail_price_renew');
                $retail_tr  = (float)post('retail_price_transfer');
                
                if ($retail_reg <= 0) {
                    $retail_reg = ($markup_type === 'percentage') ? round($base_reg * (1 + $markup_val / 100), 2) : round($base_reg + $markup_val, 2);
                }
                if ($retail_ren <= 0) {
                    $retail_ren = ($markup_type === 'percentage') ? round($base_ren * (1 + $markup_val / 100), 2) : round($base_ren + $markup_val, 2);
                }
                if ($retail_tr <= 0) {
                    $retail_tr = ($markup_type === 'percentage') ? round($base_tr * (1 + $markup_val / 100), 2) : round($base_tr + $markup_val, 2);
                }
                
                DB::execute(
                    "INSERT INTO domain_tlds (tld, registrar, base_price_register, base_price_renew, base_price_transfer, markup_type, markup_value, retail_price_register, retail_price_renew, retail_price_transfer, dns_management, email_forwarding, id_protection, epp_code, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'active')",
                    'ssdddsddddiiii', [$new_tld, $registrar, $base_reg, $base_ren, $base_tr, $markup_type, $markup_val, $retail_reg, $retail_ren, $retail_tr, $dns, $email, $id_prot, $epp]
                );
                $success = "Successfully duplicated .{$source['tld']} to .{$new_tld} with custom pricing!";
            }
        }
    }
}

// 7. Bulk Apply Markup Action
if(is_post() && post('action') === 'bulk_markup' && csrf_verify()) {
    $markup_val = (float)post('bulk_markup_value');
    $markup_type = post('bulk_markup_type') === 'fixed' ? 'fixed' : 'percentage';
    
    $tlds = DB::rows("SELECT * FROM domain_tlds");
    $updated = 0;
    foreach ($tlds as $row) {
        $base_reg = (float)$row['base_price_register'];
        $base_ren = (float)$row['base_price_renew'];
        $base_tr  = (float)$row['base_price_transfer'];

        if ($markup_type === 'percentage') {
            $retail_reg = round($base_reg * (1 + $markup_val / 100), 2);
            $retail_ren = round($base_ren * (1 + $markup_val / 100), 2);
            $retail_tr  = round($base_tr * (1 + $markup_val / 100), 2);
        } else {
            $retail_reg = round($base_reg + $markup_val, 2);
            $retail_ren = round($base_ren + $markup_val, 2);
            $retail_tr  = round($base_tr + $markup_val, 2);
        }

        DB::execute(
            "UPDATE domain_tlds SET markup_type=?, markup_value=?, retail_price_register=?, retail_price_renew=?, retail_price_transfer=? WHERE id=?",
            'sddddi', [$markup_type, $markup_val, $retail_reg, $retail_ren, $retail_tr, $row['id']]
        );
        $updated++;
    }
    $success = "Successfully bulk-applied {$markup_val}" . ($markup_type==='percentage'?'%':'') . " markup to all {$updated} active TLD(s).";
}

$tlds = DB::rows("SELECT * FROM domain_tlds ORDER BY tld ASC");
include 'partials/header.php';
?>
<div class="bp-content">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px">
    <div>
      <h1 class="bp-page-title" style="margin-bottom:4px">🌐 WHMCS Domain Pricing & Registrar Config</h1>
      <p class="bp-page-sub">Configure auto-registration registrars, TLD pricing models, and client features (DNS, Forwarding, EPP) on a per-extension basis.</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <form method="POST" style="display:inline-block">
        <?=csrf_input()?>
        <input type="hidden" name="action" value="sync_tlds">
        <button type="submit" class="bp-btn bp-btn-primary">
          🔄 Sync ConnectReseller TLDs
        </button>
      </form>
      <form method="POST" style="display:inline-block">
        <?=csrf_input()?>
        <input type="hidden" name="action" value="sync_resellerclub">
        <button type="submit" class="bp-btn bp-btn-accent">
          🔄 Sync ResellerClub TLDs
        </button>
      </form>
      <form method="POST" style="display:inline-block">
        <?=csrf_input()?>
        <input type="hidden" name="action" value="sync_upperlink">
        <button type="submit" class="bp-btn bp-btn-success" style="background-color:#10b981;border-color:#10b981">
          🔄 Sync Upperlink TLDs
        </button>
      </form>
    </div>
  </div>

  <?php if($error):?><div class="alert-custom alert-danger mb-3"><span>✕</span> <?=h($error)?></div><?php endif?>
  <?php if($success):?><div class="alert-custom alert-success mb-3"><span>✓</span> <?=h($success)?></div><?php endif?>

  <div class="row g-4">
    <!-- Grid System containing main configurations -->
    <div class="col-lg-9">
      <div class="bp-card">
        <div class="bp-card-header">
          <h3 class="bp-card-title">Domain TLD List & Auto-Registration Settings</h3>
        </div>
        
        <form method="POST" id="whmcs-pricing-form">
          <?=csrf_input()?>
          <input type="hidden" name="action" value="save_all_tlds">
          
          <div style="overflow-x:auto">
            <table class="bp-table" style="min-width: 900px;">
              <thead>
                <tr>
                  <th style="width:50px">Active</th>
                  <th>TLD</th>
                  <th style="width:200px;text-align:center">Pricing & Actions</th>
                  <th style="text-align:center">DNS Management</th>
                  <th style="text-align:center">Email Forwarding</th>
                  <th style="text-align:center">ID Protection</th>
                  <th style="text-align:center">EPP Code</th>
                  <th>Auto Registration</th>
                  <th style="text-align:right">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if(empty($tlds)):?>
                  <tr>
                    <td colspan="9" style="text-align:center;padding:40px;color:#94a3b8">
                      <div style="font-size:32px;margin-bottom:8px">📡</div>
                      <strong>No domain extensions found.</strong><br>Use one of the "Sync" buttons above or use the "Add TLD" row below to initialize.
                    </td>
                  </tr>
                <?php else: foreach($tlds as $t):?>
                  <tr>
                    <td style="text-align:center">
                      <input type="checkbox" name="tlds[<?=$t['id']?>][status]" value="active" <?=$t['status']==='active'?'checked':''?> style="transform:scale(1.2);cursor:pointer">
                    </td>
                    <td style="font-weight:700;color:#0f172a;font-family:monospace;font-size:16px">.<?=h($t['tld'])?></td>
                    <td style="text-align:center">
                      <div style="display:flex;gap:4px;justify-content:center">
                        <button type="button" class="bp-btn bp-btn-outline bp-btn-sm" style="font-size:11px;padding:4px 8px;border-radius:4px" onclick='openPricingModal(<?=json_encode($t, JSON_HEX_APOS | JSON_HEX_QUOT)?>)'>
                          💲 Pricing
                        </button>
                        <button type="button" class="bp-btn bp-btn-outline bp-btn-sm" style="font-size:11px;padding:4px 8px;border-radius:4px;color:#8b5cf6;border-color:rgba(139,92,246,0.3)" onclick='openDuplicateModal(<?=json_encode($t, JSON_HEX_APOS | JSON_HEX_QUOT)?>)'>
                          📋 Duplicate
                        </button>
                      </div>
                    </td>
                    <td style="text-align:center">
                      <input type="checkbox" name="tlds[<?=$t['id']?>][dns_management]" value="1" <?=$t['dns_management']?'checked':''?> style="transform:scale(1.2);cursor:pointer">
                    </td>
                    <td style="text-align:center">
                      <input type="checkbox" name="tlds[<?=$t['id']?>][email_forwarding]" value="1" <?=$t['email_forwarding']?'checked':''?> style="transform:scale(1.2);cursor:pointer">
                    </td>
                    <td style="text-align:center">
                      <input type="checkbox" name="tlds[<?=$t['id']?>][id_protection]" value="1" <?=$t['id_protection']?'checked':''?> style="transform:scale(1.2);cursor:pointer">
                    </td>
                    <td style="text-align:center">
                      <input type="checkbox" name="tlds[<?=$t['id']?>][epp_code]" value="1" <?=$t['epp_code']?'checked':''?> style="transform:scale(1.2);cursor:pointer">
                    </td>
                    <td>
                      <select name="tlds[<?=$t['id']?>][registrar]" class="bp-select" style="padding:4px 8px;font-size:12px;height:auto">
                        <option value="none" <?=$t['registrar']==='none'?'selected':''?>>None (Manual)</option>
                        <option value="connectreseller" <?=$t['registrar']==='connectreseller'?'selected':''?>>ConnectReseller</option>
                        <option value="resellerclub" <?=$t['registrar']==='resellerclub'?'selected':''?>>ResellerClub</option>
                        <option value="upperlink" <?=$t['registrar']==='upperlink'?'selected':''?>>Upperlink (.NG)</option>
                      </select>
                    </td>
                    <td style="text-align:right">
                      <button type="button" class="bp-btn bp-btn-outline bp-btn-sm" style="color:#ef4444;border-color:rgba(239,68,68,0.2)" onclick="confirmDelete(<?=$t['id']?>, '.<?=h($t['tld'])?>')">
                        ✕
                      </button>
                    </td>
                  </tr>
                <?php endforeach; endif;?>
                
                <!-- Bottom "Add TLD" Row in Grid (WHMCS Style) -->
                <tr style="background:#f8fafc;border-top:2px solid #e2e8f0">
                  <td style="text-align:center">💡</td>
                  <td>
                    <input type="text" id="add-tld-name" placeholder="eg. com" class="bp-input" style="padding:4px 8px;font-size:12px;height:auto;font-family:monospace">
                  </td>
                  <td style="text-align:center;font-size:11px;color:#94a3b8">New TLD</td>
                  <td style="text-align:center">
                    <input type="checkbox" id="add-dns" value="1" checked style="transform:scale(1.2);cursor:pointer">
                  </td>
                  <td style="text-align:center">
                    <input type="checkbox" id="add-email" value="1" checked style="transform:scale(1.2);cursor:pointer">
                  </td>
                  <td style="text-align:center">
                    <input type="checkbox" id="add-id" value="1" style="transform:scale(1.2);cursor:pointer">
                  </td>
                  <td style="text-align:center">
                    <input type="checkbox" id="add-epp" value="1" checked style="transform:scale(1.2);cursor:pointer">
                  </td>
                  <td>
                    <select id="add-registrar" class="bp-select" style="padding:4px 8px;font-size:12px;height:auto">
                      <option value="none">None (Manual)</option>
                      <option value="connectreseller">ConnectReseller</option>
                      <option value="resellerclub">ResellerClub</option>
                      <option value="upperlink">Upperlink (.NG)</option>
                    </select>
                  </td>
                  <td style="text-align:right">
                    <button type="button" class="bp-btn bp-btn-success bp-btn-sm" style="padding:4px 10px;font-size:11px" onclick="submitAddInline()">
                      ➕ Add
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          
          <div style="display:flex;justify-content:space-between;padding:16px 20px;background:#f8fafc;border-top:1px solid #e2e8f0;border-bottom-left-radius:12px;border-bottom-right-radius:12px">
            <span style="font-size:12px;color:#64748b;align-self:center">💡 Don't forget to click Save Changes to persist checkbox & registrar changes!</span>
            <button type="submit" class="bp-btn bp-btn-primary">
              💾 Save Changes
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Bulk Markup & Sidebar Features -->
    <div class="col-lg-3">
      <div class="bp-card mb-4">
        <div class="bp-card-header"><h3 class="bp-card-title">📈 Bulk Profit Markup</h3></div>
        <div class="bp-card-body">
          <form method="POST">
            <?=csrf_input()?>
            <input type="hidden" name="action" value="bulk_markup">
            
            <div class="bp-form-group">
              <label class="bp-label">Markup Method</label>
              <select name="bulk_markup_type" class="bp-select">
                <option value="percentage">Percentage (%)</option>
                <option value="fixed">Fixed Flat Margin (<?=h($currency)?>)</option>
              </select>
            </div>
            
            <div class="bp-form-group">
              <label class="bp-label">Profit Markup Amount</label>
              <input type="number" name="bulk_markup_value" class="bp-input" step="0.01" min="0" placeholder="e.g. 20" required>
              <div class="bp-input-hint">This profit rule will bulk-apply over base costs across all extensions.</div>
            </div>
            
            <button type="submit" class="bp-btn bp-btn-accent" style="width:100%;justify-content:center" onclick="return confirm('Bulk apply this markup to ALL extensions? This will recalculate customer retail prices.')">
              Apply Profit Margin
            </button>
          </form>
        </div>
      </div>
      
      <div class="bp-card">
        <div class="bp-card-header"><h3 class="bp-card-title">🔌 Domain Registrars</h3></div>
        <div class="bp-card-body" style="font-size:13px;color:#64748b;line-height:1.6">
          <p>You can manage credentials and endpoints for registrars inside the <strong>Settings</strong> page.</p>
          <hr style="border:0;border-top:1px solid #e2e8f0;margin:12px 0">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
            <span style="color:#10b981">●</span> ConnectReseller: Active
          </div>
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
            <span style="color:#10b981">●</span> ResellerClub: Configured
          </div>
          <div style="display:flex;align-items:center;gap:8px">
            <span style="color:#10b981">●</span> Upperlink: Active
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Helper forms for inline Add and inline Delete -->
<form method="POST" id="inline-add-form" style="display:none">
  <?=csrf_input()?>
  <input type="hidden" name="action" value="add_tld_inline">
  <input type="hidden" name="tld" id="form-add-tld">
  <input type="hidden" name="registrar" id="form-add-registrar">
  <input type="checkbox" name="dns_management" id="form-add-dns" value="1">
  <input type="checkbox" name="email_forwarding" id="form-add-email" value="1">
  <input type="checkbox" name="id_protection" id="form-add-id" value="1">
  <input type="checkbox" name="epp_code" id="form-add-epp" value="1">
</form>

<form method="POST" id="delete-tld-form" style="display:none">
  <?=csrf_input()?>
  <input type="hidden" name="action" value="delete_tld">
  <input type="hidden" name="tld_id" id="delete-tld-id">
</form>

<!-- Pricing Modal Dialog (Interactive Pricing configuration) -->
<div id="pricing-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(15,23,42,0.4);backdrop-filter:blur(3px);z-index:999;align-items:center;justify-content:center">
  <div class="bp-card" style="width:520px;max-width:95%">
    <div class="bp-card-header">
      <h3 class="bp-card-title">Configure Pricing: <span id="pricing-tld-label" style="font-family:monospace;color:#3b82f6">.com</span></h3>
      <button type="button" class="bp-btn bp-btn-outline bp-btn-sm" onclick="closePricingModal()">✕</button>
    </div>
    <div class="bp-card-body" style="max-height:80vh;overflow-y:auto">
      <form method="POST">
        <?=csrf_input()?>
        <input type="hidden" name="action" value="save_tld_pricing">
        <input type="hidden" name="tld_id" id="pricing-tld-id">

        <div style="font-weight:700;font-size:12px;text-transform:uppercase;color:#64748b;margin-bottom:12px;border-bottom:1px solid #e2e8f0;padding-bottom:6px">💰 Wholesale Cost Prices (USD)</div>
        <div class="row">
          <div class="col-md-4">
            <div class="bp-form-group">
              <label class="bp-label">Register Cost ($)</label>
              <input type="number" name="base_price_register" id="p-base-reg" class="bp-input" step="0.01" required>
            </div>
          </div>
          <div class="col-md-4">
            <div class="bp-form-group">
              <label class="bp-label">Renew Cost ($)</label>
              <input type="number" name="base_price_renew" id="p-base-ren" class="bp-input" step="0.01" required>
            </div>
          </div>
          <div class="col-md-4">
            <div class="bp-form-group">
              <label class="bp-label">Transfer Cost ($)</label>
              <input type="number" name="base_price_transfer" id="p-base-tr" class="bp-input" step="0.01" required>
            </div>
          </div>
        </div>

        <div style="font-weight:700;font-size:12px;text-transform:uppercase;color:#64748b;margin-bottom:12px;border-bottom:1px solid #e2e8f0;padding-bottom:6px">📈 Profit Rules & Markups</div>
        <div class="row">
          <div class="col-md-6">
            <div class="bp-form-group">
              <label class="bp-label">Markup Type</label>
              <select name="markup_type" id="p-markup-type" class="bp-select">
                <option value="percentage">Percentage (%)</option>
                <option value="fixed">Fixed flat margin (<?=h($currency)?>)</option>
              </select>
            </div>
          </div>
          <div class="col-md-6">
            <div class="bp-form-group">
              <label class="bp-label">Profit Markup Value</label>
              <input type="number" name="markup_value" id="p-markup-val" class="bp-input" step="0.01" required>
            </div>
          </div>
        </div>

        <div style="font-weight:700;font-size:12px;text-transform:uppercase;color:#64748b;margin-bottom:12px;border-bottom:1px solid #e2e8f0;padding-bottom:6px">🏷 Retail Selling Prices (<?=h($currency)?>)</div>
        <div class="row">
          <div class="col-md-4">
            <div class="bp-form-group">
              <label class="bp-label">Register Price</label>
              <input type="number" name="retail_price_register" id="p-retail-reg" class="bp-input" step="0.01" required>
              <div class="bp-input-hint">Enter custom or 0 to auto-compute</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="bp-form-group">
              <label class="bp-label">Renewal Price</label>
              <input type="number" name="retail_price_renew" id="p-retail-ren" class="bp-input" step="0.01" required>
            </div>
          </div>
          <div class="col-md-4">
            <div class="bp-form-group">
              <label class="bp-label">Transfer Price</label>
              <input type="number" name="retail_price_transfer" id="p-retail-tr" class="bp-input" step="0.01" required>
            </div>
          </div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:24px">
          <button type="button" class="bp-btn bp-btn-outline" onclick="closePricingModal()">Cancel</button>
          <button type="submit" class="bp-btn bp-btn-primary">Save Pricing</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Duplicate Modal Dialog -->
<div id="duplicate-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(15,23,42,0.4);backdrop-filter:blur(3px);z-index:999;align-items:center;justify-content:center">
  <div class="bp-card" style="width:520px;max-width:95%">
    <div class="bp-card-header">
      <h3 class="bp-card-title">👯 Duplicate TLD: <span id="duplicate-tld-label" style="font-family:monospace;color:#8b5cf6">.com</span></h3>
      <button type="button" class="bp-btn bp-btn-outline bp-btn-sm" onclick="closeDuplicateModal()">✕</button>
    </div>
    <div class="bp-card-body" style="max-height:80vh;overflow-y:auto">
      <form method="POST">
        <?=csrf_input()?>
        <input type="hidden" name="action" value="duplicate_tld">
        <input type="hidden" name="source_tld_id" id="duplicate-source-id">

        <div class="bp-form-group" style="background:#f1f5f9;padding:12px;border-radius:8px;margin-bottom:16px">
          <label class="bp-label" style="color:#0f172a;font-weight:700">New TLD Extension Name</label>
          <div style="display:flex;align-items:center;gap:8px">
            <span style="font-weight:700;font-size:16px;color:#64748b">.</span>
            <input type="text" name="new_tld" id="dup-new-tld" class="bp-input" style="font-family:monospace;font-size:16px" placeholder="e.g. net or com.ng" required>
          </div>
          <div class="bp-input-hint">Enter the new TLD extension name without a leading dot.</div>
        </div>

        <div style="font-weight:700;font-size:12px;text-transform:uppercase;color:#64748b;margin-bottom:12px;border-bottom:1px solid #e2e8f0;padding-bottom:6px">💰 Wholesale Cost Prices (USD)</div>
        <div class="row">
          <div class="col-md-4">
            <div class="bp-form-group">
              <label class="bp-label">Register Cost ($)</label>
              <input type="number" name="base_price_register" id="d-base-reg" class="bp-input" step="0.01" required>
            </div>
          </div>
          <div class="col-md-4">
            <div class="bp-form-group">
              <label class="bp-label">Renew Cost ($)</label>
              <input type="number" name="base_price_renew" id="d-base-ren" class="bp-input" step="0.01" required>
            </div>
          </div>
          <div class="col-md-4">
            <div class="bp-form-group">
              <label class="bp-label">Transfer Cost ($)</label>
              <input type="number" name="base_price_transfer" id="d-base-tr" class="bp-input" step="0.01" required>
            </div>
          </div>
        </div>

        <div style="font-weight:700;font-size:12px;text-transform:uppercase;color:#64748b;margin-bottom:12px;border-bottom:1px solid #e2e8f0;padding-bottom:6px">📈 Profit Rules & Markups</div>
        <div class="row">
          <div class="col-md-6">
            <div class="bp-form-group">
              <label class="bp-label">Markup Type</label>
              <select name="markup_type" id="d-markup-type" class="bp-select">
                <option value="percentage">Percentage (%)</option>
                <option value="fixed">Fixed flat margin (<?=h($currency)?>)</option>
              </select>
            </div>
          </div>
          <div class="col-md-6">
            <div class="bp-form-group">
              <label class="bp-label">Profit Markup Value</label>
              <input type="number" name="markup_value" id="d-markup-val" class="bp-input" step="0.01" required>
            </div>
          </div>
        </div>

        <div style="font-weight:700;font-size:12px;text-transform:uppercase;color:#64748b;margin-bottom:12px;border-bottom:1px solid #e2e8f0;padding-bottom:6px">🏷 Retail Selling Prices (<?=h($currency)?>)</div>
        <div class="row">
          <div class="col-md-4">
            <div class="bp-form-group">
              <label class="bp-label">Register Price</label>
              <input type="number" name="retail_price_register" id="d-retail-reg" class="bp-input" step="0.01" required>
              <div class="bp-input-hint">Enter 0 to auto-compute</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="bp-form-group">
              <label class="bp-label">Renewal Price</label>
              <input type="number" name="retail_price_renew" id="d-retail-ren" class="bp-input" step="0.01" required>
            </div>
          </div>
          <div class="col-md-4">
            <div class="bp-form-group">
              <label class="bp-label">Transfer Price</label>
              <input type="number" name="retail_price_transfer" id="d-retail-tr" class="bp-input" step="0.01" required>
            </div>
          </div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:24px">
          <button type="button" class="bp-btn bp-btn-outline" onclick="closeDuplicateModal()">Cancel</button>
          <button type="submit" class="bp-btn bp-btn-primary" style="background:#8b5cf6;border-color:#8b5cf6">Clone & Save TLD</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function submitAddInline() {
    var tld = document.getElementById('add-tld-name').value.trim();
    if (!tld) {
        alert('Please enter a TLD extension (e.g. com)');
        return;
    }
    
    document.getElementById('form-add-tld').value = tld;
    document.getElementById('form-add-registrar').value = document.getElementById('add-registrar').value;
    document.getElementById('form-add-dns').checked = document.getElementById('add-dns').checked;
    document.getElementById('form-add-email').checked = document.getElementById('add-email').checked;
    document.getElementById('form-add-id').checked = document.getElementById('add-id').checked;
    document.getElementById('form-add-epp').checked = document.getElementById('add-epp').checked;
    
    document.getElementById('inline-add-form').submit();
}

function confirmDelete(id, name) {
    if (confirm('Are you absolutely sure you want to delete ' + name + ' from your TLD list? This action is permanent.')) {
        document.getElementById('delete-tld-id').value = id;
        document.getElementById('delete-tld-form').submit();
    }
}

function openPricingModal(t) {
    document.getElementById('pricing-tld-id').value = t.id;
    document.getElementById('pricing-tld-label').textContent = '.' + t.tld;
    
    document.getElementById('p-base-reg').value = t.base_price_register;
    document.getElementById('p-base-ren').value = t.base_price_renew;
    document.getElementById('p-base-tr').value = t.base_price_transfer;
    
    document.getElementById('p-markup-type').value = t.markup_type || 'percentage';
    document.getElementById('p-markup-val').value = t.markup_value || '20.00';
    
    document.getElementById('p-retail-reg').value = t.retail_price_register;
    document.getElementById('p-retail-ren').value = t.retail_price_renew;
    document.getElementById('p-retail-tr').value = t.retail_price_transfer;
    
    document.getElementById('pricing-modal').style.display = 'flex';
}

function closePricingModal() {
    document.getElementById('pricing-modal').style.display = 'none';
}

function openDuplicateModal(t) {
    document.getElementById('duplicate-source-id').value = t.id;
    document.getElementById('duplicate-tld-label').textContent = '.' + t.tld;
    document.getElementById('dup-new-tld').value = '';
    
    document.getElementById('d-base-reg').value = t.base_price_register;
    document.getElementById('d-base-ren').value = t.base_price_renew;
    document.getElementById('d-base-tr').value = t.base_price_transfer;
    
    document.getElementById('d-markup-type').value = t.markup_type || 'percentage';
    document.getElementById('d-markup-val').value = t.markup_value || '20.00';
    
    document.getElementById('d-retail-reg').value = t.retail_price_register;
    document.getElementById('d-retail-ren').value = t.retail_price_renew;
    document.getElementById('d-retail-tr').value = t.retail_price_transfer;
    
    document.getElementById('duplicate-modal').style.display = 'flex';
}

function closeDuplicateModal() {
    document.getElementById('duplicate-modal').style.display = 'none';
}
</script>
<?php include 'partials/footer.php'; ?>
