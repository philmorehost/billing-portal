<?php
/**
 * Provisioning Dispatcher
 * Central router — maps product modules to provisioning classes
 * Called by cron jobs and admin actions
 */

require_once __DIR__ . '/base.php';
require_once __DIR__ . '/cpanel.php';
require_once __DIR__ . '/resellerclub.php';
require_once __DIR__ . '/namecheap.php';
require_once __DIR__ . '/connectreseller.php';
require_once __DIR__ . '/upperlink.php';
require_once __DIR__ . '/nocix.php';
require_once __DIR__ . '/time4vps.php';

class ProvisioningDispatcher {

    /**
     * Get the provisioning module instance for a given service
     */
    public static function getModule(int $service_id): ?ProvisioningBase {
        $service = DB::row(
            "SELECT s.*, p.module, p.module_config FROM services s JOIN products p ON p.id=s.product_id WHERE s.id=?",
            'i', [$service_id]
        );
        if (!$service || !$service['module']) return null;

        return self::buildModule($service['module'], $service['server_id'], $service['module_config']);
    }

    public static function buildModule(string $module, ?int $server_id = null, ?string $module_config_json = null): ?ProvisioningBase {
        // Get server config if linked
        $server_config = [];
        if ($server_id) {
            $server = DB::row("SELECT * FROM servers WHERE id=?", 'i', [$server_id]);
            if ($server) {
                $server_config = [
                    'hostname' => $server['hostname'],
                    'port'     => $server['port'],
                    'username' => $server['username'],
                    'password' => $server['password'] ?? '',
                    'api_key'  => $server['api_key'] ?? '',
                ];
            }
        }

        // Module-specific settings from DB settings table
        $module_defaults = self::getModuleSettings($module);
        $module_config   = json_decode($module_config_json ?? '{}', true) ?? [];
        $config          = array_merge($module_defaults, $server_config, $module_config);

        return match(strtolower($module)) {
            'cpanel'          => new CpanelModule($config),
            'resellerclub'    => new ResellerClubModule($config),
            'namecheap'       => new NamecheapModule($config),
            'connectreseller' => new ConnectResellerModule($config),
            'upperlink'       => new UpperlinkModule($config),
            'nocix'           => new NocixModule($config),
            'time4vps'        => new Time4VPSModule($config),
            default           => null,
        };
    }

    private static function getModuleSettings(string $module): array {
        $prefix = "module_{$module}_";
        $rows   = DB::rows("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE ?", 's', [$prefix . '%']);
        $config = [];
        foreach ($rows as $row) {
            $key = str_replace($prefix, '', $row['setting_key']);
            $config[$key] = $row['setting_value'];
        }
        return $config;
    }

    /**
     * Provision a new service automatically after payment
     */
    public static function provision(int $service_id): array {
        $service = DB::row(
            "SELECT s.*, p.module, p.auto_provision, c.email, c.first_name, c.last_name FROM services s
             JOIN products p ON p.id=s.product_id
             JOIN clients c ON c.id=s.client_id
             WHERE s.id=?",
            'i', [$service_id]
        );

        if (!$service) return ['success' => false, 'error' => 'Service not found.'];
        if (!$service['auto_provision']) return ['success' => false, 'error' => 'Auto-provision disabled for this product.'];

        $module = self::getModule($service_id);
        if (!$module) {
            DB::execute("UPDATE services SET status='active' WHERE id=?", 'i', [$service_id]);
            return ['success' => true, 'message' => 'Service activated (no module, manual).'];
        }

        $params = [
            'domain'   => $service['domain'],
            'username' => $service['username'],
            'password' => $service['password'],
            'email'    => $service['email'],
            'hostname' => $service['domain'],
        ];

        // Merge service module_data if exists
        if ($service['module_data']) {
            $params = array_merge($params, json_decode($service['module_data'], true) ?? []);
        }

        $result = $module->create($params);

        if ($result['success']) {
            $updates = [
                'status'      => 'active',
                'module_data' => json_encode($result['server_data'] ?? $result),
            ];
            if (!empty($result['username'])) $updates['username'] = $result['username'];
            if (!empty($result['password'])) $updates['password'] = $result['password'];

            $set = implode(', ', array_map(fn($k) => "{$k}=?", array_keys($updates)));
            DB::execute(
                "UPDATE services SET {$set} WHERE id=?",
                str_repeat('s', count($updates)) . 'i',
                [...array_values($updates), $service_id]
            );

            // Send welcome/activation email
            Mailer::sendTemplate($service['email'], $service['first_name'], 'service_activated', [
                'client_name' => $service['first_name'],
                'domain'      => $service['domain'],
                'username'    => $result['username'] ?? $service['username'] ?? '',
                'password'    => $result['password'] ?? '(see welcome email)',
            ]);

            log_activity('service_provisioned', "Service #{$service_id} ({$service['domain']}) provisioned via {$service['module']}");
        } else {
            log_activity('service_provision_failed', "Service #{$service_id} provision failed: " . ($result['error'] ?? 'Unknown error'));
        }

        return $result;
    }

    /**
     * Suspend service via provisioning module
     */
    public static function suspend(int $service_id): array {
        $service = DB::row("SELECT s.*, p.module FROM services s JOIN products p ON p.id=s.product_id WHERE s.id=?", 'i', [$service_id]);
        if (!$service) return ['success' => false, 'error' => 'Service not found.'];

        $ref    = self::getServiceRef($service);
        $module = self::getModule($service_id);

        DB::execute("UPDATE services SET status='suspended' WHERE id=?", 'i', [$service_id]);

        if ($module && $ref) {
            $result = $module->suspend($ref);
            log_activity('service_suspended', "Service #{$service_id} suspended via API");
            return $result;
        }
        return ['success' => true, 'message' => 'Service suspended (no API module).'];
    }

    /**
     * Unsuspend service
     */
    public static function unsuspend(int $service_id): array {
        $service = DB::row("SELECT s.*, p.module FROM services s JOIN products p ON p.id=s.product_id WHERE s.id=?", 'i', [$service_id]);
        if (!$service) return ['success' => false, 'error' => 'Service not found.'];

        $ref    = self::getServiceRef($service);
        $module = self::getModule($service_id);

        DB::execute("UPDATE services SET status='active' WHERE id=?", 'i', [$service_id]);

        if ($module && $ref) {
            $result = $module->unsuspend($ref);
            log_activity('service_unsuspended', "Service #{$service_id} unsuspended via API");
            return $result;
        }
        return ['success' => true, 'message' => 'Service unsuspended (no API module).'];
    }

    /**
     * Terminate service
     */
    public static function terminate(int $service_id): array {
        $service = DB::row("SELECT s.*, p.module FROM services s JOIN products p ON p.id=s.product_id WHERE s.id=?", 'i', [$service_id]);
        if (!$service) return ['success' => false, 'error' => 'Service not found.'];

        $ref    = self::getServiceRef($service);
        $module = self::getModule($service_id);

        DB::execute("UPDATE services SET status='terminated', termination_date=NOW() WHERE id=?", 'i', [$service_id]);

        if ($module && $ref) {
            $result = $module->terminate($ref);
            log_activity('service_terminated', "Service #{$service_id} terminated via API");
            return $result;
        }
        return ['success' => true, 'message' => 'Service terminated (no API module).'];
    }

    /**
     * Extract service reference (username / server_id) from module_data
     */
    private static function getServiceRef(array $service): ?string {
        if ($service['username']) return $service['username'];
        if ($service['module_data']) {
            $data = json_decode($service['module_data'], true) ?? [];
            return $data['server_id'] ?? $data['service_id'] ?? $data['order_id'] ?? $data['cpanel_username'] ?? null;
        }
        return null;
    }
}
