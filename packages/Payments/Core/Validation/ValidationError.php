<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Validation;

final class ValidationError
{
    public function __construct(
        public readonly string $level,     // error|warning
        public readonly string $code,      // machine-readable
        public readonly string $path,      // config path, e.g. operations.purchase.request_class
        public readonly string $message,   // human message
        public readonly array $meta = [],  // any extra data
    ) {}

    public static function error(string $code, string $path, string $message, array $meta = []): self
    {
        return new self('error', $code, $path, $message, $meta);
    }

    public static function warning(string $code, string $path, string $message, array $meta = []): self
    {
        return new self('warning', $code, $path, $message, $meta);
    }
}
