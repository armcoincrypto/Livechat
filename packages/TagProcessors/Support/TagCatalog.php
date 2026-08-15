<?php

declare(strict_types=1);

namespace iEXPackages\TagProcessors\Support;

use iEXPackages\TagProcessors\TagProcessors;
use iEXPackages\TagProcessors\Contracts\TagProcessorInterface;
use Illuminate\Http\Request;
use iEXPackages\TagProcessors\Contracts\DescribableTagProcessorInterface;
use iEXPackages\TagProcessors\Support\TagDefinition;

class TagCatalog
{
    public function __construct(
        protected TagProcessors $tagProcessors,
        protected Request $request,
    ) {
    }

    /**
     * Вариант 1.1 — общий список всех ключей (без группировки).
     *
     * Пример результата:
     * [
     *   '[site_name]',
     *   '[default_email_from]',
     *   '[order_id]',
     *   '[public_id]',
     *   ...
     * ]
     */
    public function allKeys(): array
    {
        $keys = [];

        foreach ($this->getProcessors() as $processor) {
            $keys = array_merge($keys, $processor->tags());
        }

        return array_values(array_unique($keys));
    }

    /**
     * Вариант 1.2 — общий список ключ => значение (разрешённые теги).
     *
     * В $data передаёшь то, что обычно отдаёшь в TagProcessors::setData().
     *
     * Пример результата:
     * [
     *   '[site_name]'          => 'My App',
     *   '[default_email_from]' => 'noreply@example.com',
     *   '[order_id]'           => '12345',
     *   ...
     * ]
     */
    public function allValues(mixed $data = null): array
    {
        $result = [];

        foreach ($this->getProcessors() as $processor) {
            foreach ($processor->tags() as $tag) {
                $value = $processor->handle($tag, $data, $this->request);

                if (!is_scalar($value) && !is_null($value)) {
                    continue;
                }

                $result[$tag] = (string) $value;
            }
        }

        return $result;
    }

    /**
     * Вариант 2.1 — ключи по процессорам.
     *
     * Пример результата:
     * [
     *   'system' => ['[site_name]', '[default_email_from]', ...],
     *   'order'  => ['[order_id]', '[public_id]', ...],
     *   ...
     * ]
     */
    public function keysByProcessor(): array
    {
        $result = [];

        foreach ($this->getProcessors(true) as $key => $processor) {
            $result[$key] = $processor->tags();
        }

        return $result;
    }

    /**
     * Вариант 2.2 — значения по процессорам.
     *
     * $defaultData — общее $data для всех процессоров.
     * $dataByProcessor['order'] / ['system'] — если хочешь разное $data
     * для разных процессоров (опционально).
     *
     * Пример результата:
     * [
     *   'system' => [
     *       '[site_name]'          => 'My App',
     *       '[default_email_from]' => 'noreply@example.com',
     *   ],
     *   'order' => [
     *       '[order_id]'           => '12345',
     *       '[public_id]'          => 'ABC-123',
     *   ],
     * ]
     */
    public function valuesByProcessor(
        mixed $defaultData = null,
        array $dataByProcessor = [],
    ): array {
        $result = [];

        foreach ($this->getProcessors(true) as $key => $processor) {
            $data = $dataByProcessor[$key] ?? $defaultData;

            $result[$key] = [];

            foreach ($processor->tags() as $tag) {
                $value = $processor->handle($tag, $data, $this->request);

                if (!is_scalar($value) && !is_null($value)) {
                    continue;
                }

                $result[$key][$tag] = (string) $value;
            }
        }

        return $result;
    }

    /**
     * Общий список описаний тегов (без группировки по процессорам).
     *
     * Если процессор не поддерживает TagDefinition, создаём простые заглушки.
     *
     * @return TagDefinition[]
     */
    public function allDefinitions(): array
    {
        $definitions = [];

        foreach ($this->getProcessors(true) as $processorKey => $processor) {
            if ($processor instanceof DescribableTagProcessorInterface) {
                foreach ($processor->definitions() as $def) {
                    $definitions[] = $def;
                }
                continue;
            }

            // fallback для обычных процессоров без descriptions()
            foreach ($processor->tags() as $tag) {
                $definitions[] = new TagDefinition(
                    key: $tag,
                    name: $tag,
                    description: 'Тег без описания (процессор не реализует DescribableTagProcessorInterface).',
                    example: null,
                    group: $processorKey,
                    deprecated: false,
                    resolver: null,
                );
            }
        }

        return $definitions;
    }

    /**
     * Описания тегов, сгруппированные по процессорам.
     *
     * Пример:
     * [
     *   'system' => [ TagDefinition(...), ... ],
     *   'order'  => [ TagDefinition(...), ... ],
     * ]
     *
     * @return array<string, TagDefinition[]>
     */
    public function definitionsByProcessor(): array
    {
        $result = [];

        foreach ($this->getProcessors(true) as $processorKey => $processor) {
            $result[$processorKey] = [];

            if ($processor instanceof DescribableTagProcessorInterface) {
                $result[$processorKey] = $processor->definitions();
                continue;
            }

            // fallback — процессор не умеет descriptions(), строим примитивные TagDefinition
            foreach ($processor->tags() as $tag) {
                $result[$processorKey][] = new TagDefinition(
                    key: $tag,
                    name: $tag,
                    description: 'Тег без описания (процессор не реализует DescribableTagProcessorInterface).',
                    example: null,
                    group: $processorKey,
                    deprecated: false,
                    resolver: null,
                );
            }
        }

        return $result;
    }

    /**
     * Внутренний helper: получить процессоры.
     *
     * @param bool $preserveKeys true — сохраняем ключи ('system', 'order', ...),
     *                           false — просто массив значений.
     *
     * @return TagProcessorInterface[]
     */
    protected function getProcessors(bool $preserveKeys = false): array
    {
        $processors = $this->tagProcessors->getProcessors();

        return $preserveKeys ? $processors : array_values($processors);
    }
}
