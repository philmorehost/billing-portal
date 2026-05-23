<?php
/**
 * TheSSLStore Provisioning Module
 * API Docs: https://www.thesslstore.com/api/
 */

require_once __DIR__ . '/base.php';

class TheSSLStoreModule extends ProvisioningBase {

    private function getAuth(): array {
        return [
            'PartnerCode' => $this->config['partner_code'] ?? '',
            'AuthToken'   => $this->config['auth_token'] ?? '',
        ];
    }

    private function apiRequest(string $endpoint, array $data = []): array {
        $testMode = ($this->config['test_mode'] ?? '0') === '1';
        $base = $testMode ? 'https://sandbox-api.thesslstore.com/rest/v1' : 'https://api.thesslstore.com/rest/v1';
        $url = $base . $endpoint;

        $payload = array_merge($this->getAuth(), $data);

        return $this->request('POST', $url, json_encode($payload), [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ]);
    }

    /**
     * Create / Order a certificate
     */
    public function create(array $params): array {
        $data = [
            'ProductCode'    => $this->config['external_id'] ?? '',
            'ValidityPeriod' => 12, // 1 year default
            'ServerCount'    => 1,
            'CSR'           => $params['csr'] ?? '',
            'DomainName'    => $params['domain'],
            'AdminContact'  => [
                'FirstName' => $params['first_name'] ?? 'Admin',
                'LastName'  => $params['last_name'] ?? 'Contact',
                'Phone'     => $params['phone'] ?? '',
                'Email'     => $params['email'] ?? '',
            ],
            // Add other required fields based on TheSSLStore's Order/NewOrder endpoint
        ];

        $res = $this->apiRequest('/order/neworder', $data);

        if ($res['success'] && !empty($res['data']['TheSSLStoreOrderID'])) {
            return [
                'success'   => true,
                'server_id' => $res['data']['TheSSLStoreOrderID'],
                'message'   => 'SSL Certificate ordered successfully.',
                'server_data' => $res['data']
            ];
        }

        return [
            'success' => false,
            'error'   => $res['data']['AuthResponse']['Message'][0] ?? 'SSL Store API Error'
        ];
    }

    public function suspend(string $service_ref): array {
        return ['success' => true, 'message' => 'Suspend not supported for SSL via API.'];
    }

    public function unsuspend(string $service_ref): array {
        return ['success' => true];
    }

    public function terminate(string $service_ref): array {
        // Typically involves cancellation/refund request
        $res = $this->apiRequest('/order/cancel', ['TheSSLStoreOrderID' => $service_ref]);
        return ['success' => $res['success'], 'error' => $res['data']['AuthResponse']['Message'][0] ?? null];
    }

    public function getStatus(string $service_ref): array {
        $res = $this->apiRequest('/order/status', ['TheSSLStoreOrderID' => $service_ref]);
        if ($res['success']) {
            return [
                'success' => true,
                'status'  => $res['data']['OrderStatus']['MajorStatus'] ?? 'active',
                'data'    => $res['data']
            ];
        }
        return ['success' => false, 'error' => 'Could not fetch certificate status.'];
    }
}
