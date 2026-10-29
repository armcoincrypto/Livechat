<?php

declare(strict_types=1);

namespace App\Services\Rates\FastMarket;

use RuntimeException;

/**
 * Domain failure for locked-quote validation / application.
 * Order create must map these to explicit client codes — never silent reprice.
 */
final class LockedQuoteException extends RuntimeException
{
    public const EXPIRED = 'QUOTE_EXPIRED';

    public const NOT_FOUND = 'QUOTE_NOT_FOUND_OR_EXPIRED';

    public const INPUT_MISMATCH = 'QUOTE_INPUT_MISMATCH';

    public const INVALIDATED = 'QUOTE_INVALIDATED';

    public const UNSUPPORTED = 'QUOTE_UNSUPPORTED_FAMILY';

    public const REDIS_UNAVAILABLE = 'QUOTE_REDIS_UNAVAILABLE';

    public const MALFORMED = 'QUOTE_MALFORMED';

    public const USED = 'QUOTE_USED';

    /** Snapshot unavailable and persisted catalog rate older than fallback max age. */
    public const RATE_UNAVAILABLE = 'RATE_UNAVAILABLE';

    public function __construct(
        public readonly string $errorCode,
        string $message = '',
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($message !== '' ? $message : $errorCode);
    }

    public static function expired(): self
    {
        return new self(self::EXPIRED, 'Quote token has expired', 422);
    }

    public static function notFound(): self
    {
        return new self(self::NOT_FOUND, 'Quote token not found or expired', 422);
    }

    public static function inputMismatch(string $detail = ''): self
    {
        return new self(
            self::INPUT_MISMATCH,
            $detail !== '' ? 'Quote input mismatch: '.$detail : 'Quote input mismatch',
            422
        );
    }

    public static function invalidated(): self
    {
        return new self(self::INVALIDATED, 'Quote token invalidated', 422);
    }

    public static function unsupported(): self
    {
        return new self(self::UNSUPPORTED, 'Quote pricing family not supported for lock', 422);
    }

    public static function redisUnavailable(): self
    {
        return new self(self::REDIS_UNAVAILABLE, 'Quote store unavailable', 503);
    }

    public static function malformed(): self
    {
        return new self(self::MALFORMED, 'Quote record malformed', 422);
    }

    public static function used(): self
    {
        return new self(self::USED, 'Quote token already consumed', 422);
    }

    public static function rateUnavailable(string $detail = ''): self
    {
        return new self(
            self::RATE_UNAVAILABLE,
            $detail !== '' ? 'Rate unavailable: '.$detail : 'Rate unavailable: snapshot expired and persisted fallback too old',
            503
        );
    }
}
