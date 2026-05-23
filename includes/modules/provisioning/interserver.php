<?php
/**
 * Interserver Provisioning Module
 * API: https://my.interserver.net/api-docs/elements.html
 */

require_once __DIR__ . '/base.php';

class InterserverModule extends ProvisioningBase {

    private function headers(): array {
        return [
            'X-API-KEY'    => $this->config['api_key'] ?? '',
            'Accept'       => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
    }

    private function apiRequest(string $method, string $endpoint, array $data = []): array {
        $url = 'https://my.interserver.net/apiv2' . $endpoint;
        return $this->request($method, $url, $data, $this->headers());
    }

    /**
     * Provision a VPS or Dedicated Server
     */
    public function create(array $params): array {
        $type = $this->config['type'] ?? 'vps'; // Default to vps
        $endpoint = ($type === 'dedicated') ? '/dedicated' : '/vps';

        // Interserver often requires hostname and a plan ID (external_id)
        $data = [
            'hostname' => $params['hostname'] ?? $params['domain'],
            'plan'     => $this->config['external_id'] ?? '',
        ];

        if (!empty($params['os'])) {
            $data['os'] = $params['os'];
        }

        $res = $this->apiRequest('POST', $endpoint, $data);

        if ($res['success']) {
            return [
                'success'   => true,
                'server_id' => $res['data']['id'] ?? $res['data']['vps_id'] ?? '',
                'message'   => 'Server provisioned successfully.',
                'server_data' => $res['data']
            ];
        }

        return [
            'success' => false,
            'error'   => $res['data']['message'] ?? 'Interserver API Error'
        ];
    }

    public function suspend(string $service_ref): array {
        // Interserver API uses service ID
        $res = $this->apiRequest('POST', "/vps/{$service_ref}/suspend");
        return ['success' => $res['success'], 'error' => $res['data']['message'] ?? null];
    }

    public function unsuspend(string $service_ref): array {
        $res = $this->apiRequest('POST', "/vps/{$service_ref}/unsuspend");
        return ['success' => $res['success'], 'error' => $res['data']['message'] ?? null];
    }

    public function terminate(string $service_ref): array {
        $res = $this->apiRequest('DELETE', "/vps/{$service_ref}");
        return ['success' => $res['success'], 'error' => $res['data']['message'] ?? null];
    }

    public function reboot(string $service_ref): array {
        $res = $this->apiRequest('POST', "/vps/{$service_ref}/reboot");
        return ['success' => $res['success'], 'error' => $res['data']['message'] ?? null];
    }

    public function getStatus(string $service_ref): array {
        $res = $this->apiRequest('GET', "/vps/{$service_ref}");
        if ($res['success']) {
            return [
                'success' => true,
                'status'  => $res['data']['status'] ?? 'active',
                'ip'      => $res['data']['ip'] ?? '',
                'data'    => $res['data']
            ];
        }
        return ['success' => false, 'error' => 'Could not fetch status.'];
    }
}
