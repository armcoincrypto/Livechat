<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Context;

/**
 * Environment — окружение операции (ip/email/locale/userAgent).
 * Правила НЕ должны брать это из request().
 */
final class Environment
{
    public function __construct(
        private readonly string $ip,
        private readonly ?string $email,
        private readonly ?string $locale = null,
        private readonly ?string $userAgent = null,
    ) {}

    public function ip(): string
    {
        return $this->ip;
    }

    public function email(): ?string
    {
        $e = trim((string)$this->email);
        return $e !== '' ? $e : null;
    }

    public function locale(): ?string
    {
        return $this->locale;
    }

    public function userAgent(): ?string
    {
        return $this->userAgent;
    }
}
