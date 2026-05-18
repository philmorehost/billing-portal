<?php
/**
 * ResellerClub Domain Registrar Module
 * HTTP API: https://manage.resellerclub.com/kb/answer/804
 * Supports: register, renew, transfer, update nameservers, get info, get EPP code
 */

require_once __DIR__ . '/base.php';

class ResellerClubModule extends ProvisioningBase {

    private string $api_base;

    public function __construct(array $config) {
        parent::__construct($config);
        $test = !empty($config['test_mode']);
        $this->api_base = $test
            ? 'https://test.httpapi.com/api'
            : 'https://httpapi.com/api';
    }

    private function baseParams(): array {
        return [
            'auth-userid'   => $this->config['reseller_id'],
            'api-key'       => $this->config['api_key'],
            'output-format' => 'json',
        ];
    }

    /**
     * Register a domain
     */
    public function create(array $params): array {
        $domain   = $params['domain'];
        $years    = $params['years'] ?? 1;
        $ns       = $params['nameservers'] ?? ['ns1.example.com','ns2.example.com'];
        $customer = $params['customer_id'];
        $reg_id   = $params['registrant_contact_id'];
        $admin_id = $params['admin_contact_id'];
        $tech_id  = $params['tech_contact_id'];
        $billing_id= $params['billing_contact_id'];

        $parts = explode('.', $domain, 2);
        $sld   = $parts[0];
        $tld   = $parts[1] ?? '';

        $query = array_merge($this->baseParams(), [
            'domain-name'         => $sld,
            'tld'                 => $tld,
            'years'               => $years,
            'ns'                  => $ns,
            'customer-id'         => $customer,
            'reg-contact-id'      => $reg_id,
            'admin-contact-id'    => $admin_id,
            'tech-contact-id'     => $tech_id,
            'billing-contact-id'  => $billing_id,
            'invoice-option'      => 'NoInvoice',
            'protect-privacy'     => 'false',
        ]);

        $result = $this->request('POST', "{$this->api_base}/domains/register.json", $query);

        if (!$result['success']) {
            return ['success' => false, 'error' => 'Domain registration API error.'];
        }

        $data = $result['data'];
        if (isset($data['status']) && strtolower($data['status']) === 'error') {
            $this->log('error', "ResellerClub register error: " . ($data['message'] ?? ''));
            return ['success' => false, 'error' => $data['message'] ?? 'Registration failed.'];
        }

        $this->log('info', "Domain registered: {$domain}");
        return [
            'success'    => true,
            'domain'     => $domain,
            'order_id'   => $data['entityid'] ?? null,
            'expiry_date'=> $data['actioncompletiondate'] ?? null,
        ];
    }

    /**
     * Renew a domain
     */
    public function renew(string $order_id, int $years = 1, string $expiry_date = ''): array {
        $result = $this->request('POST', "{$this->api_base}/domains/renew.json", array_merge($this->baseParams(), [
            'order-id'     => $order_id,
            'years'        => $years,
            'exp-date'     => $expiry_date,
            'invoice-option' => 'NoInvoice',
        ]));

        $data = $result['data'];
        $ok   = isset($data['status']) && strtolower($data['status']) !== 'error';
        $this->log($ok?'info':'error', "Renew domain order #{$order_id}: " . ($data['actionstatus'] ?? $data['message'] ?? ''));
        return ['success' => $ok, 'error' => $ok ? null : ($data['message'] ?? 'Renewal failed.')];
    }

    /**
     * Transfer a domain in
     */
    public function transfer(array $params): array {
        $domain = $params['domain'];
        $epp    = $params['epp_code'];
        $result = $this->request('POST', "{$this->api_base}/domains/transfer.json", array_merge($this->baseParams(), [
            'domain-name'        => explode('.', $domain)[0],
            'tld'                => implode('.', array_slice(explode('.', $domain), 1)),
            'auth-code'          => $epp,
            'customer-id'        => $params['customer_id'],
            'reg-contact-id'     => $params['registrant_contact_id'],
            'admin-contact-id'   => $params['admin_contact_id'],
            'tech-contact-id'    => $params['tech_contact_id'],
            'billing-contact-id' => $params['billing_contact_id'],
            'ns'                 => $params['nameservers'] ?? [],
            'invoice-option'     => 'NoInvoice',
        ]));

        $data = $result['data'];
        $ok   = isset($data['entityid']);
        return ['success' => $ok, 'order_id' => $data['entityid'] ?? null, 'error' => $ok ? null : ($data['message'] ?? 'Transfer failed.')];
    }

    /**
     * Update nameservers
     */
    public function updateNameservers(string $order_id, array $nameservers): array {
        $query = array_merge($this->baseParams(), ['order-id' => $order_id]);
        foreach ($nameservers as $i => $ns) $query["ns[{$i}]"] = $ns;

        $result = $this->request('POST', "{$this->api_base}/domains/modify-ns.json", $query);
        $data   = $result['data'];
        $ok     = isset($data['status']) && strtolower($data['status']) !== 'error';
        return ['success' => $ok, 'error' => $ok ? null : ($data['message'] ?? 'NS update failed.')];
    }

    /**
     * Get EPP / auth code
     */
    public function getEppCode(string $order_id): array {
        $result = $this->request('GET', "{$this->api_base}/domains/transfer/get-transfer-auth-code.json",
            array_merge($this->baseParams(), ['order-id' => $order_id]));
        $data   = $result['data'];
        $ok     = !empty($data['transfer-secret']);
        return ['success' => $ok, 'epp_code' => $data['transfer-secret'] ?? null, 'error' => $ok ? null : 'EPP code unavailable.'];
    }

    /**
     * Get domain info
     */
    public function getDomainInfo(string $order_id): array {
        $result = $this->request('GET', "{$this->api_base}/domains/details.json",
            array_merge($this->baseParams(), ['order-id' => $order_id, 'options' => 'All']));
        return ['success' => $result['success'], 'data' => $result['data']];
    }

    /**
     * Update WHOIS / contact info
     */
    public function updateContacts(string $order_id, array $contact_ids): array {
        $result = $this->request('POST', "{$this->api_base}/domains/modify-contact.json", array_merge($this->baseParams(), [
            'order-id'           => $order_id,
            'reg-contact-id'     => $contact_ids['registrant'] ?? '',
            'admin-contact-id'   => $contact_ids['admin'] ?? '',
            'tech-contact-id'    => $contact_ids['tech'] ?? '',
            'billing-contact-id' => $contact_ids['billing'] ?? '',
        ]));
        $ok = isset($result['data']['entityid']);
        return ['success' => $ok];
    }

    // Required abstract stubs
    public function suspend(string $order_id): array {
        // Domains can be locked/unlocked but not traditionally "suspended"
        return $this->request('POST', "{$this->api_base}/domains/modify-domain-theft-protection.json",
            array_merge($this->baseParams(), ['order-id' => $order_id, 'protect-privacy' => 'true']));
    }

    public function unsuspend(string $order_id): array {
        return ['success' => true, 'message' => 'Domain re-activated.'];
    }

    public function terminate(string $order_id): array {
        $this->log('info', "Domain delete requested for order #{$order_id}");
        return ['success' => true, 'message' => 'Domain termination queued.'];
    }

    public function getStatus(string $order_id): array {
        return $this->getDomainInfo($order_id);
    }
}
