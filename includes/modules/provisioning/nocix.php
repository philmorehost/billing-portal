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
        $base = str_replace('/apidoc', '/api', $base);
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
        $this->log('info', "NOCIX server provision request received for " . ($params['domain'] ?? ''));
        return [
            'success'     => true,
            'server_id'   => 'nocix_' . bin2hex(random_bytes(4)),
            'ip_address'  => 'Pending Setup',
            'hostname'    => $params['domain'] ?? 'pending.nocix.net',
            'status'      => 'pending',
        ];
    }

    /**
     * Suspend server (disconnect from network)
     */
    public function suspend(string $server_id): array {
        $result = $this->jsonRequest('GET', "/disconnect-server/?service_id=" . urlencode($server_id));
        $ok = $result['success'];
        $this->log($ok?'info':'error', "NOCIX suspend server #{$server_id}");
        return ['success' => $ok, 'error' => $ok ? null : ($result['data']['message'] ?? 'Suspend failed.')];
    }

    /**
     * Unsuspend server (reconnect to network)
     */
    public function unsuspend(string $server_id): array {
        $result = $this->jsonRequest('GET', "/reconnect-server/?service_id=" . urlencode($server_id));
        $ok = $result['success'];
        $this->log($ok?'info':'error', "NOCIX unsuspend server #{$server_id}");
        return ['success' => $ok, 'error' => $ok ? null : 'Unsuspend failed.'];
    }

    /**
     * Terminate / cancel server
     */
    public function terminate(string $server_id): array {
        $result = $this->jsonRequest('GET', "/disconnect-server/?service_id=" . urlencode($server_id));
        $ok = $result['success'];
        $this->log($ok?'info':'error', "NOCIX terminate/disconnect server #{$server_id}");
        return ['success' => $ok, 'error' => $ok ? null : 'Termination failed.'];
    }

    /**
     * Reboot server
     */
    public function reboot(string $server_id, string $type = 'soft'): array {
        $result = $this->jsonRequest('GET', "/reboot-server/?service_id=" . urlencode($server_id));
        $ok = $result['success'];
        $this->log($ok?'info':'error', "NOCIX reboot server #{$server_id}");
        return ['success' => $ok, 'error' => $ok ? null : 'Reboot failed.'];
    }

    /**
     * Get server status and details
     */
    public function getStatus(string $server_id): array {
        $result = $this->jsonRequest('GET', "/list-services-details/?id=" . urlencode($server_id));
        if (!$result['success']) {
            return ['success' => false, 'error' => 'Server not found.'];
        }
        $data = $result['data'][$server_id] ?? $result['data'] ?? [];
        return [
            'success'    => true,
            'server_id'  => $server_id,
            'hostname'   => $data['name'] ?? '',
            'ip_address' => is_array($data['ipaddress'] ?? null) ? implode(', ', $data['ipaddress']) : ($data['ipaddress'] ?? ''),
            'status'     => $data['status'] ?? 'active',
            'type'       => $data['type'] ?? '',
            'addons'     => $data['addons'] ?? [],
        ];
    }

    /**
     * List all customer active services
     */
    public function listServices(array $params = []): array {
        $query = [];
        if (!empty($params['type'])) $query['type'] = $params['type'];
        if (isset($params['active'])) $query['active'] = $params['active'];
        if (!empty($params['id'])) $query['id'] = $params['id'];

        $endpoint = '/list-services/';
        if (!empty($query)) {
            $endpoint .= '?' . http_build_query($query);
        }

        $result = $this->jsonRequest('GET', $endpoint);
        if (!$result['success']) {
            $err = !empty($result['data']['message']) ? $result['data']['message'] : ($result['error'] ?? 'HTTP ' . ($result['http_code'] ?? 'Unknown'));
            throw new Exception("Nocix API error: " . $err . " (Status: " . ($result['http_code'] ?? '0') . ")");
        }
        return $result['data'] ?? [];
    }

    /**
     * List available OS options
     */
    public function listOS(string $service_id = ''): array {
        if (empty($service_id)) {
            try {
                $services = $this->listServices();
                if (!empty($services)) {
                    $first = reset($services);
                    $service_id = $first['id'] ?? '';
                }
            } catch (Exception $e) {}
        }
        $endpoint = '/os-list/';
        if (!empty($service_id)) {
            $endpoint .= '?service_id=' . urlencode($service_id);
        }
        $result = $this->jsonRequest('GET', $endpoint);
        if (!$result['success']) {
            $err = !empty($result['data']['message']) ? $result['data']['message'] : ($result['error'] ?? 'HTTP ' . ($result['http_code'] ?? 'Unknown'));
            throw new Exception("Nocix API error: " . $err . " (Status: " . ($result['http_code'] ?? '0') . ")");
        }
        return $result['data']['operating_systems'] ?? $result['data'] ?? [];
    }

    /**
     * List available server products / in-stock servers
     */
    public function listProducts(): array {
        $result = $this->jsonRequest('GET', '/in-stock-servers/');
        if (!$result['success']) {
            $err = !empty($result['data']['message']) ? $result['data']['message'] : ($result['error'] ?? 'HTTP ' . ($result['http_code'] ?? 'Unknown'));
            throw new Exception("Nocix API error: " . $err . " (Status: " . ($result['http_code'] ?? '0') . ")");
        }
        return $result['data'] ?? [];
    }
}
