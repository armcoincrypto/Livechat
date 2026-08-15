<?php
declare(strict_types=1);

namespace iEXPackages\Courses\Concerns;

use App\Models\Currency;
use App\Models\CurrencyCategory;
use App\Models\DirectionExchange;
use App\Models\FilterCurrency;
use App\Models\Reserve;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Trait CallsCompilers
 *
 * Набор компиляторов (builder-friendly) для подготовки данных курсов/валют/фильтров/резервов.
 *
 * Ключевые принципы:
 * - Без N+1: тяжёлые зависимости подгружаем батчами (eager loading / батч-запросы / чанки).
 * - Точность денег: арифметика через Brick\Math\BigDecimal, наружу (в API) отдаём строку без экспоненты.
 * - Новая система связанных резервов:
 *   - Корневой резерв определяется ТОЛЬКО по closure table `reserve_closures` (ancestor/descendant/depth),
 *     без опоры на `reserve_links`.
 *   - Эффективный резерв валюты берётся по корню цепочки.
 *
 * @phpstan-type DirectionReserveRow array{from_id:int, to_id:int, reserve:string}
 * @phpstan-type LabelRow array{id:int, title:string, text_color:string|null, bg_color:string|null}
 * @phpstan-type CurrencyCategoryRow array{id:int, title:string, currency_ids:array<int,int>}
 */
trait CallsCompilers
{
    /**
     * Кеш резервов направлений в рамках одного builder().
     *
     * @var array<int, DirectionReserveRow>
     */
    private array $directionReservesAll = [];

    /**
     * Флаг, что резервы направлений уже были рассчитаны.
     */
    private bool $directionReservesLoaded = false;

    /**
     * Получить резервы направлений.
     *
     * Поведение:
     * - Первый вызов загружает данные из БД чанками.
     * - Повторные вызовы возвращают уже подготовленный массив (без повторной нагрузки).
     *
     * Примечание:
     * - Выбираем только нужные поля.
     * - Нормализуем null -> "0".
     *
     * @return array<int, DirectionReserveRow>
     */
    public function getDirectionReserves(): array
    {
        if ($this->directionReservesLoaded) {
            return $this->directionReservesAll;
        }

        $this->directionReservesLoaded = true;
        $this->directionReservesAll = [];

        DirectionExchange::active()
            ->where('type_reserve', 1)
            ->select(['id', 'id_currency1', 'id_currency2', 'direction_reserve'])
            ->chunkById(500, function (Collection $items): void {
                foreach ($items as $item) {
                    $this->directionReservesAll[] = [
                        'from_id' => (int) $item->id_currency1,
                        'to_id' => (int) $item->id_currency2,
                        'reserve' => (string) ($item->direction_reserve ?? '0'),
                    ];
                }
            });

        return $this->directionReservesAll;
    }

    /**
     * Список резервов по валютам (эффективный резерв по корню цепочки).
     *
     * Гарантии:
     * - Возвращаем значение ДЛЯ КАЖДОЙ активной валюты (если резерва нет — 0).
     * - Корень цепочки определяется по `reserve_closures` (ancestor на максимальной глубине).
     * - Доступный резерв = (summa - black_amount), отрицательные/нулевые -> 0.
     * - Ограничение max_display_reserve учитывается.
     *
     * Важно по точности:
     * - Внутри считаем BigDecimal.
     * - На выходе возвращаем float|int (как в текущем контракте).
     *   Если нужна абсолютная точность в API (до 18 знаков) — меняй контракт на string и убирай float.
     *
     * @return array<int, float|int>
     */
    protected function compilerReserve(): array
    {
        $currencies = Currency::query()
            ->select(['id', 'max_display_reserve', 'number_format'])
            ->where('status', 0)
            ->get();

        if ($currencies->isEmpty()) {
            return [];
        }

        // 1) Гарантируем ключи по всем валютам
        $out = [];
        foreach ($currencies as $currency) {
            $out[(int) $currency->id] = 0;
        }

        $currencyIds = array_keys($out);

        /** @var Collection<int, Reserve> $reserves */
        $reserves = Reserve::query()
            ->select(['id', 'id_currency', 'summa', 'black_amount'])
            ->whereIn('id_currency', $currencyIds)
            ->get();

        if ($reserves->isEmpty()) {
            return $out;
        }

        $reserveByCurrency = $reserves->keyBy('id_currency');

        $reserveIds = $reserves
            ->pluck('id')
            ->map(static fn ($v) => (int) $v)
            ->values()
            ->all();

        // 2) reserve_id -> root_id (через closure table)
        $rootByReserveId = $this->resolveRootReserveIds($reserveIds);

        // 3) Подгружаем корневые резервы одним запросом
        $rootIds = [];
        foreach ($reserveIds as $rid) {
            $rootIds[] = (int) ($rootByReserveId[$rid] ?? $rid);
        }
        $rootIds = array_values(array_unique($rootIds));

        $rootReserves = Reserve::query()
            ->select(['id', 'summa', 'black_amount'])
            ->whereIn('id', $rootIds)
            ->get()
            ->keyBy('id');

        // 4) Собираем результат по валютам
        foreach ($currencies as $currency) {
            $currencyId = (int) $currency->id;

            /** @var Reserve|null $reserve */
            $reserve = $reserveByCurrency->get($currencyId);
            if ($reserve === null) {
                $out[$currencyId] = 0;
                continue;
            }

            $rid = (int) $reserve->id;
            $rootId = (int) ($rootByReserveId[$rid] ?? $rid);

            /** @var Reserve $effective */
            $effective = $rootReserves->get($rootId) ?? $reserve;

            // ВАЖНО: текущий контракт float|int, поэтому тут приводим к float.
            // Если хочешь точность без потерь — поменяй контракт на string и используй reserveAmountString().
            $out[$currencyId] = (float) $this->reserveAmountString($effective, $currency);
        }

        return $out;
    }

    /**
     * Получение списка фильтров.
     *
     * @return array<int, array{id:int, name:string, icon:string}>
     */
    protected function compileFilters(): array
    {
        return FilterCurrency::query()
            ->orderBy('sorting')
            ->get(['id', 'name', 'icon'])
            ->map(static function ($item): array {
                $icon = (string) ($item->icon ?? '');

                return [
                    'id' => (int) $item->id,
                    'name' => (string) $item->name,
                    'icon' => $icon !== '' ? '/storage/filters/' . $icon : '',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Получить категории валют.
     *
     * @return array<int, CurrencyCategoryRow>
     */
    protected function compilerCurrencyCategories(): array
    {
        $categories = CurrencyCategory::query()
            ->where('is_active', 1)
            ->orderBy('position')
            ->with(['currencies' => function ($q): void {
                $q->select('currencies.id')
                    ->wherePivot('is_active', 1)
                    ->orderBy('currency_category_items.position');
            }])
            ->get();

        return $categories
            ->map(static function (CurrencyCategory $category): array {
                return [
                    'id' => (int) $category->id,
                    'title' => (string) $category->title, // spatie/array: отдаём как строку (текущая локаль)
                    'currency_ids' => $category->currencies
                        ->pluck('id')
                        ->map(static fn ($v) => (int) $v)
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Преобразовать коллекцию меток валют в массив для API.
     *
     * Важно:
     * - У меток есть pivot: side/priority/is_active.
     * - Здесь мы уже предполагаем, что в relation labelsIn/labelsOut отфильтровано is_active=1 и отсортировано.
     *
     * @param Collection<int, \App\Models\CurrencyLabel> $labels
     * @return array<int, LabelRow>
     */
    private function mapLabels(Collection $labels): array
    {
        $norm = static fn (?string $c): ?string => $c ? ('#' . ltrim($c, '#')) : null;

        return $labels
            ->map(static fn ($l): array => [
                'id' => (int) $l->id,
                'title' => (string) $l->title,
                'text_color' => $norm($l->text_color),
                'bg_color' => $norm($l->bg_color),
            ])
            ->values()
            ->all();
    }

    /**
     * Список доступных валют (для initial/курсов).
     *
     * Требования:
     * - Без N+1 по резервам/корням: корни вычисляем одним батчем по reserve_ids.
     * - Эффективный резерв:
     *   - Берём корневой Reserve (по closure table).
     *   - Считаем доступный резерв = (summa - black_amount), <=0 -> "0".
     *   - Учитываем max_display_reserve конкретной валюты.
     *
     * Важно:
     * - В reserve.amount отдаём строку (без экспоненты), чтобы не ловить 1E+18.
     * - is_linked корректно определяется через сравнение reserve_id и root_reserve_id.
     *
     * Производительность:
     * - Убраны тяжёлые relations, которые тут не используются (commands/fields и т.п.).
     * - Для каждой relation указан минимальный select (где это критично).
     *
     * @return array<int, array<string, mixed>>
     */
    public function compilerCurrency(): array
    {
        /** @var Collection<int, Currency> $currencies */
        $currencies = Currency::query()
            ->with([
                'payment:id,name,logo',
                'code_currency:id,name',
                'currency_group_network:id,title,display_type,icon',
                'reserve:id,id_currency,summa,black_amount',
                'filters:id', // нам нужны только id
                // Метки: важно убрать неактивные и отсортировать (pivot.priority)
                'labelsIn' => function ($q): void {
                    $q->select(['currencies_labels.id', 'currencies_labels.title', 'currencies_labels.text_color', 'currencies_labels.bg_color'])
                        ->wherePivot('is_active', 1)
                        ->orderByPivot('priority');
                },
                'labelsOut' => function ($q): void {
                    $q->select(['currencies_labels.id', 'currencies_labels.title', 'currencies_labels.text_color', 'currencies_labels.bg_color'])
                        ->wherePivot('is_active', 1)
                        ->orderByPivot('priority');
                },
            ])
            ->where('status', 0)
            ->get();

        if ($currencies->isEmpty()) {
            return [];
        }

        // Public paymentSystems must not include zero-direction orphans.
        // builder() runs configureDirectionRates() before compilerCurrency(), so
        // $this->directionRatesAll is the eligible public graph. Keep admin/DB rows intact.
        $eligiblePublicIds = [];
        foreach ($this->directionRatesAll as $fromId => $tos) {
            $fromId = (int) $fromId;
            $eligiblePublicIds[$fromId] = true;
            if (!is_array($tos)) {
                continue;
            }
            foreach ($tos as $toId) {
                $eligiblePublicIds[(int) $toId] = true;
            }
        }

        if ($eligiblePublicIds !== []) {
            $currencies = $currencies->filter(
                static fn ($item) => isset($eligiblePublicIds[(int) $item->id])
            )->values();
        }

        if ($currencies->isEmpty()) {
            return [];
        }

        // Список reserve_id, которые реально есть у валют
        $reserveIds = $currencies
            ->pluck('reserve')
            ->filter()
            ->pluck('id')
            ->map(static fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        $rootByReserveId = [];
        $rootReserves = collect();

        if ($reserveIds !== []) {
            $rootByReserveId = $this->resolveRootReserveIds($reserveIds);

            $rootIds = [];
            foreach ($reserveIds as $rid) {
                $rootIds[] = (int) ($rootByReserveId[$rid] ?? $rid);
            }
            $rootIds = array_values(array_unique($rootIds));

            $rootReserves = Reserve::query()
                ->select(['id', 'summa', 'black_amount'])
                ->whereIn('id', $rootIds)
                ->get()
                ->keyBy('id');
        }

        $data = [];

        foreach ($currencies as $item) {
            $id = (int) $item->id;

            $paymentName = (string) ($item->payment?->name ?? '');
            $codeName = (string) ($item->code_currency?->name ?? '');
            // tech_currency_name хранится как translatable (JSON). Нормализуем в строку текущей локали.
            $techName = $item->getTranslation('tech_currency_name', app()->getLocale(), false);
            if (is_array($techName)) {
                $techName = $techName[app()->getLocale()] ?? reset($techName) ?? '';
            }
            $techName = (string) ($techName ?? '');

            // Avoid duplicated settlement tokens (e.g. payment "CASH AMD" + code "AMD").
            if ((int) ($item->visible_code_currency ?? 0) === 1 && $codeName !== '') {
                $paymentTrim = trim($paymentName);
                $codeTrim = trim($codeName);
                $alreadyHasCode = $codeTrim !== ''
                    && preg_match('/(?:^|[\s·\-])' . preg_quote($codeTrim, '/') . '$/iu', $paymentTrim) === 1;
                $displayName = $alreadyHasCode
                    ? $paymentTrim
                    : trim($paymentTrim . ' ' . $codeTrim);
            } else {
                $displayName = trim($paymentName);
            }

            $reserveAmount = '0';
            $rootReserveId = 0;
            $reserveIsLinked = false;

            if ($item->reserve) {
                $rid = (int) $item->reserve->id;
                $rootReserveId = (int) ($rootByReserveId[$rid] ?? $rid);
                $reserveIsLinked = ($rootReserveId !== $rid);

                /** @var Reserve $effective */
                $effective = $rootReserves->get($rootReserveId) ?? $item->reserve;

                $reserveAmount = $this->reserveAmountString($effective, $item);
            }

            $row = [
                'id' => $id,
                'attributes' => [
                    'name' => $displayName,

                    'tech_name' => $techName,

                    'with_currency_code' => (int) ($item->visible_code_currency ?? 0),
                    'letter_cod' => (string) ($item->designation_xml ?? ''),
                    'payment_system' => $paymentName,
                    'currency_iso_code' => $codeName,

                    'default_value' => $item->convert_by,
                    'amount_decimal' => (int) ($item->number_format ?? 0),
                    'short_code' => (string) ($item->small_code ?? ''),

                    'filter_ids' => $item->filters
                        ? $item->filters->pluck('id')->map(static fn ($v) => (int) $v)->values()->all()
                        : [],

                    'icon_url_path' => $item->payment?->logo
                        ? '/storage/payment_systems/' . $item->payment->logo
                        : '',

                    'position_num_left' => (int) ($item->sorting_1 ?? 0),
                    'tags' => $item->tags ?? '',

                    'reserve' => [
                        'amount' => $reserveAmount,
                        'is_linked' => $reserveIsLinked,
                        'root_reserve_id' => $rootReserveId,
                    ],
                ],
            ];

            $labelsIn = $this->mapLabels($item->labelsIn ?? collect());
            $labelsOut = $this->mapLabels($item->labelsOut ?? collect());

            if ($labelsIn !== []) {
                $row['attributes']['labels_in'] = $labelsIn;
            }
            if ($labelsOut !== []) {
                $row['attributes']['labels_out'] = $labelsOut;
            }

            if ($item->currency_group_network) {
                $row['attributes']['network'] = [
                    'id' => (int) $item->currency_group_network->id,
                    'title' => (string) $item->currency_group_network->title,
                    'display_type' => (int) ($item->currency_group_network->display_type ?? 0),
                    'icon' => !empty($item->currency_group_network->icon)
                        ? '/storage/currencies-icon/' . $item->currency_group_network->icon
                        : '',
                ];
            }

            $data[$id] = $row;
        }

        return $data;
    }

    /**
     * Определить корневые резервы для списка reserve_ids через closure table.
     *
     * Правило:
     * - root = ancestor_id на максимальной depth для каждого descendant_id.
     *
     * Почему так:
     * - `reserve_links` может не содержать записи для корней (и вообще может быть неполным источником истины).
     * - closure table гарантирует корректность цепочек.
     *
     * О производительности:
     * - Это 1 батч-запрос, который даёт тебе mapping reserve_id -> root_id.
     * - При наличии индекса на (descendant_id, depth) и/или (descendant_id, ancestor_id) работает быстро.
     *
     * @param array<int,int> $reserveIds
     * @return array<int,int> reserve_id => root_id
     */
    private function resolveRootReserveIds(array $reserveIds): array
    {
        if ($reserveIds === []) {
            return [];
        }

        $maxDepthSub = DB::table('reserve_closures')
            ->whereIn('descendant_id', $reserveIds)
            ->groupBy('descendant_id')
            ->selectRaw('descendant_id, MAX(depth) as max_depth');

        $rows = DB::table('reserve_closures as rc')
            ->joinSub($maxDepthSub, 'md', function ($join): void {
                $join->on('md.descendant_id', '=', 'rc.descendant_id')
                    ->on('md.max_depth', '=', 'rc.depth');
            })
            ->whereIn('rc.descendant_id', $reserveIds)
            ->selectRaw('rc.descendant_id as reserve_id, rc.ancestor_id as root_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->reserve_id] = (int) $row->root_id;
        }

        return $map;
    }

    /**
     * Вернуть резерв как СТРОКУ (без экспоненты), с учётом black_amount и max_display_reserve.
     *
     * Правила:
     * - Доступный резерв = (summa - black_amount).
     * - Если результат <= 0 — "0".
     * - Если max_display_reserve задан и сумма больше — ограничиваем.
     *
     * Почему строка:
     * - В JSON/UI строка надежнее: не ловим экспоненты и не теряем точность при float.
     *
     * @param Currency $currency
     */
    private function reserveAmountString(Reserve $reserve, Currency $currency): string
    {
        $scale = 18;

        // NOTE: используем только корректный RoundingMode::DOWN (Brick ожидает именно константы).
        $summa = BigDecimal::of((string) ($reserve->summa ?? '0'))
            ->toScale($scale, RoundingMode::DOWN);

        $black = BigDecimal::of((string) ($reserve->black_amount ?? '0'))
            ->toScale($scale, RoundingMode::DOWN);

        $available = $summa->minus($black);

        if ($available->isLessThanOrEqualTo(BigDecimal::zero())) {
            return '0';
        }

        $maxDisplayRaw = $currency->max_display_reserve;

        if ($maxDisplayRaw !== null && trim((string) $maxDisplayRaw) !== '') {
            $maxDisplay = BigDecimal::of((string) $maxDisplayRaw)
                ->toScale($scale, RoundingMode::DOWN);

            if ($maxDisplay->isGreaterThan(BigDecimal::zero()) && $available->isGreaterThan($maxDisplay)) {
                $available = $maxDisplay;
            }
        }

        // Нормализация: без экспоненты, без хвостовых нулей, -0 -> 0
        return (string) iex_money_normalize($available->__toString(), $scale);
    }
}
