<?php

declare(strict_types=1);

namespace iEXPackages\TagProcessors\Processors;

use App\Models\TagProcessorCustomTag;
use iEXPackages\TagProcessors\Contracts\DescribableTagProcessorInterface;
use iEXPackages\TagProcessors\Support\TagDefinition;
use Illuminate\Http\Request;

class CustomTagProcessor extends AbstractTagProcessor implements DescribableTagProcessorInterface
{
    /**
     * Описания всех кастомных тегов из БД.
     *
     * @return TagDefinition[]
     */
    public function definitions(): array
    {
        return TagProcessorCustomTag::query()
            ->active()
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->map(function (TagProcessorCustomTag $tag) {
                return new TagDefinition(
                    key: '[' . $tag->key . ']', // ключ вида [promo_2025]
                    name: $tag->label ?: $tag->key,
                    description: $tag->description ?: 'Пользовательский шорткод.',
                    example: is_string($tag->value) ? mb_substr($tag->value, 0, 80) : null,
                    group: $tag->group ?: 'custom',
                    deprecated: false,
                    resolver: fn() => $tag->value,
                );
            })
            ->all();
    }

    /**
     * Карта [tag => resolver] для AbstractTagProcessor/TagProcessors.
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
