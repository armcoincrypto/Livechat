<?php

declare(strict_types=1);

namespace iEXPackages\TagProcessors\Processors;

use App\Models\TagProcessorEntityCustomTag;
use iEXPackages\TagProcessors\Contracts\DescribableTagProcessorInterface;
use iEXPackages\TagProcessors\Support\TagDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Процессор кастомных тегов, привязанных к конкретной сущности
 * (Order, User, DirectionExchange и т.д.) через таблицу tag_processor_entity_custom_tags.
 *
 * Использование:
 *  - [entity:bonus]     → для сущности, переданной в setData()
 *  - [entity:comment]
 *  - [bonus] / [comment] в общем режиме (если разрешишь глобальные)
 */
class EntityCustomTagProcessor extends AbstractTagProcessor implements DescribableTagProcessorInterface
{
    /**
     * Описания всех возможных entity-тегов (по ключам).
     *
     * Здесь мы не знаем конкретную сущность, поэтому
     * просто отдадим описание "по ключу", без значений.
     *
     * @return TagDefinition[]
     */
    public function definitions(): array
    {
        // Собираем уникальные ключи и берём первую запись для описания
        $rows = TagProcessorEntityCustomTag::query()
            ->select('key', 'label', 'description', 'group')
            ->active()
            ->groupBy('key', 'label', 'description', 'group')
            ->orderBy('group')
            ->orderBy('key')
            ->get();

        return $rows->map(function (TagProcessorEntityCustomTag $row) {
            return new TagDefinition(
                key: '[' . $row->key . ']', // сам шорткод вида [bonus]
                name: $row->label ?: $row->key,
                description: $row->description ?: 'Кастомный тег, привязанный к сущности.',
                example: null,
                group: $row->group ?: 'entity',
                deprecated: false,
                resolver: function (mixed $data) use ($row) {
                    $entityType = null;
                    $entityId   = null;
                    $entity     = null;

                    // Вариант 1: контекст — это сама модель (DirectionExchange, Order и т.п.)
                    if ($data instanceof Model) {
                        $entity     = $data;
                        $entityType = $data::class;
                        $entityId   = $data->getKey();
                    }
                    // Вариант 2: массив с entity_type / entity_id (динамический вариант)
                    elseif (is_array($data)) {
                        if (isset($data['entity_type'], $data['entity_id'])) {
                            $entityType = (string) $data['entity_type'];
                            $entityId   = (int) $data['entity_id'];
                        } elseif (isset($data['entity']) && $data['entity'] instanceof Model) {
                            $entity     = $data['entity'];
                            $entityType = $entity::class;
                            $entityId   = $entity->getKey();
                        }
                    }

                    if ($entityType === null || $entityId === null) {
                        // Не удалось определить, к какой сущности привязан тег
                        return '[' . $row->key . ']';
                    }

                    // Ищем фактическую строку entity‑тега для этой пары (entity_type, entity_id)
                    $tagRow = TagProcessorEntityCustomTag::query()
                        ->active()
                        ->where('entity_type', $entityType)
                        ->where('entity_id', $entityId)
                        ->where('key', $row->key)
                        ->first();

                    if (!$tagRow) {
                        return '[' . $row->key . ']';
                    }

                    // Поддержка режима "column":
                    // если type = 'column', то в поле value лежит имя колонки модели (например: "profit")
                    if ($tagRow->type === 'column' && !empty($tagRow->value)) {
                        $column = $tagRow->value;

                        // Если у нас есть загруженная модель — пробуем взять значение поля напрямую
                        if ($entity instanceof Model && isset($entity->{$column})) {
                            return $entity->{$column};
                        }

                        // Если модели нет, но есть entity_type — можно попробовать подгрузить модель
                        if ($entity === null && class_exists($entityType)) {
                            /** @var \Illuminate\Database\Eloquent\Model|null $loaded */
                            $loaded = $entityType::query()->find($entityId);
                            if ($loaded && isset($loaded->{$column})) {
                                return $loaded->{$column};
                            }
                        }

                        // В крайнем случае — вернуть исходный тег
                        return '[' . $row->key . ']';
                    }

                    // В обычном режиме возвращаем сохранённое значение тега
                    return $tagRow->value ?? '[' . $row->key . ']';
                },
            );
        })->all();
    }

    /**
     * Карта [tag => resolver] для AbstractTagProcessor/TagProcessors.
     *
     * Здесь key = '[bonus]', '[comment]' и т.п.
     */
    protected function tagHandlers(): array
    {
        $handlers = [];

        foreach ($this->definitions() as $definition) {
            if ($definition->resolver instanceof \Closure) {
                $handlers[$definition->key] = $definition->resolver;
            }
        }

        return $handlers;
    }
}
