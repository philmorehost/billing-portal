<?php
/**
 * Billing Module
 * Handles invoice creation, payment processing, credit system, multi-currency
 */

class Billing {

    // ── Invoice Management ─────────────────────────────────────────────────

    public static function createInvoice(array $data): int {
        $cur       = $data['currency'] ?? DB::setting('base_currency', 'NGN');
        $due_days  = (int) DB::setting('invoice_due_days', 7);
        $due_date  = $data['due_date'] ?? date('Y-m-d', strtotime("+{$due_days} days"));
        $inv_num   = generate_invoice_number();

        $subtotal  = 0;
        foreach ($data['items'] as $item) {
            $subtotal += $item['unit_price'] * $item['quantity'];
        }

        $tax_enabled = DB::setting('tax_enabled', '1') === '1';
        $tax_rate    = (float) DB::setting('tax_rate', 0);
        $discount    = (float) ($data['discount_amount'] ?? 0);
        $tax_amount  = $tax_enabled ? round(($subtotal - $discount) * ($tax_rate / 100), 2) : 0;
        $total       = max(0, $subtotal + $tax_amount - $discount);

        $r = DB::execute(
            "INSERT INTO invoices (client_id, order_id, invoice_number, subtotal, tax_amount, discount_amount, total, currency, status, due_date, notes)
             VALUES (?,?,?,?,?,?,?,?,'unpaid',?,?)",
            'iisddddss',
            [
                $data['client_id'],
                $data['order_id'] ?? null,
                $inv_num,
                $subtotal,
                $tax_amount,
                $discount,
                $total,
                $cur,
                $due_date,
                $data['notes'] ?? null,
            ]
        );
        $inv_id = $r['insert_id'];

        foreach ($data['items'] as $item) {
            DB::execute(
                "INSERT INTO invoice_items (invoice_id, service_id, description, quantity, unit_price, total, tax_rate)
                 VALUES (?,?,?,?,?,?,?)",
                'iisddd' . 'd',
                [
                    $inv_id,
                    $item['service_id'] ?? null,
                    $item['description'],
                    $item['quantity'] ?? 1,
                    $item['unit_price'],
                    ($item['unit_price'] * ($item['quantity'] ?? 1)),
                    $tax_enabled ? $tax_rate : 0,
                ]
            );
        }

        log_activity('invoice_created', "Invoice #{$inv_num} created for client #{$data['client_id']}", 'system');
        return $inv_id;
    }

    public static function markPaid(int $inv_id, string $gateway, string $gateway_ref = '', float $amount_paid = 0): bool {
        $inv = DB::row("SELECT * FROM invoices WHERE id=?", 'i', [$inv_id]);
        if (!$inv || $inv['status'] === 'paid') return false;

        DB::execute(
            "UPDATE invoices SET status='paid', paid_date=NOW(), payment_method=?, gateway_transaction_id=? WHERE id=?",
            'ssi', [$gateway, $gateway_ref, $inv_id]
        );

        // Record transaction
        DB::execute(
            "INSERT INTO transactions (client_id, invoice_id, type, amount, currency, gateway, gateway_ref, description, status)
             VALUES (?,?,'payment',?,?,?,?,'Invoice payment','completed')",
            'iidssss',
            [$inv['client_id'], $inv_id, $inv['total'], $inv['currency'], $gateway, $gateway_ref]
        );

        // Deduct reseller balance if this service is branded / resold
        $resold_services = DB::rows(
            "SELECT s.id, s.product_id, s.billing_cycle, s.reseller_id, s.domain 
             FROM services s
             JOIN invoice_items ii ON ii.service_id = s.id
             WHERE ii.invoice_id = ? AND s.status = 'pending' AND s.reseller_id IS NOT NULL",
            'i', [$inv_id]
        );

        foreach ($resold_services as $rs) {
            require_once INC_PATH . '/modules/reseller.php';
            $wholesale_price = Reseller::getWholesalePrice((int)$rs['product_id'], $rs['billing_cycle'], (int)$rs['reseller_id']);
            Reseller::deductBalance((int)$rs['reseller_id'], $wholesale_price, "Wholesale cost for service {$rs['domain']} (service #{$rs['id']})");
        }

        // Activate associated services
        DB::execute(
            "UPDATE services s
             JOIN invoice_items ii ON ii.service_id = s.id
             SET s.status = 'active', s.registration_date = CURDATE()
             WHERE ii.invoice_id = ? AND s.status = 'pending'",
            'i', [$inv_id]
        );

        // Send payment confirmation
        $client = DB::row("SELECT * FROM clients WHERE id=?", 'i', [$inv['client_id']]);
        if ($client) {
            Mailer::sendTemplate($client['email'], $client['first_name'], 'payment_received', [
                'client_name'    => $client['first_name'],
                'invoice_number' => $inv['invoice_number'],
                'amount'         => format_currency($inv['total'], $inv['currency']),
            ]);
        }

        log_activity('invoice_paid', "Invoice #{$inv['invoice_number']} marked paid via {$gateway}", 'system');
        return true;
    }

    // ── Credit System ──────────────────────────────────────────────────────

    public static function addCredit(int $client_id, float $amount, string $description = 'Credit added', string $gateway = 'manual'): bool {
        DB::execute("UPDATE clients SET credit_balance = credit_balance + ? WHERE id=?", 'di', [$amount, $client_id]);
        DB::execute(
            "INSERT INTO transactions (client_id, type, amount, currency, gateway, description, status) VALUES (?,'credit',?,?,?,'$description','completed')",
            'idss', [$client_id, $amount, DB::setting('base_currency', 'NGN'), $gateway]
        );
        log_activity('credit_added', "₦{$amount} credit added to client #{$client_id}", 'system');
        return true;
    }

    public static function deductCredit(int $client_id, float $amount, string $description = 'Credit used'): bool {
        $balance = (float) DB::value("SELECT credit_balance FROM clients WHERE id=?", 'i', [$client_id]);
        if ($balance < $amount) return false;
        DB::execute("UPDATE clients SET credit_balance = credit_balance - ? WHERE id=?", 'di', [$amount, $client_id]);
        DB::execute(
            "INSERT INTO transactions (client_id, type, amount, currency, description, status) VALUES (?,'debit',?,?,?,'completed')",
            'idss', [$client_id, $amount, DB::setting('base_currency', 'NGN'), $description]
        );
        return true;
    }

    public static function payInvoiceWithCredit(int $inv_id, int $client_id): array {
        $inv = DB::row("SELECT * FROM invoices WHERE id=? AND client_id=? AND status='unpaid'", 'ii', [$inv_id, $client_id]);
        if (!$inv) return ['success' => false, 'error' => 'Invoice not found or already paid.'];

        $balance = (float) DB::value("SELECT credit_balance FROM clients WHERE id=?", 'i', [$client_id]);
        if ($balance < $inv['total']) return ['success' => false, 'error' => 'Insufficient credit balance.'];

        self::deductCredit($client_id, $inv['total'], "Payment for Invoice #{$inv['invoice_number']}");
        self::markPaid($inv_id, 'credit', 'CREDIT-' . time());
        return ['success' => true];
    }

    // ── Currency Conversion ────────────────────────────────────────────────

    public static function getLiveRates(bool $force = false): array {
        $cached_rates = DB::setting('live_rates_cache');
        $last_updated = (int) DB::setting('live_rates_last_updated', 0);
        $now = time();

        // Cache rates for 6 hours (21600 seconds), unless forced
        if (!$force && $cached_rates && ($now - $last_updated) < 21600) {
            $rates = json_decode($cached_rates, true);
            if (is_array($rates) && !empty($rates['NGN'])) {
                return $rates;
            }
        }

        // Fetch new rates
        $endpoints = [
            'https://open.er-api.com/v6/latest/USD',
            'https://api.exchangerate-api.com/v4/latest/USD'
        ];

        foreach ($endpoints as $url) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $resp = curl_exec($ch);
            curl_close($ch);

            if ($resp) {
                $data = json_decode($resp, true);
                $fetched_rates = $data['rates'] ?? ($data['conversion_rates'] ?? null);
                if (!empty($fetched_rates) && is_array($fetched_rates) && !empty($fetched_rates['NGN'])) {
                    // Save to DB
                    self::saveSetting('live_rates_cache', json_encode($fetched_rates));
                    self::saveSetting('live_rates_last_updated', (string)$now);
                    return $fetched_rates;
                }
            }
        }

        // Fallback rates if API is offline
        if ($cached_rates) {
            $rates = json_decode($cached_rates, true);
            if (is_array($rates)) return $rates;
        }

        return ['USD' => 1.0, 'NGN' => 1550.0, 'EUR' => 0.93, 'GBP' => 0.78, 'GHS' => 15.5, 'KES' => 132.0, 'ZAR' => 18.2];
    }

    public static function refreshLiveRates(): bool {
        $rates = self::getLiveRates(true);
        $last_updated = (int) DB::setting('live_rates_last_updated', 0);
        return (time() - $last_updated) < 60; // Success if updated in last minute
    }

    private static function saveSetting(string $key, string $value): void {
        $exists = DB::value("SELECT COUNT(*) FROM settings WHERE setting_key=?", 's', [$key]);
        if ($exists > 0) {
            DB::execute("UPDATE settings SET setting_value=? WHERE setting_key=?", 'ss', [$value, $key]);
        } else {
            DB::execute("INSERT INTO settings (setting_key, setting_value, setting_group) VALUES (?, ?, 'billing')", 'ss', [$key, $value]);
        }
    }

    public static function convertCurrency(float $amount, string $from_cur, string $to_cur): float {
        if ($from_cur === $to_cur) return $amount;

        $rates = self::getLiveRates();
        $from_rate = (float)($rates[$from_cur] ?? 1.0);
        $to_rate = (float)($rates[$to_cur] ?? 1.0);

        $markup_percent = (float) DB::setting('usd_to_ngn_markup_percent', 0);
        $ngn_markup_multiplier = 1.0 + ($markup_percent / 100);

        if ($from_cur === 'USD' && $to_cur === 'NGN') {
            $effective_rate = $to_rate * $ngn_markup_multiplier;
            return round($amount * $effective_rate, 2);
        }

        if ($from_cur === 'NGN' && $to_cur === 'USD') {
            $effective_rate = $from_rate * $ngn_markup_multiplier;
            return round($amount / $effective_rate, 2);
        }

        // General conversion for other currencies
        // First convert to USD
        $amount_usd = $amount / $from_rate;
        // Then convert to destination
        return round($amount_usd * $to_rate, 2);
    }

    public static function convertToUSD(float $ngn_amount): float {
        return self::convertCurrency($ngn_amount, 'NGN', 'USD');
    }

    public static function convertFromUSD(float $usd_amount): float {
        return self::convertCurrency($usd_amount, 'USD', 'NGN');
    }

    // ── Coupon Validation ──────────────────────────────────────────────────

    public static function validateCoupon(string $code, int $client_id, float $subtotal): array {
        $coupon = DB::row("SELECT * FROM coupons WHERE code=? AND status='active'", 's', [$code]);
        if (!$coupon) return ['valid' => false, 'error' => 'Invalid coupon code.'];

        $now = date('Y-m-d');
        if ($coupon['valid_from'] && $now < $coupon['valid_from']) return ['valid' => false, 'error' => 'Coupon not yet active.'];
        if ($coupon['valid_until'] && $now > $coupon['valid_until']) return ['valid' => false, 'error' => 'Coupon has expired.'];
        if ($coupon['max_uses'] && $coupon['uses_count'] >= $coupon['max_uses']) return ['valid' => false, 'error' => 'Coupon usage limit reached.'];

        $client_uses = DB::value(
            "SELECT COUNT(*) FROM invoices WHERE client_id=? AND JSON_CONTAINS(notes, ?)",
            'is', [$client_id, json_encode($code)]
        ) ?? 0;
        if ($coupon['max_uses_per_client'] && $client_uses >= $coupon['max_uses_per_client']) {
            return ['valid' => false, 'error' => 'You have already used this coupon.'];
        }

        $discount = $coupon['type'] === 'percentage'
            ? round($subtotal * ($coupon['value'] / 100), 2)
            : min($coupon['value'], $subtotal);

        return ['valid' => true, 'discount' => $discount, 'coupon' => $coupon];
    }

    public static function applyCoupon(int $coupon_id): void {
        DB::execute("UPDATE coupons SET uses_count = uses_count + 1 WHERE id=?", 'i', [$coupon_id]);
    }

    // ── Paystack ──────────────────────────────────────────────────────────

    public static function paystackInitialize(int $inv_id, string $currency = 'NGN'): array {
        $inv    = DB::row("SELECT i.*, c.email, c.first_name, c.last_name FROM invoices i JOIN clients c ON c.id=i.client_id WHERE i.id=?", 'i', [$inv_id]);
        if (!$inv) return ['success' => false, 'error' => 'Invoice not found.'];

        $secret = DB::setting('paystack_secret_key');
        if (!$secret) return ['success' => false, 'error' => 'Paystack not configured.'];

        // Convert amount based on currency selection
        $inv_cur = $inv['currency'] ?? 'NGN';
        $total = (float)$inv['total'];

        $amount_usd = self::convertCurrency($total, $inv_cur, 'USD');
        $amount_ngn = self::convertCurrency($total, $inv_cur, 'NGN');

        if ($currency === 'USD') {
            $pay_amount = (int) round($amount_usd * 100); // Paystack in cents
        } else {
            $pay_amount = (int) round($amount_ngn * 100); // Paystack in kobo
            $currency = 'NGN'; // Force NGN
        }

        $ref = 'INV-' . $inv['invoice_number'] . '-' . time();

        $ch = curl_init('https://api.paystack.co/transaction/initialize');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$secret}", "Content-Type: application/json"],
            CURLOPT_POSTFIELDS     => json_encode([
                'email'      => $inv['email'],
                'amount'     => $pay_amount,
                'currency'   => $currency,
                'reference'  => $ref,
                'callback_url' => BASE_URL . '/api/paystack-webhook.php',
                'metadata'   => ['invoice_id' => $inv_id, 'client_id' => $inv['client_id']],
            ]),
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) return ['success' => false, 'error' => 'Payment gateway error.'];
        $data = json_decode($resp, true);
        if (!$data['status']) return ['success' => false, 'error' => $data['message'] ?? 'Gateway error.'];

        return ['success' => true, 'auth_url' => $data['data']['authorization_url'], 'reference' => $ref];
    }

    public static function paystackVerify(string $reference): array {
        $secret = DB::setting('paystack_secret_key');
        $ch = curl_init("https://api.paystack.co/transaction/verify/" . urlencode($reference));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$secret}"],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (!$resp['status'] || $resp['data']['status'] !== 'success') {
            return ['success' => false, 'error' => 'Payment verification failed.'];
        }
        return ['success' => true, 'data' => $resp['data']];
    }

    // ── Plisio Cryptocurrency ──────────────────────────────────────────────

    public static function plisioInitialize(int $inv_id): array {
        $inv = DB::row("SELECT i.*, c.email, c.first_name, c.last_name FROM invoices i JOIN clients c ON c.id=i.client_id WHERE i.id=?", 'i', [$inv_id]);
        if (!$inv) return ['success' => false, 'error' => 'Invoice not found.'];

        $apiKey = DB::setting('crypto_plisio_api_key');
        if (!$apiKey) return ['success' => false, 'error' => 'Plisio API is not configured. Please contact the administrator.'];

        $currency = $inv['currency'] ?? 'USD';
        $amount = (float)$inv['total'];
        $source_amount = self::convertCurrency($amount, $currency, 'USD');
        $source_currency = 'USD';

        $allowed_coins = DB::setting('crypto_plisio_allowed_coins', 'BTC,LTC,USDT,ETH');
        $ref = 'INV-' . $inv['invoice_number'] . '-' . time();

        $params = [
            'source_currency' => $source_currency,
            'source_amount'   => $source_amount,
            'order_number'    => $ref,
            'order_name'      => 'Invoice #' . $inv['invoice_number'],
            'email'           => $inv['email'],
            'callback_url'    => BASE_URL . '/api/plisio-webhook.php?json=true',
            'allowed_psys_cids' => $allowed_coins,
            'api_key'         => $apiKey
        ];

        $url = 'https://api.plisio.net/api/v1/invoices/new?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['success' => false, 'error' => 'Plisio gateway connection timeout.'];
        }

        $data = json_decode($resp, true);
        if (empty($data) || ($data['status'] ?? '') !== 'success') {
            $msg = $data['data']['message'] ?? ($data['message'] ?? 'Unable to generate crypto invoice.');
            return ['success' => false, 'error' => 'Plisio API Error: ' . $msg];
        }

        return [
            'success'  => true,
            'auth_url' => $data['data']['invoice_url'],
            'reference' => $ref
        ];
    }

    public static function getBankDetails(int $inv_id): string {
        // 1. Try to find the reseller linked to the invoice order
        $reseller = DB::row("
            SELECT r.bank_transfer_details 
            FROM invoices i 
            JOIN orders o ON o.id = i.order_id 
            JOIN resellers r ON r.id = o.reseller_id 
            WHERE i.id = ?
        ", 'i', [$inv_id]);

        // 2. If not found via order, check if there's a reseller domain session active
        if (empty($reseller) && !empty($_SESSION['reseller_domain_id'])) {
            $reseller = DB::row("SELECT bank_transfer_details FROM resellers WHERE id = ?", 'i', [$_SESSION['reseller_domain_id']]);
        }

        // 3. If reseller bank details are set and not placeholder/empty, return them
        if (!empty($reseller) && !empty(trim($reseller['bank_transfer_details']))) {
            $details = trim($reseller['bank_transfer_details']);
            if (stripos($details, 'Bank: N/A') === false && stripos($details, 'Account Number: N/A') === false) {
                return $details;
            }
        }

        // 4. Fallback to super admin bank transfer details from settings
        $admin_details = DB::setting('bank_transfer_details');
        if (empty($admin_details) || stripos($admin_details, 'Bank: N/A') !== false || stripos($admin_details, 'Account Number: N/A') !== false || trim($admin_details) === "Bank: \nAccount Name: \nAccount Number:") {
            $company = DB::setting('company_name', 'Philmore Host');
            return "Bank: United Bank for Africa (UBA)\nAccount Name: {$company}\nAccount Number: 1022394012";
        }
        return $admin_details;
    }
}
