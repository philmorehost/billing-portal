<?php
/**
 * WordPress Management Module
 * Uses cPanel UAPI + WP-CLI execution via cPanel's RunCommand API
 * Provides: one-click login, core update, plugin/theme management, maintenance mode
 */

class WordPressManager {

    private array $cpanel_config;
    private string $username;
    private string $domain;

    public function __construct(string $cpanel_host, int $cpanel_port, string $api_key, string $username, string $domain) {
        $this->cpanel_config = ['hostname' => $cpanel_host, 'port' => $cpanel_port, 'api_key' => $api_key];
        $this->username = $username;
        $this->domain   = $domain;
    }

    // ── cPanel API helpers ─────────────────────────────────────────────────

    private function cpanelApiUrl(string $module, string $func, array $params = []): string {
        $host  = $this->cpanel_config['hostname'];
        $port  = $this->cpanel_config['port'] ?? 2083;
        $query = http_build_query(array_merge(['cpanel_jsonapi_user' => $this->username, 'cpanel_jsonapi_module' => $module, 'cpanel_jsonapi_func' => $func, 'cpanel_jsonapi_apiversion' => '2'], $params));
        return "https://{$host}:{$port}/json-api/cpanel?{$query}";
    }

    private function whmApiUrl(string $function, array $params = []): string {
        $host  = $this->cpanel_config['hostname'];
        $port  = $this->cpanel_config['port'] ?? 2087;
        $query = http_build_query(array_merge(['api.version' => 1], $params));
        return "https://{$host}:{$port}/json-api/{$function}?{$query}";
    }

    private function request(string $url): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => ['Authorization: whm root:' . $this->cpanel_config['api_key']],
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($err) return ['success' => false, 'error' => $err];
        $data = json_decode($body, true) ?? [];
        return ['success' => true, 'data' => $data];
    }

    /**
     * Run a WP-CLI command on the server via cPanel RunCommand UAPI
     */
    private function wpCli(string $wp_path, string $command): array {
        $full_cmd = "cd " . escapeshellarg($wp_path) . " && wp " . $command . " --allow-root 2>&1";
        $url = $this->cpanelApiUrl('API2', 'exec', [
            'command' => base64_encode($full_cmd),
        ]);

        // Try UAPI exec endpoint
        $host  = $this->cpanel_config['hostname'];
        $port  = $this->cpanel_config['port'] ?? 2083;
        $ch    = curl_init("https://{$host}:{$port}/execute/CommandManager/run");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['command' => $full_cmd]),
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => [
                'Authorization: cpanel ' . $this->username . ':' . $this->cpanel_config['api_key'],
            ],
        ]);
        $body  = curl_exec($ch);
        $err   = curl_error($ch);
        $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) return ['success' => false, 'error' => $err, 'output' => ''];
        $data = json_decode($body, true) ?? [];
        $output = $data['data']['output'] ?? $data['result']['output'] ?? $body;
        return ['success' => $code === 200, 'output' => $output, 'data' => $data];
    }

    /**
     * Find WordPress installations on the account
     * Scans common paths via cPanel Softaculous or directory listing
     */
    public function findInstallations(): array {
        // Query cPanel for WordPress installations via Softaculous
        $host = $this->cpanel_config['hostname'];
        $port = $this->cpanel_config['port'] ?? 2083;
        $ch   = curl_init("https://{$host}:{$port}/frontend/jupiter/softaculous/index.live.php?act=installations&soft=26&api=json");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => ['Authorization: cpanel ' . $this->username . ':' . $this->cpanel_config['api_key']],
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($body, true) ?? [];

        if (!empty($data['installations'])) {
            return array_map(function($inst) {
                return [
                    'install_id'   => $inst['insid'] ?? '',
                    'domain'       => $inst['softdomain'] ?? '',
                    'path'         => $inst['softdirectory'] ?? '',
                    'install_path' => '/home/' . $this->username . '/public_html' . ($inst['softdirectory'] ? '/' . $inst['softdirectory'] : ''),
                    'wp_version'   => $inst['ver'] ?? 'Unknown',
                    'site_url'     => 'https://' . ($inst['softdomain'] ?? $this->domain),
                ];
            }, $data['installations']);
        }

        // Fallback: check common paths
        $paths = [
            '/home/' . $this->username . '/public_html',
            '/home/' . $this->username . '/public_html/wp',
            '/home/' . $this->username . '/public_html/wordpress',
            '/home/' . $this->username . '/public_html/blog',
        ];

        $found = [];
        foreach ($paths as $path) {
            $result = $this->wpCli($path, 'core version --quiet');
            if ($result['success'] && !empty(trim($result['output'])) && !str_contains($result['output'], 'Error')) {
                $found[] = [
                    'install_id'   => md5($path),
                    'domain'       => $this->domain,
                    'path'         => str_replace('/home/' . $this->username . '/public_html', '', $path),
                    'install_path' => $path,
                    'wp_version'   => trim($result['output']),
                    'site_url'     => 'https://' . $this->domain,
                ];
            }
        }
        return $found;
    }

    // ── One-Click Login ────────────────────────────────────────────────────

    /**
     * Generate a one-click WP-Admin login URL
     * Creates a temporary auth token via WP-CLI
     */
    public function getLoginUrl(string $install_path, string $admin_user = ''): array {
        // Get admin user if not specified
        if (!$admin_user) {
            $r = $this->wpCli($install_path, 'user list --role=administrator --field=user_login --format=csv');
            if (!$r['success'] || empty(trim($r['output']))) {
                return ['success' => false, 'error' => 'Could not find admin user.'];
            }
            $admin_user = explode("\n", trim($r['output']))[0];
        }

        // Generate login URL valid for 1 minute
        $r = $this->wpCli($install_path, 'user session create ' . escapeshellarg($admin_user) . ' --expiration=60 --porcelain 2>/dev/null || wp eval "echo esc_url(wp_login_url());"');

        // Fallback: use magic link via eval
        $token_r = $this->wpCli($install_path, 'eval "
            \$user = get_user_by(\'login\', ' . "'" . $admin_user . "'" . ');
            if (\$user) {
                \$token = wp_generate_auth_cookie(\$user->ID, time()+60, \'auth\');
                \$url = admin_url() . \'?token_login=\' . urlencode(\$token);
                echo \$url;
            }
        "');

        if ($token_r['success'] && filter_var(trim($token_r['output']), FILTER_VALIDATE_URL)) {
            return ['success' => true, 'login_url' => trim($token_r['output']), 'admin_user' => $admin_user];
        }

        // Ultimate fallback: direct admin URL
        return [
            'success'    => true,
            'login_url'  => 'https://' . $this->domain . '/wp-admin/',
            'admin_user' => $admin_user,
            'note'       => 'Direct admin URL (auto-login unavailable)',
        ];
    }

    // ── Core Updates ───────────────────────────────────────────────────────

    public function getCoreInfo(string $install_path): array {
        $r = $this->wpCli($install_path, 'core check-update --format=json');
        $current = $this->wpCli($install_path, 'core version');
        $updates = json_decode($r['output'] ?? '[]', true) ?? [];
        return [
            'success'         => $r['success'],
            'current_version' => trim($current['output'] ?? ''),
            'updates'         => $updates,
            'has_update'      => !empty($updates),
            'latest_version'  => $updates[0]['version'] ?? null,
        ];
    }

    public function updateCore(string $install_path): array {
        $r = $this->wpCli($install_path, 'core update');
        $ok = $r['success'] && !str_contains(strtolower($r['output'] ?? ''), 'error');
        return ['success' => $ok, 'output' => $r['output'] ?? '', 'error' => $ok ? null : ($r['output'] ?? 'Update failed.')];
    }

    // ── Plugin Management ──────────────────────────────────────────────────

    public function getPlugins(string $install_path): array {
        $r = $this->wpCli($install_path, 'plugin list --format=json');
        if (!$r['success']) return ['success' => false, 'plugins' => [], 'error' => $r['error'] ?? ''];
        $plugins = json_decode($r['output'] ?? '[]', true) ?? [];
        return ['success' => true, 'plugins' => $plugins];
    }

    public function activatePlugin(string $install_path, string $plugin): array {
        $r = $this->wpCli($install_path, 'plugin activate ' . escapeshellarg($plugin));
        $ok = $r['success'] && str_contains(strtolower($r['output'] ?? ''), 'activated');
        return ['success' => $ok, 'output' => $r['output'] ?? ''];
    }

    public function deactivatePlugin(string $install_path, string $plugin): array {
        $r = $this->wpCli($install_path, 'plugin deactivate ' . escapeshellarg($plugin));
        $ok = $r['success'] && str_contains(strtolower($r['output'] ?? ''), 'deactivated');
        return ['success' => $ok, 'output' => $r['output'] ?? ''];
    }

    public function deletePlugin(string $install_path, string $plugin): array {
        // Must deactivate first
        $this->deactivatePlugin($install_path, $plugin);
        $r = $this->wpCli($install_path, 'plugin delete ' . escapeshellarg($plugin));
        $ok = $r['success'] && !str_contains(strtolower($r['output'] ?? ''), 'error');
        return ['success' => $ok, 'output' => $r['output'] ?? ''];
    }

    public function updatePlugin(string $install_path, string $plugin = '--all'): array {
        $r = $this->wpCli($install_path, 'plugin update ' . escapeshellarg($plugin));
        $ok = $r['success'];
        return ['success' => $ok, 'output' => $r['output'] ?? ''];
    }

    // ── Theme Management ───────────────────────────────────────────────────

    public function getThemes(string $install_path): array {
        $r = $this->wpCli($install_path, 'theme list --format=json');
        if (!$r['success']) return ['success' => false, 'themes' => []];
        $themes = json_decode($r['output'] ?? '[]', true) ?? [];
        return ['success' => true, 'themes' => $themes];
    }

    public function activateTheme(string $install_path, string $theme): array {
        $r = $this->wpCli($install_path, 'theme activate ' . escapeshellarg($theme));
        $ok = $r['success'] && str_contains(strtolower($r['output'] ?? ''), 'activated');
        return ['success' => $ok, 'output' => $r['output'] ?? ''];
    }

    public function deleteTheme(string $install_path, string $theme): array {
        $r = $this->wpCli($install_path, 'theme delete ' . escapeshellarg($theme));
        $ok = $r['success'] && !str_contains(strtolower($r['output'] ?? ''), 'error');
        return ['success' => $ok, 'output' => $r['output'] ?? ''];
    }

    // ── Maintenance Mode ───────────────────────────────────────────────────

    public function getMaintenanceStatus(string $install_path): bool {
        $r = $this->wpCli($install_path, 'maintenance-mode status');
        return str_contains(strtolower($r['output'] ?? ''), 'maintenance mode is active');
    }

    public function enableMaintenance(string $install_path): array {
        $r = $this->wpCli($install_path, 'maintenance-mode activate');
        $ok = $r['success'] && !str_contains(strtolower($r['output'] ?? ''), 'error');
        return ['success' => $ok, 'output' => $r['output'] ?? ''];
    }

    public function disableMaintenance(string $install_path): array {
        $r = $this->wpCli($install_path, 'maintenance-mode deactivate');
        $ok = $r['success'] && !str_contains(strtolower($r['output'] ?? ''), 'error');
        return ['success' => $ok, 'output' => $r['output'] ?? ''];
    }

    // ── Factory ────────────────────────────────────────────────────────────

    /**
     * Build WP manager from a cPanel service
     */
    public static function fromService(int $service_id): ?self {
        $svc = DB::row(
            "SELECT s.username, s.domain, s.server_id FROM services s
             JOIN products p ON p.id=s.product_id
             WHERE s.id=? AND p.module='cpanel'",
            'i', [$service_id]
        );
        if (!$svc || !$svc['server_id']) return null;

        $srv = DB::row("SELECT * FROM servers WHERE id=?", 'i', [$svc['server_id']]);
        if (!$srv) return null;

        return new self(
            $srv['hostname'],
            $srv['port'] ?? 2083,
            $srv['api_key'],
            $svc['username'],
            $svc['domain']
        );
    }
}
