<?php

declare(strict_types=1);

namespace iEXPackages\TagProcessors\Support;

/**
 * Описание одного шорткода (тега).
 */
final class TagDefinition
{
    /**
     * @param string        $key         Ключ тега, как он используется в тексте (напр. "[site_name]" или "site_name")
     * @param string        $name        Краткое человекочитаемое название
     * @param string        $description Подробное описание: что делает тег
     * @param string|null   $example     Пример значения
     * @param string|null   $group       Группа / категория (system, order, direction и т.п.)
     * @param bool          $deprecated  Помечен ли тег как устаревший
     * @param \Closure|null $resolver    Функция, которая возвращает значение тега
     */
    public function __construct(
        public string   $key,
        public string   $name,
        public string   $description,
        public ?string  $example   = null,
        public ?string  $group     = null,
        public bool     $deprecated = false,
        public ?\Closure $resolver = null,
    ) {
    }
}
