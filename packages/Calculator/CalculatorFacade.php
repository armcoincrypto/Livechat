<?php
declare(strict_types=1);

namespace iEXPackages\Calculator;

use App\Models\DirectionExchange;
use App\Models\Task;
use Illuminate\Support\Facades\Facade;

/**
 * CalculatorFacade
 *
 * Фасад для доступа к сервису расчёта курса обмена.
 *
 * Назначение:
 * - Упрощает вызов калькулятора в любом месте приложения через статический интерфейс.
 * - Делегирует вызовы реальному сервису \iEXPackages\Calculator\Calculator,
 *   зарегистрированному в контейнере под ключом `calculator.exchange`.
 *
 * Пример:
 * ```php
 * $rate = CalculatorFacade::setDirectionExchange($direction)
 *     ->calculate()
 *     ->getRateValue();
 * ```
 *
 * @method static Calculator setDirectionExchange(DirectionExchange $directionExchange) Устанавливает направление обмена.
 * @method static Calculator setOrder(Task $item) Устанавливает текущую заявку (задачу).
 * @method static Task|null getOrder() Возвращает текущую заявку (если установлена).
 * @method static Calculator setOptions(array $options) Устанавливает дополнительные опции расчёта.
 * @method static array getOptions() Возвращает дополнительные опции расчёта.
 * @method static Calculator calculate() Выполняет расчёт курса (без дополнительных опций заявки).
 * @method static Calculator calculateWithOptions(array $options = []) Выполняет расчёт с дополнительными опциями (город, суммы, выбранные комиссии и т.п.).
 * @method static string getRateValue() Возвращает рассчитанный курс (decimal-string).
 * @method static string getRateValueString() Возвращает курс, отформатированный для отображения.
 * @method static string getFullRate() Возвращает курс в читабельном виде (например: "1 USD = 0.00002 BTC").
 * @method static array toArray() Возвращает массив данных результата расчёта.
 *
 * @see \iEXPackages\Calculator\Calculator
 */
final class CalculatorFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'calculator.exchange';
    }
}
