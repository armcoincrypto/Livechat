<?php
declare(strict_types=1);

namespace iEXPackages\SmartMailer\Facades;

use Illuminate\Support\Facades\Facade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Фасад для удобного вызова методов SmartMailerService.
 *
 * * @method static bool dispatch(string|Mailable|ShouldQueue $sendable, Model $model, ?string $email = null, ?int $delaySeconds = null, string $queue = 'default', ?string $cacheKey = null, int $cacheMinutes = 10, array $data = []) Отправка письма или Job-задачи.
 * @method static bool isMailEnabled() Проверка, включена ли отправка писем.
 * @method static void registerMailable(string $key, string $mailableClass) Регистрация нового Mailable-класса.
 * @method static void registerJob(string $key, string $jobClass) Регистрация нового Job-класса.
 *
 * @see \iEXPackages\SmartMailer\Services\SmartMailerService
 */
class SmartMailer extends Facade
{
    /**
     * Определяет сервис, зарегистрированный в контейнере Laravel.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return \iEXPackages\SmartMailer\Contracts\MailDispatcherContract::class;
    }
}
