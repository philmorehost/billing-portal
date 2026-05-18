<?php
/**
 * Time4VPS VPS Provisioning Module
 * API: https://api.time4vps.com/
 * Supports: provision, suspend, terminate, reboot, reinstall OS, get status
 */

require_once __DIR__ . '/base.php';

class Time4VPSModule extends ProvisioningBase {

    private string $api_base = 'https://api.time4vps.com/v2';

    private function headers(): array {
        $credentials = base64_encode($this->config['username'] . ':' . $this->config['password']);
        return [
            'Authorization' => 'Basic ' . $credentials,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
    }

    private function jsonRequest(string $method, string $endpoint, array $data = []): array {
        $url  = $this->api_base . $endpoint;
        $hdrs = array_map(fn($k,$v)=>"{$k}: {$v}", array_keys($this->headers()), $this->headers());
        $ch   = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => $hdrs,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        ]);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        $body      = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err       = curl_error($ch);
        curl_close($ch);

        if ($err) return ['success' => false, 'error' => $err];
        $parsed = json_decode($body, true) ?? [];
        return ['success' => $http_code >= 200 && $http_code < 300, 'data' => $parsed, 'http_code' => $http_code];
    }

    /**
     * Order / provision a VPS
     */
    public function create(array $params): array {
        // Step 1: Get available products
        $product_id = $params['product_id'] ?? $this->config['default_product_id'];

        // Step 2: Place order
        $result = $this->jsonRequest('POST', '/orders', [
            'product_id'  => $product_id,
            'hostname'    => $params['hostname'] ?? $params['domain'],
            'password'    => $params['password'] ?? bin2hex(random_bytes(8)),
            'template_id' => $params['template_id'] ?? $this->config['default_template'] ?? 1,
            'period'      => $params['period'] ?? 1,
        ]);

        if (!$result['success']) {
            $this->log('error', 'Time4VPS create failed', $result);
            return ['success' => false, 'error' => $result['data']['message'] ?? 'VPS creation failed.'];
        }

        $data = $result['data'];
        $this->log('info', "Time4VPS VPS ordered: order #{$data['id']}");

        // Get service from the order
        $service_id = $data['service_id'] ?? null;

        return [
            'success'     => true,
            'order_id'    => $data['id'] ?? null,
            'service_id'  => $service_id,
            'ip_address'  => $data['main_ip'] ?? null,
            'hostname'    => $params['hostname'] ?? '',
            'status'      => 'pending',
            'server_data' => $data,
        ];
    }

    /**
     * Suspend VPS (shutdown)
     */
    public function suspend(string $service_id): array {
        $result = $this->jsonRequest('POST', "/services/{$service_id}/vps/shutdown");
        $ok = $result['success'];
        $this->log($ok?'info':'error', "Time4VPS suspend #{$service_id}");
        return ['success' => $ok, 'error' => $ok ? null : ($result['data']['message'] ?? 'Suspend failed.')];
    }

    /**
     * Unsuspend VPS (boot)
     */
    public function unsuspend(string $service_id): array {
        $result = $this->jsonRequest('POST', "/services/{$service_id}/vps/boot");
        $ok = $result['success'];
        $this->log($ok?'info':'error', "Time4VPS unsuspend #{$service_id}");
        return ['success' => $ok, 'error' => $ok ? null : 'Boot failed.'];
    }

    /**
     * Terminate VPS
     */
    public function terminate(string $service_id): array {
        $result = $this->jsonRequest('POST', "/services/{$service_id}/cancel", [
            'reason'      => 'Terminated by billing system',
            'immediately' => true,
        ]);
        $ok = $result['success'];
        $this->log($ok?'info':'error', "Time4VPS terminate #{$service_id}");
        return ['success' => $ok, 'error' => $ok ? null : 'Termination failed.'];
    }

    /**
     * Reboot VPS
     */
    public function reboot(string $service_id, bool $hard = false): array {
        $endpoint = $hard ? "/services/{$service_id}/vps/reset" : "/services/{$service_id}/vps/reboot";
        $result   = $this->jsonRequest('POST', $endpoint);
        $ok = $result['success'];
        $this->log($ok?'info':'error', "Time4VPS ".($hard?'hard':'soft')." reboot #{$service_id}");
        return ['success' => $ok, 'error' => $ok ? null : 'Reboot failed.'];
    }

    /**
     * Reinstall OS on VPS
     */
    public function reinstallOS(string $service_id, int $template_id): array {
        $result = $this->jsonRequest('POST', "/services/{$service_id}/vps/reinstall", [
            'template_id' => $template_id,
        ]);
        $ok = $result['success'];
        $this->log($ok?'info':'error', "Time4VPS reinstall OS on #{$service_id} template:{$template_id}");
        return ['success' => $ok, 'error' => $ok ? null : ($result['data']['message'] ?? 'OS reinstall failed.')];
    }

    /**
     * Get VPS status and details
     */
    public function getStatus(string $service_id): array {
        $result = $this->jsonRequest('GET', "/services/{$service_id}/vps");
        if (!$result['success']) {
            return ['success' => false, 'error' => 'VPS info not available.'];
        }
        $data = $result['data'];
        return [
            'success'    => true,
            'service_id' => $service_id,
            'hostname'   => $data['hostname'] ?? '',
            'ip_address' => $data['main_ip'] ?? '',
            'status'     => $data['status'] ?? 'unknown',
            'os'         => $data['template'] ?? '',
            'cpu'        => $data['cpu_cores'] ?? 0,
            'ram'        => $data['memory'] ?? 0,
            'disk'       => $data['disk'] ?? 0,
            'bandwidth'  => $data['bandwidth'] ?? 0,
            'power'      => $data['power_status'] ?? 'unknown',
        ];
    }

    /**
     * List available OS templates
     */
    public function listTemplates(): array {
        $result = $this->jsonRequest('GET', '/templates');
        return $result['success'] ? ($result['data']['templates'] ?? []) : [];
    }

    /**
     * List available VPS products
     */
    public function listProducts(): array {
        $result = $this->jsonRequest('GET', '/products?category=vps');
        return $result['success'] ? ($result['data']['products'] ?? []) : [];
    }

    /**
     * Get VPS console access URL
     */
    public function getConsoleUrl(string $service_id): array {
        $result = $this->jsonRequest('POST', "/services/{$service_id}/vps/console");
        $ok = $result['success'] && !empty($result['data']['url']);
        return ['success' => $ok, 'url' => $result['data']['url'] ?? null];
    }
}
