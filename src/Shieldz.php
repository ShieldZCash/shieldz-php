<?php

declare(strict_types=1);

namespace Shieldz;

class Shieldz
{
    private const VERSION = '0.1.0';
    private const RETRYABLE = [429, 500, 502, 503, 504];

    private string $apiKey;
    private string $baseUrl;
    private int $timeout;
    private int $maxRetries;
    public Invoices $invoices;

    /**
     * @param array{base_url?: string, timeout?: int, max_retries?: int} $opts
     */
    public function __construct(string $apiKey, array $opts = [])
    {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('Shieldz: apiKey is required');
        }
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($opts['base_url'] ?? 'https://shieldz.cash/api/v1', '/');
        $this->timeout = $opts['timeout'] ?? 30;
        $this->maxRetries = max(0, $opts['max_retries'] ?? 2);
        $this->invoices = new Invoices($this);
    }

    public function request(string $method, string $path, array $query = [], ?array $body = null): array
    {
        $url = $this->baseUrl . $path;
        $query = array_filter($query, static fn ($v) => $v !== null);
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        // Attach an idempotency key to a retryable POST so retries can't duplicate.
        if ($method === 'POST' && $this->maxRetries > 0 && is_array($body) && empty($body['idempotency_key'])) {
            $body['idempotency_key'] = 'auto_' . bin2hex(random_bytes(16));
        }
        $payload = $body !== null ? json_encode($body) : null;

        $attempt = 0;
        while (true) {
            $requestId = null;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->apiKey,
                    'Content-Type: application/json',
                    'User-Agent: shieldz-php/' . self::VERSION,
                ],
                CURLOPT_HEADERFUNCTION => static function ($ch, $line) use (&$requestId) {
                    $low = strtolower($line);
                    if (str_starts_with($low, 'x-request-id:')) {
                        $requestId = trim(substr($line, 13));
                    } elseif ($requestId === null && str_starts_with($low, 'cf-ray:')) {
                        $requestId = trim(substr($line, 7));
                    }
                    return strlen($line);
                },
            ]);
            if ($payload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }

            $respBody = curl_exec($ch);
            $errno = curl_errno($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($errno !== 0) {
                if ($attempt < $this->maxRetries) {
                    usleep($this->backoff($attempt));
                    $attempt++;
                    continue;
                }
                throw new ShieldzError(0, [
                    'type' => 'connection_error',
                    'code' => $errno === CURLE_OPERATION_TIMEDOUT ? 'timeout' : 'network_error',
                    'message' => curl_strerror($errno) ?: 'request failed',
                ]);
            }

            if (in_array($status, self::RETRYABLE, true) && $attempt < $this->maxRetries) {
                usleep($this->backoff($attempt));
                $attempt++;
                continue;
            }

            $data = ($respBody === '' || $respBody === false) ? [] : json_decode((string) $respBody, true);
            if ($status >= 200 && $status < 300) {
                return is_array($data) ? $data : [];
            }
            $env = is_array($data) && isset($data['error']) ? $data['error'] : [];
            throw new ShieldzError($status, $env, $requestId);
        }
    }

    private function backoff(int $attempt): int
    {
        $ms = min(8000, 500 * (2 ** $attempt));
        return (int) ($ms * (0.5 + (mt_rand() / mt_getrandmax()) * 0.5) * 1000);
    }
}
