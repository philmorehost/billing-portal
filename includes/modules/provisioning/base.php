<?php
/**
 * Base Provisioning Module
 * All provisioning modules extend this class
 */

abstract class ProvisioningBase {

    protected array $config = [];

    public function __construct(array $config) {
        $this->config = $config;
    }

    /**
     * Make an HTTP request (used by all modules)
     */
    protected function request(
        string $method,
        string $url,
        array  $data    = [],
        array  $headers = [],
        int    $timeout = 30
    ): array {
        $ch = curl_init();
        $method = strtoupper($method);

        if ($method === 'GET' && $data) {
            $url .= '?' . http_build_query($data);
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => array_map(
                fn($k,$v) => "{$k}: {$v}",
                array_keys($headers),
                $headers
            ),
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
        } elseif ($method === 'PUT' || $method === 'DELETE' || $method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? json_encode($data) : $data);
        }

        $body     = curl_exec($ch);
        $http_code= curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->log('error', "cURL error: {$error}", ['url' => $url]);
            return ['success' => false, 'error' => $error, 'http_code' => 0];
        }

        return [
            'success'   => $http_code >= 200 && $http_code < 300,
            'http_code' => $http_code,
            'body'      => $body,
            'data'      => json_decode($body, true) ?? $body,
        ];
    }

    /**
     * Log API transactions to activity_log
     */
    protected function log(string $level, string $message, array $context = []): void {
        $desc = "[{$level}] " . static::class . ": {$message}";
        if ($context) $desc .= ' | ' . json_encode($context);
        try {
            DB::execute(
                "INSERT INTO activity_log (actor_type, action, description) VALUES ('system', ?, ?)",
                'ss', ["provisioning_{$level}", mb_substr($desc, 0, 1000)]
            );
        } catch (Exception $e) { /* silent */ }
    }

    // Abstract methods all modules must implement
    abstract public function create(array $params): array;
    abstract public function suspend(string $service_ref): array;
    abstract public function unsuspend(string $service_ref): array;
    abstract public function terminate(string $service_ref): array;
    abstract public function getStatus(string $service_ref): array;
}
