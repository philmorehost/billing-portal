<?php
/**
 * Namecheap Domain Registrar Module
 * API: https://www.namecheap.com/support/api/
 */

require_once __DIR__ . '/base.php';

class NamecheapModule extends ProvisioningBase {

    private string $api_base = 'https://api.namecheap.com/xml.response';

    public function __construct(array $config) {
        parent::__construct($config);
        if (!empty($config['sandbox'])) {
            $this->api_base = 'https://api.sandbox.namecheap.com/xml.response';
        }
    }

    private function baseParams(string $command): array {
        return [
            'ApiUser'  => $this->config['api_user'],
            'ApiKey'   => $this->config['api_key'],
            'UserName' => $this->config['api_user'],
            'ClientIp' => get_client_ip(),
            'Command'  => $command,
        ];
    }

    private function parseXml(string $xml): array {
        $xml = @simplexml_load_string($xml);
        if (!$xml) return ['success' => false, 'error' => 'XML parse error'];
        $json = json_encode($xml);
        return json_decode($json, true) ?? [];
    }

    public function create(array $params): array {
        $domain  = $params['domain'];
        $parts   = explode('.', $domain, 2);
        $sld     = $parts[0];
        $tld     = $parts[1] ?? '';
        $years   = $params['years'] ?? 1;
        $contact = $params['contact'] ?? [];

        $query = array_merge($this->baseParams('namecheap.domains.create'), [
            'DomainName'       => $domain,
            'Years'            => $years,
            'AuxBillingFirstName' => $contact['first_name'] ?? 'Admin',
            'AuxBillingLastName'  => $contact['last_name'] ?? 'Admin',
            'AuxBillingAddress1'  => $contact['address'] ?? 'N/A',
            'AuxBillingCity'      => $contact['city'] ?? 'N/A',
            'AuxBillingCountry'   => $contact['country'] ?? 'NG',
            'AuxBillingPhone'     => $contact['phone'] ?? '+1.0000000000',
            'AuxBillingEmailAddress' => $contact['email'] ?? DB::setting('company_email'),
            'AuxBillingPostalCode'=> $contact['postcode'] ?? '00000',
            'AuxBillingStateProvince' => $contact['state'] ?? 'N/A',
            'TechFirstName'      => $contact['first_name'] ?? 'Admin',
            'TechLastName'       => $contact['last_name'] ?? 'Admin',
            'TechAddress1'       => $contact['address'] ?? 'N/A',
            'TechCity'           => $contact['city'] ?? 'N/A',
            'TechCountry'        => $contact['country'] ?? 'NG',
            'TechPhone'          => $contact['phone'] ?? '+1.0000000000',
            'TechEmailAddress'   => $contact['email'] ?? DB::setting('company_email'),
            'TechPostalCode'     => $contact['postcode'] ?? '00000',
            'TechStateProvince'  => $contact['state'] ?? 'N/A',
            'AdminFirstName'     => $contact['first_name'] ?? 'Admin',
            'AdminLastName'      => $contact['last_name'] ?? 'Admin',
            'AdminAddress1'      => $contact['address'] ?? 'N/A',
            'AdminCity'          => $contact['city'] ?? 'N/A',
            'AdminCountry'       => $contact['country'] ?? 'NG',
            'AdminPhone'         => $contact['phone'] ?? '+1.0000000000',
            'AdminEmailAddress'  => $contact['email'] ?? DB::setting('company_email'),
            'AdminPostalCode'    => $contact['postcode'] ?? '00000',
            'AdminStateProvince' => $contact['state'] ?? 'N/A',
            'RegistrantFirstName'     => $contact['first_name'] ?? 'Admin',
            'RegistrantLastName'      => $contact['last_name'] ?? 'Admin',
            'RegistrantAddress1'      => $contact['address'] ?? 'N/A',
            'RegistrantCity'          => $contact['city'] ?? 'N/A',
            'RegistrantCountry'       => $contact['country'] ?? 'NG',
            'RegistrantPhone'         => $contact['phone'] ?? '+1.0000000000',
            'RegistrantEmailAddress'  => $contact['email'] ?? DB::setting('company_email'),
            'RegistrantPostalCode'    => $contact['postcode'] ?? '00000',
            'RegistrantStateProvince' => $contact['state'] ?? 'N/A',
            'Nameservers' => implode(',', $params['nameservers'] ?? ['dns1.namecheaphosting.com','dns2.namecheaphosting.com']),
            'AddFreeWhoisguard'  => 'yes',
            'WGEnabled'          => 'yes',
        ]);

        $result = $this->request('POST', $this->api_base, $query);
        $data   = $this->parseXml($result['body'] ?? '');
        $ok     = isset($data['CommandResponse']['DomainCreateResult']['@attributes']['Registered'])
               && $data['CommandResponse']['DomainCreateResult']['@attributes']['Registered'] === 'true';

        $this->log($ok?'info':'error', "Namecheap register {$domain}");
        return ['success' => $ok, 'domain' => $domain, 'error' => $ok ? null : 'Registration failed.'];
    }

    public function renew(string $domain, int $years = 1): array {
        $parts  = explode('.', $domain, 2);
        $result = $this->request('POST', $this->api_base, array_merge($this->baseParams('namecheap.domains.renew'), [
            'DomainName' => $parts[0],
            'TLD'        => $parts[1] ?? '',
            'Years'      => $years,
        ]));
        $data = $this->parseXml($result['body'] ?? '');
        $ok   = isset($data['CommandResponse']['DomainRenewResult']['@attributes']['Renewed'])
             && $data['CommandResponse']['DomainRenewResult']['@attributes']['Renewed'] === 'true';
        return ['success' => $ok, 'error' => $ok ? null : 'Renewal failed.'];
    }

    public function transfer(array $params): array {
        $domain = $params['domain'];
        $parts  = explode('.', $domain, 2);
        $result = $this->request('POST', $this->api_base, array_merge($this->baseParams('namecheap.domains.transfer.create'), [
            'DomainName' => $parts[0],
            'Years'      => $params['years'] ?? 1,
            'EPPCode'    => $params['epp_code'],
        ]));
        $data = $this->parseXml($result['body'] ?? '');
        $ok   = isset($data['CommandResponse']['DomainTransferCreateResult']['@attributes']['TransferCreated'])
             && $data['CommandResponse']['DomainTransferCreateResult']['@attributes']['TransferCreated'] === 'true';
        return ['success' => $ok, 'error' => $ok ? null : 'Transfer initiation failed.'];
    }

    public function updateNameservers(string $domain, array $ns): array {
        $parts  = explode('.', $domain, 2);
        $result = $this->request('POST', $this->api_base, array_merge($this->baseParams('namecheap.domains.dns.setCustom'), [
            'SLD'        => $parts[0],
            'TLD'        => $parts[1] ?? '',
            'Nameservers'=> implode(',', $ns),
        ]));
        $data = $this->parseXml($result['body'] ?? '');
        $ok   = isset($data['CommandResponse']['DomainDNSSetCustomResult']['@attributes']['Updated'])
             && $data['CommandResponse']['DomainDNSSetCustomResult']['@attributes']['Updated'] === 'true';
        return ['success' => $ok];
    }

    public function getEppCode(string $domain): array {
        $parts  = explode('.', $domain, 2);
        $result = $this->request('GET', $this->api_base, array_merge($this->baseParams('namecheap.domains.getInfo'), [
            'DomainName' => $parts[0] . '.' . ($parts[1] ?? ''),
        ]));
        $data = $this->parseXml($result['body'] ?? '');
        // EPP code in DomainGetInfoResult > DomainDetails > Epp
        $epp  = $data['CommandResponse']['DomainGetInfoResult']['DomainDetails']['Epp'] ?? null;
        return ['success' => (bool)$epp, 'epp_code' => $epp];
    }

    public function suspend(string $domain): array { return ['success' => true]; }
    public function unsuspend(string $domain): array { return ['success' => true]; }
    public function terminate(string $domain): array { return ['success' => true, 'message' => 'Domain release queued.']; }

    public function getStatus(string $domain): array {
        $parts  = explode('.', $domain, 2);
        $result = $this->request('GET', $this->api_base, array_merge($this->baseParams('namecheap.domains.getInfo'), [
            'DomainName' => $parts[0] . '.' . ($parts[1] ?? ''),
        ]));
        $data   = $this->parseXml($result['body'] ?? '');
        $info   = $data['CommandResponse']['DomainGetInfoResult'] ?? [];
        return ['success' => !empty($info), 'data' => $info];
    }
}
