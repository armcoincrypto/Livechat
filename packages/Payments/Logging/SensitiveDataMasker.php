<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Logging;

final class SensitiveDataMasker
{
    /** @var array<int,string> */
    private array $needles = [
        'authorization',
        'token',
        'api_key',
        'api-key',
        'private_key',
        'secret',
        'signature',
        'x-signature',
        'jwt',
        'access-token',
        'access_token',
    ];

    public function mask(array $data): array
    {
        return $this->walk($data);
    }

    private function walk(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $out = [];

        foreach ($value as $k => $v) {
            $key = is_string($k) ? mb_strtolower($k, 'UTF-8') : '';

            if ($key !== '' && $this->isSensitiveKey($key)) {
                $out[$k] = '***';
                continue;
            }

            $out[$k] = $this->walk($v);
        }

        return $out;
    }

    private function isSensitiveKey(string $key): bool
    {
        foreach ($this->needles as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}
