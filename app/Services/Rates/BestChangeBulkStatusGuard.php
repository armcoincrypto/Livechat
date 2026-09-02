<?php

declare(strict_types=1);

namespace App\Services\Rates;

use App\Models\BestChangeDirection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Fail-closed guard for BestChange mapping status mutations.
 *
 * Prevents accidental platform-wide disable/enable via bulk admin actions.
 * Does not change pricing formulas — only authorization of status writes.
 */
final class BestChangeBulkStatusGuard
{
    public const SMALL_MAX = 20;

    public const LARGE_MIN = 101;

    /** Fraction of currently-active BC mappings; crossing requires emergency override. */
    public const CIRCUIT_BREAKER_RATIO = 0.10;

    public const CODE_EMPTY_IDS = 'BULK_STATUS_EMPTY_IDS';

    public const CODE_CONFIRM_REQUIRED = 'BULK_STATUS_CONFIRM_REQUIRED';

    public const CODE_COUNT_MISMATCH = 'BULK_STATUS_COUNT_MISMATCH';

    public const CODE_EXPECTED_COUNT_REQUIRED = 'BULK_STATUS_EXPECTED_COUNT_REQUIRED';

    public const CODE_TYPED_CONFIRM_REQUIRED = 'BULK_STATUS_TYPED_CONFIRM_REQUIRED';

    public const CODE_CIRCUIT_BREAKER = 'BULK_STATUS_CIRCUIT_BREAKER';

    /**
     * Normalize client IDs. Empty / missing → [].
     *
     * @return list<int>
     */
    public static function normalizeIds(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $v) {
            if (is_int($v) || (is_string($v) && ctype_digit($v))) {
                $id = (int) $v;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    public static function typedDisablePhrase(int $count): string
    {
        return sprintf('ОТКЛЮЧИТЬ %d НАПРАВЛЕНИЯ', $count);
    }

    public static function typedEnablePhrase(int $count): string
    {
        return sprintf('ВКЛЮЧИТЬ %d НАПРАВЛЕНИЯ', $count);
    }

    public static function activeMappingCount(): int
    {
        return (int) BestChangeDirection::query()->where('status', 1)->count();
    }

    /**
     * @param  list<int>  $ids
     * @param  list<int>|null  $existingIds  Injected for unit tests (skip DB lookup when set).
     * @param  int|null  $activeBeforeOverride  Injected active BC count for unit tests.
     * @return array{ok:bool,code:?string,http:int,payload:array<string,mixed>}
     */
    public static function evaluate(
        Request $request,
        array $ids,
        int $targetStatus,
        ?array $existingIds = null,
        ?int $activeBeforeOverride = null
    ): array {
        $count = count($ids);
        if ($count === 0) {
            return self::deny(self::CODE_EMPTY_IDS, 422, [
                'message' => 'Пустой список ID: BestChange status не изменён (0 записей).',
                'count' => 0,
                'scope' => 'bestchange_directions_ids_only',
            ]);
        }

        // Existing IDs only — reject phantom IDs so count cannot silently shrink.
        if ($existingIds === null) {
            $existing = BestChangeDirection::query()
                ->whereIn('id', $ids)
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();
        } else {
            $existing = array_values(array_unique(array_map('intval', $existingIds)));
        }
        sort($existing);
        $requested = $ids;
        sort($requested);
        if ($existing !== $requested) {
            return self::deny(self::CODE_COUNT_MISMATCH, 422, [
                'message' => sprintf(
                    'Часть ID не найдена в bestchange_directions (requested=%d existing=%d). Запись отменена.',
                    $count,
                    count($existing)
                ),
                'count' => $count,
                'existing_count' => count($existing),
                'scope' => 'bestchange_directions_ids_only',
            ]);
        }

        if (!$request->boolean('confirm')) {
            $action = $targetStatus === 1 ? 'включить' : 'выключить';

            return self::deny(self::CODE_CONFIRM_REQUIRED, 200, [
                'status' => 1,
                'requires_confirm' => true,
                'count' => $count,
                'expected_count_required' => $count > self::SMALL_MAX,
                'typed_confirmation_required' => $count >= self::LARGE_MIN,
                'typed_confirmation_phrase' => $targetStatus === 1
                    ? self::typedEnablePhrase($count)
                    : self::typedDisablePhrase($count),
                'scope' => 'bestchange_directions_ids_only',
                'scope_label' => sprintf(
                    'Будет %s: %d направлений BestChange (маппинги bestchange_directions, не статус обмена).',
                    $action,
                    $count
                ),
                'message' => sprintf(
                    'Подтвердите: будет изменён статус BestChange у %d пар(ы). '
                    .'Передайте confirm=1%s%s, чтобы %s.',
                    $count,
                    $count > self::SMALL_MAX ? ', expected_count='.$count : '',
                    $count >= self::LARGE_MIN
                        ? ', typed_confirmation="'.($targetStatus === 1
                            ? self::typedEnablePhrase($count)
                            : self::typedDisablePhrase($count)).'"'
                        : '',
                    $action
                ),
            ]);
        }

        if ($count > self::SMALL_MAX) {
            if (!$request->exists('expected_count')) {
                return self::deny(self::CODE_EXPECTED_COUNT_REQUIRED, 422, [
                    'message' => sprintf(
                        'Для изменения %d BestChange-маппингов требуется expected_count=%d.',
                        $count,
                        $count
                    ),
                    'count' => $count,
                    'expected_count_required' => true,
                ]);
            }
            $expected = (int) $request->input('expected_count');
            if ($expected !== $count) {
                return self::deny(self::CODE_COUNT_MISMATCH, 422, [
                    'message' => sprintf(
                        'expected_count=%d не совпадает с фактическим числом ID (%d). Запись отменена.',
                        $expected,
                        $count
                    ),
                    'count' => $count,
                    'expected_count' => $expected,
                ]);
            }
        }

        if ($count >= self::LARGE_MIN) {
            $phrase = $targetStatus === 1
                ? self::typedEnablePhrase($count)
                : self::typedDisablePhrase($count);
            $typed = trim((string) $request->input('typed_confirmation', ''));
            if ($typed !== $phrase) {
                return self::deny(self::CODE_TYPED_CONFIRM_REQUIRED, 422, [
                    'message' => sprintf(
                        'Крупная операция (%d). Введите точную фразу: %s',
                        $count,
                        $phrase
                    ),
                    'count' => $count,
                    'typed_confirmation_required' => true,
                    'typed_confirmation_phrase' => $phrase,
                ]);
            }
        }

        $activeBefore = $activeBeforeOverride ?? self::activeMappingCount();
        if ($targetStatus === 0 && $activeBefore > 0) {
            $ratio = $count / $activeBefore;
            if ($ratio > self::CIRCUIT_BREAKER_RATIO && !$request->boolean('emergency_override')) {
                return self::deny(self::CODE_CIRCUIT_BREAKER, 422, [
                    'message' => sprintf(
                        'Circuit breaker: отключение %d из %d активных BestChange-маппингов (%.1f%% > %.0f%%). '
                        .'Передайте emergency_override=1 вместе с typed_confirmation и expected_count.',
                        $count,
                        $activeBefore,
                        $ratio * 100,
                        self::CIRCUIT_BREAKER_RATIO * 100
                    ),
                    'count' => $count,
                    'active_bc_before' => $activeBefore,
                    'ratio' => round($ratio, 4),
                    'circuit_breaker' => true,
                    'emergency_override_required' => true,
                    'typed_confirmation_phrase' => self::typedDisablePhrase($count),
                ]);
            }
        }

        return [
            'ok' => true,
            'code' => null,
            'http' => 200,
            'payload' => [
                'count' => $count,
                'active_bc_before' => $activeBefore,
                'ids' => $ids,
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array{ok:bool,code:?string,http:int,payload:array<string,mixed>}
     */
    private static function deny(string $code, int $http, array $payload): array
    {
        $payload['code'] = $code;
        if (!array_key_exists('status', $payload)) {
            // Admin SPA treats status=1 as business/validation error body.
            $payload['status'] = 1;
        }

        return [
            'ok' => false,
            'code' => $code,
            'http' => $http,
            'payload' => $payload,
        ];
    }

    /**
     * Snapshot helper used by recovery tooling (read-only when dry).
     *
     * @return array{generated_at:string,count:int,rows:list<array<string,mixed>>}
     */
    public static function snapshotActiveMappings(): array
    {
        $rows = DB::table('bestchange_directions as bc')
            ->join('direction_exchange as de', 'de.id', '=', 'bc.id_direction_exchange')
            ->where('bc.status', 1)
            ->where('de.status', 1)
            ->whereNull('de.deleted_at')
            ->orderBy('bc.id')
            ->get([
                'bc.id as bc_id',
                'bc.id_direction_exchange',
                'bc.position_num',
                'bc.rate_mode',
                'bc.id_currency_in',
                'bc.id_currency_out',
                'bc.city_id',
                'de.profit',
                'de.parser_source_name',
            ])
            ->map(static fn ($r) => (array) $r)
            ->all();

        return [
            'generated_at' => gmdate('c'),
            'count' => count($rows),
            'rows' => $rows,
        ];
    }
}
