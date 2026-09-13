<?php

declare(strict_types=1);

namespace App\Services\Rates;

use Illuminate\Database\Eloquent\Builder;

/**
 * Server-side BestChange admin listing search.
 *
 * Matches mapping id, direction id, tech_name, and currency/payment codes.
 * Does not mutate rates, mappings, or ownership.
 */
final class BestChangeAdminListingSearch
{
    // $query is Eloquent Builder or EloquentFilter ModelFilter (both expose where()).
    /**
     * @return list<string>
     */
    public static function tokens(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[\s,;|>→\/\\\\-]+/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(array_map('trim', $parts), static fn (string $t): bool => $t !== ''));
    }

    /**
     * @return list<string>
     */
    public static function expansions(string $token): array
    {
        $out = [$token];
        $lower = mb_strtolower($token);
        $aliases = [
            'yoomoney',
            'yamoney',
            'yamrub',
            'юmoney',
            'юмани',
            'yandex.money',
            'yandex money',
            'yandex',
        ];
        if (in_array($lower, $aliases, true)) {
            $out[] = 'YAMRUB';
            $out[] = 'ЮMoney';
            $out[] = 'YooMoney';
            $out[] = 'Yamoney';
        }

        return array_values(array_unique($out));
    }

    public static function apply(object $query, string $raw): void
    {
        $tokens = self::tokens($raw);
        if ($tokens === []) {
            return;
        }

        foreach ($tokens as $token) {
            $expansions = self::expansions($token);
            $query->where(function ($outer) use ($token, $expansions): void {
                if (ctype_digit($token)) {
                    $id = (int) $token;
                    $outer->orWhere('bestchange_directions.id', $id)
                        ->orWhere('bestchange_directions.id_direction_exchange', $id);
                }

                foreach ($expansions as $needle) {
                    $like = '%' . self::escapeLike($needle) . '%';
                    $outer->orWhereHas('direction_exchange', function (Builder $dir) use ($like, $needle): void {
                        $dir->where(function (Builder $dq) use ($like, $needle): void {
                            $dq->where('tech_name', 'like', $like);
                            if (ctype_digit($needle)) {
                                $dq->orWhere('id', (int) $needle);
                            }
                            $dq->orWhereHas('currency1', function (Builder $c) use ($like): void {
                                $c->where('designation_xml', 'like', $like);
                            });
                            $dq->orWhereHas('currency2', function (Builder $c) use ($like): void {
                                $c->where('designation_xml', 'like', $like);
                            });
                            $dq->orWhereHas('currency1.payment', function (Builder $p) use ($like): void {
                                $p->where('name', 'like', $like);
                            });
                            $dq->orWhereHas('currency2.payment', function (Builder $p) use ($like): void {
                                $p->where('name', 'like', $like);
                            });
                        });
                    });
                }
            });
        }
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
