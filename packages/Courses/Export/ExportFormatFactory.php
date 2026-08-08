<?php

declare(strict_types=1);

namespace iEXPackages\Courses\Export;

use iEXPackages\Courses\Export\Formats\AbstractExportFormat;
use iEXPackages\Courses\Export\Formats\BestChangeXMLExportFormat;
use iEXPackages\Courses\Export\Formats\XMLExportFormat;
use InvalidArgumentException;

/**
 * ExportFormatFactory
 *
 * Фабрика форматов экспорта курсов.
 *
 * Оптимизация:
 * - Форматы кешируются по типу, чтобы не создавать объекты повторно при генерации нескольких файлов.
 *
 * Масштабирование:
 * - Добавлены константы типов.
 * - Упрощено расширение (например JSON).
 */
final class ExportFormatFactory
{
    public const int TYPE_XML = 0;
    public const int TYPE_BESTCHANGE_XML = 1;
    // public const int TYPE_JSON = 2; // задел на будущее

    /**
     * Кеш экземпляров форматов.
     *
     * @var array<int, AbstractExportFormat>
     */
    private static array $cache = [];

    /**
     * Создать объект формата экспорта.
     *
     * @throws InvalidArgumentException
     */
    public static function make(int $type): AbstractExportFormat
    {
        if (isset(self::$cache[$type])) {
            return self::$cache[$type];
        }

        $format = match ($type) {
            self::TYPE_XML => new XMLExportFormat(),
            self::TYPE_BESTCHANGE_XML => new BestChangeXMLExportFormat(),
            default => throw new InvalidArgumentException(
                "Неизвестный формат экспорта: {$type}. Поддерживаемые типы: "
                . self::TYPE_XML . " (XML), "
                . self::TYPE_BESTCHANGE_XML . " (XML BestChange)."
            ),
        };

        return self::$cache[$type] = $format;
    }
}
