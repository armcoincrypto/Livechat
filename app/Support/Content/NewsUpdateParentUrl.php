<?php

declare(strict_types=1);

namespace App\Support\Content;

use Illuminate\Support\Str;

/**
 * Resolves news.parent_url for authenticated admin updates.
 *
 * Safety:
 * - Does not accept an arbitrary client-supplied parent_url string.
 * - preserve_parent_url=true keeps the existing DB slug (content-only / post-repair restore).
 * - Otherwise regenerates from the first submitted name value (legacy Laravel behavior).
 */
final class NewsUpdateParentUrl
{
    public static function resolve(string $currentParentUrl, mixed $namePayload, bool $preserveExisting): string
    {
        if ($preserveExisting) {
            return $currentParentUrl;
        }

        $first = null;
        if (is_array($namePayload)) {
            foreach ($namePayload as $value) {
                $first = $value;
                break;
            }
        }

        return Str::slug((string) $first);
    }

    public static function preserveFlagFromRequest(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
