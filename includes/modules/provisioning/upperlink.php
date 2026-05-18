<?php
/**
 * Upperlink .NG Domain Registrar Module
 * Handles .ng / .com.ng / .org.ng registrations
 * API: REST/JSON
 */

require_once __DIR__ . '/base.php';

class UpperlinkModule extends ProvisioningBase {

    private string $api_base = 'https://api.upperlink.ng/api/v1';

    private function headers(): array {
        return [
            'Authorization' => 'Bearer ' . $this->config['api_key'],
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
    }

    public function create(array $params): array {
        $domain  = $params['domain'];
        $years   = $params['years'] ?? 1;
        $contact = $params['contact'] ?? [];

        $payload = json_encode([
            'domain'   => $domain,
            'years'    => $years,
            'nameservers' => $params['nameservers'] ?? ['ns1.upperlink.ng','ns2.upperlink.ng'],
            'contact'  => [
                'firstname' => $contact['first_name'] ?? 'Admin',
                'lastname'  => $contact['last_name'] ?? 'Admin',
                'email'     => $contact['email'] ?? DB::setting('company_email'),
                'phone'     => $contact['phone'] ?? '+234.0000000000',
                'address'   => $contact['address'] ?? 'N/A',
                'city'      => $contact['city'] ?? 'Lagos',
                'state'     => $contact['state'] ?? 'Lagos',
                'country'   => 'NG',
                'postcode'  => $contact['postcode'] ?? '100001',
            ],
        ]);

        $result = $this->request('POST', "{$this->api_base}/domains/register", [], $this->headers());
        // Override body for JSON POST
        $ch = curl_init("{$this->api_base}/domains/register");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer '.$this->config['api_key'],'Content-Type: application/json','Accept: application/json'],
        ]);
        $body = curl_exec($ch); curl_close($ch);
        $data = json_decode($body, true) ?? [];

        $ok = !empty($data['success']);
        $this->log($ok?'info':'error', "Upperlink register {$domain}");
        return ['success' => $ok, 'order_id' => $data['data']['id'] ?? null, 'error' => $ok ? null : ($data['message'] ?? 'Registration failed.')];
    }

    public function renew(string $domain_id, int $years = 1): array {
        $ch = curl_init("{$this->api_base}/domains/{$domain_id}/renew");
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['years'=>$years]),CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$this->config['api_key'],'Content-Type: application/json']]);
        $data=json_decode(curl_exec($ch),true)??[]; curl_close($ch);
        $ok=!empty($data['success']);
        return ['success'=>$ok,'error'=>$ok?null:($data['message']??'Renewal failed.')];
    }

    public function transfer(array $params): array {
        $ch = curl_init("{$this->api_base}/domains/transfer");
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['domain'=>$params['domain'],'auth_code'=>$params['epp_code']]),CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$this->config['api_key'],'Content-Type: application/json']]);
        $data=json_decode(curl_exec($ch),true)??[]; curl_close($ch);
        $ok=!empty($data['success']);
        return ['success'=>$ok,'error'=>$ok?null:($data['message']??'Transfer failed.')];
    }

    public function updateNameservers(string $domain_id, array $ns): array {
        $ch = curl_init("{$this->api_base}/domains/{$domain_id}/nameservers");
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>'PUT',CURLOPT_POSTFIELDS=>json_encode(['nameservers'=>$ns]),CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$this->config['api_key'],'Content-Type: application/json']]);
        $data=json_decode(curl_exec($ch),true)??[]; curl_close($ch);
        return ['success'=>!empty($data['success'])];
    }

    public function getEppCode(string $domain_id): array {
        $ch = curl_init("{$this->api_base}/domains/{$domain_id}/auth-code");
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$this->config['api_key']]]);
        $data=json_decode(curl_exec($ch),true)??[]; curl_close($ch);
        $ok=!empty($data['auth_code']);
        return ['success'=>$ok,'epp_code'=>$data['auth_code']??null];
    }

    public function suspend(string $domain_id): array { return ['success'=>true]; }
    public function unsuspend(string $domain_id): array { return ['success'=>true]; }
    public function terminate(string $domain_id): array { return ['success'=>true]; }

    public function getStatus(string $domain_id): array {
        $ch = curl_init("{$this->api_base}/domains/{$domain_id}");
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$this->config['api_key']]]);
        $data=json_decode(curl_exec($ch),true)??[]; curl_close($ch);
        return ['success'=>!empty($data['data']),'data'=>$data['data']??[]];
    }
}
