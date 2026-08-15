<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Blacklist\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Проблема транспорта/соединения (таймауты, DNS, сеть).
 */
final class BlacklistTransportException extends RuntimeException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
