<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DirectionExchange;
use App\Services\Rates\RateDirectionEligibility;
use App\Services\Rates\RubFamilyPremiumPolicy;
use Illuminate\Console\Command;

/**
 * Explain why a direction is active / quarantined / not exportable.
 */
final class RatesDirectionStatusCommand extends Command
{
    protected $signature = 'rates:direction-status
        {id : direction_exchange id}
        {--format=json : json|table}';

    protected $description = 'Explain direction eligibility for quote, order, and export (read-only).';

    public function handle(): int
    {
        $id = (int) $this->argument('id');
        $direction = DirectionExchange::query()
            ->with(['currency1:id,designation_xml', 'currency2:id,designation_xml'])
            ->find($id);

        if ($direction === null) {
            $this->error('direction_not_found');

            return self::FAILURE;
        }

        $policy = RubFamilyPremiumPolicy::fromStorageApp();
        $payload = RateDirectionEligibility::make()->evaluateDirection($direction);
        $payload['rub_family_policy'] = $policy->summary();
        $payload['rub_family'] = $policy->familyForDestination((string) $payload['to'])['family_key'] ?? null;
        $payload['rub_family_classification'] = $payload['classification'];
        $payload['economic_note'] = ($payload['baseline_status'] ?? null) === 'NO_BASELINE'
            ? 'crypto_rub_requires_independent_baseline'
            : null;

        if ((string) $this->option('format') === 'table') {
            $this->table(
                ['field', 'value'],
                collect($payload)->map(fn ($v, $k) => [$k, is_scalar($v) || $v === null ? (string) $v : json_encode($v)])->values()->all()
            );
        } else {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }
}
