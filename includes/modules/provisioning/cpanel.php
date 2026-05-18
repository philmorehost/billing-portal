<?php
/**
 * WHM/cPanel Provisioning Module
 * Uses cPanel UAPI and WHM API v1
 * Supports: create, suspend, unsuspend, terminate, modify package, password change
 */

require_once __DIR__ . '/base.php';

class CpanelModule extends ProvisioningBase {

    /**
     * Build WHM API v1 URL
     */
    private function whmUrl(string $function, array $params = []): string {
        $host   = rtrim($this->config['hostname'], '/');
        $port   = $this->config['port'] ?? 2087;
        $base   = "https://{$host}:{$port}/json-api/{$function}?api.version=1";
        if ($params) $base .= '&' . http_build_query($params);
        return $base;
    }

    /**
     * WHM auth headers
     */
    private function whmHeaders(): array {
        if (!empty($this->config['api_key'])) {
            return ['Authorization' => 'whm root:' . $this->config['api_key']];
        }
        $credentials = base64_encode($this->config['username'] . ':' . $this->config['password']);
        return ['Authorization' => 'Basic ' . $credentials];
    }

    /**
     * Create cPanel hosting account
     */
    public function create(array $params): array {
        $username = $params['username'] ?? $this->generateUsername($params['domain']);
        $password = $params['password'] ?? $this->generatePassword();
        $domain   = $params['domain'];
        $package  = $params['package'] ?? $this->config['default_package'] ?? 'default';
        $email    = $params['email'] ?? '';

        $result = $this->request('GET', $this->whmUrl('createacct', [
            'username'   => $username,
            'password'   => $password,
            'domain'     => $domain,
            'plan'       => $package,
            'contactemail'=> $email,
            'ip'         => 'n',
            'cgi'        => 'y',
            'frontpage'  => 'n',
        ]), [], $this->whmHeaders());

        if (!$result['success']) {
            $this->log('error', "createacct failed for {$domain}", $result);
            return ['success' => false, 'error' => $result['error'] ?? 'Account creation failed.'];
        }

        $data = $result['data'];
        if (isset($data['result']['status']) && $data['result']['status'] == 0) {
            $err = $data['result']['statusmsg'] ?? 'Account creation failed.';
            $this->log('error', "createacct error: {$err}");
            return ['success' => false, 'error' => $err];
        }

        $this->log('info', "cPanel account created: {$username} / {$domain}");

        return [
            'success'  => true,
            'username' => $username,
            'password' => $password,
            'domain'   => $domain,
            'ip'       => $data['result']['ip'] ?? '',
            'nameservers' => $data['result']['nameservers'] ?? [],
            'server_data' => ['cpanel_username' => $username, 'domain' => $domain],
        ];
    }

    /**
     * Suspend account
     */
    public function suspend(string $username): array {
        $result = $this->request('GET', $this->whmUrl('suspendacct', [
            'user'   => $username,
            'reason' => 'Suspended by billing system',
        ]), [], $this->whmHeaders());

        $ok = isset($result['data']['result']['status']) && $result['data']['result']['status'] == 1;
        if ($ok) $this->log('info', "cPanel suspended: {$username}");
        else $this->log('error', "cPanel suspend failed: {$username}", $result);
        return ['success' => $ok, 'error' => $ok ? null : ($result['data']['result']['statusmsg'] ?? 'Suspend failed.')];
    }

    /**
     * Unsuspend account
     */
    public function unsuspend(string $username): array {
        $result = $this->request('GET', $this->whmUrl('unsuspendacct', [
            'user' => $username,
        ]), [], $this->whmHeaders());

        $ok = isset($result['data']['result']['status']) && $result['data']['result']['status'] == 1;
        if ($ok) $this->log('info', "cPanel unsuspended: {$username}");
        else $this->log('error', "cPanel unsuspend failed: {$username}", $result);
        return ['success' => $ok, 'error' => $ok ? null : 'Unsuspend failed.'];
    }

    /**
     * Terminate (remove) account
     */
    public function terminate(string $username): array {
        $result = $this->request('GET', $this->whmUrl('removeacct', [
            'user'    => $username,
            'keepdns' => 0,
        ]), [], $this->whmHeaders());

        $ok = isset($result['data']['result']['status']) && $result['data']['result']['status'] == 1;
        if ($ok) $this->log('info', "cPanel terminated: {$username}");
        else $this->log('error', "cPanel terminate failed: {$username}", $result);
        return ['success' => $ok, 'error' => $ok ? null : 'Termination failed.'];
    }

    /**
     * Get account status and info
     */
    public function getStatus(string $username): array {
        $result = $this->request('GET', $this->whmUrl('accountsummary', [
            'user' => $username,
        ]), [], $this->whmHeaders());

        if (!$result['success'] || empty($result['data']['acct'])) {
            return ['success' => false, 'error' => 'Account not found.'];
        }

        $acct = $result['data']['acct'][0];
        return [
            'success'   => true,
            'username'  => $acct['user'] ?? $username,
            'domain'    => $acct['domain'] ?? '',
            'suspended' => (bool)($acct['suspended'] ?? false),
            'package'   => $acct['plan'] ?? '',
            'disk_used' => $acct['diskused'] ?? 0,
            'disk_limit'=> $acct['disklimit'] ?? 0,
            'ip'        => $acct['ip'] ?? '',
        ];
    }

    /**
     * Change hosting package
     */
    public function changePackage(string $username, string $package): array {
        $result = $this->request('GET', $this->whmUrl('changepackage', [
            'user' => $username,
            'pkg'  => $package,
        ]), [], $this->whmHeaders());

        $ok = isset($result['data']['result']['status']) && $result['data']['result']['status'] == 1;
        return ['success' => $ok, 'error' => $ok ? null : 'Package change failed.'];
    }

    /**
     * Change cPanel password
     */
    public function changePassword(string $username, string $new_password): array {
        $result = $this->request('GET', $this->whmUrl('passwd', [
            'user'   => $username,
            'pass'   => $new_password,
            'db_pass_update' => 1,
        ]), [], $this->whmHeaders());

        $ok = isset($result['data']['result']['status']) && $result['data']['result']['status'] == 1;
        return ['success' => $ok];
    }

    /**
     * List all packages on the server
     */
    public function listPackages(): array {
        $result = $this->request('GET', $this->whmUrl('listpkgs'), [], $this->whmHeaders());
        if (!$result['success']) return [];
        return array_column($result['data']['package'] ?? [], 'name');
    }

    /**
     * Get cPanel login URL (SSO)
     */
    public function getLoginUrl(string $username): string {
        $host = rtrim($this->config['hostname'], '/');
        $port = $this->config['cpanel_port'] ?? 2083;
        return "https://{$host}:{$port}/login/?user={$username}";
    }

    private function generateUsername(string $domain): string {
        $name = preg_replace('/[^a-z0-9]/', '', strtolower(explode('.', $domain)[0]));
        return substr($name, 0, 8) . rand(10, 99);
    }

    private function generatePassword(int $length = 16): string {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
        $pass  = '';
        for ($i = 0; $i < $length; $i++) $pass .= $chars[random_int(0, strlen($chars) - 1)];
        return $pass;
    }
}
