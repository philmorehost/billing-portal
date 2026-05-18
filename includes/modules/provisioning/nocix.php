<?php
/**
 * NOCIX Dedicated Server Provisioning Module
 * API: https://www.nocix.net/api/
 * Supports: provision, suspend, terminate, reboot, get status
 */

require_once __DIR__ . '/base.php';

class NocixModule extends ProvisioningBase {

    private function headers(): array {
        $userId = $this->config['username'] ?? '';
        $apiKey = $this->config['api_key'] ?? '';
        $credentials = base64_encode($userId . ':' . $apiKey);
        return [
            'Authorization' => 'Basic ' . $credentials,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
    }

    private function jsonRequest(string $method, string $endpoint, array $data = []): array {
        $base = !empty($this->config['hostname']) ? rtrim($this->config['hostname'], '/') : 'https://my.nocix.net/api';
        $url  = $base . $endpoint;
        $hdrs = array_map(fn($k,$v)=>"{$k}: {$v}", array_keys($this->headers()), $this->headers());
        $ch   = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => $hdrs,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        ]);
        if ($data && $method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        $body      = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err       = curl_error($ch);
        curl_close($ch);

        if ($err) return ['success'=>false,'error'=>$err];
        $parsed = json_decode($body, true) ?? [];
        return ['success' => $http_code >= 200 && $http_code < 300, 'data' => $parsed, 'http_code' => $http_code];
    }

    /**
     * Order / provision a dedicated server
     */
    public function create(array $params): array {
        $result = $this->jsonRequest('POST', '/servers/order', [
            'product_id'    => $params['product_id'] ?? $this->config['default_product'],
            'hostname'      => $params['hostname'] ?? $params['domain'],
            'location'      => $params['location'] ?? $this->config['default_location'] ?? 'dallas',
            'os'            => $params['os'] ?? 'centos-7-64',
            'root_password' => $params['password'] ?? bin2hex(random_bytes(8)),
            'billing_cycle' => $params['billing_cycle'] ?? 'monthly',
            'notes'         => $params['notes'] ?? '',
        ]);

        if (!$result['success']) {
            $this->log('error', 'NOCIX order failed', $result);
            return ['success' => false, 'error' => $result['data']['message'] ?? 'Server order failed.'];
        }

        $data = $result['data'];
        $this->log('info', "NOCIX server ordered: " . ($data['server_id'] ?? 'unknown'));

        return [
            'success'     => true,
            'server_id'   => $data['server_id'] ?? null,
            'ip_address'  => $data['primary_ip'] ?? null,
            'hostname'    => $data['hostname'] ?? null,
            'status'      => $data['status'] ?? 'pending',
            'server_data' => $data,
        ];
    }

    /**
     * Suspend server (power off)
     */
    public function suspend(string $server_id): array {
        $result = $this->jsonRequest('POST', "/servers/{$server_id}/power", ['action' => 'off']);
        $ok = $result['success'];
        $this->log($ok?'info':'error', "NOCIX suspend server #{$server_id}");
        return ['success' => $ok, 'error' => $ok ? null : ($result['data']['message'] ?? 'Suspend failed.')];
    }

    /**
     * Unsuspend server (power on)
     */
    public function unsuspend(string $server_id): array {
        $result = $this->jsonRequest('POST', "/servers/{$server_id}/power", ['action' => 'on']);
        $ok = $result['success'];
        $this->log($ok?'info':'error', "NOCIX unsuspend server #{$server_id}");
        return ['success' => $ok, 'error' => $ok ? null : 'Unsuspend failed.'];
    }

    /**
     * Terminate / cancel server
     */
    public function terminate(string $server_id): array {
        $result = $this->jsonRequest('POST', "/servers/{$server_id}/cancel", [
            'reason'      => 'Cancelled by billing system',
            'immediately' => true,
        ]);
        $ok = $result['success'];
        $this->log($ok?'info':'error', "NOCIX terminate server #{$server_id}");
        return ['success' => $ok, 'error' => $ok ? null : 'Termination failed.'];
    }

    /**
     * Reboot server
     */
    public function reboot(string $server_id, string $type = 'soft'): array {
        $result = $this->jsonRequest('POST', "/servers/{$server_id}/power", [
            'action' => $type === 'hard' ? 'reset' : 'restart',
        ]);
        $ok = $result['success'];
        $this->log($ok?'info':'error', "NOCIX reboot ({$type}) server #{$server_id}");
        return ['success' => $ok, 'error' => $ok ? null : 'Reboot failed.'];
    }

    /**
     * Get server status and details
     */
    public function getStatus(string $server_id): array {
        $result = $this->jsonRequest('GET', "/servers/{$server_id}");
        if (!$result['success']) {
            return ['success' => false, 'error' => 'Server not found.'];
        }
        $data = $result['data'];
        return [
            'success'    => true,
            'server_id'  => $data['id'] ?? $server_id,
            'hostname'   => $data['hostname'] ?? '',
            'ip_address' => $data['primary_ip'] ?? '',
            'status'     => $data['status'] ?? 'unknown',
            'os'         => $data['os'] ?? '',
            'location'   => $data['location'] ?? '',
            'power'      => $data['power_status'] ?? 'unknown',
            'bandwidth'  => $data['bandwidth_used'] ?? 0,
        ];
    }

    /**
     * List available OS options
     */
    public function listOS(): array {
        $result = $this->jsonRequest('GET', '/os');
        return $result['success'] ? ($result['data']['operating_systems'] ?? []) : [];
    }

    /**
     * List available server products
     */
    public function listProducts(): array {
        $result = $this->jsonRequest('GET', '/products');
        return $result['success'] ? ($result['data']['products'] ?? []) : [];
    }
}
