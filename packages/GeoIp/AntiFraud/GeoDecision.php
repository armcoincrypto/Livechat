<?php
declare(strict_types=1);

namespace iEXPackages\GeoIp\AntiFraud;

final class GeoDecision
{
    public function __construct(
        public readonly string $action,     // allow|review|deny
        public readonly string $reasonCode, // например country_denied
        public readonly array $context = [],// детали
        public readonly ?string $rule = null,
    ) {}

    public static function allow(string $reason = 'ok', array $ctx = [], ?string $rule = null): self
    {
        return new self('allow', $reason, $ctx, $rule);
    }

    public static function review(string $reason, array $ctx = [], ?string $rule = null): self
    {
        return new self('review', $reason, $ctx, $rule);
    }

    public static function deny(string $reason, array $ctx = [], ?string $rule = null): self
    {
        return new self('deny', $reason, $ctx, $rule);
    }

    public function isAllowed(): bool
    {
        return $this->action !== 'deny';
    }
}
