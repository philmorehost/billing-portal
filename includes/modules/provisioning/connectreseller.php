<?php
/**
 * ConnectReseller Domain Registrar Module
 * API Docs: https://api.connectreseller.com/
 */

require_once __DIR__ . '/base.php';

class ConnectResellerModule extends ProvisioningBase {

    private string $api_base = 'https://api.connectreseller.com/ConnectReseller/ESHOP/';

    private function apiUrl(string $endpoint): string {
        return $this->api_base . $endpoint;
    }

    private function authParams(): array {
        return ['websiteUsername' => $this->config['username'], 'websitePassword' => $this->config['password']];
    }

    public function create(array $params): array {
        $domain = $params['domain'];
        $years  = $params['years'] ?? 1;
        $ns     = $params['nameservers'] ?? ['ns1.connectreseller.com','ns2.connectreseller.com'];
        $contact= $params['contact'] ?? [];

        $result = $this->request('GET', $this->apiUrl('RegisterDomain'), array_merge($this->authParams(), [
            'DomainName' => $domain,
            'year'       => $years,
            'ns1'        => $ns[0] ?? '',
            'ns2'        => $ns[1] ?? '',
            'ns3'        => $ns[2] ?? '',
            'ns4'        => $ns[3] ?? '',
            'RegistrantFirstName'  => $contact['first_name'] ?? 'Admin',
            'RegistrantLastName'   => $contact['last_name'] ?? 'Admin',
            'RegistrantEmail'      => $contact['email'] ?? DB::setting('company_email'),
            'RegistrantPhone'      => $contact['phone'] ?? '+234.0000000000',
            'RegistrantAddress'    => $contact['address'] ?? 'N/A',
            'RegistrantCity'       => $contact['city'] ?? 'Lagos',
            'RegistrantStateProvince'=> $contact['state'] ?? 'Lagos',
            'RegistrantPostalCode' => $contact['postcode'] ?? '100001',
            'RegistrantCountry'    => $contact['country'] ?? 'NG',
        ]));

        $data = $result['data'];
        $ok   = isset($data['responseCode']) && $data['responseCode'] == 200;
        $this->log($ok?'info':'error', "ConnectReseller register {$domain}: ".($data['message']??''));
        return ['success' => $ok, 'order_id' => $data['orderid'] ?? null, 'error' => $ok ? null : ($data['message'] ?? 'Registration failed.')];
    }

    public function renew(string $domain, int $years = 1): array {
        $result = $this->request('GET', $this->apiUrl('RenewDomain'), array_merge($this->authParams(), [
            'DomainName' => $domain,
            'year'       => $years,
        ]));
        $data = $result['data'];
        $ok   = isset($data['responseCode']) && $data['responseCode'] == 200;
        return ['success' => $ok, 'error' => $ok ? null : ($data['message'] ?? 'Renewal failed.')];
    }

    public function transfer(array $params): array {
        $result = $this->request('GET', $this->apiUrl('TransferDomain'), array_merge($this->authParams(), [
            'DomainName' => $params['domain'],
            'authcode'   => $params['epp_code'],
        ]));
        $data = $result['data'];
        $ok   = isset($data['responseCode']) && $data['responseCode'] == 200;
        return ['success' => $ok, 'error' => $ok ? null : ($data['message'] ?? 'Transfer failed.')];
    }

    public function updateNameservers(string $domain, array $ns): array {
        $query = array_merge($this->authParams(), ['DomainName' => $domain]);
        foreach ($ns as $i => $n) $query['ns'.($i+1)] = $n;
        $result = $this->request('GET', $this->apiUrl('ModifyNameServer'), $query);
        $ok = isset($result['data']['responseCode']) && $result['data']['responseCode'] == 200;
        return ['success' => $ok];
    }

    public function getEppCode(string $domain): array {
        $result = $this->request('GET', $this->apiUrl('GetEPPCode'), array_merge($this->authParams(), ['DomainName' => $domain]));
        $data   = $result['data'];
        $ok     = isset($data['eppcode']);
        return ['success' => $ok, 'epp_code' => $data['eppcode'] ?? null];
    }

    public function getStatus(string $domain): array {
        $result = $this->request('GET', $this->apiUrl('GetDomainInfo'), array_merge($this->authParams(), ['DomainName' => $domain]));
        return ['success' => $result['success'], 'data' => $result['data']];
    }

    public function suspend(string $domain): array { return ['success' => true]; }
    public function unsuspend(string $domain): array { return ['success' => true]; }
    public function terminate(string $domain): array { return ['success' => true]; }
}
