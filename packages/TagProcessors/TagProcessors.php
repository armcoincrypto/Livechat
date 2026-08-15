<?php

declare(strict_types=1);

namespace iEXPackages\TagProcessors;

use iEXPackages\TagProcessors\Contracts\TagProcessorInterface;
use Illuminate\Http\Request;

/**
 * Ядро системы шорткодов (тегов).
 *
 * Основные задачи:
 * - регистрация процессоров тегов (system, order, custom, entity и т.д.);
 * - выбор активных процессоров (setProcessor, addProcessor, программы useProgram);
 * - обработка текста: условные блоки, неймспейсные теги, глобальные теги, кастомные теги;
 * - поддержка фильтров вида [order:amount_in|money] и [order:created_at|date:Y-m-d].
 */
class TagProcessors
{
    protected Request $request;

    /**
     * Дополнительные метаданные для процессоров (ID направления, режимы и т.п.).
     *
     * @var array<string, mixed>
     */
    protected array $meta = [];

    /**
     * Зарегистрированные процессоры тегов.
     *
     * Ключ — строковый идентификатор процессора (system, order, custom, entity, conditionals),
     * значение — экземпляр процессора.
     *
     * @var array<string, TagProcessorInterface>
     */
    protected array $processors = [];

    /**
     * Список активных процессоров тегов.
     *
     * null  — использовать все зарегистрированные процессоры (режим по умолчанию),
     * массив — использовать только указанные ключи процессоров.
     *
     * @var string[]|null
     */
    protected ?array $currentProcessors = null;

    /**
     * Текст, в котором будут заменяться теги.
     *
     * @var string|null
     */
    protected ?string $text = null;

    /**
     * Данные контекста, которые будут передаваться обработчикам тегов.
     *
     * @var mixed
     */
    protected mixed $data = null;

    /**
     * Кастомные теги (подстановка по "сырому" ключу).
     *
     * @var array<string, scalar|null>
     */
    protected array $customTags = [];

    /**
     * Индекс тегов: ключ — сам тег (например "[site_name]"),
     * значение — список ключей процессоров, которые умеют этот тег обрабатывать.
     *
     * @var array<string, string[]>
     */
    protected array $tagIndex = [];

    /**
     * Зарегистрированные программы (профили) процессоров.
     *
     * Ключ — название программы, значение — список ключей процессоров.
     *
     * @var array<string, string[]>
     */
    protected array $processorPrograms = [];

    /**
     * Зарегистрированные фильтры значений.
     *
     * Ключ — имя фильтра (money, date, upper, ...),
     * значение — callable вида:
     *   function (mixed $value, mixed $data, ?Request $request, ...$args): mixed
     *
     * @var array<string, callable>
     */
    protected array $filters = [];

    /**
     * TagProcessors constructor.
     *
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Установить метаданные для процессоров (например, id направления).
     *
     * @param array<string, mixed> $meta
     *
     * @return static
     */
    public function withMeta(array $meta): static
    {
        $this->meta = $meta;

        return $this;
    }

    /**
     * Добавить один мета-ключ (direction_id, entity_type и т.п.).
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return static
     */
    public function addMeta(string $key, mixed $value): static
    {
        $this->meta[$key] = $value;

        return $this;
    }

    /**
     * Зарегистрировать программу (профиль) процессоров.
     *
     * Пример:
     *   ->registerProgram('email_default', ['system', 'order', 'custom'])
     *
     * @param string   $name Название программы.
     * @param string[] $keys Список ключей процессоров.
     *
     * @return static
     */
    public function registerProgram(string $name, array $keys): static
    {
        // Оставляем только существующие процессоры
        $keys = array_values(array_filter($keys, function ($key) {
            return is_string($key) && isset($this->processors[$key]);
        }));

        $this->processorPrograms[$name] = $keys;

        return $this;
    }

    /**
     * Включить программу процессоров.
     *
     * Примеры:
     *   ->useProgram('email_default')               // жёстко установить список
     *   ->useProgram('email_order', append: true)   // добавить к уже установленным
     *
     * @param string $name   Название программы.
     * @param bool   $append true — добавить к текущему списку, false — заменить.
     *
     * @return static
     */
    public function useProgram(string $name, bool $append = false): static
    {
        if (!isset($this->processorPrograms[$name])) {
            return $this;
        }

        $programKeys = $this->processorPrograms[$name];

        if ($append) {
            return $this->addProcessor($programKeys);
        }

        // заменить текущий список
        $this->currentProcessors = $programKeys !== [] ? $programKeys : null;

        return $this;
    }

    /**
     * Регистрация нового процессора тегов.
     *
     * @param string                $key       Ключ процессора (system, order, custom, ...)
     * @param TagProcessorInterface $processor Экземпляр процессора.
     *
     * @return static
     */
    public function registerProcessor(string $key, TagProcessorInterface $processor): static
    {
        $this->processors[$key] = $processor;

        // Строим индекс тегов для глобального поиска [tag]
        foreach ($processor->tags() as $tag) {
            $this->tagIndex[$tag][] = $key;
        }

        return $this;
    }

    /**
     * Установить активные процессоры тегов.
     *
     * Можно передать:
     *   ->setProcessor('system')
     *   ->setProcessor(['order', 'system'])
     *
     * При передаче массива будут использоваться только те процессоры,
     * которые реально зарегистрированы в TagProcessors.
     *
     * @param string|string[] $keys
     *
     * @return static
     */
    public function setProcessor(string|array $keys): static
    {
        $keys = (array) $keys;

        // Оставляем только строковые ключи, которые реально зарегистрированы
        $keys = array_values(array_filter($keys, function ($key) {
            return is_string($key) && isset($this->processors[$key]);
        }));

        $this->currentProcessors = $keys !== [] ? $keys : null;

        return $this;
    }

    /**
     * Добавить один или несколько процессоров к уже установленному списку.
     *
     * Примеры:
     *   ->setProcessor('system')->addProcessor('order')
     *   ->addProcessor(['custom', 'entity'])
     *
     * @param string|string[] $keys
     *
     * @return static
     */
    public function addProcessor(string|array $keys): static
    {
        $keys = (array) $keys;

        $keys = array_values(array_filter($keys, function ($key) {
            return is_string($key) && isset($this->processors[$key]);
        }));

        if ($keys === []) {
            return $this;
        }

        // если до этого ничего не было — просто задаём список
        if ($this->currentProcessors === null) {
            $this->currentProcessors = $keys;

            return $this;
        }

        // иначе объединяем и убираем дубли
        $this->currentProcessors = array_values(array_unique(
            array_merge($this->currentProcessors, $keys)
        ));

        return $this;
    }

    /**
     * Сбросить выбор процессоров (использовать все).
     *
     * @return static
     */
    public function resetProcessor(): static
    {
        $this->currentProcessors = null;

        return $this;
    }

    /**
     * Установить текст для обработки.
     *
     * @param string $text
     *
     * @return static
     */
    public function setText(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    /**
     * Установить данные для замены тегов.
     *
     * @param mixed $data
     *
     * @return static
     */
    public function setData(mixed $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Добавить кастомные теги с их значениями.
     *
     * @param array<string, scalar|null> $tags
     *
     * @return static
     */
    public function withCustomTags(array $tags): static
    {
        $this->customTags = $tags;

        return $this;
    }

    /**
     * Применить текущие метаданные к Request, чтобы процессоры могли их читать.
     *
     * @return mixed|null Предыдущее значение метаданных в Request (если было).
     */
    protected function applyMetaToRequest(): mixed
    {
        if (!$this->request instanceof \Illuminate\Http\Request) {
            return null;
        }

        /** @var \Symfony\Component\HttpFoundation\ParameterBag $bag */
        $bag = $this->request->attributes;

        $previous = $bag->get('tag_meta');
        $bag->set('tag_meta', $this->meta);

        return $previous;
    }

    /**
     * Восстановить метаданные в Request после обработки.
     *
     * @param mixed $previousMeta
     *
     * @return void
     */
    protected function restoreMetaInRequest(mixed $previousMeta): void
    {
        if (!$this->request instanceof \Illuminate\Http\Request) {
            return;
        }

        /** @var \Symfony\Component\HttpFoundation\ParameterBag $bag */
        $bag = $this->request->attributes;

        if ($previousMeta === null) {
            $bag->remove('tag_meta');
        } else {
            $bag->set('tag_meta', $previousMeta);
        }
    }

    /**
     * Зарегистрировать фильтр значений (например, money, date).
     *
     * Фильтр вызывается как:
     *   function (mixed $value, mixed $data, ?Request $request, ...$args): mixed
     *
     * @param string   $name     Имя фильтра (используется в синтаксисе |name).
     * @param callable $callback Обработчик фильтра.
     *
     * @return static
     */
    public function registerFilter(string $name, callable $callback): static
    {
        $this->filters[$name] = $callback;

        return $this;
    }

    /**
     * Обрабатывает текст, заменяя найденные теги значениями из зарегистрированных процессоров и кастомных тегов.
     *
     * @return static
     */
    public function process(): static
    {
        if (empty($this->text)) {
            return $this;
        }

        // Прокидываем метаданные в Request, чтобы процессоры и фильтры могли их использовать
        $previousMeta = $this->applyMetaToRequest();

        // 1. [if:is_...] блоки (conditionals-процессор)
        $this->processConditionals();

        // 2. Неймспейсы: [system:site_name], [order:public_id] и т.п.
        // Работают всегда, независимо от setProcessor()
        $this->processNamespacedTags();

        // 3. Если setProcessor НЕ указан — обрабатываем короткие теги [site_name], [public_id]
        // глобально по всем процессорам через индекс.
        if ($this->currentProcessors === null) {
            $this->processGlobalTags();
        }

        // 4. Обычная подстановка по полным тегам из processors->tags()
        $processors = $this->getActiveProcessors();

        foreach ($processors as $processor) {

            if (method_exists($processor, 'processDynamicTags')) {
                $this->text = $processor->processDynamicTags($this->text, $this->data, $this->request);
            }

            foreach ($processor->tags() as $tag) {
                // Если тег в тексте не встречается — пропускаем обработку
                if ($this->text === null || !str_contains($this->text, $tag)) {
                    continue;
                }

                $replacement = $processor->handle($tag, $this->data, $this->request);

                if (!is_scalar($replacement) && !is_null($replacement)) {
                    continue;
                }

                $this->text = str_replace($tag, (string) $replacement, $this->text);
            }
        }

        // 5. Кастомные теги как и раньше
        foreach ($this->customTags as $tag => $value) {
            $this->text = str_replace($tag, (string) $value, $this->text);
        }

        // Восстанавливаем предыдущие метаданные в Request
        $this->restoreMetaInRequest($previousMeta);

        return $this;
    }

    /**
     * Обработка условных блоков вида [if:...]...[/if:...].
     */
    protected function processConditionals(): void
    {
        if (!isset($this->processors['conditionals'])) {
            return;
        }

        $conditionalProcessor = $this->processors['conditionals'];

        $this->text = $conditionalProcessor->processConditionals($this->text, $this->data, $this->request);
    }

    /**
     * Обработка неймспейсных тегов вида [processor:tag] и [processor:tag|filters].
     */
    protected function processNamespacedTags(): void
    {
        if (empty($this->text)) {
            return;
        }

        // Ищем теги вида:
        // [system:site_name]
        // [order:public_id|money]
        // [order:created_at|date:Y-m-d]
        $pattern = '/\[(\w+):([^\]|]+)(?:\|([^\]]+))?\]/';

        $this->text = preg_replace_callback($pattern, function (array $matches) {
            $processorKey = $matches[1]; // system | order | entity | ...
            $tagName      = $matches[2]; // site_name | public_id | ...
            $filtersChain = $matches[3] ?? null;

            // Если процессора нет — оставляем тег как есть
            if (!isset($this->processors[$processorKey])) {
                return $matches[0];
            }

            $processor = $this->processors[$processorKey];

            // Возможные варианты ключей внутри процессора:
            // 1) '[site_name]'  — как у SystemTagProcessor сейчас
            // 2) 'site_name'    — если когда-то решишь перейти на имена без скобок
            $possibleKeys = [
                '[' . $tagName . ']',
                $tagName,
            ];

            foreach ($possibleKeys as $tagKey) {
                if (!in_array($tagKey, $processor->tags(), true)) {
                    continue;
                }

                $replacement = $processor->handle($tagKey, $this->data, $this->request);

                if (!is_scalar($replacement) && !is_null($replacement)) {
                    return $matches[0];
                }

                // Применяем фильтры, если они указаны
                if ($filtersChain !== null) {
                    $filterSpecs = array_map('trim', explode('|', $filtersChain));
                    $replacement = $this->applyFilters($replacement, $filterSpecs);
                }

                return (string) $replacement;
            }

            // Если тег не найден в процессоре — оставляем как есть
            return $matches[0];
        }, $this->text);
    }

    /**
     * Получить список активных процессоров с учётом setProcessor()/useProgram().
     *
     * @return array<string, TagProcessorInterface>
     */
    protected function getActiveProcessors(): array
    {
        if ($this->currentProcessors !== null) {
            $active = [];

            foreach ($this->currentProcessors as $key) {
                if (isset($this->processors[$key])) {
                    $active[$key] = $this->processors[$key];
                }
            }

            return $active;
        }

        return $this->processors;
    }

    /**
     * Обработка коротких глобальных тегов вида [tag] и [tag|filters].
     */
    protected function processGlobalTags(): void
    {
        if (empty($this->text)) {
            return;
        }

        // [tag] или [tag|money|date:Y-m-d]
        $pattern = '/\[([^\]|]+)(?:\|([^\]]+))?\]/';

        $this->text = preg_replace_callback($pattern, function (array $matches) {
            $tagName      = $matches[1];
            $filtersChain = $matches[2] ?? null;

            $possibleKeys = [
                '[' . $tagName . ']',
                $tagName,
            ];

            foreach ($possibleKeys as $tagKey) {
                if (!isset($this->tagIndex[$tagKey])) {
                    continue;
                }

                foreach ($this->tagIndex[$tagKey] as $processorKey) {
                    // учитываем setProcessor/useProgram: если указаны —
                    // работаем только с перечисленными ключами процессоров
                    if ($this->currentProcessors !== null
                        && !in_array($processorKey, $this->currentProcessors, true)
                    ) {
                        continue;
                    }

                    $processor = $this->processors[$processorKey] ?? null;
                    if (!$processor) {
                        continue;
                    }

                    $replacement = $processor->handle($tagKey, $this->data, $this->request);

                    if (!is_scalar($replacement) && !is_null($replacement)) {
                        continue;
                    }

                    if ($filtersChain !== null) {
                        $filterSpecs = array_map('trim', explode('|', $filtersChain));
                        $replacement = $this->applyFilters($replacement, $filterSpecs);
                    }

                    return (string) $replacement;
                }
            }

            return $matches[0];
        }, $this->text);
    }

    /**
     * Применить цепочку фильтров к значению.
     *
     * @param mixed    $value       Исходное значение тега.
     * @param string[] $filterSpecs Список фильтров вида ["money", "date:Y-m-d"].
     *
     * @return mixed
     */
    protected function applyFilters(mixed $value, array $filterSpecs): mixed
    {
        if ($filterSpecs === [] || $this->filters === []) {
            return $value;
        }

        foreach ($filterSpecs as $spec) {
            $spec = trim($spec);
            if ($spec === '') {
                continue;
            }

            // money
            // date:Y-m-d
            $parts = explode(':', $spec, 2);
            $name  = $parts[0];
            $args  = isset($parts[1]) && $parts[1] !== ''
                ? array_map('trim', explode(',', $parts[1]))
                : [];

            if (!isset($this->filters[$name])) {
                continue;
            }

            $callback = $this->filters[$name];

            // Порядок аргументов: (value, data, request, ...extraArgs)
            array_unshift($args, $value, $this->data, $this->request);

            $value = $callback(...$args);
        }

        return $value;
    }

    /**
     * Получить обработанный текст.
     *
     * @return string|null
     */
    public function getText(): ?string
    {
        return $this->text;
    }

    /**
     * Получить зарегистрированные процессоры тегов.
     *
     * @return array<string, TagProcessorInterface>
     */
    public function getProcessors(): array
    {
        return $this->processors;
    }
}
