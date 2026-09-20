<?php

declare(strict_types=1);

namespace App\Services\Rates;

use App\Models\BestChangeDirection;
use App\Models\DirectionExchange;
use Illuminate\Support\Str;

/**
 * Operator-facing pricing safety helpers.
 *
 * Read-only preview / truth / convergence. Does not change Calculator math,
 * Top-N selection, profit formulas, ownership mappings, or positions.
 */
final class AdminPricingOperatorSafety
{
    public const CONVERGED = 'CONVERGED';

    public const WAITING_FOR_COMPILER = 'WAITING_FOR_COMPILER';

    public const PRICING_MISMATCH = 'PRICING_MISMATCH';

    /**
     * Full operator truth panel for a direction edit page.
     *
     * @return array<string,mixed>
     */
    public static function truthPanel(DirectionExchange $item): array
    {
        $explain = AdminPricingTransparency::explain($item);
        $family = (string) ($explain['ownership']['family'] ?? 'UNKNOWN');
        $item->loadMissing('bestchange_directions');
        $bc = $item->bestchange_directions;
        if ($bc === null) {
            $bc = BestChangeDirection::query()
                ->where('id_direction_exchange', (int) $item->id)
                ->orderByDesc('status')
                ->first();
        }

        $storedCourse = (string) ($item->course_value ?? '');
        $base = (string) ($explain['base'] ?? $storedCourse);
        $customer = (string) ($explain['final_floating'] ?? $explain['customer_rates']['floating'] ?? '');
        $xmlRate = self::xmlRateForDirection((int) $item->id, $storedCourse);
        $warnings = self::warnings($item, $explain, $storedCourse, $base, $customer);

        return [
            'direction_id' => (int) $item->id,
            'pair' => $explain['pair'] ?? null,
            'labels' => [
                'BASE' => 'BASE (источник / BestChange book rate)',
                'PROFIT' => 'PROFIT (коммерческая наценка)',
                'FINAL_CUSTOMER_RATE' => 'FINAL CUSTOMER RATE (курс клиента)',
                'STORED_COURSE' => 'STORED COURSE (course_value — коммерческий снимок, не «курс BestChange»)',
            ],
            'OWNER' => $family,
            'RATE_MODE' => $bc ? (string) ($bc->rate_mode ?? '') : null,
            'BESTCHANGE_MAPPING' => $bc ? [
                'id' => (int) $bc->id,
                'status' => (int) ($bc->status ?? 0),
                'is_error_parser' => (int) ($bc->is_error_parser ?? 0),
                'id_from' => $bc->id_from ?? null,
                'id_to' => $bc->id_to ?? null,
            ] : null,
            'BESTCHANGE_PAIR' => [
                'from' => (string) ($explain['pair']['from'] ?? ''),
                'to' => (string) ($explain['pair']['to'] ?? ''),
            ],
            'CONFIGURED_POSITION' => $explain['position']['pair_override'] ?? null,
            'EFFECTIVE_POSITION' => $explain['position']['effective'] ?? null,
            'SELECTED_EXCHANGER' => $explain['source_exchanger'] ?? null,
            'SELECTED_RATE_VALUE' => $explain['bestchange_rate'] ?? ($explain['source_rate']['bestchange_rate'] ?? null),
            'PROFIT' => $explain['profit'] ?? ($explain['commercial']['explicit_profit_percent'] ?? null),
            'FIX_FEE' => $explain['fix_fee'] ?? ($explain['commercial']['fix_fee'] ?? null),
            'BASE' => $base,
            'CALCULATOR_FINAL_RATE' => $customer,
            'STORED_COURSE_VALUE' => $storedCourse,
            'PUBLIC_QUOTE_RATE' => $customer,
            'XML_RATE' => $xmlRate,
            'LAST_BESTCHANGE_UPDATE' => $explain['source_updated_at'] ?? ($explain['freshness']['updated_at'] ?? null),
            'LAST_COURSE_UPDATE' => $item->updated_at?->toDateTimeString(),
            'PRICING_HEALTH' => $explain['freshness']['state'] ?? null,
            'PRICING_HEALTH_LABEL' => $explain['freshness']['state_label'] ?? null,
            'manual_rate_authoritative' => $family === 'MANUAL',
            'manual_controls_non_authoritative' => in_array($family, ['BESTCHANGE', 'DERIVED', 'ZELLE'], true),
            'ownership_switch_required_for_manual_authority' => $family !== 'MANUAL',
            'warnings' => $warnings,
            'explain' => $explain,
        ];
    }

    /**
     * Preview Position/Profit (and optional fee) changes without DB writes.
     *
     * @param  array{profit?:mixed,fix_fee?:mixed,floating_fee?:mixed,position_num?:mixed,rate_mode?:mixed}  $overrides
     * @return array<string,mixed>
     */
    public static function preview(DirectionExchange $item, array $overrides = []): array
    {
        $before = self::truthPanel($item);

        $clone = $item->replicate();
        $clone->id = (int) $item->id;
        $clone->exists = false;
        $clone->setRelation('currency1', $item->currency1);
        $clone->setRelation('currency2', $item->currency2);

        $bc = BestChangeDirection::query()
            ->where('id_direction_exchange', (int) $item->id)
            ->orderByDesc('status')
            ->first();
        $bcClone = null;
        if ($bc !== null) {
            $bcClone = $bc->replicate();
            $bcClone->id = (int) $bc->id;
            $bcClone->exists = false;
            if (array_key_exists('position_num', $overrides) && $overrides['position_num'] !== null && $overrides['position_num'] !== '') {
                $bcClone->position_num = (string) $overrides['position_num'];
            }
            if (array_key_exists('rate_mode', $overrides) && $overrides['rate_mode'] !== null && $overrides['rate_mode'] !== '') {
                $bcClone->rate_mode = (string) $overrides['rate_mode'];
            }
            $clone->setRelation('bestchange_directions', $bcClone);
        }

        $resolver = new CommercialAdjustmentResolver();
        $family = $resolver->family($item);

        if (array_key_exists('profit', $overrides) && $overrides['profit'] !== null && $overrides['profit'] !== '') {
            $profit = (string) $overrides['profit'];
            if ($family === 'ZELLE') {
                $clone->floating_fee = bcmul($profit, '-1', 8);
                $clone->profit = '0';
            } else {
                $clone->profit = $profit;
            }
        }
        if (array_key_exists('fix_fee', $overrides) && $overrides['fix_fee'] !== null && $overrides['fix_fee'] !== '') {
            $clone->fix_fee = (string) $overrides['fix_fee'];
        }
        if (array_key_exists('floating_fee', $overrides) && $overrides['floating_fee'] !== null && $overrides['floating_fee'] !== '') {
            $clone->floating_fee = (string) $overrides['floating_fee'];
        }

        // Position preview cannot invent a new BestChange book rate without the book.
        // Expected BASE stays current selected rate_value; note WAITING_FOR_COMPILER.
        $afterExplain = AdminPricingTransparency::explain($clone);
        $afterBase = (string) ($afterExplain['base'] ?? '');
        $afterCustomer = (string) ($afterExplain['final_floating'] ?? '');
        $positionChanged = array_key_exists('position_num', $overrides)
            && (string) ($overrides['position_num'] ?? '') !== (string) ($before['EFFECTIVE_POSITION'] ?? $before['CONFIGURED_POSITION'] ?? '');

        return [
            'change_id' => (string) Str::uuid(),
            'dry_run' => true,
            'OWNER' => $before['OWNER'],
            'CURRENT_POSITION' => $before['EFFECTIVE_POSITION'],
            'NEW_POSITION' => $positionChanged
                ? ($overrides['position_num'] ?? $before['EFFECTIVE_POSITION'])
                : $before['EFFECTIVE_POSITION'],
            'CURRENT_PROFIT' => $before['PROFIT'],
            'NEW_PROFIT' => array_key_exists('profit', $overrides) ? (string) $overrides['profit'] : $before['PROFIT'],
            'CURRENT_BASE' => $before['BASE'],
            'EXPECTED_NEW_BASE' => $positionChanged
                ? null // book rate after compiler; unknown until BestChange refresh
                : $afterBase,
            'CURRENT_CUSTOMER_RATE' => $before['CALCULATOR_FINAL_RATE'],
            'EXPECTED_NEW_CUSTOMER_RATE' => $positionChanged ? null : $afterCustomer,
            'POSITION_PREVIEW_NOTE' => $positionChanged
                ? 'Position change selects a new BestChange BASE after the next compiler cycle. Customer rate preview uses the current book until then.'
                : null,
            'BASE_LABEL' => 'BASE',
            'PROFIT_LABEL' => 'PROFIT',
            'FINAL_CUSTOMER_RATE_LABEL' => 'FINAL CUSTOMER RATE',
            'warnings' => array_values(array_unique(array_merge(
                $before['warnings'] ?? [],
                $positionChanged ? ['Position change will apply when BestChange/compiler converges.'] : []
            ))),
            'before' => [
                'BASE' => $before['BASE'],
                'PROFIT' => $before['PROFIT'],
                'FINAL_CUSTOMER_RATE' => $before['CALCULATOR_FINAL_RATE'],
                'STORED_COURSE_VALUE' => $before['STORED_COURSE_VALUE'],
            ],
            'after' => [
                'BASE' => $positionChanged ? $before['BASE'] : $afterBase,
                'PROFIT' => array_key_exists('profit', $overrides) ? (string) $overrides['profit'] : $before['PROFIT'],
                'FINAL_CUSTOMER_RATE' => $positionChanged ? $before['CALCULATOR_FINAL_RATE'] : $afterCustomer,
            ],
        ];
    }

    /**
     * Post-save convergence status (read-only).
     *
     * @param  array<string,mixed>|null  $expected from save response / preview
     * @return array<string,mixed>
     */
    public static function convergence(DirectionExchange $item, ?array $expected = null): array
    {
        $truth = self::truthPanel($item);
        $status = self::CONVERGED;
        $reasons = [];

        $fresh = (string) ($truth['PRICING_HEALTH'] ?? '');
        if (in_array($fresh, ['STALE', 'SOURCE_ERROR', 'AGING'], true)) {
            $status = self::WAITING_FOR_COMPILER;
            $reasons[] = 'BestChange/source freshness is '.$fresh;
        }

        $base = (string) ($truth['BASE'] ?? '');
        $stored = (string) ($truth['STORED_COURSE_VALUE'] ?? '');
        $customer = (string) ($truth['CALCULATOR_FINAL_RATE'] ?? '');
        $profit = (string) ($truth['PROFIT'] ?? '0');

        // For BestChange profit=0, stored course should equal BASE after reassert/compiler.
        if (($truth['OWNER'] ?? '') === 'BESTCHANGE'
            && is_numeric($profit) && (float) $profit == 0.0
            && is_numeric($base) && is_numeric($stored) && (float) $base > 0
            && abs(((float) $stored - (float) $base) / (float) $base) > 0.01
        ) {
            $status = self::PRICING_MISMATCH;
            $reasons[] = 'STORED_COURSE_VALUE diverges from BASE by >1% at zero profit';
        }

        if ($expected !== null) {
            foreach (['EXPECTED_NEW_CUSTOMER_RATE', 'FINAL_CUSTOMER_RATE', 'customer_rate'] as $k) {
                if (!isset($expected[$k]) || $expected[$k] === null || $expected[$k] === '') {
                    continue;
                }
                if (!is_numeric((string) $expected[$k]) || !is_numeric($customer) || (float) $customer <= 0) {
                    continue;
                }
                $exp = (float) $expected[$k];
                if (abs(($customer - $exp) / max(abs($exp), 1e-12)) > 0.02) {
                    $status = self::WAITING_FOR_COMPILER;
                    $reasons[] = 'Customer rate not yet within 2% of expected save preview';
                }
            }
        }

        return [
            'SAVE_ACCEPTED' => true,
            'status' => $status,
            'CONVERGED' => $status === self::CONVERGED,
            'WAITING_FOR_COMPILER' => $status === self::WAITING_FOR_COMPILER,
            'PRICING_MISMATCH' => $status === self::PRICING_MISMATCH,
            'reasons' => $reasons,
            'SELECTED_COMPETITOR' => $truth['SELECTED_EXCHANGER'],
            'RATE_VALUE' => $truth['SELECTED_RATE_VALUE'],
            'CALCULATOR_FINAL' => $truth['CALCULATOR_FINAL_RATE'],
            'COURSE_VALUE' => $truth['STORED_COURSE_VALUE'],
            'PUBLIC_QUOTE' => $truth['PUBLIC_QUOTE_RATE'],
            'XML_RATE' => $truth['XML_RATE'],
            'warnings' => $truth['warnings'],
            'truth' => $truth,
        ];
    }

    /**
     * Mass-edit preview (no writes).
     *
     * @param  array<string,mixed>  $payload template + details
     * @return array<string,mixed>
     */
    public static function massPreview(array $payload): array
    {
        $template = (string) ($payload['template'] ?? '');
        $details = $payload['details'] ?? [];
        if (!is_array($details) || $template === '') {
            return [
                'ok' => false,
                'error' => 'template and details required',
            ];
        }

        $forbidden = ['parser_source_name', 'ownership', 'rate_mode', 'owner', 'course_value'];
        if (in_array($template, $forbidden, true)) {
            return [
                'ok' => false,
                'error' => 'OWNER/RATE_MODE/course_value cannot be changed via mass profit/position editor. Use a deliberate ownership action.',
                'blocked_template' => $template,
            ];
        }

        $ids = [];
        $samples = [];
        if (in_array($template, ['amounts', 'oth-fee', 'pay-fee', 'profit', 'partner-profit'], true)) {
            foreach ($details as $field => $byId) {
                if (in_array((string) $field, $forbidden, true)) {
                    return [
                        'ok' => false,
                        'error' => 'Field '.$field.' cannot piggyback on mass edit',
                        'blocked_field' => $field,
                    ];
                }
                if (!is_array($byId)) {
                    continue;
                }
                foreach ($byId as $id => $value) {
                    $ids[] = (int) $id;
                    if (count($samples) < 5) {
                        $dir = DirectionExchange::query()->with(['bestchange_directions', 'currency1', 'currency2'])->find((int) $id);
                        if ($dir) {
                            $ov = [(string) $field => $value];
                            $samples[] = [
                                'direction_id' => (int) $id,
                                'field' => (string) $field,
                                'new_value' => $value,
                                'preview' => self::preview($dir, $ov),
                            ];
                        }
                    }
                }
            }
        } else {
            if (in_array($template, $forbidden, true)) {
                return [
                    'ok' => false,
                    'error' => 'Blocked template',
                    'blocked_template' => $template,
                ];
            }
            foreach ($details as $id => $value) {
                $ids[] = (int) $id;
                if (count($samples) < 5) {
                    $dir = DirectionExchange::query()->with(['bestchange_directions', 'currency1', 'currency2'])->find((int) $id);
                    if ($dir) {
                        $samples[] = [
                            'direction_id' => (int) $id,
                            'field' => $template,
                            'new_value' => $value,
                            'preview' => self::preview($dir, [$template => $value]),
                        ];
                    }
                }
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));

        return [
            'ok' => true,
            'dry_run' => true,
            'change_id' => (string) Str::uuid(),
            'template' => $template,
            'affected_count' => count($ids),
            'affected_ids_sample' => array_slice($ids, 0, 50),
            'samples' => $samples,
            'confirm_required' => true,
            'confirm_token' => hash('sha256', json_encode([$template, $details], JSON_UNESCAPED_UNICODE)),
            'confirm_token_hint' => 'Send the same payload with confirm=1 and confirm_token to apply.',
        ];
    }

    /**
     * @param  array<string,mixed>  $explain
     * @return list<string>
     */
    public static function warnings(
        DirectionExchange $item,
        array $explain,
        string $storedCourse,
        string $base,
        string $customer,
    ): array {
        $out = [];
        $fresh = (string) ($explain['freshness']['state'] ?? '');
        if ($fresh === 'STALE' || $fresh === 'SOURCE_ERROR') {
            $out[] = 'BestChange data is stale or errored — saved configuration will apply when feed recovers.';
        } elseif ($fresh === 'AGING') {
            $out[] = 'BestChange book is aging; compiler may still be catching up.';
        }
        if (RatesXmlMonitorGate::isHidden()) {
            $out[] = 'XML hide flag is set — monitors may see an empty feed while the site is paused.';
        }
        $topnStatus = (string) ($explain['topn_status'] ?? ($explain['topn']['status'] ?? ''));
        if ($topnStatus !== '' && !in_array(strtoupper($topnStatus), ['OK', 'HEALTHY', 'PASS', ''], true)) {
            $out[] = 'Top-N alert: '.$topnStatus;
        }
        $family = (string) ($explain['ownership']['family'] ?? '');
        $profit = (string) ($explain['profit'] ?? '0');
        if ($family === 'BESTCHANGE' && is_numeric($profit) && (float) $profit == 0.0
            && is_numeric($base) && is_numeric($storedCourse) && (float) $base > 0
            && abs(((float) $storedCourse - (float) $base) / (float) $base) > 0.01
        ) {
            $out[] = 'Stored course diverges from BestChange BASE at zero profit — waiting for stored-course reassert/compiler.';
        }
        if ($family !== 'MANUAL') {
            $out[] = 'Manual rate controls are non-authoritative for OWNER='.$family.'. Changing ownership requires a deliberate rate-mode/ownership action.';
        }

        return $out;
    }

    private static function xmlRateForDirection(int $directionId, string $fallback): string
    {
        // Public XML is assembled from stored commercial course; use course_value as XML consumer rate.
        return $fallback;
    }
}
