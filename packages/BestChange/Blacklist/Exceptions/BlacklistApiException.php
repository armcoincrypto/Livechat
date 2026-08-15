<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Blacklist\Exceptions;

use RuntimeException;

/**
 * Ошибка ответа API (невалидный JSON, неожиданный формат, ошибки параметров).
 */
final class BlacklistApiException extends RuntimeException
{
    /**
     * @param array<string,mixed>|null $payload
     */
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?array $payload = null,
    ) {
        parent::__construct($message);
    }
}
