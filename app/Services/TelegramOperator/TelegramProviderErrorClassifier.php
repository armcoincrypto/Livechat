<?php

declare(strict_types=1);

namespace App\Services\TelegramOperator;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;

/**
 * Classify Telegram/provider failures for bounded retry vs permanent fail.
 * No secrets; status codes only.
 */
final class TelegramProviderErrorClassifier
{
    public static function isPermanent(Throwable $e): bool
    {
        if ($e instanceof ModelNotFoundException) {
            return true;
        }

        $status = self::httpStatus($e);
        if ($status === null) {
            return false;
        }

        // Permanent client/auth/validation failures — do not retry.
        if (in_array($status, [400, 401, 403, 404], true)) {
            return true;
        }

        return false;
    }

    public static function isRetryable(Throwable $e): bool
    {
        if (self::isPermanent($e)) {
            return false;
        }

        if ($e instanceof ConnectException) {
            return true;
        }

        $status = self::httpStatus($e);
        if ($status === null) {
            // Unknown / runtime — allow bounded job retries.
            return true;
        }

        // Rate limit + transient provider/server errors.
        return $status === 429 || $status >= 500;
    }

    public static function httpStatus(Throwable $e): ?int
    {
        if ($e instanceof ClientException || $e instanceof ServerException || $e instanceof RequestException) {
            $response = $e->getResponse();
            if ($response !== null) {
                return $response->getStatusCode();
            }
        }

        // Nested (e.g. RuntimeException wrapping Guzzle).
        $prev = $e->getPrevious();
        if ($prev instanceof Throwable) {
            return self::httpStatus($prev);
        }

        return null;
    }
}
