<?php

declare(strict_types=1);

namespace iEXPackages\Courses;

use iEXPackages\Courses\Export\ExportCourses;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Courses
 *
 * Главная точка сборки и кеширования "initial" данных курсов/направлений для фронтенда.
 *
 * Задачи класса:
 * - Собрать набор данных (резервы, курсы, сортировки, фильтры, категории и т.д.)
 * - Сохранить результат в выбранное хранилище кеша (redis/array/иное)
 * - Поддержать контекст сборки (cache-store, convert_to и т.п.)
 * - Работать корректно как из Artisan (с выводом в консоль), так и из runtime (через лог)
 *
 * Важно:
 * - Ключ кеша зависит от метода (обычно "initial") и текущей локали.
 * - Локаль выставляется в app()->setLocale() на время сборки.
 *
 * @phpstan-type CoursesConfig array<string,mixed>
 * @phpstan-type CoursesContext array{convert_to?: mixed, cache?: string}
 * @phpstan-type InitialPayload array<string,mixed>
 */
final class Courses
{
    use Concerns\CallsCompilers;
    use Concerns\ConfiguresAttributes;
    use Concerns\ManualCompilers;
    use Concerns\GeneratorCompilers;

    /**
     * Команда консоли (если сборка выполняется из Artisan).
     */
    private ?Command $command = null;

    /**
     * Настройки модуля.
     *
     * @var CoursesConfig
     */
    private array $config;

    /**
     * Контекст сборки (cache store, convert_to и т.д.).
     *
     * @var CoursesContext
     */
    private array $context = [];

    /**
     * Файловая система.
     */
    private Filesystem $filesystem;

    /**
     * Текущая локаль сборки.
     */
    private string $locale = 'ru';

    /**
     * Временное хранение initial-ответа для store=array.
     *
     * @var InitialPayload
     */
    private array $arrayInitialResponse = [];

    /**
     * Кеш результата availableLanguage() на время жизни объекта.
     *
     * @var array<string,mixed>|null
     */
    private ?array $availableLanguagesCache = null;

    /**
     * @param CoursesConfig $config
     */
    public function __construct(array $config, Filesystem $filesystem)
    {
        $this->config = $config;
        $this->filesystem = $filesystem;
    }

    /**
     * Привязать Artisan-команду для вывода сообщений.
     *
     * @return $this
     */
    public function setCommand(Command $command): self
    {
        $this->command = $command;

        return $this;
    }

    /**
     * Экспорт курсов (вынесено в отдельный сервис).
     */
    public function export(Command $command): ExportCourses
    {
        return new ExportCourses($command);
    }

    /**
     * Установить контекст сборки.
     *
     * Поддерживаемые ключи:
     * - convert_to: mixed|null
     * - cache: string (например: redis|array|file|database...)
     *
     * Примечание:
     * - Если cache не передан — используем уже заданный ранее, иначе дефолт "redis".
     *
     * @param CoursesContext $context
     * @return $this
     */
    public function withData(array $context = []): self
    {
        if (array_key_exists('convert_to', $context)) {
            $this->context['convert_to'] = $context['convert_to'];
        }

        $this->context['cache'] = isset($context['cache']) && trim((string) $context['cache']) !== ''
            ? (string) $context['cache']
            : ($this->context['cache'] ?? 'redis');

        return $this;
    }

    /**
     * Установить локаль сборки.
     *
     * @return $this
     */
    public function setLocale(string $locale): self
    {
        $locale = trim($locale);
        $this->locale = $locale !== '' ? $locale : 'ru';

        return $this;
    }

    /**
     * Получить текущую локаль сборки.
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Собрать initial-данные и сохранить в кеш.
     *
     * Содержимое initial обычно используется фронтендом сразу при загрузке:
     * - direction_rates, reserves, sorting, filters, categories и т.д.
     *
     * @throws RuntimeException если не удалось сохранить данные в кеш
     */
    public function builder(): void
    {
        $previousLocale = (string) app()->getLocale();

        try {
            app()->setLocale($this->locale);

            $now = Carbon::now();

            $reserves = $this->compilerReserve();
            $reservesView = $this->filterReservesForHomeView($reserves);

            /** @var InitialPayload $payload */
            $payload = [
                'app_version' => (string) config('iexexchanger.version.current'),
                'locale' => $this->locale,
                'cs' => [
                    'created_at' => $now->format('Y-m-d H:i:s O'),
                    'dr_snapshot_id' => Cache::get('dr_snapshot_id', null),

                    'direction_rates' => $this->configureDirectionRates(),
                    'directions_reserves' => $this->getDirectionReserves(),
                    'directions_sorting' => $this->getDirectionSorting(),

                    'reserves' => $reservesView,
                    'reserves_view' => $reservesView,

                    'filters' => $this->compileFilters(),
                    'currency_categories' => $this->compilerCurrencyCategories(),
                ],
                'paymentSystems' => $this->compilerCurrency(),
            ];

            $this->cache($payload, 'initial');
        } finally {
            app()->setLocale($previousLocale);
        }
    }

    /**
     * Сохранить данные в кеш и при необходимости — в локальное поле (store=array).
     *
     * Логика:
     * - Формируем ключ cacheKey($method)
     * - Оборачиваем payload в CoursesResponse (единый формат)
     * - Пишем в выбранный store
     *
     * Важно:
     * - Для store=array дополнительно сохраняем копию в $this->arrayInitialResponse,
     *   чтобы результат можно было получить напрямую без обращения к Cache.
     *
     * @param InitialPayload $payload
     * @throws RuntimeException если store не найден или запись не удалась
     */
    public function cache(array $payload, string $method = 'initial'): void
    {
        $store = $this->getCacheStoreName();
        $key = $this->cacheKey($method);

        $response = new CoursesResponse($payload);

        $this->info("Cache store: {$store}");
        $this->info("Cache key: {$key}");

        $repository = $this->cacheRepository($store);

        // Для array-store сохраняем сериализованный вид (jsonSerialize) — удобно для отладки/тестов.
        if ($store === 'array') {
            /** @var array<string,mixed> $serialized */
            $serialized = (array) $response->jsonSerialize();

            // put() — стандартная запись (TTL не задан => зависит от драйвера; array живёт в рамках запроса)
            $repository->put($key, $serialized);

            $this->setInitialResponse($serialized);

            return;
        }

        // Для остальных store пишем "нормальный" массив (как раньше делал toArray()).
        // Если тебе принципиально хранить именно jsonSerialize() — можно унифицировать под него.
        /** @var array<string,mixed> $stored */
        $stored = $response->toArray();
        $ok = $repository->put($key, $stored);

        if ($ok === false) {
            throw new RuntimeException("Не удалось записать initial-данные в cache store '{$store}' по ключу '{$key}'.");
        }

        // То же значение, что ушло в store — нужно для manualInitial() / getInitialResponse()
        // сразу после builder() (иначе in-memory пустой при redis и срабатывает initial_response_empty).
        $this->setInitialResponse($stored);
    }

    /**
     * Получить список доступных языков (кешируется на время жизни объекта).
     *
     * Источники:
     * - config('app.all_locale') — полный набор
     * - iEXSetting('app_multilanguage_locale') — список разрешённых (через запятую)
     *
     * @return array<string,mixed>
     */
    public function availableLanguage(): array
    {
        if ($this->availableLanguagesCache !== null) {
            return $this->availableLanguagesCache;
        }

        /** @var array<string,mixed> $all */
        $all = (array) config('app.all_locale', []);
        $allowed = trim((string) iEXSetting('app_multilanguage_locale'));

        // Если ограничений нет — возвращаем всё.
        if ($allowed === '') {
            return $this->availableLanguagesCache = $all;
        }

        $allowedList = array_values(array_filter(array_map('trim', explode(',', $allowed))));
        if ($allowedList === []) {
            return $this->availableLanguagesCache = $all;
        }

        // Быстрое membership-проверка через set (ассоц. массив), чтобы не гонять in_array на каждом элементе.
        $allowedSet = array_fill_keys($allowedList, true);

        $this->availableLanguagesCache = collect($all)
            ->reject(static fn ($value, $key) => !isset($allowedSet[(string) $key]))
            ->toArray();

        return $this->availableLanguagesCache;
    }

    /**
     * Сохранить initial-ответ для store=array.
     *
     * @param InitialPayload $array
     * @return $this
     */
    public function setInitialResponse(array $array): self
    {
        $this->arrayInitialResponse = $array;

        return $this;
    }

    /**
     * Получить initial-ответ для store=array.
     *
     * @return InitialPayload
     */
    public function getInitialResponse(): array
    {
        return $this->arrayInitialResponse;
    }

    /**
     * Сформировать ключ кеша для указанного метода и текущей локали.
     *
     * Правило:
     * exchange-iex-{method}-rates-{locale}
     */
    private function cacheKey(string $method): string
    {
        $method = trim($method);
        if ($method === '') {
            $method = 'initial';
        }

        return 'exchange-iex-' . $method . '-rates-' . $this->locale;
    }

    /**
     * Отфильтровать резервы для отображения на главной (home view).
     *
     * Берём строку iEXSetting('ids_currencies_reserves_home_view'):
     * - если пусто — ничего не скрываем
     * - иначе скрываем перечисленные ID валют
     *
     * Оптимизация:
     * - нормализуем список скрытых id в set для O(1) проверки
     * - работаем корректно как с array, так и с Collection
     *
     * @param array<int|string,mixed>|Collection<int|string,mixed> $reserves
     * @return array<int|string,mixed>
     */
    private function filterReservesForHomeView(array|Collection $reserves): array
    {
        $hidden = trim((string) iEXSetting('ids_currencies_reserves_home_view'));
        if ($hidden === '') {
            return $reserves instanceof Collection ? $reserves->all() : $reserves;
        }

        $hiddenIds = array_values(array_filter(array_map('trim', explode(',', $hidden))));
        if ($hiddenIds === []) {
            return $reserves instanceof Collection ? $reserves->all() : $reserves;
        }

        $hiddenSet = array_fill_keys($hiddenIds, true);

        $collection = $reserves instanceof Collection ? $reserves : collect($reserves);

        return $collection
            ->reject(static fn ($value, $key) => isset($hiddenSet[(string) $key]))
            ->all();
    }

    /**
     * Получить имя cache store из контекста.
     *
     * @return non-empty-string
     */
    private function getCacheStoreName(): string
    {
        $store = (string) ($this->context['cache'] ?? 'redis');
        $store = trim($store);

        return $store !== '' ? $store : 'redis';
    }

    /**
     * Получить репозиторий кеша для указанного store.
     *
     * Причина существования:
     * - централизованно проверять/логировать проблемы, если store не настроен
     *
     * @throws RuntimeException
     */
    private function cacheRepository(string $store): CacheRepository
    {
        try {
            return Cache::store($store);
        } catch (\Throwable $e) {
            throw new RuntimeException("Cache store '{$store}' недоступен или не настроен.", 0, $e);
        }
    }

    /**
     * Безопасный вывод сообщений:
     * - если есть Artisan-команда — пишем в консоль
     * - иначе — пишем в лог
     */
    private function info(string $message): void
    {
        if ($this->command !== null) {
            $this->command->info($message);

            return;
        }

        Log::info($message);
    }
}
