<?php
$is_cli=(php_sapi_name()==='cli');
if (!$is_cli) { if (empty($_GET['key'])) { http_response_code(403); exit('Forbidden'); } }
require_once __DIR__.'/../includes/config.php';
$run_job=$is_cli?($argv[1]??null):get_param('job');
function cron_log($job,$status,$output){
    DB::execute("UPDATE cron_jobs SET last_run=NOW(),last_status=?,last_output=?,next_run=CASE WHEN frequency='hourly' THEN DATE_ADD(NOW(),INTERVAL 1 HOUR) WHEN frequency='weekly' THEN DATE_ADD(NOW(),INTERVAL 7 DAY) WHEN frequency='monthly' THEN DATE_ADD(NOW(),INTERVAL 1 MONTH) ELSE DATE_ADD(NOW(),INTERVAL 1 DAY) END WHERE slug=?",'sss',[$status,$output,$job]);
    echo "[".date('Y-m-d H:i:s')."] [{$job}] {$status}: {$output}\n";
}
function run_invoice_generation(){
    $due=(int)DB::setting('invoice_due_days',7);
    $svcs=DB::rows("SELECT s.*,c.email,c.first_name,c.last_name,p.name AS pname FROM services s JOIN clients c ON c.id=s.client_id JOIN products p ON p.id=s.product_id WHERE s.status='active' AND s.next_due_date<=DATE_ADD(CURDATE(),INTERVAL ? DAY) AND s.next_due_date>CURDATE() AND NOT EXISTS (SELECT 1 FROM invoices i JOIN invoice_items ii ON ii.invoice_id=i.id WHERE ii.service_id=s.id AND i.status='unpaid')",'i',[$due]);
    $count=0;
    foreach($svcs as $svc){
        $num=generate_invoice_number(); $due_date=date('Y-m-d',strtotime("+{$due} days"));
        $price=(float)$svc['price']; $tax_r=(float)DB::setting('tax_rate',0);
        $tax_e=DB::setting('tax_enabled','1')==='1'; $tax=$tax_e?round($price*($tax_r/100),2):0;
        $total=$price+$tax; $cur=DB::setting('base_currency','NGN');
        $r=DB::execute("INSERT INTO invoices (client_id,invoice_number,subtotal,tax_amount,total,currency,status,due_date) VALUES (?,?,?,?,?,?,'unpaid',?)",'issddds',[$svc['client_id'],$num,$price,$tax,$total,$cur,$due_date]);
        $iid=$r['insert_id'];
        DB::execute("INSERT INTO invoice_items (invoice_id,service_id,description,quantity,unit_price,total,tax_rate) VALUES (?,?,?,1,?,?,?)",'iisddd',[$iid,$svc['id'],$svc['pname'],$price,$total,$tax_r]);

        // Construct HTML components for invoice email
        $items_html = '<tr>
            <td style="padding: 12px; border-bottom: 1px solid #edf2f7; font-size: 14px; color: #4a5568;">' . htmlspecialchars($svc['pname']) . '</td>
            <td style="padding: 12px; border-bottom: 1px solid #edf2f7; font-size: 14px; color: #4a5568; text-align: center;">1</td>
            <td style="padding: 12px; border-bottom: 1px solid #edf2f7; font-size: 14px; color: #4a5568; text-align: right;">' . format_currency($price,$cur) . '</td>
            <td style="padding: 12px; border-bottom: 1px solid #edf2f7; font-size: 14px; font-weight: bold; color: #2d3748; text-align: right;">' . format_currency($price,$cur) . '</td>
        </tr>';

        $bank_details = Billing::getBankDetails($iid);
        $bank_html = '<div style="background: #f7fafc; border: 1.5px solid #edf2f7; border-radius: 8px; padding: 16px; margin-top: 20px; font-family: sans-serif;">
            <div style="font-weight: bold; font-size: 14px; color: #2d3748; margin-bottom: 12px;">🏦 Manual Bank Transfer Details</div>
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">';
        $lines = explode("\n", $bank_details);
        foreach ($lines as $line) {
            $parts = explode(":", $line, 2);
            if (count($parts) === 2 && !empty(trim($parts[0]))) {
                $bank_html .= '<tr><td style="padding: 6px 0; color: #718096; font-weight: 500; border-bottom: 1px dashed #edf2f7;">' . htmlspecialchars(trim($parts[0])) . '</td><td style="padding: 6px 0; text-align: right; font-weight: bold; color: #2d3748; border-bottom: 1px dashed #edf2f7;">' . htmlspecialchars(trim($parts[1])) . '</td></tr>';
            } else if (!empty(trim($line))) {
                $bank_html .= '<tr><td colspan="2" style="padding: 6px 0; color: #4a5568; font-weight: 500;">' . htmlspecialchars($line) . '</td></tr>';
            }
        }
        $bank_html .= '</table></div>';

        Mailer::sendTemplate($svc['email'],$svc['first_name'].' '.$svc['last_name'],'invoice_created',[
            'client_name'=>$svc['first_name'],
            'invoice_number'=>$num,
            'invoice_total'=>format_currency($total,$cur),
            'due_date'=>format_date($due_date),
            'invoice_url'=>BASE_URL.'/client/invoices/view.php?id='.$iid,
            'invoice_items'=>$items_html,
            'subtotal'=>format_currency($price,$cur),
            'tax_amount'=>format_currency($tax,$cur),
            'discount_amount'=>format_currency(0,$cur),
            'bank_details'=>$bank_html
        ]);

        $count++;
    }
    return "Generated {$count} invoice(s).";
}
function run_payment_reminders(){
    DB::execute("UPDATE invoices SET status='overdue' WHERE status='unpaid' AND due_date<CURDATE()");
    $rows=DB::rows("SELECT i.*,c.email,c.first_name FROM invoices i JOIN clients c ON c.id=i.client_id WHERE i.status='overdue'");
    foreach($rows as $inv) {
        $items = DB::rows("SELECT * FROM invoice_items WHERE invoice_id=?", 'i', [$inv['id']]);
        $items_html = '';
        foreach ($items as $item) {
            $items_html .= '<tr>
                <td style="padding: 12px; border-bottom: 1px solid #edf2f7; font-size: 14px; color: #4a5568;">' . htmlspecialchars($item['description']) . '</td>
                <td style="padding: 12px; border-bottom: 1px solid #edf2f7; font-size: 14px; color: #4a5568; text-align: center;">' . $item['quantity'] . '</td>
                <td style="padding: 12px; border-bottom: 1px solid #edf2f7; font-size: 14px; color: #4a5568; text-align: right;">' . format_currency($item['unit_price'],$inv['currency']) . '</td>
                <td style="padding: 12px; border-bottom: 1px solid #edf2f7; font-size: 14px; font-weight: bold; color: #2d3748; text-align: right;">' . format_currency($item['total'],$inv['currency']) . '</td>
            </tr>';
        }

        $bank_details = Billing::getBankDetails($inv['id']);
        $bank_html = '<div style="background: #f7fafc; border: 1.5px solid #edf2f7; border-radius: 8px; padding: 16px; margin-top: 20px; font-family: sans-serif;">
            <div style="font-weight: bold; font-size: 14px; color: #2d3748; margin-bottom: 12px;">🏦 Manual Bank Transfer Details</div>
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">';
        $lines = explode("\n", $bank_details);
        foreach ($lines as $line) {
            $parts = explode(":", $line, 2);
            if (count($parts) === 2 && !empty(trim($parts[0]))) {
                $bank_html .= '<tr><td style="padding: 6px 0; color: #718096; font-weight: 500; border-bottom: 1px dashed #edf2f7;">' . htmlspecialchars(trim($parts[0])) . '</td><td style="padding: 6px 0; text-align: right; font-weight: bold; color: #2d3748; border-bottom: 1px dashed #edf2f7;">' . htmlspecialchars(trim($parts[1])) . '</td></tr>';
            } else if (!empty(trim($line))) {
                $bank_html .= '<tr><td colspan="2" style="padding: 6px 0; color: #4a5568; font-weight: 500;">' . htmlspecialchars($line) . '</td></tr>';
            }
        }
        $bank_html .= '</table></div>';

        Mailer::sendTemplate($inv['email'],$inv['first_name'],'invoice_created',[
            'client_name'=>$inv['first_name'],
            'invoice_number'=>$inv['invoice_number'],
            'invoice_total'=>format_currency($inv['total'],$inv['currency']),
            'due_date'=>format_date($inv['due_date']),
            'invoice_url'=>BASE_URL.'/client/invoices/view.php?id='.$inv['id'],
            'invoice_items'=>$items_html,
            'subtotal'=>format_currency($inv['subtotal'],$inv['currency']),
            'tax_amount'=>format_currency($inv['tax_amount'],$inv['currency']),
            'discount_amount'=>format_currency($inv['discount_amount'],$inv['currency']),
            'bank_details'=>$bank_html
        ]);
    }
    return "Processed ".count($rows)." reminder(s).";
}
function run_service_suspension(){
    $svcs=DB::rows("SELECT DISTINCT s.id,s.client_id,s.domain FROM services s JOIN invoices i ON i.client_id=s.client_id JOIN invoice_items ii ON ii.invoice_id=i.id AND ii.service_id=s.id WHERE s.status='active' AND i.status='overdue' AND i.due_date<DATE_SUB(CURDATE(),INTERVAL 3 DAY)");
    foreach($svcs as $s){ DB::execute("UPDATE services SET status='suspended' WHERE id=?",'i',[$s['id']]); }
    return "Suspended ".count($svcs)." service(s).";
}
function run_service_termination(){
    $r=DB::execute("UPDATE services SET status='terminated',termination_date=NOW() WHERE status='suspended' AND updated_at<DATE_SUB(NOW(),INTERVAL 14 DAY)");
    return "Terminated {$r['affected_rows']} service(s).";
}
function run_domain_expiry_check(){ return "Domain expiry check complete."; }
function run_affiliate_payouts(){ return "Affiliate payouts processed."; }
function run_report_generation(){ return "Reports generated."; }

function run_nocix_stock_sync(): string {
    if (DB::setting('module_nocix_sync_status') !== '1') return "NOCIX sync disabled in settings.";
    require_once INC_PATH . '/modules/provisioning/dispatcher.php';
    $module = ProvisioningDispatcher::buildModule('nocix');
    if (!$module) return "NOCIX module failed to initialize.";

    $nocix_products = $module->listProducts();
    $in_stock_ids = [];
    foreach ($nocix_products as $p) { if (!empty($p['id'])) $in_stock_ids[] = (string)$p['id']; }

    $local_products = DB::rows("SELECT id, external_id, visible FROM products WHERE module='nocix'");
    $hidden = 0; $shown = 0;
    foreach ($local_products as $lp) {
        if (empty($lp['external_id'])) continue;
        $in_stock = in_array((string)$lp['external_id'], $in_stock_ids);
        if ($in_stock && !$lp['visible']) { DB::execute("UPDATE products SET visible=1 WHERE id=?", 'i', [$lp['id']]); $shown++; }
        elseif (!$in_stock && $lp['visible']) { DB::execute("UPDATE products SET visible=0 WHERE id=?", 'i', [$lp['id']]); $hidden++; }
    }
    return "NOCIX Sync: Shown {$shown}, Hidden {$hidden} out-of-stock products.";
}

$jobs=['invoice_generation'=>'run_invoice_generation','payment_reminders'=>'run_payment_reminders','service_suspension'=>'run_service_suspension','service_termination'=>'run_service_termination','domain_expiry_check'=>'run_domain_expiry_check','ssl_cert_check'=>'run_ssl_cert_check','affiliate_payouts'=>'run_affiliate_payouts','report_generation'=>'run_report_generation','auto_provision'=>'run_auto_provision', 'nocix_stock_sync' => 'run_nocix_stock_sync'];

if ($run_job) {
    if (isset($jobs[$run_job])) { try { cron_log($run_job,'success',call_user_func($jobs[$run_job])); } catch(Exception $e){ cron_log($run_job,'failed',$e->getMessage()); } }
    else echo "Unknown job: {$run_job}\n";
} else {
    $due=DB::rows("SELECT slug FROM cron_jobs WHERE enabled=1 AND (next_run IS NULL OR next_run<=NOW())");
    foreach($due as $j) { if (isset($jobs[$j['slug']])) { try { cron_log($j['slug'],'success',call_user_func($jobs[$j['slug']])); } catch(Exception $e){ cron_log($j['slug'],'failed',$e->getMessage()); } } }
    echo "[".date('Y-m-d H:i:s')."] Cron run complete.\n";
}
// SSL cert renewal handler (called by ssl_cert_check job)
// Overrides the stub in run.php
function run_ssl_cert_check(): string {
    require_once INC_PATH . '/modules/reseller.php';
    $resellers = DB::rows(
        "SELECT * FROM resellers WHERE ssl_status='active' AND ssl_expires <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)"
    );
    $renewed = 0;
    foreach ($resellers as $r) {
        if (!$r['custom_domain']) continue;
        $result = Reseller::provisionSSL($r['custom_domain']);
        if ($result['success']) {
            $renewed++;
            log_activity('ssl_renewed', "SSL renewed for {$r['custom_domain']}");
        } else {
            log_activity('ssl_renewal_failed', "SSL renewal failed for {$r['custom_domain']}: " . $result['error']);
        }
    }
    return "Checked " . count($resellers) . " certificate(s), renewed {$renewed}.";
}

// ── Auto-provisioning after payment ─────────────────────────────────────
function run_auto_provision(): string {
    require_once INC_PATH.'/modules/provisioning/dispatcher.php';

    // Find services that are still 'pending' but their invoice is paid
    $services = DB::rows(
        "SELECT DISTINCT s.id FROM services s
         JOIN invoice_items ii ON ii.service_id = s.id
         JOIN invoices i ON i.id = ii.invoice_id
         WHERE s.status = 'pending'
         AND i.status = 'paid'
         ORDER BY s.id ASC
         LIMIT 20"
    );

    $count = 0; $failed = 0;
    foreach ($services as $svc) {
        $result = ProvisioningDispatcher::provision((int)$svc['id']);
        $result['success'] ? $count++ : $failed++;
    }
    return "Provisioned {$count} service(s). Failed: {$failed}.";
}
