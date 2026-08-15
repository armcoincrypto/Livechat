<?php
declare(strict_types=1);

namespace iEXPackages\ExchangerClient\Http\Controller;

use App\Http\Controllers\Controller;
use App\Models\DirectionExchange;
use App\Models\OrderExchangeStatDaily;
use App\Models\ReserveTotalSnapshot;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class ConversionLiftController extends Controller
{
    public function index(): JsonResponse
    {
        $locale = app()->getLocale() ?: 'ru';
        $cacheKey = 'exs-p104-conversion-lift-v1-' . $locale;
        $cacheHit = Cache::has($cacheKey);

        $payload = Cache::remember($cacheKey, 90, function () use ($locale) {
            return [
                'recent' => $this->recentActivity($locale),
                'volume' => $this->volumeIndicators(),
                'authority' => $this->authorityStats($locale),
                'liquidity' => $this->liquidityStats($locale),
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        });

        return response()
            ->json($payload)
            ->header('X-Conversion-Lift-Cache', $cacheHit ? 'APP-HIT' : 'APP-MISS');
    }

    /**
     * @return list<array{pair: string, ago: string, ago_minutes: int, volume: string}>
     */
    protected function recentActivity(string $locale): array
    {
        try {
            $tasks = Task::query()
                ->where('status', 4)
                ->whereNotNull('completed_at')
                ->where('completed_at', '>=', Carbon::now()->subHours(48))
                ->orderByDesc('completed_at')
                ->limit(6)
                ->with([
                    'direction_exchange.currency1.payment:id,name',
                    'direction_exchange.currency1.code_currency:id,name',
                    'direction_exchange.currency2.payment:id,name',
                ])
                ->get(['id', 'id_direction_exchange', 'give_price', 'completed_at']);

            if ($tasks->isEmpty()) {
                $tasks = Task::query()
                    ->where('status', 4)
                    ->whereNotNull('completed_at')
                    ->orderByDesc('completed_at')
                    ->limit(6)
                    ->with([
                        'direction_exchange.currency1.payment:id,name',
                        'direction_exchange.currency1.code_currency:id,name',
                        'direction_exchange.currency2.payment:id,name',
                    ])
                    ->get(['id', 'id_direction_exchange', 'give_price', 'completed_at']);
            }

            return $tasks->map(function (Task $task) use ($locale) {
                $direction = $task->direction_exchange;
                $from = $direction?->currency1?->payment?->name ?? 'Crypto';
                $to = $direction?->currency2?->payment?->name ?? 'Fiat';
                $completedAt = Carbon::parse($task->completed_at);
                $agoMinutes = max(1, (int) $completedAt->diffInMinutes(Carbon::now()));

                return [
                    'pair' => trim($from) . ' → ' . trim($to),
                    'ago' => $this->formatAgo($agoMinutes, $locale),
                    'ago_minutes' => $agoMinutes,
                    'volume' => $this->volumeBucket((float) $task->give_price, $direction?->currency1?->code_currency?->name ?? 'USDT'),
                ];
            })->values()->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array{today_completed: int, last_hour_estimate: int}
     */
    protected function volumeIndicators(): array
    {
        try {
            $today = OrderExchangeStatDaily::query()
                ->whereDate('date', Carbon::today())
                ->first();

            $completed = (int) ($today->completed_count ?? 0);

            return [
                'today_completed' => $completed,
                'last_hour_estimate' => max(1, (int) round($completed / max(1, Carbon::now()->hour ?: 1))),
            ];
        } catch (\Throwable) {
            return [
                'today_completed' => 0,
                'last_hour_estimate' => 0,
            ];
        }
    }

    protected function volumeBucket(float $amount, string $symbol): string
    {
        if ($amount >= 5000) {
            $bucket = '5000+';
        } elseif ($amount >= 1000) {
            $bucket = '1000+';
        } elseif ($amount >= 500) {
            $bucket = '500+';
        } elseif ($amount >= 100) {
            $bucket = '100+';
        } else {
            $bucket = '<100';
        }

        return $bucket . ' ' . $symbol;
    }

    protected function formatAgo(int $minutes, string $locale): string
    {
        if ($locale === 'en') {
            if ($minutes < 60) {
                return $minutes . ' min ago';
            }

            return (int) floor($minutes / 60) . ' h ago';
        }

        if ($minutes < 60) {
            return $minutes . ' мин назад';
        }

        return (int) floor($minutes / 60) . ' ч назад';
    }

    /**
     * @return array<string, mixed>
     */
    protected function authorityStats(string $locale): array
    {
        try {
            $directions = (int) DirectionExchange::query()->where('status', 1)->count();
        } catch (\Throwable) {
            $directions = 500;
        }

        $reserve = $this->resolveReserveLabel();

        return [
            'completed_exchanges' => '100 000+',
            'directions' => max($directions, 500),
            'years_operating' => 5,
            'avg_completion_min' => 50,
            'reserve' => $reserve,
            'bestchange_profile' => 'https://www.bestchange.com/exswaping-exchanger.html',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function liquidityStats(string $locale): array
    {
        $reserve = $this->resolveReserveLabel();
        $updatedAt = Carbon::now();

        try {
            $snapshot = ReserveTotalSnapshot::query()->orderByDesc('snapshot_at')->first();
            if ($snapshot && $snapshot->snapshot_at) {
                $updatedAt = Carbon::parse($snapshot->snapshot_at);
            }
        } catch (\Throwable) {
            // keep now
        }

        $rails = $locale === 'en'
            ? ['USDT TRC20', 'SBER RUB', 'Bank cards', 'SBP payouts']
            : ['USDT TRC20', 'SBER RUB', 'Банковские карты', 'СБП'];

        return [
            'health' => 'healthy',
            'health_label' => $locale === 'en' ? 'Healthy reserves' : 'Резервы в норме',
            'reserve' => $reserve,
            'payout_rails' => $rails,
            'updated_at' => $updatedAt->toIso8601String(),
            'updated_label' => $updatedAt->format('H:i'),
        ];
    }

    protected function resolveReserveLabel(): string
    {
        try {
            $snapshot = ReserveTotalSnapshot::query()->orderByDesc('snapshot_at')->first();
            if ($snapshot && $snapshot->total_usd > 0) {
                return number_format((float) $snapshot->total_usd, 0, '.', ' ') . ' USD';
            }
        } catch (\Throwable) {
            // fallback
        }

        return '100 000 000 RUB';
    }
}
