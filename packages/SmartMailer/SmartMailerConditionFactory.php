<?php

namespace iEXPackages\SmartMailer;

use iEXPackages\SmartMailer\Conditions\MailConditionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Фабрика условий для отправки писем.
 * Позволяет динамически создавать экземпляры условий,
 * используя конфигурацию smart-mailer.php.
 */
class SmartMailerConditionFactory
{
    /**
     * Кеш созданных экземпляров условий.
     *
     * @var array<string, MailConditionInterface>
     */
    protected static array $instances = [];

    /**
     * Создаёт экземпляр указанного условия.
     *
     * @param string $condition Ключ условия из конфигурации.
     * @param Model $model Связанная модель.
     *
     * @return MailConditionInterface
     *
     * @throws InvalidArgumentException
     */
    public static function make(string $condition, Model $model): MailConditionInterface
    {
        $conditionsMap = self::conditionsMap();

        if (!isset($conditionsMap[$condition])) {
            $message = "Condition '{$condition}' is not defined in configuration.";
            Log::error("[SmartMailerConditionFactory] " . $message, [
                'condition' => $condition,
                'model_type' => get_class($model),
                'model_id' => $model->getKey(),
            ]);

            throw new InvalidArgumentException($message);
        }

        $key = self::getInstanceKey($condition, $model);

        return self::$instances[$key] ??= App::make($conditionsMap[$condition], ['model' => $model]);
    }

    /**
     * Проверяет, зарегистрировано ли указанное условие.
     *
     * @param string $condition
     *
     * @return bool
     */
    public static function has(string $condition): bool
    {
        return isset(self::conditionsMap()[$condition]);
    }

    /**
     * Возвращает список всех зарегистрированных классов условий.
     *
     * @return array<int, string>
     */
    public static function conditions(): array
    {
        return array_values(self::conditionsMap());
    }

    /**
     * Регистрирует новое условие в конфигурации (временно на время работы приложения).
     *
     * @param string $name
     * @param string $conditionClass
     */
    public static function registerCondition(string $name, string $conditionClass): void
    {
        Config::set("smart-mailer.conditions.$name", $conditionClass);
    }

    /**
     * Получает карту условий из конфигурации.
     *
     * @return array<string, string>
     */
    protected static function conditionsMap(): array
    {
        return Config::get('smart-mailer.conditions', []);
    }

    /**
     * Генерирует уникальный ключ для кеширования экземпляра.
     *
     * @param string $condition
     * @param Model $model
     *
     * @return string
     */
    protected static function getInstanceKey(string $condition, Model $model): string
    {
        return "{$condition}:" . get_class($model) . ":{$model->getKey()}";
    }
}
