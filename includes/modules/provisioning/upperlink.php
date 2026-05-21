<?php
/**
 * Upperlink .NG Domain Registrar Module
 * Handles .ng / .com.ng / .org.ng registrations
 * API: REST/JSON
 */

require_once __DIR__ . '/base.php';

class UpperlinkModule extends ProvisioningBase {

    private string $api_base = 'https://client.upperlink.ng/clients/modules/addons/DomainsReseller/api/index.php';

    private function getHeaders(): array {
        $username = DB::setting('module_upperlink_username');
        if (empty($username)) {
            $username = DB::setting('company_email', '');
        }
        $apiKey = $this->config['api_key'] ?? '';
        
        $timeStr = gmdate("y-m-d H");
        $hash = hash_hmac("sha256", $apiKey, "{$username}:{$timeStr}", true);
        $token = base64_encode($hash);

        return [
            "username: {$username}",
            "token: {$token}",
            "User-Agent: WHMBiller/1.1"
        ];
    }

    private function executeCall(string $action, string $method = 'POST', array $params = []): array {
        $url = "{$this->api_base}{$action}";
        $ch = curl_init();
        
        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        } else {
            if (!empty($params)) {
                $url .= '?' . http_build_query($params);
            }
            curl_setopt($ch, CURLOPT_URL, $url);
        }
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeaders());

        $body = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($body, true) ?? [];
        return [
            'http_code' => $http_code,
            'response' => $data,
            'raw' => $body
        ];
    }

    public function create(array $params): array {
        $domain  = $params['domain'];
        $years   = $params['years'] ?? 1;
        $contact = $params['contact'] ?? [];
        $ns      = $params['nameservers'] ?? ['ns1.upperlink.ng', 'ns2.upperlink.ng'];

        $contact_data = [
            'firstname'   => $contact['first_name'] ?? 'Client',
            'lastname'    => $contact['last_name'] ?? 'User',
            'fullname'    => ($contact['first_name'] ?? 'Client') . ' ' . ($contact['last_name'] ?? 'User'),
            'companyname' => $contact['company'] ?? 'N/A',
            'email'       => $contact['email'] ?? DB::setting('company_email'),
            'address1'    => $contact['address'] ?? 'N/A',
            'city'        => $contact['city'] ?? 'Lagos',
            'state'       => $contact['state'] ?? 'Lagos',
            'postcode'    => $contact['postcode'] ?? '100001',
            'country'     => $contact['country'] ?? 'NG',
            'phonenumber' => $contact['phone'] ?? '+234.0000000000'
        ];

        $payload = [
            'domain'      => $domain,
            'regperiod'   => $years,
            'nameservers' => [
                'ns1' => $ns[0] ?? 'ns1.upperlink.ng',
                'ns2' => $ns[1] ?? 'ns2.upperlink.ng',
                'ns3' => $ns[2] ?? '',
                'ns4' => $ns[3] ?? '',
                'ns5' => $ns[4] ?? ''
            ],
            'contacts'    => [
                'registrant' => $contact_data,
                'tech'       => $contact_data,
                'billing'    => $contact_data,
                'admin'      => $contact_data
            ],
            'addons'      => [
                'dnsmanagement'   => 0,
                'emailforwarding' => 0,
                'idprotection'    => 0
            ]
        ];

        $res = $this->executeCall('/order/domains/register', 'POST', $payload);
        $data = $res['response'];
        $ok = (isset($res['http_code']) && $res['http_code'] == 200 && !empty($data['result']) && $data['result'] === 'success') || (!empty($data['success']));
        
        $this->log($ok ? 'info' : 'error', "Upperlink register {$domain}: " . ($data['message'] ?? ''));
        return [
            'success'  => $ok,
            'order_id' => $data['orderid'] ?? null,
            'error'    => $ok ? null : ($data['message'] ?? 'Registration failed.')
        ];
    }

    public function renew(string $domain, int $years = 1): array {
        $payload = [
            'domain'    => $domain,
            'regperiod' => $years,
            'addons'    => [
                'dnsmanagement'   => 0,
                'emailforwarding' => 0,
                'idprotection'    => 0
            ]
        ];

        $res = $this->executeCall('/order/domains/renew', 'POST', $payload);
        $data = $res['response'];
        $ok = (isset($res['http_code']) && $res['http_code'] == 200 && !empty($data['result']) && $data['result'] === 'success') || (!empty($data['success']));
        
        return [
            'success' => $ok,
            'error'   => $ok ? null : ($data['message'] ?? 'Renewal failed.')
        ];
    }

    public function transfer(array $params): array {
        $domain  = $params['domain'];
        $years   = $params['years'] ?? 1;
        $contact = $params['contact'] ?? [];
        $ns      = $params['nameservers'] ?? ['ns1.upperlink.ng', 'ns2.upperlink.ng'];

        $contact_data = [
            'firstname'   => $contact['first_name'] ?? 'Client',
            'lastname'    => $contact['last_name'] ?? 'User',
            'fullname'    => ($contact['first_name'] ?? 'Client') . ' ' . ($contact['last_name'] ?? 'User'),
            'companyname' => $contact['company'] ?? 'N/A',
            'email'       => $contact['email'] ?? DB::setting('company_email'),
            'address1'    => $contact['address'] ?? 'N/A',
            'city'        => $contact['city'] ?? 'Lagos',
            'state'       => $contact['state'] ?? 'Lagos',
            'postcode'    => $contact['postcode'] ?? '100001',
            'country'     => $contact['country'] ?? 'NG',
            'phonenumber' => $contact['phone'] ?? '+234.0000000000'
        ];

        $payload = [
            'domain'      => $domain,
            'eppcode'     => $params['epp_code'] ?? '',
            'regperiod'   => $years,
            'nameservers' => [
                'ns1' => $ns[0] ?? 'ns1.upperlink.ng',
                'ns2' => $ns[1] ?? 'ns2.upperlink.ng',
                'ns3' => $ns[2] ?? '',
                'ns4' => $ns[3] ?? '',
                'ns5' => $ns[4] ?? ''
            ],
            'contacts'    => [
                'registrant' => $contact_data,
                'tech'       => $contact_data,
                'billing'    => $contact_data,
                'admin'      => $contact_data
            ],
            'addons'      => [
                'dnsmanagement'   => 0,
                'emailforwarding' => 0,
                'idprotection'    => 0
            ]
        ];

        $res = $this->executeCall('/order/domains/transfer', 'POST', $payload);
        $data = $res['response'];
        $ok = (isset($res['http_code']) && $res['http_code'] == 200 && !empty($data['result']) && $data['result'] === 'success') || (!empty($data['success']));
        
        return [
            'success' => $ok,
            'error'   => $ok ? null : ($data['message'] ?? 'Transfer failed.')
        ];
    }

    public function updateNameservers(string $domain, array $ns): array {
        $payload = [
            'domain' => $domain,
            'ns1'    => $ns[0] ?? '',
            'ns2'    => $ns[1] ?? '',
            'ns3'    => $ns[2] ?? '',
            'ns4'    => $ns[3] ?? '',
            'ns5'    => $ns[4] ?? ''
        ];

        $res = $this->executeCall("/domains/{$domain}/nameservers", 'POST', $payload);
        $data = $res['response'];
        $ok = (isset($res['http_code']) && $res['http_code'] == 200 && !empty($data['result']) && $data['result'] === 'success') || (!empty($data['success']));
        return ['success' => $ok, 'error' => $ok ? null : ($data['message'] ?? 'Nameserver update failed.')];
    }

    public function getEppCode(string $domain): array {
        $res = $this->executeCall("/domains/{$domain}/eppcode", 'GET');
        $data = $res['response'];
        $ok = (isset($res['http_code']) && $res['http_code'] == 200 && !empty($data['eppcode']));
        return [
            'success'  => $ok,
            'epp_code' => $data['eppcode'] ?? null,
            'error'    => $ok ? null : ($data['message'] ?? 'Failed to retrieve EPP code.')
        ];
    }

    public function suspend(string $domain_id): array { return ['success'=>true]; }
    public function unsuspend(string $domain_id): array { return ['success'=>true]; }
    public function terminate(string $domain_id): array { return ['success'=>true]; }

    public function getStatus(string $domain): array {
        $res = $this->executeCall("/domains/{$domain}/information", 'GET');
        $data = $res['response'];
        return [
            'success' => !empty($data),
            'data'    => $data
        ];
    }
}
