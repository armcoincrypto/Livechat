<?php

declare(strict_types=1);

namespace iEXPackages\KYCPlugin\Drivers\Didit;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal Didit Session API client (server-side only).
 */
final class DiditClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly int $timeoutSeconds = 15,
    ) {
    }

    public static function fromConfig(): self
    {
        $cfg = config('kyc.didit', []);

        return new self(
            baseUrl: (string) ($cfg['base_url'] ?? 'https://verification.didit.me'),
            apiKey: (string) ($cfg['api_key'] ?? ''),
            timeoutSeconds: (int) ($cfg['timeout_seconds'] ?? 15),
        );
    }

    /**
     * @param array{workflow_id:string,vendor_data:string,callback?:string} $payload
     * @return array<string, mixed>
     */
    public function createSession(array $payload): array
    {
        $this->assertConfigured();

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
                ->timeout($this->timeoutSeconds)
                ->post($this->baseUrl . '/v3/session/', $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Didit session create timeout/connection failure', 0, $e);
        }

        if ($response->failed()) {
            throw new RuntimeException('Didit session create failed with HTTP ' . $response->status());
        }

        $body = $response->json();
        if (!is_array($body) || empty($body['session_id'])) {
            throw new RuntimeException('Didit session create returned invalid payload');
        }

        return $body;
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveDecision(string $sessionId): array
    {
        $this->assertConfigured();
        $sessionId = trim($sessionId);
        if ($sessionId === '') {
            throw new RuntimeException('Didit session id required');
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Accept' => 'application/json',
            ])
                ->timeout($this->timeoutSeconds)
                ->get($this->baseUrl . '/v3/session/' . rawurlencode($sessionId) . '/decision/');
        } catch (ConnectionException $e) {
            throw new RuntimeException('Didit decision retrieve timeout/connection failure', 0, $e);
        }

        if ($response->failed()) {
            throw new RuntimeException('Didit decision retrieve failed with HTTP ' . $response->status());
        }

        $body = $response->json();
        if (!is_array($body)) {
            throw new RuntimeException('Didit decision retrieve returned invalid payload');
        }

        return $body;
    }

    private function assertConfigured(): void
    {
        if (trim($this->apiKey) === '') {
            throw new RuntimeException('Didit API key is not configured');
        }
    }
}
