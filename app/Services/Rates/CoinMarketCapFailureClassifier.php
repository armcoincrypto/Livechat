<?php

declare(strict_types=1);

namespace App\Services\Rates;

/**
 * Classify CoinMarketCap API failures without exposing credentials.
 */
final class CoinMarketCapFailureClassifier
{
    /**
     * @param array<string,mixed>|null $decoded
     * @return array{class: string, http_status: int|null, provider_code: int|null, message: string|null}
     */
    public function classify(?array $decoded, ?int $httpStatus = null, ?string $rawBody = null): array
    {
        $providerCode = null;
        $message = null;

        if (is_array($decoded)) {
            $status = $decoded['status'] ?? null;
            if (is_array($status)) {
                $providerCode = isset($status['error_code']) ? (int) $status['error_code'] : null;
                $message = isset($status['error_message']) ? (string) $status['error_message'] : null;
            }
            if (($decoded['error'] ?? null) === 'Invalid JSON format') {
                return [
                    'class' => 'PARSER_FAILURE',
                    'http_status' => $httpStatus,
                    'provider_code' => $providerCode,
                    'message' => 'Invalid JSON format',
                ];
            }
        }

        $hay = strtolower(($message ?? '') . ' ' . (string) $rawBody);

        // CMC 1010 = monthly credit limit exceeded (check before generic "api key" text)
        if ($providerCode === 1010 || str_contains($hay, 'credit limit') || str_contains($hay, 'monthly credit')) {
            return [
                'class' => 'PLAN_LIMIT_EXCEEDED',
                'http_status' => $httpStatus,
                'provider_code' => $providerCode,
                'message' => $this->sanitizeMessage($message),
            ];
        }

        if ($httpStatus === 429 || $providerCode === 1008 || str_contains($hay, 'rate limit')) {
            return [
                'class' => 'RATE_LIMITED',
                'http_status' => $httpStatus,
                'provider_code' => $providerCode,
                'message' => $this->sanitizeMessage($message),
            ];
        }

        if ($providerCode === 1001 || str_contains($hay, 'api key is invalid') || str_contains($hay, 'invalid api key')) {
            return [
                'class' => 'CREDENTIAL_REJECTED',
                'http_status' => $httpStatus,
                'provider_code' => $providerCode,
                'message' => $this->sanitizeMessage($message),
            ];
        }

        if ($httpStatus === 401 && ($message === null || trim((string) $message) === '') && trim((string) $rawBody) === '') {
            return [
                'class' => 'CREDENTIAL_MISSING',
                'http_status' => $httpStatus,
                'provider_code' => $providerCode,
                'message' => null,
            ];
        }

        if ($httpStatus === 401 || str_contains($hay, 'unauthorized') || str_contains($hay, 'api key')) {
            return [
                'class' => 'CREDENTIAL_REJECTED',
                'http_status' => $httpStatus,
                'provider_code' => $providerCode,
                'message' => $this->sanitizeMessage($message),
            ];
        }

        if ($httpStatus === 404 || str_contains($hay, 'deprecated') || str_contains($hay, 'endpoint')) {
            return [
                'class' => 'ENDPOINT_DEPRECATED',
                'http_status' => $httpStatus,
                'provider_code' => $providerCode,
                'message' => $this->sanitizeMessage($message),
            ];
        }

        if ($httpStatus !== null && $httpStatus >= 500) {
            return [
                'class' => 'NETWORK_FAILURE',
                'http_status' => $httpStatus,
                'provider_code' => $providerCode,
                'message' => $this->sanitizeMessage($message),
            ];
        }

        if ($decoded === null && $rawBody !== null && $rawBody !== '' && json_decode($rawBody, true) === null) {
            return [
                'class' => 'PARSER_FAILURE',
                'http_status' => $httpStatus,
                'provider_code' => $providerCode,
                'message' => 'unparseable_body',
            ];
        }

        if ($providerCode !== null && $providerCode !== 0) {
            return [
                'class' => 'UNKNOWN_PROVIDER_FAILURE',
                'http_status' => $httpStatus,
                'provider_code' => $providerCode,
                'message' => $this->sanitizeMessage($message),
            ];
        }

        return [
            'class' => 'UNKNOWN_PROVIDER_FAILURE',
            'http_status' => $httpStatus,
            'provider_code' => $providerCode,
            'message' => $this->sanitizeMessage($message),
        ];
    }

    private function sanitizeMessage(?string $message): ?string
    {
        if ($message === null || $message === '') {
            return $message;
        }
        // Never echo key-like tokens
        return preg_replace('/[a-f0-9]{32,}/i', '[redacted]', $message);
    }
}
