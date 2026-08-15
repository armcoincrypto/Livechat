<?php
namespace iEXPackages\ExchangerClient\Http\Controller\Operations;

use App\Models\DirectionExchange;
use Exception;
use iEXPackages\ExchangerClient\Facades\StartServiceFacade;
use iEXPackages\ExchangerClient\Http\Resources\Operations\DirectionDetailResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;

class OperationsController
{
    /**
     * Первичный запуска данных при открытии страницы обмена
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     */
    public function index(Request $request): JsonResponse
    {
        $defaultMode = (int)iEXSetting('default_exchange_direction_mode', 0);

        $from = $request->filled('codeFrom')
            ? formatted_permitted_codes($request->codeFrom)
            : ($defaultMode === 0
                ? formatted_permitted_codes($request->cookie('exchange_last_buy', ''))
                : null);

        $to = $request->filled('codeTo')
            ? formatted_permitted_codes($request->codeTo)
            : ($defaultMode === 0
                ? formatted_permitted_codes($request->cookie('exchange_last_sell', ''))
                : null);

        $item = null;

        // Возвращаем направление только если оба параметра валидны
        if ($from && $to) {
            $item = DirectionExchange::activeDirection($from, $to)->first();
        }

        if (!$item) {
            if ((int)iEXSetting('default_exchange_direction_mode', 0)) {
                $item = DirectionExchange::activeDirection()->where('is_main', 1)->first();
            } else {
                $item = DirectionExchange::activeDirection()->inRandomOrder()->first();
            }
        }

        $locale = Str::lower(app()->getLocale());

        $response = Cache::store('redis')->get('exchange-iex-initial-rates-' . $locale);


        return response()->json([
            'locale' => app()->getLocale(),
            'config' => StartServiceFacade::buildExchange(),
            'props' => $response,
            'initialDirection' => $item ? new DirectionDetailResource($item) : null
        ]);
    }

    /**
     * Получаем данные по выбранному направлению.
     * Также сохраняет последний выбор пользователя в кэше.
     *
     * @param string|int $buy  id или designation_xml валюты отдачи
     * @param string|int $sell id или designation_xml валюты получения
     * @param Request $request
     * @return DirectionDetailResource|JsonResponse
     */
    public function show(string|int $buy, string|int $sell, Request $request)
    {
        $item = $this->findActiveDirection($buy, $sell);

        if (!$item) {
            Log::warning('Direction exchange not found', compact('buy', 'sell'));

            return response()->json([
                'errors' => [
                    [
                        'status' => '404',
                        'title' => 'Not Found',
                        'detail' => 'Exchange direction not found.',
                    ],
                ],
            ], 404);
        }

        // Персональный ключ для кэша (сохраняем только при нужной настройке)
        if ((int)iEXSetting('default_exchange_direction_mode', 0) === 0) {
            cookie()->queue('exchange_last_buy', is_numeric($buy) ? $buy : formatted_permitted_codes($buy), 43200);
            cookie()->queue('exchange_last_sell', is_numeric($sell) ? $sell : formatted_permitted_codes($sell), 43200);
        }

        return new DirectionDetailResource($item);
    }

    /**
     * SEO Phase D4 / Release C1: resolve only active, enabled exchange directions.
     * Explicit pair codes never fall back to an unrelated default pair.
     * Ambiguous multi-edge aliases fail closed (null → 404).
     */
    private function findActiveDirection(string|int $buy, string|int $sell): ?DirectionExchange
    {
        $buyId = is_numeric($buy) ? (int) $buy : null;
        $sellId = is_numeric($sell) ? (int) $sell : null;

        $resolved = \App\Services\Rates\CanonicalDirectionResolver::resolve(
            is_numeric($buy) ? (string) $buy : (string) formatted_permitted_codes((string) $buy),
            is_numeric($sell) ? (string) $sell : (string) formatted_permitted_codes((string) $sell),
            'WEBSITE_DEEP_LINK',
            $buyId,
            $sellId,
        );

        if (
            $resolved['direction_id'] === null
            || !in_array($resolved['status'], [
                \App\Services\Rates\CanonicalDirectionResolver::STATUS_EXACT_PAIR_FOUND,
                \App\Services\Rates\CanonicalDirectionResolver::STATUS_PAIR_ALIAS_RESOLVED,
            ], true)
        ) {
            if ($resolved['status'] === \App\Services\Rates\CanonicalDirectionResolver::STATUS_AMBIGUOUS_PAIR) {
                Log::warning('Direction exchange ambiguous', [
                    'buy' => $buy,
                    'sell' => $sell,
                    'reason' => $resolved['reason'],
                ]);
            }

            return null;
        }

        return DirectionExchange::query()
            ->quoteable()
            ->whereKey($resolved['direction_id'])
            ->first();
    }

    /**
     * Возвращает кэш всех курсов для фронта.
     *
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    public function update(): JsonResponse
    {
        $locale = Str::lower(app()->getLocale());
        $cacheKey = 'exchange-iex-initial-rates-' . $locale;
        $response = Cache::store('redis')->get($cacheKey);

        // Admin invalidation can briefly clear Redis. Never serve an empty catalog
        // to the public calculator — rebuild the snapshot on miss.
        if (!is_array($response) || $response === [] || empty($response['paymentSystems'] ?? null)) {
            try {
                app(\App\Services\Rates\ExchangeRatesCacheInvalidator::class)
                    ->rebuildPublicRatesSnapshots('rates.update.cache_miss');
                $response = Cache::store('redis')->get($cacheKey);
            } catch (\Throwable $e) {
                Log::error('rates_update_cache_miss_rebuild_failed', [
                    'locale' => $locale,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return response()->json(is_array($response) ? $response : new \stdClass());
    }
}
