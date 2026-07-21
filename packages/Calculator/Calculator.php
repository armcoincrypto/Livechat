<?php
declare(strict_types=1);

namespace iEXPackages\Calculator;

use App\Facades\SystemLogger;
use App\Models\DirectionExchange;
use App\Models\SelectorFee;
use App\Models\SystemLog;
use App\Models\Task;
use App\Services\Calculator\CalculatorMathService;
use App\Services\Rates\IndependentMarketBaseline;
use iEXPackages\Calculator\Contracts\StrategyInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Log;

/**
 * Класс Calculator предназначен для комплексного расчёта курсов обмена валют,
 * включая криптовалюты и фиатные валюты, с учетом различных комиссий, ограничений,
 * источников данных и дополнительных условий.
 *
 * Основные возможности и назначения:
 * - Расчёт актуального курса обмена с учётом комиссий (групповых, городских, индивидуальных).
 * - Применение минимальных и максимальных ограничений на курс.
 * - Поддержка различных источников получения курса: BestChange, Competitor, Manual, Formula и другие.
 * - Высокая точность вычислений с использованием BCMath, что особенно важно при работе с криптовалютами.
 * - Гибкость и расширяемость благодаря применению интерфейсов и отдельным классам-источникам курса.
 *
 * Порядок работы:
 * 1. Устанавливается направление обмена (DirectionExchange).
 * 2. Определяется источник получения курса.
 * 3. Выполняется расчёт курса с применением всех необходимых комиссий и ограничений.
 * 4. Подготавливаются дополнительные опции и параметры расчёта.
 * 5. Результат расчета доступен в виде массива, строки или форматированного значения.
 *
 * Методы класса позволяют гибко настраивать и управлять расчётом курса,
 * обеспечивая прозрачность и удобство использования в приложении.
 *
 * @implements Arrayable Интерфейс для представления объекта в виде массива.
 *
 * Примеры использования:
 * $calculator = (new Calculator())
 *                  ->setDirectionExchange($directionExchange)
 *                  ->calculate()
 *                  ->getRateValue();
 *
 * $rateWithOptions = $calculator->calculateWithOptions(['exchange_city_id' => 5])->getRateValueString();
 *
 * Результаты доступны через:
 * - getRateValue(): курс в виде строки.
 * - getRateValueString(): курс в формате, удобном для отображения.
 * - getFullRate(): читабельное и информативное представление курса.
 * - toArray(): полное состояние объекта в виде ассоциативного массива.
 */
class Calculator implements Arrayable
{
    use Traits\InteractsWithNumbers,
        Traits\CalculationPipeline,
        Traits\BrickMathNumbersTrait;

    /**
     * Кеш конфигурации источников калькулятора на процесс.
     *
     * Почему это важно:
     * - Внутри массового пересчёта направлений `calculate()` вызывается сотни/тысячи раз.
     * - `config('calculator.sources')` и `config('calculator.priority')` — это хоть и не SQL,
     *   но всё равно лишняя работа на каждом вызове (чтение, аллокации, нормализация).
     *
     * Гарантии:
     * - В продакшене конфиги не меняются “на лету”, поэтому кеш на процесс безопасен.
     * - Если тебе нужно “обновить конфиг” без перезапуска PHP-процесса — этот подход не подойдёт,
     *   но для твоего cron/worker сценария это именно то, что нужно.
     */
    private static ?array $cachedSourcesConfig = null;

    /**
     * Кеш нормализованного списка приоритетов источников (на процесс).
     *
     * Что именно кешируем:
     * - уже очищенный и нормализованный массив ключей источников (trim + remove empty).
     *
     * Зачем:
     * - чтобы не выполнять `trim/filter/map` на каждом `calculate()`.
     */
    private static ?array $cachedPriorityList = null;

    /**
     * Имя текущего источника курса, если определён.
     *
     * @var string|null
     */
    protected ?string $currentSourceName = null;

    /**
     * Информация о направлении обмена
     *
     * @var DirectionExchange
     */
    protected DirectionExchange $directionExchange;

    /**
     * Данные текущей заявки (задачи).
     * Может быть null, если заявка еще не создана.
     *
     * @var Task|null
     */
    protected ?Task $order = null;

    /**
     * Дополнительные опции для обработки
     *
     * @var array
     */
    protected array $options = [];

    /**
     * Курс обмена валюты.
     *
     * Для обеспечения точности, особенно при работе с криптовалютами,
     * рекомендуется использовать строковое представление.
     *
     * @var string
     */
    protected string $rateValue = '0';


    protected bool $isInverted = false;

    /**
     * Признак необходимости формировать дополнительные данные (options) для вывода.
     *
     * Когда true — калькулятор дополнительно собирает массив $this->options
     * (например: комиссии по городам, диапазоны сумм, тип курса, selector fees и т.п.).
     *
     * Когда false — калькулятор считает ТОЛЬКО курс и НЕ делает лишние запросы к БД
     * ради данных, которые нужны только для UI.
     *
     * Использовать:
     * - В админке/клиентском UI: оставить true (по умолчанию).
     * - В cron/массовых пересчётах direction_exchange: выключать через withoutOptions().
     */
    protected bool $buildOptions = true;

    /**
     * Конструктор
     *
     * @return void
     */
    public function __construct(
        protected $config = []
    ) {}

    /**
     * Отключить формирование дополнительных данных (options) и считать только курс.
     *
     * Важно:
     * - Не влияет на расчёт rateValue / exchange_rate (курс считается как обычно).
     * - Сильно ускоряет массовые операции (cron), т.к. убирает запросы за:
     *   - selector fees (глобальные и индивидуальные),
     *   - комиссии по городам и профили,
     *   - диапазоны сумм и прочие UI-данные.
     *
     * @return static
     */
    public function withoutOptions(): static
    {
        $this->buildOptions = false;
        return $this;
    }

    /**
     * Устанавливает направление обмена.
     *
     * @param DirectionExchange $item
     * @return static
     */
    public function setDirectionExchange(DirectionExchange $item): static
    {
        $this->directionExchange = $item;
        return $this;
    }

    /**
     * Получает текущее направление обмена.
     *
     * @return DirectionExchange
     */
    public function getDirectionExchange(): DirectionExchange
    {
        return $this->directionExchange;
    }

    /**
     * Устанавливает заявку (задачу).
     *
     * @param Task $item
     * @return static
     */
    public function setOrder(Task $item): static
    {
        $this->order = $item;

        return $this;
    }

    /**
     * Возвращает текущую заявку.
     *
     * @return Task|null
     */
    public function getOrder(): ?Task
    {
        return $this->order instanceof Task ? $this->order : null;
    }

    /**
     * Записывает дополнительные опции.
     *
     * @param array $options
     * @return static
     */
    public function setOptions(array $options = []): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Возвращает дополнительные опции.
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Выполняет полный расчёт курса обмена, учитывая все комиссии, ограничения, дополнительные условия и источники курса.
     *
     * Порядок выполнения расчёта:
     * 1. Сбрасывает текущие опции и значение курса на начальные значения (пустой массив и '0').
     * 2. Подбирает источник курса по приоритету (BestChange, crypto, formula, …) и при необходимости
     *    пробует следующий источник, если текущий недоступен или вернул курс ≤ 0.
     * 3. Получает положительный курс обмена (с учётом default_rate из конфигурации калькулятора, если задан).
     * 4. Инициализирует математический сервис (CalculatorMathService) с полученным курсом.
     * 5. Применяет дополнительные модификаторы курса:
     *    - Групповые комиссии (если заданы).
     *    - Ограничения курса (минимальный и максимальный курс).
     *    - Расчёт прибыли (в процентах или фиксированной суммой).
     * 6. Подготавливает дополнительные опции (комиссии городов, суммы обмена, типы расчёта курса).
     * 7. Логирует успешный расчёт и сохраняет итоговый курс.
     *
     * Ошибки не выбрасываются наружу, а логируются и возвращают безопасное состояние объекта.
     *
     * @return static Текущий экземпляр Calculator с обновлёнными значениями.
     */
    public function calculate(): static
    {
        $this->options = [];
        $this->rateValue = '0';

        if ($this->useStoredIndependentRubRate()) {
            $this->rateValue = (string) $this->directionExchange->course_value;
            $this->currentSourceName = (string) $this->directionExchange->parser_source_name;
            if ($this->buildOptions) {
                $this->options = [
                    'cities' => $this->getCityCommissions(),
                    'exchange_amount' => $this->getExchangeAmounts(),
                    'type_rate' => $this->getTypeRates(),
                    'selector_fees' => $this->getSelectorFees(),
                ];
            }

            return $this;
        }

        $resolution = $this->resolveFirstCourseSourceWithPositiveRate($this->directionExchange);

        if (!$resolution['ok']) {
            $isSourceMissing = ($resolution['reason'] === 'no_available_source');
            $this->syncNoSourceIncident($isSourceMissing);

            return $this;
        }

        $response = $resolution['rate'];
        $mathValue = new CalculatorMathService($response);

        // Максимальное количество десятичных знаков, заданное в настройках
        $maxDecimals = (int) iEXSetting('max_decimal_places', 10);

        // Проверяем, является ли текущий курс "обратным" (меньше 1)
        $this->isInverted = $this->cmpNum($response, '1', $maxDecimals) === -1;

        if ($this->isInverted) {
            // Если курс меньше 1, инвертируем его (1 / курс)
            $mathValue->setSumma($this->invertNum($response, $maxDecimals));
        }

        // Применение комиссий и ограничений:
        $this->applyGroupFee($mathValue);
        $this->applyCourseLimits($mathValue, $response);
        $this->applyProfitAdjustments($mathValue);

        // Подготовка опций для вывода
        if ($this->buildOptions) {
            $this->options = [
                'cities' => $this->getCityCommissions(),
                'exchange_amount' => $this->getExchangeAmounts(),
                'type_rate' => $this->getTypeRates(),
                'selector_fees' => $this->getSelectorFees(),
            ];
        } else {
            $this->options = [];
        }

        // Далее в конце метода, перед сохранением итогового значения курса:
        if ($this->isInverted) {
            // Если курс был инвертирован ранее, восстанавливаем исходное значение (1 / инвертированный курс)
            $mathValue->setSumma($this->invertNum($mathValue->getSumma(), $maxDecimals));
        }

        // Итоговый курс
        $this->rateValue = $mathValue->getSumma();

        // Log::info('Курс успешно рассчитан', ['rateValue' => $this->rateValue, 'options' => $this->options]);

        return $this;
    }

    private function useStoredIndependentRubRate(): bool
    {
        $snapshot = IndependentMarketBaseline::currentSnapshot();
        if (($snapshot['purpose'] ?? null) === 'rate_update') {
            return false;
        }

        if (!$this->directionExchange->relationLoaded('currency2')) {
            $this->directionExchange->loadMissing(['currency2']);
        }
        $to = strtoupper((string) ($this->directionExchange->currency2?->designation_xml ?? ''));
        $source = strtolower((string) ($this->directionExchange->parser_source_name ?? ''));
        $rate = (string) ($this->directionExchange->course_value ?? '');

        return str_contains($to, 'RUB')
            && str_starts_with($source, 'independent rub')
            && is_numeric($rate)
            && bccomp($rate, '0', 18) === 1;
    }

    /**
     * Синхронизирует инцидент отсутствия источника курса.
     *
     * Активирует или снимает проблему "Нет доступных источников для курса"
     * в зависимости от переданного флага $isActive.
     *
     * @param bool $isActive true — активировать проблему, false — снять.
     */
    protected function syncNoSourceIncident(bool $isActive): void
    {
        $module = 'direction_exchange';
        $name   = 'calculator';
        $code   = 'no_available_course_sources';
        $entity = [DirectionExchange::class, $this->directionExchange->id ?? null];

        $messages = [
            'activate' => 'Нет доступных источников для курса.',
            'resolve'  => 'Источник курса найден и расчёт успешно выполнен',
        ];

        $context = [
            'context' => [
                'directionExchange_id'        => $this->directionExchange->id ?? null,
                'directionExchange_tech_name' => $this->directionExchange->tech_name ?? null,
            ],
        ];

//        SystemLogger::syncProblem(
//            $module,
//            $name,
//            $code,
//            $entity,
//            $isActive,
//            $messages['activate'],
//            $messages['resolve'],
//            $context
//        );
    }


    /**
     * Применяет все привязанные групповые комиссии к текущему курсу обмена.
     *
     * Последовательно обрабатывает каждую групповую комиссию, проверяя её корректность.
     * Некорректные комиссии пропускаются с логированием причин.
     *
     * Шаги выполнения:
     * 1. Проверяет наличие привязанных групповых комиссий к текущему направлению.
     * 2. Валидирует математическое выражение каждой групповой комиссии.
     * 3. Очищает и подготавливает математическое выражение для расчёта.
     * 4. Применяет комиссию к текущему значению курса.
     * 5. Логирует ошибки и предупреждения в случае некорректных комиссий.
     *
     * @param CalculatorMathService $mathValue Сервис расчёта, содержащий текущее значение курса.
     *
     * @return void
     */
    protected function applyGroupFee(CalculatorMathService $mathValue): void
    {
        // Загружаем привязанные групповые комиссии
        $groupCommissions = $this->directionExchange->groupCommissions;

        // Если комиссии отсутствуют, сразу завершаем работу метода
        if ($groupCommissions->isEmpty()) {
            return;
        }

        // Применяем все привязанные комиссии последовательно
        foreach ($groupCommissions as $groupCommission) {
            // Получаем значение комиссии
            $groupFee = (string) ($groupCommission->receiving ?? '0');

            // Проверка, что значение комиссии является допустимым математическим выражением
            if (!$this->isValidMathExpression($groupFee)) {
                Log::error('Ошибка при применении групповой комиссии: некорректное математическое выражение.', [
                    'group_commission_id' => $groupCommission->id,
                    'group_fee_original' => $groupCommission->receiving,
                    'direction_exchange_id' => $this->directionExchange->id
                ]);
                continue;  // Переходим к следующей комиссии
            }

            // Очищаем выражение комиссии для безопасного использования
            $sanitizedGroupFee = $this->sanitizeFlexibleMathExpression($groupFee);

            // Проверяем значение после очистки
            if (empty($sanitizedGroupFee) || $sanitizedGroupFee === '0') {
//                Log::warning('Пропуск групповой комиссии: значение пустое после очистки.', [
//                    'group_commission_id' => $groupCommission->id,
//                    'group_fee_original' => $groupCommission->receiving,
//                    'direction_exchange_id' => $this->directionExchange->id
//                ]);
                continue;  // Пропускаем пустую комиссию
            }

            // Применяем валидную комиссию к текущему значению курса
            try {
                $mathValue->calculate($sanitizedGroupFee);
            } catch (\Throwable $e) {
                Log::error('Исключение при применении групповой комиссии.', [
                    'group_commission_id' => $groupCommission->id,
                    'sanitized_group_fee' => $sanitizedGroupFee,
                    'direction_exchange_id' => $this->directionExchange->id,
                    'exception' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Применяет ограничения курса (минимальный и максимальный курс обмена).
     *
     * @param CalculatorMathService $mathValue Сервис математических расчётов.
     * @param string &$response Текущее значение курса, передаётся по ссылке и изменяется.
     *
     * Шаги выполнения:
     * - Проверяет, находится ли текущее значение в указанных пределах.
     * - Если не в пределах, заменяет значение на новое из альтернативного источника (если доступно).
     * - Применяет дополнительную комиссию, если она задана.
     */
    protected function applyCourseLimits(CalculatorMathService $mathValue, string &$response): void
    {
        $min = $this->sanitizeNumber($this->directionExchange->rl_min2_course ?? '0');
        $max = $this->sanitizeNumber($this->directionExchange->rl_max2_course ?? '0');

        if ($this->isGreaterThanZero($response) && !$this->isWithinCourseLimits($response, $min, $max)) {
            if (!empty($this->directionExchange->rl_id_parser_exchange))
            {
                $newResponse = $this->sanitizeNumber($this->directionExchange->rl_parser_exchange?->summa ?? $response);

                $response = $newResponse;
                $mathValue->setSumma($newResponse);

                $additionalCourse = $this->sanitizeNumber($this->directionExchange->rl_add_course ?? '0');

                if ($this->isGreaterThanZero($additionalCourse)) {
                    $mathValue->calculateWithPercentage($additionalCourse);
                }
            }
        }
    }

    /**
     * Получает тип расчёта курса обмена (фиксированный или плавающий).
     *
     * Типы курсов:
     * - fixed: фиксированная комиссия.
     * - floating: плавающая комиссия.
     *
     * @return array Ассоциативный массив с типами комиссий и их значениями.
     */
    protected function getTypeRates(): array
    {
        if ((int)$this->directionExchange->is_type_rate !== 1) {
            return [];
        }

        $fees = [
            'fixed' => $this->directionExchange->fix_fee ?? '0',
            'floating' => $this->directionExchange->floating_fee ?? '0',
        ];

        foreach ($fees as $type => $fee) {
            if ($this->isValidFlexibleMathExpression($fee)) {
                $fees[$type] = $this->sanitizeFlexibleMathExpression($fee);
            } else {
                Log::error('Недопустимое математическое выражение в типе расчета курса.', [
                    'type' => $type,
                    'fee' => $fee,
                ]);
                $fees[$type] = '0';
            }
        }

        return $fees;
    }

    /**
     * Получает и проверяет валидность комиссий по городам для текущего направления обмена.
     *
     * @return array Массив с комиссиями по городам (город => комиссия).
     */
    protected function getCityCommissions(): array
    {
        if (!isset($this->directionExchange->direction_exchange_cities)) {
            return [];
        }

        return $this->directionExchange->direction_exchange_cities
            ->map(function ($city) {
                $profile = $city->profile ?? null;

                // Эффективный add_comm
                $addCommSource = $this->resolveEffectiveAddComm(
                    $city->add_comm,
                    $profile?->add_comm
                );

                // По умолчанию комиссии нет
                $sanitizedFee = '0';
                if ($addCommSource !== null && $addCommSource !== '') {
                    // Если выражение валидно — чистим и применяем
                    if ($this->isValidFlexibleMathExpression($addCommSource)) {
                        $expr = $this->sanitizeFlexibleMathExpression($addCommSource) ?: '0';

                        // "0" / "0%" считаем нейтральными
                        if ($expr !== '0' && $expr !== '0%') {
                            $sanitizedFee = $expr;
                        }
                    }
                }


                // Источник процентной прибыли: сначала индивидуальная, затем профиль, иначе 0
                $profitPercentSource = $this->resolveEffectiveProfit(
                    $city->profit,
                    $profile?->profit
                );

                // Источник фиксированной прибыли: сначала индивидуальная, затем профиль, иначе 0
                $profitFixedSource = $this->resolveEffectiveProfit(
                    $city->profit_s,
                    $profile?->profit_s
                );

                return [
                    'id'               => $city->id,
                    'fee'              => $sanitizedFee,
                    // Итоговая (эффективная) прибыль с учётом профиля
                    'profit_fee'       => $this->sanitizeNumber((string)($profitPercentSource ?? '0')),
                    'profit_fee_value' => $this->sanitizeNumber((string)($profitFixedSource ?? '0')),
                ];
            })
            ->filter()   // выкидываем null'ы
            ->values()
            ->toArray();
    }

    /**
     * Получает и фильтрует комиссии в процентах в зависимости от диапазона суммы обмена ("от и до").
     *
     * @return array Массив с комиссиями, применимыми к разным диапазонам сумм обмена.
     */
    protected function getExchangeAmounts(): array
    {
        return optional($this->directionExchange->direction_exchange_percentage_amount)
            ->filter(fn($item) => $this->isValidMathExpression($item->percentage))
            ->map(fn($item) => [
                'from' => $item->from_amount,
                'to' => $item->to_amount,
                'fee' => $this->sanitizeMathExpression($item->percentage)
            ])
            ->toArray();
    }

    /**
     * Получает комиссии, заданные для выбора клиентом при создании заявки.
     *
     * @return array Массив с комиссиями (id, название, значение комиссии).
     */
    protected function getSelectorFees(): array
    {
        $dirId  = (int) $this->directionExchange->id;
        $includeDescription = true;

        $directionFees = collect($this->directionExchange->direction_exchange_selector_fees ?? [])
            ->map(function ($fee) use ($includeDescription) {
                // Валидация и санитизация в одном месте
                if (!$this->hasAnyTranslation($fee->name) || !$this->isValidFlexibleMathExpression($fee->fee)) {
                    return null;
                }

                return [
                    'id'          => (int) $fee->id,
                    'name'        => (string) $fee->name,
                    // description часто тяжёлый JSON — по умолчанию не тащим
                    'description' => $includeDescription ? (string) ($fee->description ?? '') : '',
                    'fee_type'    => 'dynamic', // индивидуальные у тебя пока всегда dynamic
                    'fee'         => (string) $this->sanitizeFlexibleMathExpression((string) $fee->fee),
                    'type'        => 'individual',
                ];
            })
            ->filter()
            ->sortBy('id') // или убери и зафиксируй порядок в самой связи
            ->values();

        if ($directionFees->isNotEmpty()) {
            return $directionFees->all();
        }

        // 2) Глобальные (без кэша)
        return SelectorFee::query()
            ->select(['selector_fees.id','selector_fees.name','selector_fees.description','selector_fees.fee','selector_fees.fee_type','selector_fees.sorting'])
            ->where('selector_fees.status', true)
            ->whereDoesntHave('excludedDirections', fn($q) => $q->whereKey($dirId))
            ->orderBy('selector_fees.sorting')
            ->orderBy('selector_fees.id')
            ->get()
            ->map(function ($fee) use ($includeDescription) {
                if (!$this->hasAnyTranslation($fee->name)) {
                    return null;
                }

                $type = (string)($fee->fee_type ?? 'dynamic');

                // dynamic → sanitize; profit → оставляем «голое» значение
                if ($type === 'profit') {
                    $expr = (string) $fee->fee;
                    if ($expr === '' || $expr === '0' || $expr === '0%') {
                        return null;
                    }
                } else {
                    if (!$this->isValidFlexibleMathExpression($fee->fee)) {
                        return null;
                    }
                    $expr = $this->sanitizeFlexibleMathExpression((string)$fee->fee) ?: '0';
                    if ($expr === '0') {
                        return null;
                    }
                }

                return [
                    'id'          => (int) $fee->id,
                    'name'        => (string) $fee->name,
                    'description' => $includeDescription ? (string) ($fee->description ?? '') : '',
                    'fee_type'    => $type,
                    'fee'         => $expr,
                    'type'        => 'common',
                    'sorting'     => (int) ($fee->sorting ?? 0),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }


    /**
     * Проверяет, что у названия есть хотя бы одно непустое значение (любая локаль).
     */
    protected function hasAnyTranslation(?string $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    /**
     * Получить конфигурацию источников калькулятора (sources) с кешированием на процесс.
     *
     * Формат конфигурации ожидается как:
     * [
     *   'bestchange' => ['strategy' => ..., 'check' => ..., 'name' => ...],
     *   'crypto'     => [...],
     *   ...
     * ]
     *
     * Важно:
     * - Мы приводим результат к array, чтобы исключить неожиданные типы.
     * - Возвращаем пустой массив, если конфигурация отсутствует.
     *
     * @return array<string, mixed>
     */
    protected function sourcesConfig(): array
    {
        if (self::$cachedSourcesConfig !== null) {
            return self::$cachedSourcesConfig;
        }

        $sources = (array) config('calculator.sources', []);

        // Если вдруг конфиг некорректный — оставляем пустой массив
        // (это безопаснее, чем падать в рантайме).
        if ($sources === []) {
            return self::$cachedSourcesConfig = [];
        }

        return self::$cachedSourcesConfig = $sources;
    }

    /**
     * Получить список приоритетов источников (priority) с кешированием на процесс.
     *
     * Логика:
     * 1) Берём `config('calculator.priority')`.
     * 2) Если он пустой/невалидный — используем порядок ключей sources.
     * 3) Нормализуем:
     *    - trim каждого элемента
     *    - удаляем пустые строки
     *
     * Почему не делаем strtolower():
     * - Ключи источников у тебя уже задаются в конфиге в нужном регистре.
     * - Если хочешь абсолютную защиту, можно привести к strtolower() и также
     *   ключи sources хранить в нижнем регистре.
     *
     * @param array<string, mixed> $sources Конфиг sources, чтобы уметь fallback'нуться на array_keys($sources)
     * @return array<int, string> Нормализованный список ключей источников в порядке приоритета
     */
    protected function priorityList(array $sources): array
    {
        if (self::$cachedPriorityList !== null) {
            return self::$cachedPriorityList;
        }

        $priority = config('calculator.priority', []);

        // Если приоритет не задан — считаем, что порядок равен порядку конфигурации sources.
        if (!is_array($priority) || $priority === []) {
            $priority = array_keys($sources);
        }

        // Нормализуем список:
        // - оставляем только строки
        // - trim
        // - убираем пустые элементы
        $priority = array_values(array_filter(array_map(
            static function ($v): string {
                return is_string($v) ? trim($v) : '';
            },
            $priority
        )));

        return self::$cachedPriorityList = $priority;
    }

    /**
     * Применяет прибыль обменника (процентную и фиксированную) к текущему курсу.
     *
     * Шаги:
     *  - Получает исходную сумму из $mathValue.
     *  - Вычитает процент прибыли обменника (если задан и больше 0).
     * - Затем вычитает фиксированную прибыль обменника (если задана и больше 0).
     *
     * Пример:
     * - Исходное значение: 100 USD
     * - Процент прибыли: 3%
     *   Значение после процента: 100 - 3% = 97 USD
     * - Фиксированная прибыль: 2 USD
     *   Итоговое значение: 97 - 2 = 95 USD
     *
     * @param CalculatorMathService $mathValue Объект для выполнения расчётов
     */
    protected function applyProfitAdjustments(CalculatorMathService $mathValue): void
    {
        // Источник процентной прибыли: сначала индивидуальная для направления, затем профиль, иначе 0
        $profitPercentSource = $this->resolveEffectiveProfit(
            $this->directionExchange->profit,
            $this->directionExchange->profitProfile?->profit ?? null
        );
        $profitPercent = (string) ($profitPercentSource ?? '0');

        if ($this->isGreaterThanZero($profitPercent)) {
            if ($this->isInverted) {
                $mathValue->addPercentage($profitPercent);
            } else {
                $mathValue->subtractPercentage($profitPercent);
            }
        }

        // Источник фиксированной прибыли: сначала индивидуальная для направления, затем профиль, иначе 0
        $profitFixedSource = $this->resolveEffectiveProfit(
            $this->directionExchange->profit_s,
            $this->directionExchange->profitProfile?->profit_s ?? null
        );
        $profitFixed = (string) ($profitFixedSource ?? '0');

        if ($this->isGreaterThanZero($profitFixed)) {
            if ($this->isInverted) {
                $mathValue->addFixedValue($profitFixed);
            } else {
                $mathValue->subtractFixedValue($profitFixed);
            }
        }
    }

    /**
     * Подбирает первый источник по приоритету, для которого эффективный курс больше нуля.
     *
     * Расширяет прежнюю логику determineCourseSource: доступность (check) недостаточна — если
     * стратегия вернула 0 или меньше, перебираем следующие источники из priority.
     *
     * @return array{
     *     ok: true,
     *     rate: string,
     *     fallback_used: bool
     * }|array{
     *     ok: false,
     *     reason: 'no_available_source'|'no_positive_rate'|'invalid_default_rate'
     * }
     */
    protected function resolveFirstCourseSourceWithPositiveRate(DirectionExchange $directionExchange): array
    {
        $this->currentSourceName = null;

        $sourcesConfig = $this->sourcesConfig();

        if ($sourcesConfig === []) {
            return ['ok' => false, 'reason' => 'no_available_source'];
        }

        // RUB is a fail-closed canonical lane: never fall back to BestChange,
        // peer, manual or formula sources that could reintroduce a circular rate.
        if (!$directionExchange->relationLoaded('currency2')) {
            $directionExchange->loadMissing(['currency2']);
        }
        $toCode = strtoupper((string) ($directionExchange->currency2?->designation_xml ?? ''));
        $priorityList = str_contains($toCode, 'RUB')
            ? ['independent_rub']
            : $this->priorityList($sourcesConfig);
        $skippedSourceKeys = [];
        $anySourceEvaluated = false;
        $lastNonPositiveRate = '0';

        foreach ($priorityList as $sourceKey) {
            $sourceKey = trim((string) $sourceKey);

            if ($sourceKey === '') {
                continue;
            }

            /** @var array<string, mixed>|null $sourceCfg */
            $sourceCfg = is_array($sourcesConfig[$sourceKey] ?? null) ? $sourcesConfig[$sourceKey] : null;
            if ($sourceCfg === null) {
                continue;
            }

            if (empty($sourceCfg['strategy']) || !is_string($sourceCfg['strategy'])) {
                continue;
            }

            if (!$this->isSourceAvailable($sourceKey, $directionExchange, $sourcesConfig)) {
                continue;
            }

            $anySourceEvaluated = true;

            $name = $sourceCfg['name'] ?? $sourceKey;

            try {
                $this->currentSourceName = is_callable($name)
                    ? (string) $name($directionExchange)
                    : (string) $name;

                if ($this->currentSourceName === '') {
                    $this->currentSourceName = $sourceKey;
                }
            } catch (\Throwable) {
                $this->currentSourceName = $sourceKey;
            }

            $strategy = $this->createStrategy($sourceKey, $directionExchange, $sourcesConfig);
            $response = (string) $this->sanitizeNumber($strategy->getRate());

            if (isset($this->config['default_rate'])) {
                $fallbackRate = $this->sanitizeNumber((string) $this->config['default_rate']);
                if ($this->isGreaterThanZero($fallbackRate)) {
                    Log::info('Используется переданный курс по умолчанию', ['default_rate' => $fallbackRate]);
                    $response = $fallbackRate;
                } else {
                    Log::error('Переданный курс по умолчанию некорректен', ['default_rate' => $this->config['default_rate']]);

                    return ['ok' => false, 'reason' => 'invalid_default_rate'];
                }
            }

            if ($this->isGreaterThanZero($response)) {
                if ($skippedSourceKeys !== []) {
                    Log::info('Калькулятор: резервный источник курса после неположительного значения', [
                        'direction_exchange_id' => $directionExchange->id,
                        'source_key' => $sourceKey,
                        'rate' => $response,
                        'fallback_used' => true,
                        'skipped_source_keys' => $skippedSourceKeys,
                    ]);
                }

                return [
                    'ok' => true,
                    'rate' => $response,
                    'fallback_used' => $skippedSourceKeys !== [],
                ];
            }

            $lastNonPositiveRate = $response;
            $skippedSourceKeys[] = $sourceKey;

            Log::warning('Калькулятор: неположительный курс от источника, пробуем следующий', [
                'direction_exchange_id' => $directionExchange->id,
                'source_key' => $sourceKey,
                'rate' => $response,
                'fallback' => true,
            ]);
        }

        if (!$anySourceEvaluated) {
            return ['ok' => false, 'reason' => 'no_available_source'];
        }

        Log::error('Некорректное значение курса получено из источника.', [
            'direction_exchange_id' => $directionExchange->id,
            'response' => $lastNonPositiveRate,
            'fallback_exhausted' => true,
        ]);

        return ['ok' => false, 'reason' => 'no_positive_rate'];
    }

    /**
     * Проверяет доступность источника для направления.
     *
     * @param string $sourceKey
     * @param DirectionExchange $directionExchange
     * @param array<string, mixed> $sourcesConfig Уже загруженный конфиг sources
     * @return bool
     */
    protected function isSourceAvailable(string $sourceKey, DirectionExchange $directionExchange, array $sourcesConfig): bool
    {
        $sourceKey = trim($sourceKey);
        if ($sourceKey === '') {
            return false;
        }

        $check = $sourcesConfig[$sourceKey]['check'] ?? null;

        if (!is_callable($check)) {
            return false;
        }

        try {
            return (bool) $check($directionExchange);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Создаёт экземпляр стратегии по ключу источника.
     *
     * @param string $sourceKey
     * @param DirectionExchange $directionExchange
     * @param array<string, mixed> $sourcesConfig Уже загруженный конфиг sources
     * @return StrategyInterface
     */
    protected function createStrategy(string $sourceKey, DirectionExchange $directionExchange, array $sourcesConfig): StrategyInterface
    {
        $sourceKey = trim($sourceKey);

        if ($sourceKey === '') {
            throw new \InvalidArgumentException('Unknown source: (empty)');
        }

        $strategyClass = $sourcesConfig[$sourceKey]['strategy'] ?? null;

        if (!is_string($strategyClass) || $strategyClass === '' || !class_exists($strategyClass)) {
            throw new \InvalidArgumentException("Unknown source: {$sourceKey}");
        }

        $strategy = new $strategyClass($directionExchange);

        if (!$strategy instanceof StrategyInterface) {
            throw new \InvalidArgumentException("Invalid strategy for source: {$sourceKey}");
        }

        return $strategy;
    }

    /**
     * Получает имя текущего источника курса, если оно определено.
     *
     * @return string|null Имя источника курса или null, если не определено.
     */
    public function getCurrentSourceName(): ?string
    {
        return $this->currentSourceName;
    }

    /**
     * Выполняет расчёт с учётом дополнительных опций, таких как комиссии по городам,
     * типы расчета курсов, суммы обменов и другие пользовательские настройки.
     *
     * @param array $options Дополнительные опции для расчёта, такие как:
     *                       - exchange_city_id: идентификатор города для расчёта комиссий
     *                       - amount: сумма обмена
     *
     * Последовательность обработки:
     * - Проверка и применение комиссии по городу, если задана.
     * - Применение процентных комиссий по сумме обмена.
     * - Применение типа расчёта курса (фиксированный, плавающий и т.д.).
     *
     * @return static Экземпляр Calculator с применёнными настройками.
     */
    public function calculateWithOptions(array $options = []): static
    {
        $maxDecimals = (int) iEXSetting('max_decimal_places', 10);

        if (isset($options['default_rate'])) {
            $this->setDefaultRate((string)$options['default_rate']);
        }

        $this->calculate();

        $mathValue = new CalculatorMathService($this->rateValue);
        $initialRate = $mathValue->getSumma();

        $this->isInverted = $this->cmpNum($initialRate, '1', $maxDecimals) === -1;

        if ($this->isInverted)
        {
            $currentSumma = $mathValue->getSumma();
            if ($this->cmpNum($currentSumma, '0', $maxDecimals) === 0)
            {
                Log::error('Попытка деления на ноль при инверсии курса', [
                    'rateValue' => $this->rateValue,
                    'currentSumma' => $currentSumma,
                ]);
                $mathValue->setSumma('0');
            } else {
                $mathValue->setSumma($this->invertNum($currentSumma, $maxDecimals));
            }
        }


        if (
            $this->order instanceof Task &&
            isset($this->order->task_info->direction_city_id, $this->order->task_info->directionCity) &&
            (int)$this->order->task_info->direction_city_id > 0
        ) {
            $directionCity = $this->order->task_info->directionCity;
            $profile = $directionCity->profile ?? null;

            // Обработка add_comm (город → профиль)
            $effectiveAddComm = $this->resolveEffectiveAddComm(
                $directionCity->add_comm,
                $profile?->add_comm
            );

            if ($effectiveAddComm !== null && $effectiveAddComm !== '') {
                // Проверяем выражение, поддерживая %, +, - и т.п.
                if ($this->isValidFlexibleMathExpression($effectiveAddComm)) {
                    $commission = $this->sanitizeFlexibleMathExpression($effectiveAddComm) ?: '0';

                    // Нейтральные значения типа "0", "0%" не применяем
                    if ($commission !== '0' && $commission !== '0%') {
                        $mathValue->calculate($commission);
                    }
                }
            }

            // Определяем эффективную процентную прибыль: сначала город, затем профиль
            $cityProfitPercent = $this->resolveEffectiveProfit(
                $directionCity->profit,
                $profile?->profit
            );

            // Обработка profit (процентной комиссии)
            if (isset($cityProfitPercent) && is_numeric($cityProfitPercent) && (float)$cityProfitPercent > 0) {
                if ($this->isInverted) {
                    $mathValue->addPercentage($cityProfitPercent);
                } else {
                    $mathValue->subtractPercentage($cityProfitPercent);
                }
            }

            // Определяем эффективную фиксированную прибыль: сначала город, затем профиль
            $cityProfitFixed = $this->resolveEffectiveProfit(
                $directionCity->profit_s,
                $profile?->profit_s
            );

            // Обработка profit_s (фиксированной комиссии)
            if (isset($cityProfitFixed) && is_numeric($cityProfitFixed) && (float)$cityProfitFixed > 0) {
                $fixedFee = $this->sanitizeNumber($cityProfitFixed);
                $currentRate = $mathValue->getSumma();

                // Применяем только если текущая сумма больше комиссии
                if ($this->cmpNum($currentRate, $fixedFee, $maxDecimals) === 1) {
                    if ($this->isInverted) {
                        $mathValue->addFixedValue($fixedFee);
                    } else {
                        $mathValue->subtractFixedValue($fixedFee);
                    }
                }
            }
        }

        $checkboxFees = $options['checkbox_fees']
            ?? collect($this->order?->meta?->checkbox_agreements ?? [])
                ->flatMap(fn($a) => $a['fees'] ?? [])
                ->pluck('value')
                ->filter(fn($fee) => is_numeric(str_replace('%', '', $fee)))
                ->values()
                ->toArray();


        if (!empty($checkboxFees)) {
            foreach ($checkboxFees as $fee) {
                if (str_ends_with($fee, '%')) {
                    $percent = filter_var($fee, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

                    if ($percent !== false && $percent != 0) {
                        $this->isInverted
                            ? $mathValue->addPercentage($percent)
                            : $mathValue->subtractPercentage($percent);
                    }
                } else {
                    $fixedFee = $this->sanitizeNumber($fee);

                    if ($this->isGreaterThanZero($fixedFee)) {
                        $this->isInverted
                            ? $mathValue->addFixedValue($fixedFee)
                            : $mathValue->subtractFixedValue($fixedFee);
                    }
                }
            }
        }

        // Проверка типа верификации направления и опции card_verification_required
        $direction = $this->directionExchange;
        $isVerificationRequired = (bool)(
            $options['card_verification_required']
            ?? $this->order?->meta?->card_verification_required
            ?? false
        );

        // card_verification_type:
// 0 или null — использовать валюту
// 1 — использовать настройки направления (direction)
// 2 — индивидуальный конструктор (отдельные комиссии verification_fee / no_verification_fee)
        $cardType = (int) ($direction->card_verification_type ?? 0);

        if ($cardType === 2) {
            $rules = $direction->card_verification_rules ?? [];

            $noVerification   = $rules['no_verification']   ?? [];
            $withVerification = $rules['with_verification'] ?? [];

            // Выбор комиссии в зависимости от того, требуется ли верификация для этой заявки
            $rawFee = $isVerificationRequired
                ? (string)($withVerification['fee'] ?? '0')
                : (string)($noVerification['fee'] ?? '0');

            if ($this->isValidFlexibleMathExpression($rawFee)) {
                $feeExpression = $this->sanitizeFlexibleMathExpression($rawFee) ?: '0';

                // Не применяем нейтральные значения
                if ($feeExpression !== '0' && $feeExpression !== '0%') {
                    $mathValue->calculate($feeExpression);
                }
            }
        }

        if (
            isset($options['exchange_city_id'], $this->directionExchange->direction_exchange_cities) &&
            is_numeric($options['exchange_city_id'])
        ) {
            $cityInfo = $this->directionExchange->direction_exchange_cities
                ->firstWhere('id', (int)$options['exchange_city_id']);

            if ($cityInfo) {
                $profile = $cityInfo->profile ?? null;

                // Обработка add_comm (город → профиль)
                $effectiveAddComm = $this->resolveEffectiveAddComm(
                    $cityInfo->add_comm,
                    $profile?->add_comm
                );

                if ($effectiveAddComm !== null && $effectiveAddComm !== '') {
                    if ($this->isValidFlexibleMathExpression($effectiveAddComm)) {
                        $commission = $this->sanitizeFlexibleMathExpression($effectiveAddComm) ?: '0';

                        if ($commission !== '0' && $commission !== '0%') {
                            $mathValue->calculate($commission);
                        }
                    }
                }

                // Эффективная процентная прибыль: город → профиль
                $cityProfitPercent = $this->resolveEffectiveProfit(
                    $cityInfo->profit,
                    $profile?->profit
                );

                if (isset($cityProfitPercent) && is_numeric($cityProfitPercent) && (float)$cityProfitPercent > 0) {
                    if ($this->isInverted) {
                        $mathValue->addPercentage($cityProfitPercent);
                    } else {
                        $mathValue->subtractPercentage($cityProfitPercent);
                    }
                }

                // Эффективная фиксированная прибыль: город → профиль
                $cityProfitFixed = $this->resolveEffectiveProfit(
                    $cityInfo->profit_s,
                    $profile?->profit_s
                );

                if (isset($cityProfitFixed) && is_numeric($cityProfitFixed) && (float)$cityProfitFixed > 0) {
                    $fixedFee = $this->sanitizeNumber($cityProfitFixed);
                    $currentRate = $mathValue->getSumma();

                    if ($this->cmpNum($currentRate, $fixedFee, $maxDecimals) === 1) {
                        if ($this->isInverted) {
                            $mathValue->addFixedValue($fixedFee);
                        } else {
                            $mathValue->subtractFixedValue($fixedFee);
                        }
                    }
                }
            }
        }

        if (isset($this->options['exchange_amount']) && is_array($this->options['exchange_amount'])) {
            $givePrice = $this->order?->give_price ?? '0';
            $inPrice = (string)($options['amount'] ?? $givePrice);

            $exchangeAmountArray = collect($this->options['exchange_amount'])
                ->filter(fn($item) => isset($item['from_amount'], $item['to_amount'], $item['fee']))
                ->map(fn($item) => [
                    'from' => $item['from_amount'],
                    'to' => $item['to_amount'],
                    'fee' => $item['fee']
                ])
                ->toArray();


            $endPercent = $this->getApplicableCommission($inPrice, $exchangeAmountArray);

            if ($endPercent !== null && $this->isValidMathExpression($endPercent)) {
                $mathValue->calculate($endPercent);
            }
        }



        // Применение выбранных комиссий (новый формат) из options/meta через pipeline-трейт
        $this->applySelectedFeesFromOptions($mathValue, $options);


        if (isset($options['type_rate'])) {
            $typeRateKey = $options['type_rate'] == 0 ? 'fixed' : 'floating';
            if (isset($this->options['type_rate'][$typeRateKey])) {
                $typeRateValue = $this->options['type_rate'][$typeRateKey];

                if ($this->isValidFlexibleMathExpression($typeRateValue)) {
                    $mathValue->calculate($typeRateValue);
                }
            }
        }

        // Новая опция для финальной корректировки курса
        if (!empty($options['final_adjustment']) && $this->isValidMathExpression($options['final_adjustment'])) {
            $adjustmentExpression = $this->sanitizeFlexibleMathExpression($options['final_adjustment']);
            $mathValue->calculate($adjustmentExpression);
        }

        if ($this->isInverted) {
            $summa = $mathValue->getSumma();

            if ($summa && $this->cmpNum($summa, '0', $maxDecimals) !== 0) {
                $mathValue->setSumma($this->invertNum($summa, $maxDecimals));
            }
        }

        $this->rateValue = $mathValue->getSumma();

        return $this;
    }

    /**
     * Применяет выбранную клиентом комиссию к текущему значению калькулятора.
     *
     * Поведение по типам:
     * - dynamic: воспринимается как полноценное выражение с поддержкой +, -, *, /, %, дробей.
     *            Валидируется/очищается и вычисляется через CalculatorMathService::calculate().
     * - profit : значение хранится/передаётся как «голое» положительное число или процент БЕЗ знака.
     *            Здесь применяется вручную как вычитание (subtract...), а при инверсии курса — как добавление (add...):
     *              • "10%"  => subtractPercentage(10) / addPercentage(10)
     *              • "2.5"  => subtractFixedValue(2.5) / addFixedValue(2.5)
     *
     * Нейтральные случаи (пусто, "0", "0%") игнорируются без ошибок.
     *
     * @param \App\Services\Calculator\CalculatorMathService $mathValue Объект, управляющий расчётами над курсом.
     * @param string                                         $feeExpr   Строка комиссии:
     *                                                                   - для dynamic: выражение;
     *                                                                   - для profit: «голое» положительное число/процент.
     * @param string                                         $feeType   Тип комиссии: 'dynamic'|'profit' (иные значения трактуются как 'dynamic').
     *
     * @return void
     *
     * @example
     * // dynamic:
     * $this->applySelectedFee($mathValue, '+1.5%', 'dynamic');   // прибавить 1.5%
     * $this->applySelectedFee($mathValue, '-0.25', 'dynamic');   // вычесть 0.25
     * $this->applySelectedFee($mathValue, '*1.02', 'dynamic');   // умножить на 1.02
     *
     * // profit (всегда без знака):
     * $this->applySelectedFee($mathValue, '3%', 'profit');       // вычесть 3% (или прибавить при инверсии)
     * $this->applySelectedFee($mathValue, '1.75', 'profit');     // вычесть 1.75 (или прибавить при инверсии)
     */
    private function applySelectedFee(CalculatorMathService $mathValue, string $feeExpr, string $feeType): void
    {
        // 1) Нормализация входных данных
        $feeExpr = trim($feeExpr);
        $feeExpr = str_replace(',', '.', $feeExpr);             // поддержка локалей с запятой
        $feeType = strtolower(trim($feeType ?: 'dynamic'));     // страховка от null/пустых значений

        // Нечего применять
        if ($feeExpr === '' || $feeExpr === '0' || $feeExpr === '0%') {
            return;
        }

        // 2) Ветка: динамическое выражение
        if ($feeType === 'dynamic') {
            if (!$this->isValidFlexibleMathExpression($feeExpr)) {
                return; // некорректное выражение — игнорируем
            }

            $expr = $this->sanitizeFlexibleMathExpression($feeExpr);
            if ($expr === '' || $expr === '0' || !$this->isValidMathExpression($expr)) {
                return;
            }

            try {
                $mathValue->calculate($expr);
            } catch (\Throwable $e) {
                Log::warning('applySelectedFee: exception on dynamic expression', [
                    'expr'    => $expr,
                    'message' => $e->getMessage(),
                ]);
            }
            return;
        }

        // 3) Ветка: прибыль (profit)
        // В БД/опциях хранится БЕЗ знака. Применяем вручную (subtract... / add... при инверсии).
        // На всякий случай убираем ведущие знаки, если они вдруг есть.
        $clean = ltrim($feeExpr, "+- \t");

        // 3.1) Процентное значение: "10%" или "2.5%"
        if (str_ends_with(rtrim($clean), '%')) {
            // rtrim: снимаем сам '%', пробелы и управляющие символы в конце
            $raw = rtrim($clean, "% \t\n\r\0\x0B");

            // Санитизация числа с плавающей точкой
            $percent = filter_var($raw, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            if ($percent === false) {
                return;
            }

            $p = (float) $percent;
            if ($p <= 0.0) {
                return; // ноль/мусор — игнорируем
            }

            if ($this->isInverted) {
                $mathValue->addPercentage($p);
            } else {
                $mathValue->subtractPercentage($p);
            }
            return;
        }

        // 3.2) Фиксированное значение: "10" или "2.5"
        $fixed = $this->sanitizeNumber($clean); // float|string (bc‑точность)
        if (!$this->isGreaterThanZero($fixed)) {
            return;
        }

        if ($this->isInverted) {
            $mathValue->addFixedValue($fixed);
        } else {
            $mathValue->subtractFixedValue($fixed);
        }
    }

    /**
     * Определяет применимую комиссию из списка, основываясь на заданной сумме.
     *
     * @param string $amount Сумма обмена для проверки.
     * @param array $exchangeAmounts Массив с диапазонами комиссий вида ['from' => сумма, 'to' => сумма, 'fee' => комиссия].
     *
     * @return string|null Возвращает подходящую комиссию или null, если подходящих комиссий нет.
     */
    protected function getApplicableCommission(string $amount, array $exchangeAmounts): ?string
    {
        // Сортируем диапазоны по возрастанию значения "from"
        usort($exchangeAmounts, fn($a, $b) => $this->cmpNum($a['from'], $b['from'], 8));

        foreach ($exchangeAmounts as $range) {
            $from = (string) ($range['from'] ?? '0');
            $to = (string) ($range['to'] ?? '0');
            $fee = (string) ($range['fee'] ?? '0');

            if ($this->cmpNum($amount, $from, 8) >= 0 && $this->cmpNum($amount, $to, 8) <= 0) {
                return $fee;
            }
        }

        return null;
    }

    /**
     * Возвращает курс обмена в виде строки.
     *
     * Данный метод возвращает текущее значение курса обмена ($rateValue),
     * хранящееся в виде строки для обеспечения высокой точности вычислений.
     * Использование строки особенно важно при работе с криптовалютами,
     * где курсы могут иметь большое количество десятичных знаков.
     *
     * @return string Курс обмена в строковом формате, например: "0.0000123456"
     */
    public function getRateValue(): string
    {
        return $this->rateValue;
    }

    /**
     * Возвращает курс обмена, отформатированный с учетом специфики криптовалют и высокой точности.
     *
     * Данный метод предназначен для отображения курса с заданным количеством
     * знаков после запятой. Максимальное количество десятичных знаков ограничено
     * настройкой приложения (iEXSetting('max_decimal_places')).
     *
     * Шаги работы метода:
     * 1. Получаем максимально допустимое количество десятичных знаков из глобальных настроек.
     * 2. Получаем заданное количество знаков для конкретной валюты направления обмена.
     * 3. Ограничиваем количество знаков после запятой до меньшего из двух полученных значений.
     * 4. Очищаем текущее значение курса с помощью sanitizeNumber для безопасности и корректности.
     * 5. Безопасно форматируем итоговое значение курса через BCMath для точности.
     *
     * Пример результата: "0.00001234" или "1.23456789"
     *
     * @return string Отформатированное значение курса обмена.
     */
    public function getRateValueString(): string
    {
        // Максимально допустимое количество знаков после запятой из настроек
        $maxDecimals = (int) iEXSetting('max_decimal_places', 10);

        // Исходное количество знаков после запятой, установленное для валюты
        $currencyDecimals = (int) ($this->directionExchange->currency2->number_format ?? $maxDecimals);

        // Итоговое количество знаков после запятой не должно превышать максимально допустимое
        $decimals = min($currencyDecimals, $maxDecimals);

        // Используем sanitizeNumber, чтобы гарантировать корректность
        $sanitizedValue = $this->sanitizeNumber($this->rateValue);

        // Безопасное форматирование числа с использованием BCMath
        return $this->formatDecimal($sanitizedValue, $decimals);
    }

    /**
     * Возвращает курс обмена в удобном, читабельном формате с учетом высокой точности для криптовалют.
     *
     * Метод учитывает особенности отображения курса:
     * - Для курсов, близких к 0 или равных 0, возвращает явное указание, что курс равен 0.
     * - Для курсов меньше 1, отображает обратный курс, чтобы облегчить восприятие мелких значений.
     * - В остальных случаях отображает курс в стандартном формате: 1 Валюта1 = X Валюта2.
     *
     * Шаги работы метода:
     * 1. Получает максимально допустимую точность из настроек (maxDecimal).
     * 2. Проверяет, что все необходимые данные о валютных знаках доступны.
     * 3. Очищает значение курса ($rate) и приводит к нужному формату.
     * 4. Определяет количество знаков после запятой для каждой валюты.
     * 5. Сравнивает текущий курс с 0 и 1, используя BCMath для безопасного вычисления:
     *    - Если курс <= 0, возвращает "1 Валюта1 = 0 Валюта2".
     *    - Если курс меньше 1, возвращает обратный курс (например, "1000 BTC = 1 USD").
     *    - Иначе возвращает стандартный формат курса (например, "1 USD = 0.000025 BTC").
     *
     * Примеры возможных результатов:
     * - "1 USD = 0 BTC" (если курс равен 0)
     * - "50000 USD = 1 BTC" (для малых значений)
     * - "1 BTC = 35000 USD" (стандартный формат)
     *
     * @return string Читабельное и безопасное отображение курса.
     */
    public function getFullRate(): string
    {
        $maxDecimal = (int)iEXSetting('max_decimal_places', 18);

        // Проверяем доступность всех данных
        if (
            empty($this->directionExchange?->currency1?->code_currency?->name) ||
            empty($this->directionExchange?->currency2?->code_currency?->name)
        ) {
            return 'Курс недоступен';
        }

        // Явно приводим sanitizedNumber к строке
        $rate = (string)$this->sanitizeNumber($this->rateValue);

        $currencyFromSign = (string)$this->directionExchange->currency1->code_currency->name;
        $currencyToSign = (string)$this->directionExchange->currency2->code_currency->name;

        $currencyFromDecimals = $this->adjustNumberFormat(
            (int)$this->directionExchange->currency1->number_format,
            $maxDecimal
        );

        $currencyToDecimals = $this->adjustNumberFormat(
            (int)$this->directionExchange->currency2->number_format,
            $maxDecimal
        );

        if ($this->cmpNum($rate, '0', $maxDecimal) <= 0) {
            return sprintf('1 %s = 0 %s', $currencyFromSign, $currencyToSign);
        }

        $roundBcmath = function ($number, $decimals) {
            $factor = bcpow('10', (string)($decimals + 1));
            $temp = bcmul($number, $factor, 0); // Убираем дробную часть полностью
            $lastDigit = (int)substr($temp, -1); // Последняя цифра

            $roundUp = $lastDigit >= 5 ? 1 : 0;
            $result = bcdiv(bcadd(bcmul($number, bcpow('10', (string)$decimals), 0), (string)$roundUp, 0), bcpow('10', (string)$decimals), $decimals);

            return $result;
        };

        // Для малых значений (меньше 1)
        if ($this->cmpNum($rate, '1', $maxDecimal) === -1) {
            $inverseRate = $this->invertNum($rate, $maxDecimal);
            $inverseRateRounded = $roundBcmath($inverseRate, $currencyFromDecimals);

            return sprintf(
                '%s %s = 1 %s',
                $this->safeNumberFormat($inverseRateRounded, $currencyFromDecimals, true),
                $currencyFromSign,
                $currencyToSign
            );
        }

        $rateRounded = $roundBcmath($rate, $currencyToDecimals);

        return sprintf(
            '1 %s = %s %s',
            $currencyFromSign,
            $this->safeNumberFormat($rateRounded, $currencyToDecimals, true),
            $currencyToSign
        );
    }

    public function setDefaultRate(string $rate): static
    {
        $this->config['default_rate'] = $rate;
        return $this;
    }


    /**
     * Преобразует текущее состояние калькулятора в массив.
     *
     * Возвращаемые данные включают:
     * - rateValue: текущее значение курса обмена
     * - options: дополнительные параметры и комиссии, примененные при расчёте
     *
     * @return array Ассоциативный массив текущего состояния объекта.
     */
    public function toArray(): array
    {
        return [
            'rate' => (string)$this->getRateValue(),
            ...$this->getOptions(),
        ];
    }
}
