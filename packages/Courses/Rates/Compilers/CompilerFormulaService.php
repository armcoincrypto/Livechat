<?php
declare(strict_types=1);

namespace iEXPackages\Courses\Rates\Compilers;

use App\Models\ParserFormulaCoefficient;
use App\Models\ParserFormulaRates;
use App\Services\AggregatedRatesService;
use Carbon\Carbon;
use iEXPackages\Courses\FormulaTags\Handlers\AvgTagHandler;
use iEXPackages\Courses\FormulaTags\Handlers\BestRateTagHandler;
use iEXPackages\Courses\FormulaTags\Handlers\CombineTagHandler;
use iEXPackages\Courses\FormulaTags\Handlers\ConfidenceTagHandler;
use iEXPackages\Courses\FormulaTags\Handlers\CurrencyConvertTagHandler;
use iEXPackages\Courses\FormulaTags\Handlers\DecimalTagHandler;
use iEXPackages\Courses\FormulaTags\Handlers\DiffPercentTagHandler;
use iEXPackages\Courses\FormulaTags\Handlers\DiscountTagHandler;
use iEXPackages\Courses\FormulaTags\Handlers\FallbackTagHandler;
use iEXPackages\Courses\FormulaTags\Handlers\FeesTagHandler;
use iEXPackages\Courses\FormulaTags\Handlers\FilterTagHandler;
use iEXPackages\Courses\FormulaTags\Handlers\IfTagHandler;
use iEXPackages\Courses\FormulaTags\Handlers\InverseTagHandler;
use iEXPackages\Courses\FormulaTags\Handlers\LatestRateTagHandler;
use iEXPackages\Courses\FormulaTags\Handlers\LimitTagHandler;
use iEXPackages\Courses\FormulaTags\Handlers\MarkupTagHandler;
use iEXPackages\Courses\FormulaTags\Handlers\MaxTagHandler;
use iEXPackages\Courses\FormulaTags\Handlers\MinTagHandler;
use iEXPackages\Courses\FormulaTags\Handlers\PriorityRateTagHandler;
use iEXPackages\Courses\FormulaTags\Handlers\RatioTagHandler;
use iEXPackages\Courses\FormulaTags\Handlers\RoundTagHandler;
use iEXPackages\Courses\FormulaTags\Handlers\SetVariableTagHandler;
use iEXPackages\Courses\FormulaTags\Handlers\SpreadTagHandler;
use iEXPackages\Courses\FormulaTags\Handlers\System1TagHandler;
use iEXPackages\Courses\FormulaTags\Handlers\ThresholdTagHandler;
use iEXPackages\Courses\FormulaTags\Handlers\WorstRateTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;
use iEXPackages\Courses\Services\RatesLoggerService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CompilerFormulaService
{
    /**
     * Размер батча для upsert.
     *
     * Причина:
     * - слишком маленькие чанки (100) создают лишние SQL-запросы
     * - слишком большие чанки могут раздувать пакет и упираться в лимиты
     */
    private const UPSERT_CHUNK_SIZE = 800;

    private RatesLoggerService $logger;

    public function __construct(RatesLoggerService $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Компиляция всех активных формул.
     *
     * Основные оптимизации:
     * - один экземпляр FormulaParserService на весь прогон
     * - один Carbon::now() на весь прогон (а не в каждой строке)
     * - upsert более крупными чанками
     * - логи накапливаются и вставляются одним запросом через flush()
     */
    public function handle(): void
    {
        $now = Carbon::now();

        $templates = $this->fetchTemplates();
        $defaultRates = $this->fetchDefaultRates();

        $service = new FormulaParserService($defaultRates, $templates);
        $this->registerAllTagHandlers($service);

        $parsers = ParserFormulaRates::query()
            ->where('status', 1)
            ->cursor();

        $batchUpdate = $this->processParsers($parsers, $service, $now);

        $this->batchUpdateRates($batchUpdate);

        $this->logger->flush();
    }

    /**
     * Вычисляет формулу в режиме "одиночного теста" и отдаёт вспомогательные данные по тегам.
     */
    public function getFormulaResult(string $formulaName): array
    {
        $templates = $this->fetchTemplates();
        $defaultRates = $this->fetchDefaultRates();

        $service = new FormulaParserService($defaultRates, $templates);
        $this->registerAllTagHandlers($service);

        preg_match_all('/\[(\w[\w\-_]+)\]/', $formulaName, $matches);
        $allTags = $matches[0];

        $tagsCount = array_count_values($allTags);

        $tagsValues = [];
        foreach ($tagsCount as $tag => $count) {
            if (isset($defaultRates[$tag])) {
                $tagsValues[$tag] = [
                    'value' => $defaultRates[$tag],
                    'count' => $count,
                ];
            }
        }

        try {
            $result = $service->calculate($formulaName);

            return [
                'success' => true,
                'formula_name' => $formulaName,
                'result' => $result,
                'resultNumber' => removeTrailingZeros(iex_number_format((float) $result, 10, true)),
                'tags_values' => $tagsValues,
            ];
        } catch (\Throwable $e) {
            Log::error("Ошибка расчета формулы [{$formulaName}]: {$e->getMessage()}");

            return [
                'success' => false,
                'formula_name' => $formulaName,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Регистрирует все обработчики тегов.
     *
     * Важно:
     * - регистрации делаем один раз на прогон
     * - сами обработчики можно держать как новые инстансы, они лёгкие
     * - если внутри обработчиков есть тяжёлые зависимости, лучше внедрять их через DI
     */
    private function registerAllTagHandlers(FormulaParserService $service): void
    {
        $service->registerTagHandler('set', new SetVariableTagHandler());
        $service->registerTagHandler('decimal', new DecimalTagHandler());
        $service->registerTagHandler('system-1', new System1TagHandler());
        $service->registerTagHandler('if', new IfTagHandler());
        $service->registerTagHandler('avg', new AvgTagHandler());
        $service->registerTagHandler('min', new MinTagHandler());
        $service->registerTagHandler('max', new MaxTagHandler());
        $service->registerTagHandler('diff_percent', new DiffPercentTagHandler());
        $service->registerTagHandler('round', new RoundTagHandler());
        $service->registerTagHandler('spread', new SpreadTagHandler());
        $service->registerTagHandler('filter', new FilterTagHandler());
        $service->registerTagHandler('currency_convert', new CurrencyConvertTagHandler());
        $service->registerTagHandler('threshold', new ThresholdTagHandler());
        $service->registerTagHandler('fees', new FeesTagHandler());
        $service->registerTagHandler('limit', new LimitTagHandler());
        $service->registerTagHandler('confidence', new ConfidenceTagHandler());
        $service->registerTagHandler('ratio', new RatioTagHandler());
        $service->registerTagHandler('fallback', new FallbackTagHandler());
        $service->registerTagHandler('combine', new CombineTagHandler());
        $service->registerTagHandler('inverse', new InverseTagHandler());
        $service->registerTagHandler('markup', new MarkupTagHandler());
        $service->registerTagHandler('discount', new DiscountTagHandler());
        $service->registerTagHandler('latest_rate', new LatestRateTagHandler());
        $service->registerTagHandler('priority_rate', new PriorityRateTagHandler());
        $service->registerTagHandler('worst_rate', new WorstRateTagHandler());
        $service->registerTagHandler('best_rate', new BestRateTagHandler());
    }

    /**
     * Загружает шаблоны коэффициентов.
     *
     * Возвращает ассоциативный массив вида:
     * - alias => template
     */
    private function fetchTemplates(): array
    {
        return ParserFormulaCoefficient::query()
            ->where('type_index', 1)
            ->pluck('template', 'alias')
            ->toArray();
    }

    /**
     * Забирает внешний набор значений (курсы), которые используются как базовые переменные формул.
     */
    private function fetchDefaultRates(): array
    {
        $externalRates = app(AggregatedRatesService::class)->fetch();

        return is_array($externalRates) ? $externalRates : [];
    }

    /**
     * Проходит по всем формулам и готовит батч на обновление.
     *
     * Оптимизация:
     * - updated_at фиксированный (один $now), не создаём Carbon для каждой строки
     * - логирование идёт через batchLog() и будет сохранено одним запросом в конце
     */
    private function processParsers(iterable $parsers, FormulaParserService $service, Carbon $now): array
    {
        $batchUpdate = [];

        foreach ($parsers as $parser) {
            $isErrorUpdate = 0;

            try {
                $response = $service->calculate((string) $parser->name);
            } catch (\Throwable $e) {
                Log::error("Ошибка расчета формулы для [{$parser->name}]: {$e->getMessage()}");
                $response = (string) $parser->summa;
                $isErrorUpdate = 1;
            }

            $this->logger->batchLog(
                'formula',
                (int) $parser->id,
                (string) $parser->title,
                (string) $parser->summa,
                (string) $response
            );

            $batchUpdate[] = $this->createBatchEntry($parser->id, $response, $isErrorUpdate, $now);
        }

        return $batchUpdate;
    }

    /**
     * Формирует запись для массового upsert.
     *
     * Важно:
     * - updated_at уже готовый объект времени ($now)
     * - все типы приводим к строке/числу, чтобы избежать сюрпризов при записи
     */
    private function createBatchEntry(int $id, string $response, int $isErrorUpdate, Carbon $now): array
    {
        return [
            'id' => $id,
            'is_error_update' => $isErrorUpdate,
            'value' => 1,
            'summa' => $response,
            'updated_at' => $now,
        ];
    }

    /**
     * Массово обновляет рассчитанные значения.
     *
     * Оптимизация:
     * - увеличенный размер чанка уменьшает число SQL-запросов
     * - upsert остаётся безопасным и идемпотентным
     */
    private function batchUpdateRates(array $batchUpdate): void
    {
        if ($batchUpdate === []) {
            return;
        }

        foreach (array_chunk($batchUpdate, self::UPSERT_CHUNK_SIZE) as $chunk) {
            ParserFormulaRates::query()->upsert(
                $chunk,
                ['id'],
                ['is_error_update', 'value', 'summa', 'updated_at']
            );
        }
    }

    public function getTagsCatalog(bool $withVariables = true): array
    {
        /**
         * Формирует и возвращает каталог тегов для фронтенда.
         *
         * Структура каталога:
         *  - functions      → сгруппированные функции
         *  - variables      → курсы и переменные
         *  - coefficients   → коэффициенты/шаблоны
         *
         * Оптимизация:
         *  - Кеширование 5 минут
         *  - Группировка источников один раз
         */

        return Cache::remember(
            'iex_formula_tags_catalog_v5',
            now()->addMinutes(5),
            function () use ($withVariables): array {

                $catalog = [
                    'functions' => $this->getFunctionTagsGrouped(),
                ];

                if (!$withVariables) {
                    return $catalog;
                }

                $templates = $this->fetchTemplates();
                $rates     = $this->fetchDefaultRates();

                $variableItems = $this->mapVariablesForUi($rates);

                $sources = [];
                $groups  = [];

                foreach ($variableItems as $item) {

                    $key   = $item['source_key'] ?? 'unknown';
                    $title = $item['source'] ?? 'UNKNOWN';

                    if (!isset($sources[$key])) {
                        $sources[$key] = [
                            'source_key' => $key,
                            'source'     => $title,
                            'icon'       => $item['icon'],
                            'count'      => 0,
                        ];
                    }

                    $sources[$key]['count']++;

                    if (!isset($groups[$key])) {
                        $groups[$key] = [
                            'source_key' => $key,
                            'source'     => $title,
                            'icon'       => $item['icon'],
                            'count'      => 0,
                            'items'      => [],
                        ];
                    }

                    $groups[$key]['count']++;
                    $groups[$key]['items'][] = $item;
                }

                $catalog['variables'] = [
                    'title'   => 'Переменные (курсы)',
                    'items'   => $variableItems,
                    'sources' => array_values($sources),
                    'groups'  => array_values($groups),
                ];

                $catalog['coefficients'] = [
                    'title' => 'Коэффициенты / шаблоны',
                    'items' => $this->mapTemplatesForUi($templates),
                ];

                return $catalog;
            }
        );
    }

    /**
     * Нормализует ключ курса и определяет источник по префиксу.
     *
     * Примеры входа:
     * - "bestchange_btc_usdttrc20_4"
     * - "[bestchange_btc_usdttrc20_4]"
     * - "binance_btc-eth"
     * - "competitors_asd444_btc-111"
     * - "fileparser_something"
     *
     * Возвращает:
     * - code: строка без внешних скобок
     * - tag: строка в формате "[code]"
     * - source_key: системный ключ источника (bestchange/binance/competitors/fileparser/...)
     * - source_title: человеко-понятное название источника
     * - label: краткая подпись (пара/имя тега)
     *
     * @param string $rawKey
     * @return array{code:string,tag:string,source_key:string,source_title:string,label:string}
     */
    private function normalizeRateKey(string $rawKey): array
    {
        /**
         * Нормализует ключ курса.
         *
         * Поддерживает:
         *  - CODE
         *  - [CODE]
         *  - CODE[decimal:2]
         *  - [CODE][decimal:2]
         */

        $rawKey = trim($rawKey);

        // Убираем внешние скобки
        if (str_starts_with($rawKey, '[') && str_ends_with($rawKey, ']')) {
            $rawKey = substr($rawKey, 1, -1);
        }

        // Удаляем decimal модификатор если присутствует
        $rawKey = preg_replace('/\[decimal:\d+\]/i', '', $rawKey);

        $code = trim($rawKey);

        $sourceKey = 'unknown';
        $rest = $code;

        if (($pos = strpos($code, '_')) !== false) {
            $sourceKey = strtolower(substr($code, 0, $pos));
            $rest      = substr($code, $pos + 1);
        }

        $sourceMap = [
            'bestchange'  => 'BestChange',
            'fileparser'  => 'Курсы из файла',
            'competitors' => 'Курсы конкурентов',
        ];

        $sourceTitle = $sourceMap[$sourceKey] ?? strtoupper($sourceKey);

        $iconPath = '/images/parsers/' . $sourceKey . '.png';
        $publicPath = public_path(ltrim($iconPath, '/'));
        $icon = is_file($publicPath) ? $iconPath : null;

        $label = $rest ?: $code;

        if (str_contains($label, '-')) {
            [$a, $b] = explode('-', $label, 2);
            $label = strtoupper($a) . ' - ' . strtoupper($b);
        } elseif (str_contains($label, '_')) {
            $parts = array_filter(explode('_', $label));
            $parts = array_map('strtoupper', $parts);
            $label = implode(' - ', $parts);
        } else {
            $label = strtoupper($label);
        }

        return [
            'code'       => $code,
            'tag'        => '[' . $code . ']',
            'source_key' => $sourceKey,
            'source'     => $sourceTitle,
            'icon'       => $icon,
            'label'      => $label,
        ];
    }

    private function mapVariablesForUi(array $rates): array
    {
        /**
         * Преобразует массив курсов в структуру,
         * удобную для фронтенда.
         */

        $items = [];

        foreach ($rates as $code => $value) {

            if (!is_string($code)) {
                continue;
            }

            $meta = $this->normalizeRateKey($code);

            $items[] = [
                'tag'        => $meta['tag'],
                'value'      => (string) $value,
                'source_key' => $meta['source_key'],
                'source'     => $meta['source'],
                'icon'       => $meta['icon'],
                'label'      => $meta['label'],
            ];
        }

        usort($items, fn($a, $b) =>
            [$a['source_key'], $a['tag']] <=> [$b['source_key'], $b['tag']]
        );

        return $items;
    }

    private function mapTemplatesForUi(array $templates): array
    {
        /**
         * Преобразует коэффициенты в список
         * для UI-отображения.
         */

        $items = [];

        foreach ($templates as $alias => $template) {

            if (!is_string($alias) || trim($alias) === '') {
                continue;
            }

            $items[] = [
                'alias'    => $alias,
                'template' => (string) $template,
            ];
        }

        usort($items, fn($a, $b) => strcmp($a['alias'], $b['alias']));

        return $items;
    }

    private function getFunctionTagsGrouped(): array
    {
        /**
         * Возвращает список поддерживаемых функций.
         * Используется в UI для построения редактора формул.
         */

        return [

            [
                'group' => 'format',
                'title' => 'Форматирование',
                'items' => [
                    [
                        'name' => 'decimal',
                        'label' => 'Ограничение количества знаков',
                        'syntax' => '[decimal]2:VALUE[/decimal]',
                        'example' => '[decimal]2:[btc][/decimal]',
                    ],
                    [
                        'name' => 'decimal_modifier',
                        'label' => 'Модификатор точности',
                        'syntax' => '[CODE][decimal:2]',
                        'example' => '[bestchange_btc_usdt][decimal:2]',
                    ],
                    [
                        'name' => 'round',
                        'label' => 'Округление',
                        'example' => '[round]5,[btc][/round]',
                    ],
                ],
            ],

            [
                'group' => 'logic',
                'title' => 'Условия',
                'items' => [
                    ['name' => 'if', 'label' => 'Условие', 'example' => '[if]cond,a,b[/if]'],
                    ['name' => 'fallback', 'label' => 'Fallback'],
                    ['name' => 'limit', 'label' => 'Ограничение'],
                    ['name' => 'threshold', 'label' => 'Порог'],
                ],
            ],

            [
                'group' => 'math',
                'title' => 'Математика',
                'items' => [
                    ['name' => 'avg', 'label' => 'Среднее'],
                    ['name' => 'min', 'label' => 'Минимум'],
                    ['name' => 'max', 'label' => 'Максимум'],
                    ['name' => 'ratio', 'label' => 'Соотношение'],
                    ['name' => 'diff_percent', 'label' => 'Разница в %'],
                ],
            ],

            [
                'group' => 'adjustments',
                'title' => 'Надбавки и комиссии',
                'items' => [
                    ['name' => 'fees', 'label' => 'Комиссии'],
                    ['name' => 'markup', 'label' => 'Надбавка'],
                    ['name' => 'discount', 'label' => 'Скидка'],
                    ['name' => 'spread', 'label' => 'Спред'],
                ],
            ],
        ];
    }
}
