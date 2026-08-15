<?php

declare(strict_types=1);

namespace App\Services\Analytics;

/**
 * Server-side attribution sanitizer (Batch 11).
 * Never throws — invalid values become null.
 */
final class AttributionSanitizer
{
    private const MAX_UTM = 64;
    private const MAX_CAMPAIGN = 128;
    private const MAX_PATH = 255;
    private const MAX_REF = 255;
    private const MAX_SESSION = 64;

    private const LOCALES = ['en', 'ru', 'uk', 'ka', 'zh'];
    private const DEVICE = ['mobile', 'tablet', 'desktop', 'unknown'];

    private const DENY_QUERY_KEYS = [
        'token', 'access_token', 'refresh_token', 'id_token', 'auth', 'authorization',
        'password', 'passwd', 'secret', 'api_key', 'apikey', 'session', 'sid',
        'email', 'e-mail', 'phone', 'wallet', 'address', 'card', 'iban', 'seed',
        'private_key', 'privkey', 'mnemonic', 'otp', 'code',
    ];

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function sanitizePayload(array $payload): array
    {
        try {
            return [
                'public_session_id' => $this->sanitizeSessionId($payload['public_session_id'] ?? null),
                'utm_source' => $this->sanitizeUtm($payload['utm_source'] ?? null, self::MAX_UTM),
                'utm_medium' => $this->sanitizeUtm($payload['utm_medium'] ?? null, self::MAX_UTM),
                'utm_campaign' => $this->sanitizeUtm($payload['utm_campaign'] ?? null, self::MAX_CAMPAIGN),
                'utm_term' => $this->sanitizeUtm($payload['utm_term'] ?? null, self::MAX_CAMPAIGN),
                'utm_content' => $this->sanitizeUtm($payload['utm_content'] ?? null, self::MAX_CAMPAIGN),
                'referrer' => $this->sanitizeReferrer($payload['referrer'] ?? null),
                'landing_path' => $this->sanitizePath($payload['landing_path'] ?? null),
                'locale' => $this->sanitizeLocale($payload['locale'] ?? null),
                'device_class' => $this->sanitizeDevice($payload['device_class'] ?? null),
                'touch' => $this->sanitizeTouch($payload['touch'] ?? 'last'),
            ];
        } catch (\Throwable) {
            return ['public_session_id' => null];
        }
    }

    public function sanitizeSessionId(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);
        if ($value === '' || strlen($value) > self::MAX_SESSION) {
            return null;
        }
        // Opaque hex / url-safe id only.
        if (! preg_match('/^[A-Za-z0-9_-]{16,64}$/', $value)) {
            return null;
        }
        if ($this->looksSensitive($value)) {
            return null;
        }

        return $value;
    }

    public function sanitizeUtm(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = $this->stripControls($value);
        $value = trim(mb_substr($value, 0, $max));
        if ($value === '') {
            return null;
        }
        if ($this->looksSensitive($value)) {
            return null;
        }

        return $value;
    }

    public function sanitizeReferrer(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        // Strip query/fragment first so sensitive query values never enter storage or sensitivity checks.
        $value = $this->stripControls(trim($value));
        $parts = parse_url($value);
        if ($parts === false || empty($parts['host'])) {
            return null;
        }
        $host = strtolower((string) $parts['host']);
        if ($host === 'exswaping.com' || str_ends_with($host, '.exswaping.com')) {
            // Internal referrer — do not replace external campaign signal.
            return null;
        }
        $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : 'https';
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        $out = $scheme.'://'.$host;
        if (! empty($parts['path'])) {
            $path = $this->sanitizePath($parts['path']);
            if ($path) {
                $out .= $path;
            }
        }
        if ($this->looksSensitive($out)) {
            return null;
        }

        return mb_substr($out, 0, self::MAX_REF);
    }

    public function sanitizePath(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = $this->stripControls(trim($value));
        if ($value === '') {
            return null;
        }
        // Path only — drop query/fragment.
        $path = parse_url($value, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            $path = str_starts_with($value, '/') ? explode('?', $value, 2)[0] : null;
        }
        if (! is_string($path) || $path === '') {
            return null;
        }
        if ($this->looksSensitive($path)) {
            return null;
        }
        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        return mb_substr($path, 0, self::MAX_PATH);
    }

    public function sanitizeLocale(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = strtolower(trim($value));

        return in_array($value, self::LOCALES, true) ? $value : null;
    }

    public function sanitizeDevice(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = strtolower(trim($value));

        return in_array($value, self::DEVICE, true) ? $value : 'unknown';
    }

    public function sanitizeTouch(mixed $value): string
    {
        return $value === 'first' ? 'first' : 'last';
    }

    /**
     * @return list<string>
     */
    public function deniedQueryKeys(): array
    {
        return self::DENY_QUERY_KEYS;
    }

    private function stripControls(string $value): string
    {
        return preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
    }

    private function looksSensitive(string $value): bool
    {
        $lower = strtolower($value);
        if (str_contains($lower, '@')) {
            return true;
        }
        if (preg_match('/\b(0x[a-f0-9]{40}|[13][a-km-zA-HJ-NP-Z1-9]{25,34}|T[1-9A-HJ-NP-Za-km-z]{33})\b/', $value)) {
            return true;
        }
        foreach (self::DENY_QUERY_KEYS as $key) {
            if (str_contains($lower, $key.'=') || str_contains($lower, $key.':')) {
                return true;
            }
        }
        if (preg_match('/(password|private_key|seed phrase|mnemonic|bearer\s+)/i', $value)) {
            return true;
        }

        return false;
    }
}
