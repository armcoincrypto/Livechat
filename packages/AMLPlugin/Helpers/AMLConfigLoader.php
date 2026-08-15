<?php

namespace iEXPackages\AMLPlugin\Helpers;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Class AMLConfigLoader
 *
 * Загружает конфигурацию AML-драйверов из JSON-файлов.
 */
class AMLConfigLoader
{
    /**
     * Загружает конфигурацию для указанного драйвера.
     *
     * @param string $driverName Имя драйвера (например, 'GetBlock')
     * @return array Конфигурация драйвера в виде массива
     *
     * @throws InvalidArgumentException Если файл не найден или JSON некорректен
     */
    public static function load(string $driverName): array
    {
        // Абсолютный путь до папки Drivers
        $driversDir = dirname(__DIR__) . '/Drivers';

        // Получаем все папки/файлы внутри Drivers
        $folders = scandir($driversDir);

        // Ищем нужную папку (без учета регистра)
        $matchedFolder = collect($folders)
            ->first(fn($folder) => strcasecmp($folder, $driverName) === 0);

        if (!$matchedFolder) {
            throw new InvalidArgumentException(sprintf(
                'AML driver [%s] not found in directory [%s]',
                $driverName,
                $driversDir
            ));
        }

        // Формируем путь к config.json внутри найденной папки
        $configPath = $driversDir . '/' . $matchedFolder . '/config.json';

        // Проверяем существование файла
        if (!File::exists($configPath)) {
            throw new InvalidArgumentException(sprintf(
                'Configuration file not found for AML driver [%s] at path [%s]',
                $driverName,
                realpath(dirname($configPath)) ?: 'undefined'
            ));
        }

        // Получаем содержимое файла
        $jsonContent = File::get($configPath);

        // Декодируем JSON в массив
        $config = json_decode($jsonContent, true);

        // Проверка корректности JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException(sprintf(
                'Invalid JSON in AML driver [%s] configuration: %s',
                $driverName,
                json_last_error_msg()
            ));
        }

        // Проверяем, что получили массив
        if (!is_array($config)) {
            throw new InvalidArgumentException(sprintf(
                'AML driver [%s] configuration must be an array, %s given.',
                $driverName,
                gettype($config)
            ));
        }

        return $config;
    }
}
