<?php

namespace iEXPackages\TagProcessors\Processors;

use iEXPackages\TagProcessors\Contracts\TagProcessorInterface;
use Illuminate\Http\Request;

/**
 * Базовый абстрактный класс для всех процессоров тегов в пакете TagProcessors.
 *
 * Основные задачи:
 * - описать внутреннюю карту обработчиков тегов через метод tagHandlers();
 * - предоставить список поддерживаемых тегов через метод tags();
 * - разрешать конкретный тег в конечное строковое значение через метод handle().
 *
 * Конкретные процессоры должны реализовать метод tagHandlers() и вернуть массив,
 * где ключ — строковый идентификатор тега (например: "[site_name]", "[order_id]"),
 * а значение — замыкание/колбэк вида function (mixed $data, ?Request $request): mixed.
 *
 * Движок TagProcessors далее использует эти обработчики для подстановки значений в тексты.
 */
abstract class AbstractTagProcessor implements TagProcessorInterface
{
    /**
     * Возвращает карту обработчиков тегов для конкретного процессора.
     *
     * Ключами массива являются строковые идентификаторы тегов в том виде,
     * в котором они используются в шаблонах (например: "[site_name]", "[order_id]" и т.п.).
     * Значения — это вызываемые обработчики (callable) со следующей сигнатурой:
     *
     *    function (mixed $data, ?\Illuminate\Http\Request $request): mixed
     *
     * В обработчиках допускается возвращать скалярные значения (string/int/float/bool) или null.
     * Всё остальное будет проигнорировано движком и оставит тег без изменений.
     *
     * @return array<string, callable> Ассоциативный массив "тег → обработчик".
     */
    abstract protected function tagHandlers(): array;

    /**
     * Возвращает список всех поддерживаемых тегов данного процессора.
     *
     * Этот метод используется ядром TagProcessors для:
     * - построения общего индекса всех тегов;
     * - проверки доступности тега;
     * - массовой замены тегов в тексте.
     *
     * Каждый элемент массива — это строковый идентификатор тега
     * (например: "[site_name]", "[order_id]" и т.п.).
     *
     * @return string[] Список тегов в том виде, как они встречаются в тексте.
     */
    public function tags(): array
    {
        return array_keys($this->tagHandlers());
    }

    /**
     * Разрешает один конкретный тег в его итоговое строковое значение.
     *
     * Метод ищет обработчик для переданного тега в карте, возвращаемой tagHandlers(),
     * и, если он найден, вызывает соответствующий колбэк с переданными $data и $request.
     *
     * Если обработчик для тега отсутствует, метод просто возвращает исходную строку $tag
     * без каких‑либо изменений. Это позволяет безопасно обрабатывать неизвестные теги.
     *
     * @param string                         $tag     Идентификатор тега в том виде, как он хранится
     *                                               в карте tagHandlers() (например: "[site_name]").
     * @param mixed                          $data    Произвольные данные контекста, необходимые
     *                                               обработчикам тегов (массив, модель, DTO и т.п.).
     * @param \Illuminate\Http\Request|null  $request Текущий HTTP‑запрос (если применимо); может
     *                                               использоваться для получения IP, host и др.
     *
     * @return string Строковое значение тега или исходная строка $tag, если обработчик не найден.
     */
    public function handle(string $tag, mixed $data = [], ?Request $request = null): string
    {
        $handlers = $this->tagHandlers();

        return isset($handlers[$tag])
            ? (string) $handlers[$tag]($data, $request)
            : $tag;
    }
}
