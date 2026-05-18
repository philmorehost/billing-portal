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
        $tax_amount  = $tax_enabled ? round($subtotal * ($tax_rate / 100), 2) : 0;
        $discount    = (float) ($data['discount_amount'] ?? 0);
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

    public static function convertToUSD(float $ngn_amount): float {
        $rate = (float) DB::setting('usd_exchange_rate', 1600);
        return round($ngn_amount / $rate, 2);
    }

    public static function convertFromUSD(float $usd_amount): float {
        $rate = (float) DB::setting('usd_exchange_rate', 1600);
        return round($usd_amount * $rate, 2);
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
        $amount_ngn = (float) $inv['total'];
        if ($currency === 'USD') {
            $amount_usd = self::convertToUSD($amount_ngn);
            $pay_amount = (int) round($amount_usd * 100); // Paystack in kobo/cents
        } else {
            $pay_amount = (int) round($amount_ngn * 100);
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
}
