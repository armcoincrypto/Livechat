<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DirectionExchange;
use App\Services\Orders\InboundPaymentDestinationGuard;
use App\Services\Orders\PaymentDestinationRouter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class OrdersPaymentRoutingHealthCommand extends Command
{
    protected $signature = 'orders:payment-routing-health {--format=json : json|table}';

    protected $description = 'Classify quoteable (customer-payable) inbound rails by payment-destination owner.';

    public function handle(): int
    {
        // Expected payable inventory = quoteable(), not every status=1 direction.
        // Hidden/removed currencies (e.g. retired USDCERC20) must not appear as
        // requisite_rails_missing defects.
        $dirs = DirectionExchange::query()
            ->quoteable()
            ->with(['currency1.merchants', 'merchants', 'direction_requisites'])
            ->get();

        $byCurrency = [];
        foreach ($dirs as $dir) {
            $cid = (int) ($dir->id_currency1 ?? 0);
            if ($cid <= 0) {
                continue;
            }
            if (! isset($byCurrency[$cid])) {
                $byCurrency[$cid] = [
                    'currency_id' => $cid,
                    'letter_cod' => (string) ($dir->currency1?->designation_xml ?? ''),
                    'owner' => PaymentDestinationRouter::classifyDirection($dir),
                    'dirs' => 0,
                    'source_ok' => 0,
                    'source_missing' => 0,
                ];
            }
            $byCurrency[$cid]['dirs']++;
            if (InboundPaymentDestinationGuard::directionHasSource($dir)) {
                $byCurrency[$cid]['source_ok']++;
            } else {
                $byCurrency[$cid]['source_missing']++;
            }
        }

        $kobbopayHealthy = 0;
        $kobbopayUnavailable = 0;
        $zelleGated = 0;
        $requisiteReady = 0;
        $requisiteMissing = 0;
        $unknown = 0;
        $unknownLetterCods = [];

        foreach ($byCurrency as $row) {
            $owner = $row['owner'];
            $ok = $row['source_missing'] === 0 && $row['dirs'] > 0;
            if ($owner === PaymentDestinationRouter::OWNER_KOBBOPAY) {
                $ok ? $kobbopayHealthy++ : $kobbopayUnavailable++;
            } elseif ($owner === PaymentDestinationRouter::OWNER_ZELLE_VERIFICATION) {
                $zelleGated++;
            } elseif ($owner === PaymentDestinationRouter::OWNER_EXSWAPING_REQUISITE) {
                $ok ? $requisiteReady++ : $requisiteMissing++;
            } else {
                $unknown++;
                $unknownLetterCods[] = $row['letter_cod'] !== '' ? $row['letter_cod'] : ('id:'.$row['currency_id']);
            }
        }

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'order_enabled_directions' => $dirs->count(),
            'unique_inbound_currencies' => count($byCurrency),
            'kobbopay_rails_healthy' => $kobbopayHealthy,
            'kobbopay_rails_unavailable' => $kobbopayUnavailable,
            'zelle_verification_gated' => $zelleGated,
            'requisite_rails_ready' => $requisiteReady,
            'requisite_rails_missing' => $requisiteMissing,
            'order_enabled_unknown_routing' => $unknown,
            'unknown_letter_cods' => array_values(array_unique($unknownLetterCods)),
            'ORDER_ENABLED_WITH_UNRESOLVED_PAYMENT_ROUTING' => $unknown,
            'ok' => $unknown === 0,
        ];

        Log::info('orders:payment-routing-health', [
            'ok' => $payload['ok'],
            'unknown' => $unknown,
            'kobbopay_healthy' => $kobbopayHealthy,
            'kobbopay_unavailable' => $kobbopayUnavailable,
            'requisite_missing' => $requisiteMissing,
        ]);

        if ($this->option('format') === 'json') {
            $this->line(json_encode($payload, JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(
                ['metric', 'value'],
                collect($payload)
                    ->except(['unknown_letter_cods'])
                    ->map(fn ($v, $k) => [$k, is_bool($v) ? ($v ? 'true' : 'false') : (string) $v])
                    ->values()
                    ->all()
            );
        }

        return $unknown === 0 ? self::SUCCESS : self::FAILURE;
    }
}
