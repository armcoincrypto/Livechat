<?php

declare(strict_types=1);

namespace iEXPackages\Courses\Rates\Compilers;

use App\Models\GroupParserExchange;
use App\Models\ParserExchange;
use Carbon\Carbon;
use iEXPackages\Calculator\Traits\InteractsWithNumbers;
use iEXPackages\Courses\Services\DefaultParserService;
use iEXPackages\Courses\Services\RatesLoggerService;
use iEXPackages\Proxy\Facades\ProxyFacade;
use iEXPackages\Proxy\Models\Proxy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * CompilerDefaultService
 *
 * Назначение:
 * - Обновить курсы парсеров "DEFAULT" на основании данных внешних источников.
 *
 * Требования:
 * - Максимальная устойчивость: один проблемный источник/парсер не должен ломать обновление остальных.
 * - Максимальная производительность:
 *   - минимум операций внутри циклов
 *   - batched upsert
 *   - last_updated_at одним запросом
 *   - минимум вызовов Carbon::now()
 */
final class CompilerDefaultService
{
    use InteractsWithNumbers;

    /**
     * Максимальный размер пакета для upsert в ParserExchange.
     */
    private const int UPSERT_BATCH_SIZE = 500;

    public function __construct(
        private readonly RatesLoggerService $logger,
        private readonly DefaultParserService $defaultParserService,
    ) {}

    /**
     * Запустить компиляцию курсов.
     */
    public function handle(): void
    {
        $now = Carbon::now();

        $parsers = $this->loadActiveParsers();

        if ($parsers->isEmpty()) {
            $this->logger->flush();
            return;
        }

        /** @var Collection<string, Collection<int, ParserExchange>> $groupedParsers */
        $groupedParsers = $parsers->groupBy(
            static fn (ParserExchange $parser): string => (string) $parser->group_parse_exchange->alias
        );

        if ($groupedParsers->isEmpty()) {
            $this->logger->flush();
            return;
        }

        /**
         * 1) Готовим proxy options по alias (1 проход + 1 запрос к Proxy).
         */
        $options = $this->buildParserGroupOptionsWithProxies($groupedParsers);

        /**
         * 2) Один fetch по всем alias.
         *    На каждый alias вернётся массив пар или ['error' => ...]
         */
        $ratesByAlias = $this->defaultParserService->fetch(
            $groupedParsers->keys()->all(),
            $options
        );

        /**
         * 3) Батч обновлений ParserExchange (upsert).
         *
         * @var array<int, array<string, mixed>> $updates
         */
        $updates = [];

        /**
         * 4) Статистика по группам для записи в group_parser_exchange.
         *    Ключ — ID группы (group_parse_exchange->id), чтобы не зависеть от alias.
         *
         * @var array<int, array{duration_ms:int,total:int,updated:int,errors:int}>
         */
        $groupStats = [];

        foreach ($groupedParsers as $alias => $parsersGroup) {
            $alias = (string) $alias;

            // Старт измерения времени по группе
            $t0 = microtime(true);

            // total = сколько пар в группе
            $total = $parsersGroup->count();
            $updated = 0;
            $errors = 0;

            /** @var ParserExchange|null $first */
            $first = $parsersGroup->first();
            $groupId = (int) ($first?->group_parse_exchange?->id ?? 0);

            // Ответ по группе
            $groupRates = $ratesByAlias[$alias] ?? null;

            /**
             * Если fetch вернул ошибку/пусто — считаем, что группа не обновилась:
             * errors = total, updated = 0
             * Но статистику всё равно пишем, чтобы в админке было видно, что группа пыталась обновиться и упала.
             */
            if (!is_array($groupRates) || $groupRates === [] || isset($groupRates['error'])) {
                $errors = $total;
            } else {
                /**
                 * Важно: processGroup() возвращает только touched, но не даёт счётчиков.
                 * Поэтому здесь считаем updated/errors вручную через обёртку:
                 * - updated++ если type0/type1 реально обновил
                 * - errors++ иначе
                 */
                /** @var Collection<string, ParserExchange> $parserByName */
                $parserByName = $parsersGroup->keyBy('name');

                foreach ($parsersGroup as $parser) {
                    try {
                        $type = (int) $parser->type;

                        if ($type === 0) {
                            $ok = $this->processTypeZero($parser, $groupRates, $updates, $now);
                            $ok ? $updated++ : $errors++;
                            continue;
                        }

                        if ($type === 1) {
                            $ok = $this->processTypeOne($parser, $parserByName, $updates, $now);
                            $ok ? $updated++ : $errors++;
                            continue;
                        }

                        // неизвестный тип
                        $updates[] = [
                            'id' => (int) $parser->id,
                            'is_not_update' => 1,
                            'updated_at' => $now,
                        ];
                        $errors++;

                    } catch (Throwable $e) {
                        $updates[] = [
                            'id' => (int) $parser->id,
                            'is_not_update' => 1,
                            'updated_at' => $now,
                        ];
                        $errors++;

                        Log::error('Ошибка при обработке парсера', [
                            'alias' => $alias,
                            'parser_id' => (int) $parser->id,
                            'parser_name' => (string) $parser->name,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Длительность обработки группы (в мс)
            $durationMs = (int) round((microtime(true) - $t0) * 1000);

            // Запоминаем статистику по group_id (если есть)
            if ($groupId > 0) {
                $groupStats[$groupId] = [
                    'duration_ms' => $durationMs,
                    'total' => $total,
                    'updated' => $updated,
                    'errors' => $errors,
                ];
            }

            // Пишем ParserExchange батчами
            if (count($updates) >= self::UPSERT_BATCH_SIZE) {
                $this->flushUpsert($updates, $now);
                $updates = [];
            }
        }

        // Финальный flush по ParserExchange
        if ($updates !== []) {
            $this->flushUpsert($updates, $now);
        }

        /**
         * 5) Записываем статистику по группам одним запросом (CASE UPDATE).
         *    Это как раз то, что нужно, чтобы админка начала показывать новые данные.
         */
        $this->flushGroupStatsById($groupStats, $now);

        $this->logger->flush();
    }

    private function flushGroupStatsById(array $groupStats, Carbon $now): void
    {
        if ($groupStats === []) {
            return;
        }

        $ids = array_keys($groupStats);

        $caseDuration = 'CASE id';
        $caseTotal    = 'CASE id';
        $caseUpdated  = 'CASE id';
        $caseErrors   = 'CASE id';

        foreach ($groupStats as $id => $s) {
            $id = (int)$id;
            $caseDuration .= " WHEN {$id} THEN " . (int)$s['duration_ms'];
            $caseTotal    .= " WHEN {$id} THEN " . (int)$s['total'];
            $caseUpdated  .= " WHEN {$id} THEN " . (int)$s['updated'];
            $caseErrors   .= " WHEN {$id} THEN " . (int)$s['errors'];
        }

        $caseDuration .= ' END';
        $caseTotal    .= ' END';
        $caseUpdated  .= ' END';
        $caseErrors   .= ' END';

        DB::table('group_parser_exchange')
            ->whereIn('id', $ids)
            ->update([
                'last_updated_at'  => $now,
                'last_duration_ms' => DB::raw($caseDuration),
                'last_total'       => DB::raw($caseTotal),
                'last_updated'     => DB::raw($caseUpdated),
                'last_errors'      => DB::raw($caseErrors),
            ]);
    }

    /**
     * Получить список активных парсеров с группой.
     *
     * @return Collection<int, ParserExchange>
     */
    protected function loadActiveParsers(): Collection
    {
        return ParserExchange::query()
            ->with('group_parse_exchange')
            ->where('status', 1)
            ->whereHas('group_parse_exchange', static function ($query): void {
                $query->where('status', 1);
            })
            ->get();
    }

    /**
     * Обработать одну группу alias и подготовить обновления.
     *
     * @param string $alias
     * @param Collection<int, ParserExchange> $parsersGroup
     * @param array<string, mixed> $groupRates
     * @param array<int, array<string, mixed>> $updates
     * @param Carbon $now
     *
     * @return bool true если были успешные обновления
     */
    protected function processGroup(
        string $alias,
        Collection $parsersGroup,
        array $groupRates,
        array &$updates,
        Carbon $now
    ): bool {
        $touched = false;

        /** @var Collection<string, ParserExchange> $parserByName */
        $parserByName = $parsersGroup->keyBy('name');

        foreach ($parsersGroup as $parser) {
            try {
                $type = (int) $parser->type;

                if ($type === 0) {
                    $touched = $this->processTypeZero($parser, $groupRates, $updates, $now) || $touched;
                    continue;
                }

                if ($type === 1) {
                    $touched = $this->processTypeOne($parser, $parserByName, $updates, $now) || $touched;
                    continue;
                }

                $updates[] = [
                    'id' => (int) $parser->id,
                    'is_not_update' => 1,
                    'updated_at' => $now,
                ];
            } catch (Throwable $e) {
                $updates[] = [
                    'id' => (int) $parser->id,
                    'is_not_update' => 1,
                    'updated_at' => $now,
                ];

                Log::error('Ошибка при обработке парсера', [
                    'alias' => $alias,
                    'parser_id' => (int) $parser->id,
                    'parser_name' => (string) $parser->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $touched;
    }

    /**
     * type=0: курс берётся напрямую из ответа источника.
     *
     * @return bool true если обновили курс
     */
    protected function processTypeZero(
        ParserExchange $parser,
        array $groupRates,
        array &$updates,
        Carbon $now
    ): bool {
        $normalizedName = $this->normalizeParserName((string) $parser->name);

        if ($normalizedName === '' || !array_key_exists($normalizedName, $groupRates)) {
            return false;
        }

        $rate = $groupRates[$normalizedName];

        if (is_array($rate)) {
            $typePrice = strtolower((string) ($parser->type_price ?? 'default'));
            $rate = $rate[$typePrice] ?? ($rate['default'] ?? null);
        }

        $formattedRate = $this->formatRate(
            (is_string($rate) || is_numeric($rate)) ? (string) $rate : null,
            (int) $parser->number_format
        );

        if ($formattedRate === '0') {
            $updates[] = [
                'id' => (int) $parser->id,
                'is_not_update' => 1,
                'updated_at' => $now,
            ];
            return false;
        }

        $this->logger->batchLog(
            'source',
            (int) $parser->id,
            (string) $parser->name,
            $parser->summa !== null ? (string) $parser->summa : null,
            $formattedRate
        );

        $updates[] = [
            'id' => (int) $parser->id,
            'summa' => $formattedRate,
            'is_not_update' => 0,
            'updated_at' => $now,
        ];

        return true;
    }

    /**
     * type=1: курс вычисляется как обратный к связанной паре.
     *
     * @return bool true если обновили курс
     */
    protected function processTypeOne(
        ParserExchange $parser,
        Collection $parserByName,
        array &$updates,
        Carbon $now
    ): bool {
        $pair = $this->parseCurrencyPairLoose((string) $parser->name);

        if ($pair === null) {
            $updates[] = [
                'id' => (int) $parser->id,
                'is_not_update' => 1,
                'updated_at' => $now,
            ];
            return false;
        }

        [$from, $to] = $pair;

        $reversePairName = trim($to) . ' - ' . trim($from);

        /** @var ParserExchange|null $relatedParser */
        $relatedParser = $parserByName->get($reversePairName);
        $relatedRate = $relatedParser?->summa;

        if ($relatedParser === null || $relatedRate === null || trim((string) $relatedRate) === '') {
            $updates[] = [
                'id' => (int) $parser->id,
                'is_not_update' => 1,
                'updated_at' => $now,
            ];
            return false;
        }

        $relatedRateBc = $this->toBcString((string) $relatedRate, 18);

        if (bccomp($relatedRateBc, '0', 18) !== 1) {
            $updates[] = [
                'id' => (int) $parser->id,
                'is_not_update' => 1,
                'updated_at' => $now,
            ];
            return false;
        }

        $inverseRaw = bcdiv('1', $relatedRateBc, 18);

        $resultAmount = $this->formatRate($inverseRaw, (int) $parser->number_format);

        if ($resultAmount === '0') {
            $updates[] = [
                'id' => (int) $parser->id,
                'is_not_update' => 1,
                'updated_at' => $now,
            ];
            return false;
        }

        $updates[] = [
            'id' => (int) $parser->id,
            'summa' => $resultAmount,
            'is_not_update' => 0,
            'updated_at' => $now,
        ];

        $this->logger->batchLog(
            'source',
            (int) $parser->id,
            (string) $parser->name,
            $parser->summa !== null ? (string) $parser->summa : null,
            $resultAmount
        );

        return true;
    }

    /**
     * Форматирование курса.
     *
     * Правила:
     * - null/пусто/нечисловое => "0"
     * - <=0 => "0"
     * - number_format > 8 => через bc для точности (до 18)
     * - иначе быстрый number_format
     */
    private function formatRate(?string $rate, int $numberFormat = 8): string
    {
        $rate = $rate !== null ? trim($rate) : '';

        if ($rate === '' || !is_numeric($rate)) {
            return '0';
        }

        if ((float) $rate <= 0.0) {
            return '0';
        }

        if ($numberFormat > 8) {
            $scale = min(max(0, $numberFormat), 18);

            $rateBc = $this->toBcString($rate, 18);

            if (bccomp($rateBc, '0', 18) !== 1) {
                return '0';
            }

            $value = bcdiv($rateBc, '1', $scale);
            $value = rtrim(rtrim($value, '0'), '.');

            return $value === '' ? '0' : $value;
        }

        $value = number_format((float) $rate, max(0, $numberFormat), '.', '');
        $value = rtrim(rtrim($value, '0'), '.');

        return $value === '' ? '0' : $value;
    }

    /**
     * Подготовить опции прокси по alias (один запрос по Proxy).
     *
     * @param Collection<string, Collection<int, ParserExchange>> $groupedParsers
     * @return array<string, array<string, mixed>>
     */
    protected function buildParserGroupOptionsWithProxies(Collection $groupedParsers): array
    {
        $proxyIds = $groupedParsers
            ->map(static function (Collection $group): int {
                /** @var ParserExchange $first */
                $first = $group->first();
                return (int) ($first->group_parse_exchange->proxy_id ?? 0);
            })
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $proxies = Proxy::query()
            ->whereIn('id', $proxyIds)
            ->get(['id', 'host', 'port', 'type', 'status'])
            ->keyBy('id');

        $options = [];

        foreach ($groupedParsers as $alias => $parsersGroup) {
            /** @var ParserExchange $first */
            $first = $parsersGroup->first();
            $group = $first->group_parse_exchange;

            $proxyId = (int) ($group->proxy_id ?? 0);

            $proxyUrl = null;

            if ($proxyId > 0 && $proxies->has($proxyId)) {
                /** @var Proxy $proxy */
                $proxy = $proxies->get($proxyId);

                if ($this->isValidProxy($proxy)) {
                    $proxyUrl = ProxyFacade::urlById($proxyId);
                }
            }

            $options[(string) $alias] = [
                'proxy_id' => $proxyId > 0 ? $proxyId : null,
                'proxy_url' => $proxyUrl,
            ];
        }

        return $options;
    }

    /**
     * Проверка валидности прокси.
     */
    protected function isValidProxy(Proxy $proxy): bool
    {
        $validTypes = ['http', 'https', 'socks4', 'socks5'];

        return
            $proxy->status === true
            && trim((string) $proxy->host) !== ''
            && (int) $proxy->port > 0
            && in_array(strtolower((string) $proxy->type), $validTypes, true);
    }

    /**
     * "USD - RUB" -> "USDRUB"
     */
    protected function normalizeParserName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return '';
        }

        return strtoupper(str_replace(['-', '–', '—', ' '], '', $name));
    }

    /**
     * Разбор пары "AAA - BBB" с большей устойчивостью к форматам тире/пробелов.
     *
     * @return array{0:string,1:string}|null
     */
    protected function parseCurrencyPairLoose(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $norm = str_replace(['–', '—'], '-', $name);

        $parts = preg_split('/\s*-\s*/', $norm);
        if (!is_array($parts) || count($parts) !== 2) {
            return null;
        }

        $from = trim((string) $parts[0]);
        $to = trim((string) $parts[1]);

        if ($from === '' || $to === '') {
            return null;
        }

        return [$from, $to];
    }

    /**
     * Upsert пакетами:
     * - отдельно строки с summa
     * - отдельно строки без summa
     *
     * @param array<int, array<string, mixed>> $updates
     */
    private function flushUpsert(array $updates, Carbon $now): void
    {
        if ($updates === []) {
            return;
        }

        $withSumma = [];
        $withoutSumma = [];

        foreach ($updates as $row) {
            if (!isset($row['id'])) {
                continue;
            }

            $id = (int) $row['id'];

            if (array_key_exists('summa', $row)) {
                $withSumma[] = [
                    'id' => $id,
                    'summa' => (string) $row['summa'],
                    'is_not_update' => (int) ($row['is_not_update'] ?? 0),
                    'updated_at' => $now,
                ];
            } else {
                $withoutSumma[] = [
                    'id' => $id,
                    'is_not_update' => (int) ($row['is_not_update'] ?? 1),
                    'updated_at' => $now,
                ];
            }
        }

        try {
            if ($withSumma !== []) {
                ParserExchange::upsert(
                    $withSumma,
                    ['id'],
                    ['summa', 'is_not_update', 'updated_at']
                );
            }

            if ($withoutSumma !== []) {
                ParserExchange::upsert(
                    $withoutSumma,
                    ['id'],
                    ['is_not_update', 'updated_at']
                );
            }
        } catch (Throwable $e) {
            Log::error('Ошибка сохранения курсов через upsert', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
