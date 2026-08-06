<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DirectionExchange;
use App\Services\Rates\CanonicalDirectionEligibility;
use App\Services\Rates\PublicDuplicateExclusion;
use App\Services\Rates\RateDirectionEligibility;
use Illuminate\Console\Command;

/**
 * C3-A audit: advertised / quoteable / orderable / export closure.
 * Exit 1 while unexplained public gaps remain under the C3-A catalog definition.
 */
final class RatesActivePairClosureCommand extends Command
{
    protected $signature = 'rates:active-pair-closure {--json : machine-readable JSON only}';

    protected $description = 'Report advertised/selector/quote/order/BestChange closure gaps (fail closed)';

    public function handle(): int
    {
        PublicDuplicateExclusion::clearCache();
        $elig = RateDirectionEligibility::make();

        $dirs = DirectionExchange::query()
            ->with(['currency1', 'currency2'])
            ->whereNull('deleted_at')
            ->where('status', 1)
            ->orderBy('id')
            ->get();

        $counts = [
            'DB_ENABLED' => $dirs->count(),
            'CANONICAL_ELIGIBLE' => 0,
            'WEBSITE_ADVERTISED' => 0,
            'WEBSITE_SELECTOR_VISIBLE' => 0,
            'WEBSITE_QUOTEABLE' => 0,
            'ORDERABLE' => 0,
            'BESTCHANGE_EXPORTED_FLAGS' => 0,
            'advertised_not_quoteable' => 0,
            'advertised_not_orderable' => 0,
            'quoteable_not_orderable' => 0,
            'exported_not_orderable' => 0,
            'stale_or_zero_rate' => 0,
            'provider_unavailable' => 0,
            'canonical_duplicates_in_db_enabled' => 0,
        ];

        $gaps = [];

        foreach ($dirs as $d) {
            $id = (int) $d->id;
            $c1ok = (int) ($d->currency1?->status ?? -1) === 0;
            $c2ok = (int) ($d->currency2?->status ?? -1) === 0;
            $courseOk = CanonicalDirectionEligibility::passesPublicCatalogPrefilter($id, $d->course_value);
            // passesPublicCatalogPrefilter already excludes duplicates; track duplicates separately
            $excluded = PublicDuplicateExclusion::isExcluded($id);
            $coursePositive = is_numeric((string) ($d->course_value ?? ''))
                && (float) $d->course_value > 0.0;

            // C3-A public catalog/selector definition
            $catalog = $c1ok && $c2ok && $courseOk && (int) ($d->is_error_rate ?? 0) === 0;

            $raw = $elig->evaluateDirection($d);
            $quote = !empty($raw['quote_allowed']);
            $order = !empty($raw['order_allowed']);
            $export = !empty($raw['export_allowed']) && !empty($raw['BestChange_allowed']);

            if ($catalog) {
                $counts['CANONICAL_ELIGIBLE']++;
                $counts['WEBSITE_ADVERTISED']++;
                $counts['WEBSITE_SELECTOR_VISIBLE']++;
            }
            if ($quote) {
                $counts['WEBSITE_QUOTEABLE']++;
            }
            if ($order) {
                $counts['ORDERABLE']++;
            }
            if ($export) {
                $counts['BESTCHANGE_EXPORTED_FLAGS']++;
            }
            if (!$coursePositive) {
                $counts['stale_or_zero_rate']++;
            }
            if ($excluded) {
                $counts['canonical_duplicates_in_db_enabled']++;
            }

            if ($catalog && !$quote) {
                $counts['advertised_not_quoteable']++;
                $gaps[] = [
                    'kind' => 'advertised_not_quoteable',
                    'id' => $id,
                    'from' => $d->currency1?->designation_xml,
                    'to' => $d->currency2?->designation_xml,
                    'reasons' => $raw['blocking_reasons'] ?? [],
                    'classification' => $raw['classification'] ?? null,
                ];
            }
            if ($catalog && !$order) {
                $counts['advertised_not_orderable']++;
            }
            if ($quote && !$order) {
                $counts['quoteable_not_orderable']++;
            }
            if ($export && !$order) {
                $counts['exported_not_orderable']++;
            }
        }

        $unexplained = $counts['advertised_not_quoteable']
            + $counts['advertised_not_orderable']
            + $counts['quoteable_not_orderable']
            + $counts['exported_not_orderable'];

        $aligned = $counts['WEBSITE_ADVERTISED'] === $counts['WEBSITE_QUOTEABLE']
            && $counts['WEBSITE_QUOTEABLE'] === $counts['ORDERABLE']
            && $counts['WEBSITE_ADVERTISED'] === $counts['WEBSITE_SELECTOR_VISIBLE'];

        $payload = [
            'counts' => $counts,
            'gaps_sample' => array_slice($gaps, 0, 50),
            'gap_total' => count($gaps),
            'aligned' => $aligned,
            'exit' => ($unexplained === 0 && $aligned) ? 0 : 1,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($counts as $k => $v) {
                $this->line(sprintf('%s=%s', $k, $v));
            }
            $this->line('gap_total=' . count($gaps));
            $this->line('aligned=' . ($aligned ? 'yes' : 'no'));
        }

        return ($unexplained === 0 && $aligned) ? self::SUCCESS : self::FAILURE;
    }
}
