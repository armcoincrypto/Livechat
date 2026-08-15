<?php
declare(strict_types=1);

namespace iEXPackages\SmartMailer\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Интерфейс для сервиса отправки писем и задач (Jobs).
 *
 * Предоставляет методы для отправки писем через почтовую систему Laravel или через очередь задач,
 * с поддержкой отложенной отправки, кеширования и проверки состояния отправки.
 *
 * @package iEXPackages\SmartMailer\Contracts
 */
interface MailDispatcherContract
{
    /**
     * Отправляет письмо (Mailable) или задачу (Job) с указанными параметрами.
     *
     * @param string|Mailable|ShouldQueue $sendable Ключ из конфигурации или экземпляр отправляемого объекта.
     * @param Model $model Модель, связанная с отправляемым письмом (данные письма, email и др.).
     * @param string|null $email Явный email получателя (опционально, берётся из модели, если null).
     * @param array|null $data Дополнительные данные, передаваемые в Mailable или Job (например, параметры письма).
     * @param int|null $delaySeconds Задержка отправки в секундах (опционально).
     * @param string $queue Название очереди, в которую будет помещена задача.
     * @param string|null $cacheKey Ключ кеша для предотвращения повторных отправок (опционально).
     * @param int $cacheMinutes Время кеширования в минутах (по умолчанию 10 минут).
     *
     * @return bool Статус отправки: true, если отправка прошла успешно, иначе false.
     */
    public function dispatch(
        string|Mailable|ShouldQueue $sendable,
        Model $model,
        ?string $email = null,
        ?array $data = null,
        ?int $delaySeconds = null,
        string $queue = 'default',
        ?string $cacheKey = null,
        int $cacheMinutes = 10
    ): bool;

    /**
     * Проверяет, включена ли отправка писем через конфигурацию.
     *
     * @return bool true, если отправка писем включена, иначе false.
     */
    public function isMailEnabled(): bool;

    /**
     * Регистрирует новый класс письма (Mailable).
     *
     * @param string $key Ключ для идентификации письма.
     * @param class-string<Mailable> $mailableClass Класс письма для регистрации.
     */
    public function registerMailable(string $key, string $mailableClass): void;

    /**
     * Регистрирует новый класс задачи (Job).
     *
     * @param string $key Ключ для идентификации задачи.
     * @param class-string<ShouldQueue> $jobClass Класс задачи для регистрации.
     */
    public function registerJob(string $key, string $jobClass): void;
}
