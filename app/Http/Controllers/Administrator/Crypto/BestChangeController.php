<?php
declare(strict_types=1);

namespace App\Http\Controllers\Administrator\Crypto;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ParserRates\BestChangeLogResources;
use App\Http\Resources\Admin\ParserRates\BestChangeParserResources;
use App\Models\BestChangeDirection;
use App\Models\BestchangeParserError;
use App\Models\DirectionExchange;
use App\Models\GroupParserExchange;
use App\Models\ParserFormulaRates;
use App\Settings\BestChangeConfig;
use iEXPackages\BestChange\Facades\BestChangeFacade;
use iEXPackages\BestChange\Support\ValueNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class BestChangeController extends Controller
{
    /**
     * Список пар BestChange (bestchange_directions).
     *
     * Быстрые ветки:
     * - loadingDirection: список активных DirectionExchange
     * - loadingFilters:   список валют (для фильтров)
     */
    public function index(Request $request): JsonResponse
    {
        /** @var BestChangeConfig $config */
        $config = app(BestChangeConfig::class);


        if ($request->boolean('loadingDirection')) {
            return $this->responseDirectionExchangeList();
        }

        if ($request->boolean('loadingFilters')) {
            return $this->responseCurrenciesForFilters($config);
        }

        $defaultPerPage = (int) iEXSetting('admin_bestchange_parser_pagination', 20);
        $perPage = max(10, min(100, (int) $request->integer('per_page', $defaultPerPage)));

        [$orderColumn, $orderDirection] = $this->resolveSorting($request);

        $query = BestChangeDirection::query()
            ->whereHas('direction_exchange')
            ->filter($request->all())
            ->with(['direction_exchange:id,tech_name,status'])
            ->orderByDesc('is_favorite')
            ->orderBy($orderColumn, $orderDirection);

        if ($orderColumn !== 'id') {
            $query->orderBy('id', 'desc');
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'items'            => new BestChangeParserResources($paginator),
            'per_page'         => $perPage,
        ]);
    }

    /**
     * Добавление пар по direction_exchange_id.
     */
    public function store(Request $request): JsonResponse
    {
        if ($this->isDemoMode()) {
            return $this->demoForbidden();
        }

        // быстрый апдейт статуса одной записи (как было)
        if ($request->string('updateField')->toString() === 'status') {
            $id = (int) $request->integer('id', 0);
            $status = (int) $request->integer('status', 0);

            if (!in_array($status, [0, 1], true)) {
                return response()->json(['status' => 1, 'message' => __('Некорректный статус')]);
            }

            $item = BestChangeDirection::query()->find($id);
            if (!$item) {
                return response()->json(['status' => 1, 'message' => __('Запись не найдена')]);
            }

            $item->update(['status' => $status]);

            return response()->json(['status' => 0]);
        }

        $validator = Validator::make($request->all(), [
            'ids_direction_exchange' => ['required', 'array', 'min:1'],
            'ids_direction_exchange.*' => ['integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 1, 'message' => $validator->messages()->first()]);
        }

        $ids = array_values(array_unique(array_map('intval', (array) $request->input('ids_direction_exchange', []))));

        foreach ($ids as $directionExchangeId) {
            if ($directionExchangeId <= 0) {
                continue;
            }

            if (!DirectionExchange::query()->whereKey($directionExchangeId)->exists()) {
                continue;
            }

            BestChangeDirection::query()->firstOrCreate([
                'id_direction_exchange' => $directionExchangeId,
            ]);
        }

        return response()->json([
            'status'  => 0,
            'message' => __('Пары успешно добавлены'),
        ]);
    }

    /**
     * Данные для формы редактирования пары.
     */
    public function edit(int $id, Request $request): JsonResponse
    {
        /** @var BestChangeConfig $config */
        $config = app(BestChangeConfig::class);

        $item = $request->boolean('is_direction')
            ? BestChangeDirection::query()->firstOrCreate(['id_direction_exchange' => $id])
            : BestChangeDirection::query()->findOrFail($id);

        $rates = BestChangeFacade::rates();

        try {
            $currenciesAll = $rates->currencies();
            $citiesAll     = $rates->cities();
            $exchangersAll = $rates->exchangers();
        } catch (\Throwable $e) {
            Log::error('BestChange edit: catalogs error', ['error' => $e->getMessage()]);
            $currenciesAll = $citiesAll = $exchangersAll = [];
        }

        $currencies = collect($currenciesAll)
            ->whereIn('id', $config->currencies())
            ->values();

        $cities = collect($citiesAll)
            ->whereIn('id', $config->cities())
            ->values();

        $exchangers = collect($exchangersAll)->values();

        $parserFormula = ParserFormulaRates::query()
            ->where('status', 1)
            ->select('id', 'title', 'summa')
            ->get()
            ->map(static fn (ParserFormulaRates $rate): array => [
                'id' => (int) $rate->id,
                'name' => sprintf('%s (1 → %s)', $rate->title, $rate->summa),
            ]);

        $groupRates = GroupParserExchange::query()
            ->with(['parser_exchange_enabled' => fn ($q) => $q->select('id', 'name', 'summa', 'id_group', 'status')])
            ->where('status', 1)
            ->get()
            ->map(static fn ($group): array => [
                'id' => (int) $group->id,
                'name' => sprintf('%s (%d)', $group->name, $group->parser_exchange_enabled->count()),
                'rates' => $group->parser_exchange_enabled->map(static fn ($rate) => [
                    'id' => (int) $rate->id,
                    'name' => sprintf('%s (1 → %s)', $rate->name, $rate->summa),
                ]),
            ])
            ->filter(static fn ($group) => $group['rates']->isNotEmpty())
            ->values();

        return response()->json([
            'id' => (int) $item->id,
            'attributes' => [
                'status' => (bool) $item->status,

                'id_currency_in' => (int) $item->id_currency_in,
                'id_currency_out' => (int) $item->id_currency_out,
                'city_id' => (int) $item->city_id,

                'position_num' => (string) $item->position_num,
                'step' => (string) $item->step,

                'rate_mode' => (string) ($item->rate_mode ?: $config->rateMode()),
                'top_n' => (int) ($item->top_n ?: $config->topN()),

                'blacklist_ids' => ValueNormalizer::csvToIntArray($item->blacklist_ids),
                'whitelist_ids' => ValueNormalizer::csvToIntArray($item->whitelist_ids),

                'min_reserve' => (string) $item->min_reserve,
                'max_reserve' => (string) $item->max_reserve,

                'min_sum' => (string) $item->min_sum,
                'max_sum' => (string) $item->max_sum,

                'reset_course' => (bool) $item->reset_course,
                'standard_course' => (string) $item->standard_course,

                'formula_value' => $item->formula_value ? html_entity_decode((string) $item->formula_value) : null,

                'min_sum_new_default_parser' => (int) $item->min_sum_new_default_parser,
                'min_sum_new_default_parser_fee' => (string) $item->min_sum_new_default_parser_fee,
                'min_sum_new_formula_parser' => (int) $item->min_sum_new_formula_parser,
                'min_sum_new_formula_parser_fee' => (string) $item->min_sum_new_formula_parser_fee,
            ],
            'currencies' => $currencies,
            'cities' => $cities,
            'exchangers' => $exchangers,
            'parser_formula' => $parserFormula,
            'group_rates' => $groupRates,
            'config' => [
                'is_enable' => $config->isEnabled(),
                'defaults' => [
                    'rate_mode' => $config->rateMode(),
                    'top_n' => $config->topN(),
                ],
            ],
        ]);
    }

    /**
     * Обновление пары.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if ($this->isDemoMode()) {
            return $this->demoForbidden();
        }

        /** @var BestChangeConfig $config */
        $config = app(BestChangeConfig::class);

        $item = $request->boolean('is_direction')
            ? BestChangeDirection::query()->where('id_direction_exchange', $id)->firstOrFail()
            : BestChangeDirection::query()->findOrFail($id);

        // ------------------------------------------------------------
        // Быстрые частичные обновления (из таблицы)
        // ------------------------------------------------------------
        if ($request->boolean('is_update')) {
            if ($request->boolean('change_position')) {
                $item->update([
                    'position_num' => ValueNormalizer::normalizePosition($request->input('position_num', '0')),
                ]);

                return response()->json([
                    'status'  => 0,
                    'message' => 'Позиция ' . (string) $item->position_num . ' установлена',
                ]);
            }

            if ($request->boolean('change_step')) {
                $item->update([
                    'step' => ValueNormalizer::normalizeStep($request->input('step', '0')),
                ]);

                return response()->json([
                    'status'  => 0,
                    'message' => 'Шаг ' . (string) $item->step . ' установлен',
                ]);
            }

            if ($request->boolean('change_direction')) {
                $validator = Validator::make($request->all(), [
                    'id_currency_in'  => ['required', 'integer', 'min:1'],
                    'id_currency_out' => ['required', 'integer', 'min:1'],
                ]);

                if ($validator->fails()) {
                    return response()->json(['status' => 1, 'message' => $validator->messages()->first()]);
                }

                $rates = BestChangeFacade::rates();
                $currencies = $rates->currencies();

                $currencyInId  = (int) $request->integer('id_currency_in');
                $currencyOutId = (int) $request->integer('id_currency_out');

                $currencyIn  = $currencies[$currencyInId] ?? ['name' => 'N/A', 'default_code' => 'N/A', 'code' => 'N/A'];
                $currencyOut = $currencies[$currencyOutId] ?? ['name' => 'N/A', 'default_code' => 'N/A', 'code' => 'N/A'];

                [$name, $code] = $this->buildNameAndCode($currencyIn, $currencyOut, (int) $item->id);

                $item->update([
                    'name' => $name,
                    'code' => $code,
                    'id_currency_in' => $currencyInId,
                    'id_currency_out' => $currencyOutId,
                    'exchange_in' => (string) ($currencyIn['default_code'] ?? $currencyIn['code'] ?? 'N/A'),
                    'exchange_out' => (string) ($currencyOut['default_code'] ?? $currencyOut['code'] ?? 'N/A'),
                ]);

                return response()->json([
                    'status'  => 0,
                    'message' => 'Данные обновлены',
                ]);
            }

            if ($request->boolean('change_top_n')) {
                $topN = (int) $request->input('top_n', 0);
                $topN = max(1, min(100, $topN));
                $item->update(['top_n' => $topN]);

                return response()->json([
                    'status'  => 0,
                    'message' => "Количество строк {$item->top_n} установлено",
                ]);
            }

            if ($request->boolean('change_rate_mode')) {
                $rateMode = (string) $request->input('rate_mode', '');
                $allowedModes = ['position', 'median_top_n', 'weighted_avg_top_n'];

                if (!in_array($rateMode, $allowedModes, true)) {
                    return response()->json([
                        'status'  => 1,
                        'message' => __('Некорректный режим выбора'),
                    ]);
                }

                $item->update(['rate_mode' => $rateMode]);

                return response()->json([
                    'status'  => 0,
                    'message' => 'Режим выбора курса обновлён',
                ]);
            }

            return response()->json([
                'status'  => 1,
                'message' => 'Не указан тип частичного обновления',
            ]);
        }

        // ------------------------------------------------------------
        // Нормализация входа ДО валидатора (важно для numeric-string)
        // ------------------------------------------------------------
        // Эти поля храним строками, но UI может прислать number или пустую строку.
        // Чтобы `nullable|string` проходило:
        // - number -> string
        // - ''/whitespace -> null
        $normalizeNumericStringNullable = static function ($value): ?string {
            if ($value === null) {
                return null;
            }
            $value = trim((string) $value);
            return $value === '' ? null : $value;
        };

        $request->merge([
            'min_reserve'    => $normalizeNumericStringNullable($request->input('min_reserve')),
            'max_reserve'    => $normalizeNumericStringNullable($request->input('max_reserve')),
            'min_sum'        => $normalizeNumericStringNullable($request->input('min_sum')),
            'max_sum'        => $normalizeNumericStringNullable($request->input('max_sum')),
            'minmax_sum_fee' => $normalizeNumericStringNullable($request->input('minmax_sum_fee')),
            'standard_course' => $normalizeNumericStringNullable($request->input('standard_course')),
        ]);

        // ------------------------------------------------------------
        // Валидация (строго и прозрачно)
        // ------------------------------------------------------------
        $validator = Validator::make($request->all(), [
            'status' => ['nullable', 'integer', 'in:0,1'],

            'id_currency_in'  => ['required', 'integer', 'min:1'],
            'id_currency_out' => ['required', 'integer', 'min:1'],
            'city_id'         => ['nullable', 'integer', 'min:0'],

            'position_num' => ['nullable', 'string', 'max:191', 'regex:/^\s*\d+\s*(?:-\s*\d+\s*)?$/'],
            'step'         => ['nullable', 'string', 'max:191'],

            // numeric-string: допускаем null, но не блокируем number (мы уже привели к string|null)
            'min_reserve'    => ['nullable', 'string', 'max:191'],
            'max_reserve'    => ['nullable', 'string', 'max:191'],
            'min_sum'        => ['nullable', 'string', 'max:191'],
            'max_sum'        => ['nullable', 'string', 'max:191'],
            'minmax_sum_fee' => ['nullable', 'string', 'max:191'],

            'blacklist_ids'   => ['nullable', 'array'],
            'blacklist_ids.*' => ['integer'],
            'whitelist_ids'   => ['nullable', 'array'],
            'whitelist_ids.*' => ['integer'],

            'rate_mode' => ['nullable', 'string', 'in:position,median_top_n,weighted_avg_top_n'],
            'top_n'     => ['nullable', 'integer', 'min:1', 'max:100'],

            'reset_course'   => ['nullable', 'integer', 'in:0,1'],
            'standard_course'=> ['nullable', 'string', 'max:191'],

            'formula_value' => ['nullable', 'string'],

            'min_sum_new_default_parser'      => ['nullable', 'integer', 'min:0'],
            'min_sum_new_default_parser_fee'  => ['nullable', 'string', 'max:191'],
            'min_sum_new_formula_parser'      => ['nullable', 'integer', 'min:0'],
            'min_sum_new_formula_parser_fee'  => ['nullable', 'string', 'max:191'],
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 1, 'message' => $validator->messages()->first()]);
        }

        // ------------------------------------------------------------
        // Справочники / вычисления имени и code
        // ------------------------------------------------------------
        $rates = BestChangeFacade::rates();
        $currencies = $rates->currencies();

        $currencyInId  = (int) $request->integer('id_currency_in');
        $currencyOutId = (int) $request->integer('id_currency_out');

        $currencyIn  = $currencies[$currencyInId] ?? ['name' => 'N/A', 'default_code' => 'N/A', 'code' => 'N/A'];
        $currencyOut = $currencies[$currencyOutId] ?? ['name' => 'N/A', 'default_code' => 'N/A', 'code' => 'N/A'];

        [$name, $code] = $this->buildNameAndCode($currencyIn, $currencyOut, (int) $item->id);

        $rateMode = (string) ($request->input('rate_mode') ?: $item->rate_mode ?: $config->rateMode());
        $topN = (int) ($request->input('top_n') ?: $item->top_n ?: $config->topN());
        $topN = max(1, min(100, $topN));

        // Сохранение numeric-string в БД: null/'' -> '0', иначе строка как есть
        $numStr = static function ($value, string $default = '0'): string {
            if ($value === null) {
                return $default;
            }
            $value = trim((string) $value);
            return $value === '' ? $default : $value;
        };

        // ------------------------------------------------------------
        // Update
        // ------------------------------------------------------------
        $item->update([
            'name' => $name,
            'code' => $code,

            'status' => (int) $request->input('status', $item->status),
            'id_currency_in' => $currencyInId,
            'id_currency_out' => $currencyOutId,
            'city_id' => (int) $request->input('city_id', 0),

            'exchange_in' => (string) ($currencyIn['default_code'] ?? $currencyIn['code'] ?? 'N/A'),
            'exchange_out' => (string) ($currencyOut['default_code'] ?? $currencyOut['code'] ?? 'N/A'),

            'position_num' => ValueNormalizer::normalizePosition($request->input('position_num', '0')),
            'step' => ValueNormalizer::normalizeStep($request->input('step', '0')),

            'min_reserve' => $numStr($request->input('min_reserve')),
            'max_reserve' => $numStr($request->input('max_reserve')),

            'min_sum' => $numStr($request->input('min_sum')),
            'max_sum' => $numStr($request->input('max_sum')),
            'minmax_sum_fee' => $numStr($request->input('minmax_sum_fee')),

            'reset_course' => (int) $request->input('reset_course', (int) $item->reset_course),
            'standard_course' => $numStr($request->input('standard_course'), $numStr($item->standard_course, '0')),

            'formula_value' => $request->has('formula_value')
                ? (string) $request->input('formula_value')
                : $item->formula_value,

            'min_sum_new_default_parser' => (int) $request->input('min_sum_new_default_parser', (int) $item->min_sum_new_default_parser),
            'min_sum_new_default_parser_fee' => (string) $request->input('min_sum_new_default_parser_fee', (string) $item->min_sum_new_default_parser_fee),
            'min_sum_new_formula_parser' => (int) $request->input('min_sum_new_formula_parser', (int) $item->min_sum_new_formula_parser),
            'min_sum_new_formula_parser_fee' => (string) $request->input('min_sum_new_formula_parser_fee', (string) $item->min_sum_new_formula_parser_fee),

            'blacklist_ids' => ValueNormalizer::intArrayToCsv($request->input('blacklist_ids', [])),
            'whitelist_ids' => ValueNormalizer::intArrayToCsv($request->input('whitelist_ids', [])),

            'rate_mode' => $rateMode,
            'top_n' => $topN,
        ]);

        return response()->json([
            'status'  => 0,
            'message' => "Пара {$item->name} успешно обновлена",
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        if ($this->isDemoMode()) {
            return $this->demoForbidden();
        }

        $item = BestChangeDirection::query()->with('direction_exchange')->find($id);

        if (!$item) {
            return response()->json([
                'status' => 1,
                'message' => __('Запись не найдена или уже удалена.'),
            ]);
        }

        $techName = $item->direction_exchange?->tech_name ?? __('Направление');

        try {
            $item->delete();

            return response()->json([
                'status' => 0,
                'message' => __("Направление «:name» успешно удалено.", ['name' => $techName]),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 1,
                'message' => __("Ошибка при удалении направления: :message", ['message' => $e->getMessage()]),
            ]);
        }
    }

    public function logData(Request $request): JsonResponse
    {
        if ($request->boolean('clear_log')) {
            if ($this->isDemoMode()) {
                return response()->json(['status' => 1, 'message' => 'В demo версии данная функция недоступна']);
            }

            BestchangeParserError::query()->delete();
            BestchangeParserError::truncate();

            return response()->json(['status' => 0, 'message' => 'Лог успешно очищен']);
        }

        $logs = BestchangeParserError::query()->orderByDesc('id')->paginate(20);

        return response()->json([
            'items' => new BestChangeLogResources($logs),
        ]);
    }

    /**
     * Рейтинг обменников по конкретной паре.
     *
     * Важно:
     * - Таблица строится по тем же правилам, что и updateRates:
     *   filter -> deterministic sort -> select
     * - Это даёт совпадение 1-в-1 между "обновлением" и "рейтингом".
     */
    public function rating(int $id): JsonResponse
    {
        /** @var BestChangeConfig $config */
        $config = app(BestChangeConfig::class);

        $pair = BestChangeDirection::query()
            ->whereHas('direction_exchange', fn ($q) => $q->where('status', 1))
            ->whereKey($id)
            ->first();

        if (!$pair || (int)$pair->id_currency_in <= 0 || (int)$pair->id_currency_out <= 0) {
            return response()->json([]);
        }

        // 1) Получаем сырые строки с API
        $pairs = [
            $pair->city_id > 0
                ? [(int)$pair->id_currency_in, (int)$pair->id_currency_out, (int)$pair->city_id]
                : [(int)$pair->id_currency_in, (int)$pair->id_currency_out],
        ];

        $ratesConn = BestChangeFacade::rates();
        $ratesMap  = $ratesConn->rates($pairs);

        $pairKey  = array_key_first($ratesMap) ?: '';
        $rawRows  = $pairKey !== '' ? (array)($ratesMap[$pairKey] ?? []) : [];

        if ($pairKey === '' || $rawRows === []) {
            return response()->json([
                'title'   => (string)($pair->name ?? ''),
                'courses' => [],
            ]);
        }

        // 2) Политика (та же, что и в updateRates)
        /** @var \iEXPackages\BestChange\Services\RateSelectionPolicyResolver $policyResolver */
        $policyResolver = app(\iEXPackages\BestChange\Services\RateSelectionPolicyResolver::class);
        $policy = $policyResolver->resolve($pair, $config);

        $defaultPosition = max(1, (int)$config->position());
        $globalBlacklist = $config->blacklist();

        /** @var \iEXPackages\BestChange\Services\BestChangeMarketPipeline $pipeline */
        $pipeline = app(\iEXPackages\BestChange\Services\BestChangeMarketPipeline::class);

        // 3) Единый пайплайн рынка (1-в-1 как update)
        $market = $pipeline->prepare(
            direction: $pair,
            rawRows: $rawRows,
            policy: $policy,
            defaultPosition: $defaultPosition,
            globalBlacklist: $globalBlacklist,
            pairKey: $pairKey,
            withPresence: true,
            presenceTtlSeconds: 60,
        );

        $sortedRows = $market->sortedRows; // <-- ключевое отличие: НЕ rawRows

        if ($sortedRows === []) {
            return response()->json([
                'title'   => (string)($pair->name ?? ''),
                'courses' => [],
                'pairKey' => $pairKey,
                'policy'  => [
                    'typeField' => $policy->typeField,
                    'sortOrder' => $policy->sortOrder,
                    'mode'      => $policy->mode,
                    'topN'      => $policy->topN,
                ],
            ]);
        }

        // 4) Справочник обменников
        $exchangers = $ratesConn->exchangers(); // keyBy id

        // 5) Строим таблицу рейтинга по ОТСОРТИРОВАННЫМ строкам
        $table = [];
        foreach ($sortedRows as $index => $row) {
            if (!is_array($row)) continue;

            $number = $index + 1;

            $posRaw = trim((string)($pair->position_num ?? ''));
            $isMyPosition = ($posRaw !== '' && ctype_digit($posRaw) && (int)$posRaw === $number);

            $rateRaw = $row['rate'] ?? null;
            if (!is_scalar($rateRaw)) continue;

            $rate = (float)$rateRaw;
            if ($rate <= 0) continue;

            // Отображение "amountFrom -> amountTo" (как у тебя было)
            if ($rate >= 1) {
                $amountFrom = $rate;
                $amountTo = 1.0;
            } else {
                $amountFrom = 1.0;
                $amountTo = 1.0 / $rate;
            }

            $changerId = (int)($row['changer'] ?? 0);

            $table[] = [
                'number'        => $number,
                'changerName'   => (string)($exchangers[$changerId]['name'] ?? ''),
                'amountFrom'    => $amountFrom,
                'amountTo'      => $amountTo,
                'delta'         => null,
                'reserve'       => $row['reserve'] ?? null,
                'isMyPosition'  => $isMyPosition,
                'rateToOverbid' => null,

                // полезно оставить сырьё для UI (не обязательно)
                'rate'     => $row['rate'] ?? null,
                'rankrate' => $row['rankrate'] ?? null,
                'marks'    => $row['marks'] ?? [],
            ];
        }

        if ($table === []) {
            return response()->json([
                'title'   => (string)($pair->name ?? ''),
                'courses' => [],
                'pairKey' => $pairKey,
            ]);
        }

        // 6) Подсказки (как раньше)
        $baseField = ($table[0]['amountFrom'] == 1.0) ? 'amountTo' : 'amountFrom';

        $minStep = 0.00000001;
        $maxSafeCourse = null;
        $recommendedStep = null;

        foreach ($table as $i => &$item) {
            if (is_numeric($item[$baseField]) && ($maxSafeCourse === null || $item[$baseField] > $maxSafeCourse)) {
                $maxSafeCourse = $item[$baseField];
            }

            if ($i === 0) {
                $item['delta'] = '-';
                $item['rateToOverbid'] = null;
                continue;
            }

            $item['delta'] = $item[$baseField] - $table[$i - 1][$baseField];

            $needToGet = $table[$i - 1][$baseField] + $minStep;
            $item['rateToOverbid'] = $baseField === 'amountTo'
                ? round(1 / $needToGet, 12)
                : round($needToGet, 12);

            $diff = $table[$i - 1][$baseField] - $item[$baseField];
            if ($diff > 0 && ($recommendedStep === null || $diff < $recommendedStep)) {
                $recommendedStep = $diff;
            }
        }
        unset($item);

        foreach ($table as &$row) {
            $row['amountFrom'] = is_numeric($row['amountFrom']) ? round((float)$row['amountFrom'], 8) : $row['amountFrom'];
            $row['amountTo']   = is_numeric($row['amountTo']) ? round((float)$row['amountTo'], 8) : $row['amountTo'];
            $row['delta']      = is_numeric($row['delta']) ? round((float)$row['delta'], 8) : $row['delta'];
        }
        unset($row);

        $bestchangeBaseValue = null;
        $pos = (int)($pair->position_num ?: 0);
        if ($pos >= 1 && isset($table[$pos - 1])) {
            $bestchangeBaseValue = $table[$pos - 1][$baseField];
        }

        $rateValue = (float)($pair->rate_value ?? 0);
        $myBaseValue = null;

        if ($rateValue > 0) {
            $myBaseValue = ($table[0]['amountFrom'] == 1.0) ? $rateValue : (1.0 / $rateValue);
        }

        $rateDiff = null;
        if ($bestchangeBaseValue !== null && $myBaseValue !== null) {
            $rateDiff = $myBaseValue - $bestchangeBaseValue;
        }

        return response()->json([
            'title'              => (string)($pair->name ?? ''),
            'courses'            => $table,
            'recommendedStep'    => $recommendedStep !== null ? round($recommendedStep, 8) : '-',
            'maxSafeCourse'      => $maxSafeCourse !== null ? round((float)$maxSafeCourse, 8) : '-',
            'myBaseValue'        => $myBaseValue !== null ? round((float)$myBaseValue, 8) : null,
            'bestchangeBaseValue'=> $bestchangeBaseValue !== null ? round((float)$bestchangeBaseValue, 8) : null,
            'rateDiff'           => $rateDiff !== null ? round((float)$rateDiff, 8) : null,
            'baseField'          => $baseField,
            'pairKey'            => $pairKey,

            // полезно для UI/отладки (почему так отсортировалось)
            'policy' => [
                'typeField' => $policy->typeField,
                'sortOrder' => $policy->sortOrder,
                'mode'      => $policy->mode,
                'topN'      => $policy->topN,
            ],
            'presence' => $market->presence,
            'rejected' => $market->rejectCounters,
        ]);
    }

    /**
     * Переключить “избранное”.
     */
    public function toggleFavorite(int $id): JsonResponse
    {
        if ($this->isDemoMode()) {
            return $this->demoForbidden();
        }

        $direction = BestChangeDirection::query()->findOrFail($id);
        $direction->update(['is_favorite' => !$direction->is_favorite]);

        return response()->json([
            'status' => 0,
            'message' => $direction->is_favorite ? __('Курс добавлен в избранное') : __('Курс удалён из избранного'),
            'is_favorite' => (bool) $direction->is_favorite,
        ]);
    }

    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        if ($this->isDemoMode()) {
            return $this->demoForbidden();
        }

        $validator = Validator::make($request->all(), [
            'ids'    => ['required', 'array', 'min:1'],
            'ids.*'  => ['integer', 'exists:bestchange_directions,id'],
            'status' => ['required', 'integer', 'in:0,1'],
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 1, 'message' => $validator->messages()->first()]);
        }

        $ids = (array) $request->input('ids', []);
        $status = (int) $request->input('status');

        BestChangeDirection::query()->whereIn('id', $ids)->update(['status' => $status]);

        return response()->json([
            'status'  => 0,
            'message' => $status === 1 ? 'Курсы успешно включены' : 'Курсы успешно отключены',
        ]);
    }

    // ---------------------------------------------------------------------
    // Helpers (без дублей нормализации)
    // ---------------------------------------------------------------------

    private function responseDirectionExchangeList(): JsonResponse
    {
        $directionExchange = DirectionExchange::query()
            ->where('status', 1)
            ->select('id', 'tech_name')
            ->orderBy('tech_name')
            ->get();

        return response()->json([
            'directionExchange' => $directionExchange,
        ]);
    }

    private function responseCurrenciesForFilters(BestChangeConfig $config): JsonResponse
    {
        if (!$config->isEnabled() || $config->apiKey() === '') {
            return response()->json([
                'currencies' => [],
                'statusParser' => $config->isEnabled(),
            ]);
        }

        $allowedIds = $config->currencies();

        try {
            $currenciesMap = BestChangeFacade::rates()->currencies(false);

            $currencies = collect($currenciesMap)
                ->whereIn('id', $allowedIds)
                ->map(static fn ($item) => [
                    'id'    => (int) ($item['id'] ?? 0),
                    'value' => (string) ($item['name'] ?? ''),
                ])
                ->values()
                ->all();

        } catch (\Throwable $e) {
            Log::warning('BestChange loadingFilters: справочник валют недоступен', [
                'error' => $e->getMessage(),
            ]);
            $currencies = [];
        }

        return response()->json([
            'currencies'   => $currencies,
            'statusParser' => $config->isEnabled(),
        ]);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveSorting(Request $request): array
    {
        $sortMap = [
            'id'         => 'id',
            'name'       => 'name',
            'position'   => 'position_num',
            'status'     => 'status',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];

        $requested = (string) $request->input('sorting_order', 'id');
        $column = $sortMap[$requested] ?? 'id';

        $direction = strtolower((string) $request->input('sorting_type', 'desc'));
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';

        return [$column, $direction];
    }

    /**
     * Сформировать имя пары и код источника из выбранных валют.
     *
     * @param array<string,mixed> $currencyIn
     * @param array<string,mixed> $currencyOut
     * @param int $pairId
     * @return array{0:string,1:?string}
     */
    private function buildNameAndCode(array $currencyIn, array $currencyOut, int $pairId): array
    {
        $name = sprintf('%s -> %s', (string)($currencyIn['name'] ?? 'N/A'), (string)($currencyOut['name'] ?? 'N/A'));

        $code = null;
        if (preg_match_all('/\[(.*?)\]/', $name, $matches) && !empty($matches[1])) {
            $code = sprintf('[bestchange_%s_%d]', Str::lower(implode('_', $matches[1])), $pairId);
        }

        return [$name, $code];
    }

    private function isDemoMode(): bool
    {
        return (bool) config('iexexchanger.is_reading_mode');
    }

    private function demoForbidden(): JsonResponse
    {
        return response()->json([
            'status' => 1,
            'message' => 'В demo версии данная функция недоступна',
        ]);
    }
}
