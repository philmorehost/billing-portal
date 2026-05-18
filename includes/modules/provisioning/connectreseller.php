<?php
/**
 * ConnectReseller Domain Registrar Module
 * API Docs: https://api.connectreseller.com/
 */

require_once __DIR__ . '/base.php';

class ConnectResellerModule extends ProvisioningBase {

    private string $api_base = 'https://api.connectreseller.com/ConnectReseller/ESHOP/';

    public function __construct(array $config) {
        parent::__construct($config);
        // Fallback for older configuration keys (username/password)
        if (empty($this->config['brand_id']) && !empty($this->config['username'])) {
            $this->config['brand_id'] = $this->config['username'];
        }
        if (empty($this->config['api_key']) && !empty($this->config['password'])) {
            $this->config['api_key'] = $this->config['password'];
        }
    }

    private function apiUrl(string $endpoint): string {
        return $this->api_base . $endpoint;
    }

    private function authParams(): array {
        return ['APIKey' => $this->config['api_key'] ?? ''];
    }

    /**
     * Register a new domain
     */
    public function create(array $params): array {
        $domain = $params['domain'];
        $years  = $params['years'] ?? 1;
        $ns     = $params['nameservers'] ?? ['ns1.connectreseller.com', 'ns2.connectreseller.com'];
        $contact= $params['contact'] ?? [];

        // 1. Check if the customer/client already exists by email
        $email = $contact['email'] ?? DB::setting('company_email');
        $viewClientRes = $this->request('GET', $this->apiUrl('ViewClient'), [
            'APIKey'   => $this->config['api_key'] ?? '',
            'UserName' => $email
        ]);

        $clientId = null;
        if (isset($viewClientRes['data']['responseMsg']['statusCode']) && $viewClientRes['data']['responseMsg']['statusCode'] == 200) {
            $clientId = $viewClientRes['data']['responseData']['clientId'] ?? null;
        }

        // 2. If client does not exist, create client
        if (!$clientId) {
            $password = bin2hex(random_bytes(4)); // dynamic password
            $companyName = $contact['company'] ?? DB::setting('company_name', 'Client');
            $firstName = $contact['first_name'] ?? 'Client';
            $lastName = $contact['last_name'] ?? 'User';
            $address = $contact['address'] ?? 'N/A';
            $city = $contact['city'] ?? 'Lagos';
            $state = $contact['state'] ?? 'Lagos';
            $country = $contact['country'] ?? 'NG';
            $zip = $contact['postcode'] ?? '100001';
            $phone = $contact['phone'] ?? '+234.0000000000';
            
            // Format phone country code and number
            $phone_cc = '234';
            $phone_num = preg_replace('/[^0-9]/', '', $phone);
            if (strpos($phone, '+') === 0) {
                $phone_parts = explode('.', str_replace('+', '', $phone));
                if (count($phone_parts) >= 2) {
                    $phone_cc = $phone_parts[0];
                    $phone_num = $phone_parts[1];
                }
            }

            $addClientRes = $this->request('GET', $this->apiUrl('AddClient'), [
                'APIKey'      => $this->config['api_key'] ?? '',
                'UserName'    => $email,
                'Password'    => $password,
                'CompanyName' => $companyName,
                'FirstName'   => $firstName . ' ' . $lastName,
                'Address1'    => $address,
                'City'        => $city,
                'StateName'   => $state,
                'CountryName' => $country,
                'Zip'         => $zip,
                'PhoneNo_cc'  => $phone_cc,
                'PhoneNo'     => $phone_num
            ]);

            if (isset($addClientRes['data']['responseMsg']['statusCode']) && $addClientRes['data']['responseMsg']['statusCode'] == 200) {
                $clientId = $addClientRes['data']['responseData']['clientId'] ?? null;
            } else {
                $errMsg = $addClientRes['data']['responseMsg']['message'] ?? 'Failed to create client.';
                $this->log('error', "ConnectReseller AddClient failed: {$errMsg}");
                return ['success' => false, 'error' => "AddClient failed: " . $errMsg];
            }
        }

        // 3. Register the domain name (domainorder)
        $orderParams = [
            'APIKey'            => $this->config['api_key'] ?? '',
            'Id'                => $clientId,
            'ProductType'       => 1,
            'Websitename'       => $domain,
            'Duration'          => $years,
            'IsWhoisProtection' => 'false'
        ];
        if (isset($ns[0]) && !empty($ns[0])) $orderParams['ns1'] = $ns[0];
        if (isset($ns[1]) && !empty($ns[1])) $orderParams['ns2'] = $ns[1];
        if (isset($ns[2]) && !empty($ns[2])) $orderParams['ns3'] = $ns[2];
        if (isset($ns[3]) && !empty($ns[3])) $orderParams['ns4'] = $ns[3];

        $orderRes = $this->request('GET', $this->apiUrl('domainorder/'), $orderParams);
        $ok = isset($orderRes['data']['responseMsg']['statusCode']) && $orderRes['data']['responseMsg']['statusCode'] == 200;
        $errMsg = $orderRes['data']['responseMsg']['message'] ?? 'Registration failed.';

        $this->log($ok ? 'info' : 'error', "ConnectReseller register {$domain}: " . ($ok ? 'success' : $errMsg));

        return [
            'success'  => $ok,
            'order_id' => $orderRes['data']['responseData']['orderId'] ?? null,
            'error'    => $ok ? null : $errMsg
        ];
    }

    /**
     * Renew a domain
     */
    public function renew(string $domain, int $years = 1): array {
        // 1. Get Domain Info to extract customerId
        $viewRes = $this->request('GET', $this->apiUrl('ViewDomain/'), [
            'APIKey'      => $this->config['api_key'] ?? '',
            'websiteName' => $domain
        ]);

        if (!isset($viewRes['data']['responseMsg']['statusCode']) || $viewRes['data']['responseMsg']['statusCode'] != 200) {
            $errMsg = $viewRes['data']['responseMsg']['message'] ?? 'Could not retrieve domain info.';
            return ['success' => false, 'error' => "ViewDomain failed: " . $errMsg];
        }

        $customerId = $viewRes['data']['responseData']['customerId'] ?? null;
        if (!$customerId) {
            return ['success' => false, 'error' => "Customer ID not found in domain data."];
        }

        // 2. Call renewalorder
        $renewRes = $this->request('GET', $this->apiUrl('renewalorder/'), [
            'APIKey'            => $this->config['api_key'] ?? '',
            'Websitename'       => $domain,
            'OrderType'         => 2,
            'Duration'          => $years,
            'Id'                => $customerId,
            'IsWhoisProtection' => 'false'
        ]);

        $ok = isset($renewRes['data']['responseMsg']['statusCode']) && $renewRes['data']['responseMsg']['statusCode'] == 200;
        $errMsg = $renewRes['data']['responseMsg']['message'] ?? 'Renewal failed.';

        $this->log($ok ? 'info' : 'error', "ConnectReseller renew {$domain}: " . ($ok ? 'success' : $errMsg));

        return ['success' => $ok, 'error' => $ok ? null : $errMsg];
    }

    /**
     * Transfer a domain
     */
    public function transfer(array $params): array {
        $domain = $params['domain'];
        $epp_code = $params['epp_code'] ?? '';
        $contact = $params['contact'] ?? [];

        // 1. Check if client exists by email
        $email = $contact['email'] ?? DB::setting('company_email');
        $viewClientRes = $this->request('GET', $this->apiUrl('ViewClient'), [
            'APIKey'   => $this->config['api_key'] ?? '',
            'UserName' => $email
        ]);

        $clientId = null;
        if (isset($viewClientRes['data']['responseMsg']['statusCode']) && $viewClientRes['data']['responseMsg']['statusCode'] == 200) {
            $clientId = $viewClientRes['data']['responseData']['clientId'] ?? null;
        }

        // 2. If client does not exist, create client
        if (!$clientId) {
            $password = bin2hex(random_bytes(4));
            $companyName = $contact['company'] ?? DB::setting('company_name', 'Client');
            $firstName = $contact['first_name'] ?? 'Client';
            $lastName = $contact['last_name'] ?? 'User';
            $address = $contact['address'] ?? 'N/A';
            $city = $contact['city'] ?? 'Lagos';
            $state = $contact['state'] ?? 'Lagos';
            $country = $contact['country'] ?? 'NG';
            $zip = $contact['postcode'] ?? '100001';
            $phone = $contact['phone'] ?? '+234.0000000000';
            
            $phone_cc = '234';
            $phone_num = preg_replace('/[^0-9]/', '', $phone);
            if (strpos($phone, '+') === 0) {
                $phone_parts = explode('.', str_replace('+', '', $phone));
                if (count($phone_parts) >= 2) {
                    $phone_cc = $phone_parts[0];
                    $phone_num = $phone_parts[1];
                }
            }

            $addClientRes = $this->request('GET', $this->apiUrl('AddClient'), [
                'APIKey'      => $this->config['api_key'] ?? '',
                'UserName'    => $email,
                'Password'    => $password,
                'CompanyName' => $companyName,
                'FirstName'   => $firstName . ' ' . $lastName,
                'Address1'    => $address,
                'City'        => $city,
                'StateName'   => $state,
                'CountryName' => $country,
                'Zip'         => $zip,
                'PhoneNo_cc'  => $phone_cc,
                'PhoneNo'     => $phone_num
            ]);

            if (isset($addClientRes['data']['responseMsg']['statusCode']) && $addClientRes['data']['responseMsg']['statusCode'] == 200) {
                $clientId = $addClientRes['data']['responseData']['clientId'] ?? null;
            } else {
                $errMsg = $addClientRes['data']['responseMsg']['message'] ?? 'Failed to create client.';
                $this->log('error', "ConnectReseller AddClient failed: {$errMsg}");
                return ['success' => false, 'error' => "AddClient failed: " . $errMsg];
            }
        }

        // 3. Initiate TransferOrder
        $transferRes = $this->request('GET', $this->apiUrl('TransferOrder/'), [
            'APIKey'            => $this->config['api_key'] ?? '',
            'Id'                => $clientId,
            'OrderType'         => 4,
            'Websitename'       => $domain,
            'AuthCode'          => $epp_code,
            'IsWhoisProtection' => 'false'
        ]);

        $ok = isset($transferRes['data']['responseMsg']['statusCode']) && $transferRes['data']['responseMsg']['statusCode'] == 200;
        $errMsg = $transferRes['data']['responseMsg']['message'] ?? 'Transfer failed.';

        $this->log($ok ? 'info' : 'error', "ConnectReseller transfer {$domain}: " . ($ok ? 'success' : $errMsg));

        return ['success' => $ok, 'error' => $ok ? null : $errMsg];
    }

    /**
     * Update domain nameservers
     */
    public function updateNameservers(string $domain, array $ns): array {
        // 1. Get Domain Info to get domainNameId
        $viewRes = $this->request('GET', $this->apiUrl('ViewDomain/'), [
            'APIKey'      => $this->config['api_key'] ?? '',
            'websiteName' => $domain
        ]);

        if (!isset($viewRes['data']['responseMsg']['statusCode']) || $viewRes['data']['responseMsg']['statusCode'] != 200) {
            return ['success' => false, 'error' => 'ViewDomain failed'];
        }

        $domainNameId = $viewRes['data']['responseData']['domainNameId'] ?? null;
        if (!$domainNameId) {
            return ['success' => false, 'error' => 'Domain Name ID not found'];
        }

        // 2. Call UpdateNameServer
        $updateParams = [
            'APIKey'       => $this->config['api_key'] ?? '',
            'websiteName'  => $domain,
            'domainNameId' => $domainNameId
        ];
        if (isset($ns[0]) && !empty($ns[0])) $updateParams['nameServer1'] = $ns[0];
        if (isset($ns[1]) && !empty($ns[1])) $updateParams['nameServer2'] = $ns[1];
        if (isset($ns[2]) && !empty($ns[2])) $updateParams['nameServer3'] = $ns[2];
        if (isset($ns[3]) && !empty($ns[3])) $updateParams['nameServer4'] = $ns[3];

        $updateRes = $this->request('GET', $this->apiUrl('UpdateNameServer/'), $updateParams);
        $ok = isset($updateRes['data']['responseMsg']['statusCode']) && $updateRes['data']['responseMsg']['statusCode'] == 200;

        return ['success' => $ok, 'error' => $ok ? null : ($updateRes['data']['responseMsg']['message'] ?? 'Update nameservers failed.')];
    }

    /**
     * Get EPP / Auth code
     */
    public function getEppCode(string $domain): array {
        $viewRes = $this->request('GET', $this->apiUrl('ViewDomain/'), [
            'APIKey'      => $this->config['api_key'] ?? '',
            'websiteName' => $domain
        ]);

        if (isset($viewRes['data']['responseMsg']['statusCode']) && $viewRes['data']['responseMsg']['statusCode'] == 200) {
            $authCode = $viewRes['data']['responseData']['authCode'] ?? null;
            return ['success' => !empty($authCode), 'epp_code' => $authCode];
        }

        return ['success' => false, 'error' => $viewRes['data']['responseMsg']['message'] ?? 'Could not fetch EPP code.'];
    }

    /**
     * Get domain registration status
     */
    public function getStatus(string $domain): array {
        $viewRes = $this->request('GET', $this->apiUrl('ViewDomain/'), [
            'APIKey'      => $this->config['api_key'] ?? '',
            'websiteName' => $domain
        ]);

        return [
            'success' => isset($viewRes['data']['responseMsg']['statusCode']) && $viewRes['data']['responseMsg']['statusCode'] == 200,
            'data'    => $viewRes['data'] ?? []
        ];
    }

    public function suspend(string $domain): array { return ['success' => true]; }
    public function unsuspend(string $domain): array { return ['success' => true]; }
    public function terminate(string $domain): array { return ['success' => true]; }
}
