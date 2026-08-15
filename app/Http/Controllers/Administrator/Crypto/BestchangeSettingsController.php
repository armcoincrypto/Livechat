<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administrator\Crypto;

use App\Http\Controllers\Controller;
use App\Settings\BestChangeConfig;
use iEXPackages\BestChange\Facades\BestChangeFacade;
use iEXPackages\Proxy\Models\Proxy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * BestchangeSettingsController
 *
 * Управление глобальными настройками BestChange в админке.
 *
 * Важно:
 * - Справочники берутся через RatesConnection (BestChangeFacade::rates()).
 * - Кэширование справочников выполняет BestChangeCatalogRepository (автокэш).
 * - Форс-обновление справочников делаем только если изменились параметры,
 *   влияющие на ответы API: api_key / site_version / proxy_id.
 */
final class BestchangeSettingsController extends Controller
{
    /**
     * Отдать текущие настройки и справочники для UI.
     */
    public function index(BestChangeConfig $settings): JsonResponse
    {
        $currencies = [];
        $cities = [];
        $countries = [];
        $groups = [];
        $exchangers = [];

        if ($settings->apiKey() !== '') {
            try {
                $rates = BestChangeFacade::rates();

                $currencies = array_values($rates->currencies(false));
                $cities     = array_values($rates->cities(false));
                $exchangers = array_values($rates->exchangers(false));

                $countries  = array_values($rates->countries(false));
                $groups     = array_values($rates->groups(false));
            } catch (\Throwable $e) {
                Log::error('BestChange settings index: справочники недоступны', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Прокси для выбора в UI (без паролей и лишних полей)
        $proxies = Proxy::query()
            ->orderByDesc('id')
            ->get(['id', 'host', 'port', 'type', 'status'])
            ->map(static function (Proxy $proxy): array {
                $statusText = $proxy->status ? 'Активен' : 'Неактивен';
                $type = $proxy->type ?: 'http';

                return [
                    'id' => (int) $proxy->id,
                    'value' => sprintf('%s:%s (%s) — %s', (string) $proxy->host, (int) $proxy->port, (string) $type, $statusText),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'status'     => 0,
            'proxies'    => $proxies,
            'attributes' => $settings->toArray(),

            'currencies' => $currencies,
            'cities'     => $cities,
            'countries'  => $countries,
            'groups'     => $groups,
            'exchangers' => $exchangers,
        ]);
    }

    /**
     * Сохранить настройки BestChange.
     */
    public function update(Request $request, BestChangeConfig $settings): JsonResponse
    {
        $before = $settings->toArray();

        $validator = Validator::make($request->all(), [
            'api_key' => ['nullable', 'string'],

            'is_enable' => ['nullable'],
            'is_log_error' => ['nullable'],

            'timeout' => ['nullable', 'integer', 'min:1', 'max:120'],
            'interval' => ['nullable', 'integer', 'min:0'],
            'site_version' => ['nullable', 'string'],

            // 0 = без прокси, >0 = ID прокси
            'proxy_id' => ['nullable', 'integer', 'min:0'],

            'currencies' => ['nullable', 'array'],
            'currencies.*' => ['integer'],
            'cities' => ['nullable', 'array'],
            'cities.*' => ['integer'],

            'blacklist' => ['nullable', 'array'],
            'blacklist.*' => ['integer'],

            'position' => ['nullable', 'integer', 'min:1'],
            'type_position' => ['nullable', 'string', 'in:rate,rankrate'],

            'rate_mode' => ['nullable', 'string', 'in:position,median_top_n,weighted_avg_top_n'],
            'top_n' => ['nullable', 'integer', 'min:1', 'max:100'],

            'anti_fake_enabled' => ['nullable'],
            'anti_fake_min_score' => ['nullable', 'integer', 'min:0', 'max:100'],

            'exchanger_pool_mode' => ['nullable', 'string', 'in:off,soft,strict'],
            'exchanger_pool_preferred' => ['nullable', 'array'],
            'exchanger_pool_preferred.*' => ['integer'],
            'exchanger_pool_excluded' => ['nullable', 'array'],
            'exchanger_pool_excluded.*' => ['integer'],

            'auto_blacklist_enabled' => ['nullable'],
            'auto_blacklist_default_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        // Доп. строгая проверка: если proxy_id > 0 — он должен существовать
        $proxyIdInput = (int) $request->input('proxy_id', 0);
        if ($proxyIdInput > 0 && !Proxy::query()->whereKey($proxyIdInput)->exists()) {
            return response()->json([
                'status' => 1,
                'message' => __('Прокси не найден'),
            ]);
        }

        $payload = $this->normalizeSettingsPayload($request);
        $settings->update($payload);

        $after = $settings->toArray();

        $shouldRefreshCatalog =
            (string)($before['api_key'] ?? '') !== (string)($after['api_key'] ?? '')
            || (string)($before['site_version'] ?? '') !== (string)($after['site_version'] ?? '')
            || (int)($before['proxy_id'] ?? 0) !== (int)($after['proxy_id'] ?? 0);

        if ($shouldRefreshCatalog) {
            try {
                $rates = BestChangeFacade::rates();
                $forceRefresh = true;

                $rates->currencies($forceRefresh);
                $rates->cities($forceRefresh);
                $rates->exchangers($forceRefresh);

                $rates->countries($forceRefresh);
                $rates->groups($forceRefresh);
            } catch (\Throwable $e) {
                Log::error('BestChange settings update: ошибка обновления справочников', [
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'status'  => 1,
                    'message' => __('Не удалось обновить справочники BestChange. Проверьте API ключ/доступность API.'),
                ]);
            }
        }

        return response()->json([
            'status'  => 0,
            'message' => __('Настройки успешно сохранены'),
        ]);
    }

    /**
     * Нормализовать входные данные под BestChangeConfig::update().
     *
     * @return array<string,mixed>
     */
    private function normalizeSettingsPayload(Request $request): array
    {
        $currencies = $this->normalizeIntList($request->input('currencies', []));
        $cities     = $this->normalizeIntList($request->input('cities', []));
        $blacklist  = $this->normalizeIntList($request->input('blacklist', []));

        $rateMode = strtolower(trim((string) $request->input('rate_mode', 'position')));
        if (!in_array($rateMode, ['position', 'median_top_n,weighted_avg_top_n'], true)) {
            // поправка: оставим только допустимые
            $rateMode = 'position';
        }
        if (!in_array($rateMode, ['position', 'median_top_n', 'weighted_avg_top_n'], true)) {
            $rateMode = 'position';
        }

        $topN = max(1, min(100, (int) $request->input('top_n', 5)));

        $antiFakeEnabled  = (bool) $request->input('anti_fake_enabled', true);
        $antiFakeMinScore = max(0, min(100, (int) $request->input('anti_fake_min_score', 50)));

        $poolMode = strtolower(trim((string) $request->input('exchanger_pool_mode', 'off')));
        if (!in_array($poolMode, ['off', 'soft', 'strict'], true)) {
            $poolMode = 'off';
        }

        $poolPreferred = $this->normalizeIntList($request->input('exchanger_pool_preferred', []));
        $poolExcluded  = $this->normalizeIntList($request->input('exchanger_pool_excluded', []));

        $autoBlacklistEnabled = (bool) $request->input('auto_blacklist_enabled', true);
        $autoBlacklistMinutes = max(0, min(10_080, (int) $request->input('auto_blacklist_default_minutes', 120)));

        $apiKey = trim((string) $request->input('api_key', ''));

        $siteVersion = strtolower(trim((string) $request->input('site_version', 'ru')));
        if (!preg_match('/^[a-z]{2,5}$/', $siteVersion)) {
            $siteVersion = 'ru';
        }

        $typePosition = strtolower(trim((string) $request->input('type_position', 'rankrate')));
        if (!in_array($typePosition, ['rate', 'rankrate'], true)) {
            $typePosition = 'rankrate';
        }

        $timeout  = max(1, min(120, (int) $request->input('timeout', 10)));
        $interval = max(0, (int) $request->input('interval', 0));
        $position = max(1, (int) $request->input('position', 1));

        // proxy_id: int, 0 = без прокси
        $proxyId = max(0, (int) $request->input('proxy_id', 0));

        return [
            'api_key'      => $apiKey,
            'is_enable'    => (bool) $request->input('is_enable', false),
            'is_log_error' => (bool) $request->input('is_log_error', false),

            'timeout'      => $timeout,
            'interval'     => $interval,
            'site_version' => $siteVersion,
            'proxy_id'     => $proxyId,

            'currencies'   => $currencies,
            'cities'       => $cities,
            'blacklist'    => $blacklist,

            'position'      => $position,
            'type_position' => $typePosition,
            'rate_mode'     => $rateMode,
            'top_n'         => $topN,

            'anti_fake_enabled'   => $antiFakeEnabled,
            'anti_fake_min_score' => $antiFakeMinScore,

            'exchanger_pool_mode'      => $poolMode,
            'exchanger_pool_preferred' => $poolPreferred,
            'exchanger_pool_excluded'  => $poolExcluded,

            'auto_blacklist_enabled' => $autoBlacklistEnabled,
            'auto_blacklist_default_minutes' => $autoBlacklistMinutes,
        ];
    }

    /**
     * @param mixed $value
     * @return int[]
     */
    private function normalizeIntList(mixed $value): array
    {
        if (!is_array($value) || $value === []) {
            return [];
        }

        $ids = [];
        foreach ($value as $v) {
            $i = (int) $v;
            if ($i > 0) {
                $ids[$i] = true;
            }
        }

        if ($ids === []) {
            return [];
        }

        $out = array_map('intval', array_keys($ids));
        sort($out, SORT_NUMERIC);

        return array_values($out);
    }
}
