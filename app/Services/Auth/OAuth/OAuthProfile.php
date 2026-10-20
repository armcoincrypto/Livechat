<?php

declare(strict_types=1);

namespace App\Services\Auth\OAuth;

final class OAuthProfile
{
    public function __construct(
        public readonly string $provider,
        public readonly string $subject,
        public readonly ?string $email,
        public readonly ?bool $emailVerified,
        public readonly ?string $name,
    ) {
    }
}
