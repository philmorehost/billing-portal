<?php
/**
 * Reseller Module
 * Handles wholesale/retail pricing, balance deductions, white-labeling,
 * custom domain management, SSL provisioning via Let's Encrypt
 */

class Reseller {

    // ── Pricing Logic ──────────────────────────────────────────────────────

    /**
     * Get the wholesale price for a reseller on a given product+cycle
     * Admin sets a % discount from retail. Reseller pays this.
     */
    public static function getWholesalePrice(int $product_id, string $cycle, int $reseller_id = 0): float {
        $product = DB::row("SELECT * FROM products WHERE id=?", 'i', [$product_id]);
        if (!$product) return 0;

        $price_col = 'price_' . $cycle;
        $retail = (float)($product[$price_col] ?? $product['price_monthly'] ?? 0);
        if ($retail <= 0) return 0;

        // Per-product discount overrides global
        $discount = (float)$product['wholesale_discount'];
        if ($discount <= 0) {
            $discount = (float)DB::setting('reseller_default_discount', 20);
        }

        return round($retail * (1 - $discount / 100), 2);
    }

    /**
     * Get the retail price a reseller charges their Tier 2 clients
     * Reseller sets a % markup ON TOP of their wholesale price
     */
    public static function getRetailPrice(int $product_id, string $cycle, int $reseller_id): float {
        $wholesale = self::getWholesalePrice($product_id, $cycle);
        $reseller  = DB::row("SELECT markup_percentage FROM resellers WHERE id=?", 'i', [$reseller_id]);
        if (!$reseller) return $wholesale;

        $markup = (float)$reseller['markup_percentage'];
        return round($wholesale * (1 + $markup / 100), 2);
    }

    /**
     * Deduct wholesale cost from reseller balance when a sale is made
     */
    public static function deductBalance(int $reseller_id, float $amount, string $description): bool {
        $bal = (float)DB::value("SELECT balance FROM resellers WHERE id=?", 'i', [$reseller_id]);
        if ($bal < $amount) return false;

        DB::execute(
            "UPDATE resellers SET balance = balance - ? WHERE id=?",
            'di', [$amount, $reseller_id]
        );
        DB::execute(
            "INSERT INTO activity_log (actor_type,actor_id,action,description) VALUES ('system',?,'reseller_debit',?)",
            'is', [$reseller_id, "Deducted {$amount}: {$description}"]
        );
        return true;
    }

    public static function addBalance(int $reseller_id, float $amount, string $description = 'Top-up'): void {
        DB::execute("UPDATE resellers SET balance = balance + ? WHERE id=?", 'di', [$amount, $reseller_id]);
        DB::execute(
            "INSERT INTO activity_log (actor_type,actor_id,action,description) VALUES ('system',?,'reseller_credit',?)",
            'is', [$reseller_id, "Added {$amount}: {$description}"]
        );
    }

    // ── Domain & SSL ───────────────────────────────────────────────────────

    /**
     * Register a custom domain for a reseller
     * Validates the domain and queues SSL provisioning
     */
    public static function registerDomain(int $reseller_id, string $domain): array {
        $domain = strtolower(trim($domain));

        if (!preg_match('/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?(\.[a-z]{2,})+$/', $domain)) {
            return ['success' => false, 'error' => 'Invalid domain format.'];
        }

        // Check not already in use
        $existing = DB::row("SELECT id FROM resellers WHERE custom_domain=? AND id!=?", 'si', [$domain, $reseller_id]);
        if ($existing) {
            return ['success' => false, 'error' => 'Domain already registered to another reseller.'];
        }

        DB::execute(
            "UPDATE resellers SET custom_domain=?, ssl_status='pending' WHERE id=?",
            'si', [$domain, $reseller_id]
        );

        // Queue SSL provisioning via cron
        log_activity('reseller_domain_set', "Domain {$domain} set for reseller #{$reseller_id}");

        return ['success' => true, 'message' => 'Domain saved. Please point a CNAME record to ' . ($_SERVER['HTTP_HOST'] ?? 'this server') . ' and SSL will be provisioned automatically.'];
    }

    /**
     * Attempt Let's Encrypt SSL provisioning for a reseller domain
     * Uses certbot if available, otherwise records instructions
     */
    public static function provisionSSL(string $domain): array {
        $domain = escapeshellarg($domain);

        // Check if certbot is available
        exec('which certbot 2>/dev/null', $out, $code);
        if ($code !== 0) {
            return ['success' => false, 'error' => 'certbot not found on server. Install certbot and retry.'];
        }

        // Run certbot (webroot method — server must serve /.well-known/)
        $webroot = ROOT_PATH;
        $cmd = "certbot certonly --webroot -w {$webroot} -d {$domain} --non-interactive --agree-tos --email " . escapeshellarg(DB::setting('company_email', 'admin@example.com')) . " 2>&1";
        exec($cmd, $output, $exit_code);

        if ($exit_code === 0) {
            $expiry = date('Y-m-d', strtotime('+90 days'));
            DB::execute(
                "UPDATE resellers SET ssl_status='active', ssl_expires=? WHERE custom_domain=?",
                'ss', [$expiry, trim($domain, "'")]
            );
            return ['success' => true, 'output' => implode("\n", $output)];
        }

        return ['success' => false, 'error' => implode("\n", $output)];
    }

    /**
     * Verify CNAME resolution for a domain
     */
    public static function verifyCNAME(string $domain): bool {
        $server_ip = gethostbyname($_SERVER['HTTP_HOST'] ?? '');
        $domain_ip = gethostbyname($domain);
        return $domain_ip !== $domain && $domain_ip === $server_ip;
    }

    // ── Context Detection ──────────────────────────────────────────────────

    /**
     * Detect if current request is from a reseller custom domain
     * Returns reseller row or null
     */
    public static function detectFromHost(): ?array {
        $host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
        $main = strtolower(explode(':', parse_url(BASE_URL, PHP_URL_HOST) ?? 'localhost')[0]);
        if ($host === $main || $host === 'www.' . $main) return null;

        return DB::row(
            "SELECT r.*, c.first_name, c.last_name, c.email FROM resellers r
             JOIN clients c ON c.id = r.client_id
             WHERE r.custom_domain = ? AND r.status = 'active'",
            's', [$host]
        );
    }

    // ── Branding ───────────────────────────────────────────────────────────

    public static function getBranding(int $reseller_id): array {
        $r = DB::row("SELECT * FROM resellers WHERE id=?", 'i', [$reseller_id]);
        return [
            'name'  => $r['branding_name'] ?: DB::setting('company_name', 'Billing Portal'),
            'color' => $r['branding_color'] ?: '#0f172a',
            'logo'  => $r['branding_logo'] ?: null,
        ];
    }

    // ── Price Adjustment Tool ──────────────────────────────────────────────

    /**
     * Apply a percentage price change across selected products or all
     * Used by admin to bulk-adjust prices
     */
    public static function adjustPrices(array $product_ids, float $percent, string $direction = 'increase'): int {
        $factor = $direction === 'increase' ? (1 + $percent / 100) : (1 - $percent / 100);
        $count  = 0;
        $cycles = ['monthly','quarterly','semi_annually','annually','biennially'];

        foreach ($product_ids as $pid) {
            $product = DB::row("SELECT * FROM products WHERE id=?", 'i', [(int)$pid]);
            if (!$product) continue;

            $updates = [];
            $types   = '';
            $params  = [];

            foreach ($cycles as $c) {
                $col = "price_{$c}";
                if ($product[$col]) {
                    $new_price = round((float)$product[$col] * $factor, 2);
                    $updates[] = "{$col} = ?";
                    $params[]  = $new_price;
                    $types    .= 'd';
                }
            }

            if ($updates) {
                $params[] = (int)$pid;
                $types   .= 'i';
                DB::execute("UPDATE products SET " . implode(', ', $updates) . " WHERE id=?", $types, $params);
                $count++;
            }
        }
        return $count;
    }
}
