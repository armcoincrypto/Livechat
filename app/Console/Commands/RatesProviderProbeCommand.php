<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rates\CoinMarketCapFailureClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Safe provider probe: does not print API keys and does not update rates unless --apply.
 */
final class RatesProviderProbeCommand extends Command
{
    protected $signature = 'rates:provider-probe
        {--provider=coinmarketcap : Provider alias}
        {--symbols=BTC,ETH : Comma-separated symbols to summarize}
        {--apply : Persist nothing today; reserved flag — probe never writes rates}
        {--format=json : json|table}';

    protected $description = 'Probe a price provider without exposing secrets or mutating rates.';

    public function handle(): int
    {
        $provider = strtolower((string) $this->option('provider'));
        if ($provider !== 'coinmarketcap') {
            $this->error('Only coinmarketcap probe is implemented in this release.');
            return self::FAILURE;
        }

        // Presence/length only — never print key material.
        $keyMeta = DB::table('parser_api_keys')
            ->where('provider_id', 'coinmarketcap')
            ->where('status', 1)
            ->orderByDesc('id')
            ->get(['id', 'status'])
            ->map(function ($row) {
                $raw = DB::table('parser_api_keys')->where('id', $row->id)->value('api_key');
                $len = is_string($raw) ? strlen(trim($raw)) : 0;

                return [
                    'id' => (int) $row->id,
                    'status' => (int) $row->status,
                    'key_length_class' => $len === 0 ? 'missing' : ($len < 20 ? 'short' : ($len <= 40 ? 'normal' : 'long')),
                    'key_present' => $len > 0,
                ];
            })
            ->all();

        $group = DB::table('group_parser_exchange')->where('alias', 'coinmarketcap')->first();
        $keyRow = DB::table('parser_api_keys')
            ->where('provider_id', 'coinmarketcap')
            ->where('status', 1)
            ->inRandomOrder()
            ->first();

        $httpStatus = null;
        $providerCode = null;
        $classified = null;
        $symbolsOut = [];
        $error = null;

        if ($keyRow === null || !is_string($keyRow->api_key) || trim($keyRow->api_key) === '') {
            $classified = [
                'class' => 'CREDENTIAL_MISSING',
                'http_status' => null,
                'provider_code' => null,
                'message' => null,
            ];
        } else {
            $response = Http::withHeaders([
                'X-CMC_PRO_API_KEY' => trim($keyRow->api_key),
                'Accept' => 'application/json',
            ])->timeout(15)->get('https://pro-api.coinmarketcap.com/v1/cryptocurrency/quotes/latest', [
                'symbol' => (string) $this->option('symbols'),
                'convert' => 'USD',
            ]);
            $httpStatus = $response->status();
            $body = $response->body();
            $decoded = json_decode($body, true);
            $classified = (new CoinMarketCapFailureClassifier())->classify(
                is_array($decoded) ? $decoded : null,
                $httpStatus,
                $body
            );
            $providerCode = $classified['provider_code'];
            if (is_array($decoded) && isset($decoded['data']) && is_array($decoded['data'])) {
                foreach ($decoded['data'] as $sym => $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $price = $row['quote']['USD']['price'] ?? null;
                    $ts = $row['quote']['USD']['last_updated'] ?? ($row['last_updated'] ?? null);
                    $symbolsOut[] = [
                        'symbol' => (string) $sym,
                        'price_usd' => $price,
                        'last_updated' => $ts,
                    ];
                }
            } elseif (($classified['class'] ?? '') !== 'PLAN_LIMIT_EXCEEDED' && ($classified['class'] ?? '') !== 'CREDENTIAL_REJECTED') {
                $error = $classified['class'] ?? 'UNKNOWN_PROVIDER_FAILURE';
            }
        }

        // --apply is intentionally a no-op for rates; probe never mutates parser_exchange.
        if ((bool) $this->option('apply')) {
            $this->warn('--apply is ignored: rates:provider-probe never writes production rates.');
        }

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'provider' => 'coinmarketcap',
            'group_status' => $group->status ?? null,
            'http_status' => $httpStatus,
            'provider_code' => $providerCode,
            'classification' => $classified['class'] ?? null,
            'message' => $classified['message'] ?? $error,
            'keys' => $keyMeta,
            'symbols' => $symbolsOut,
            'mutates_rates' => false,
        ];

        if ((string) $this->option('format') === 'table') {
            $this->table(['field', 'value'], [
                ['classification', (string) ($payload['classification'] ?? '')],
                ['http_status', (string) ($payload['http_status'] ?? '')],
                ['provider_code', (string) ($payload['provider_code'] ?? '')],
                ['group_status', (string) ($payload['group_status'] ?? '')],
            ]);
        } else {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $ok = ($payload['classification'] === null || $payload['classification'] === 'UNKNOWN_PROVIDER_FAILURE')
            && $httpStatus === 200
            && $symbolsOut !== [];
        // Success only when quotes returned; plan/credential failures exit 1 for ops clarity.
        if ($httpStatus === 200 && $symbolsOut !== [] && (int) ($providerCode ?? 0) === 0) {
            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}
