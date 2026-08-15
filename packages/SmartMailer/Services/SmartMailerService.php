<?php
declare(strict_types=1);

namespace iEXPackages\SmartMailer\Services;

use iEXPackages\SmartMailer\Contracts\MailDispatcherContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/**
 * Сервис отправки писем и задач в очередях.
 * Поддерживает отправку через почтовую систему Laravel или через очередь задач.
 */
class SmartMailerService implements MailDispatcherContract
{
    /**
     * Карта классов писем (Mailable).
     *
     * @var array<string, class-string<Mailable>>
     */
    protected array $mailablesMap;

    /**
     * Карта классов задач (Job).
     *
     * @var array<string, class-string<ShouldQueue>>
     */
    protected array $jobsMap;

    /**
     * Создает экземпляр сервиса с загрузкой конфигураций из smart-mailer.php.
     */
    public function __construct()
    {
        $this->mailablesMap = config('smart-mailer.mailables', []);
        $this->jobsMap = config('smart-mailer.jobs', []);
    }

    /**
     * Отправляет email или задачу Job на отправку.
     *
     * @param string|Mailable|ShouldQueue $sendable Ключ из конфигурации или объект отправки.
     * @param Model $model Связанная модель.
     * @param string|null $email Email получателя (берется из модели, если не указан).
     * @param array|null $data Дополнительные данные, передаваемые в Mailable или Job (например, параметры письма).
     * @param int|null $delaySeconds Задержка отправки в секундах.
     * @param string $queue Название очереди.
     * @param string|null $cacheKey Ключ кеша для предотвращения повторных отправок.
     * @param int $cacheMinutes Минуты кеширования отправки.
     *
     * @return bool Статус отправки.
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
    ): bool {
        if (!$this->isMailEnabled()) {
            return false;
        }

        if ($cacheKey && Cache::has($cacheKey)) {
            return false;
        }

        $sendableInstance = $this->resolveSendable($sendable, $model, $data); // ← изменено тут

        try {
            if ($sendableInstance instanceof ShouldQueue) {
                $dispatch = dispatch($sendableInstance)->onQueue($queue);
                if ($delaySeconds !== null) {
                    $dispatch->delay(now()->addSeconds($delaySeconds));
                }
            } elseif ($sendableInstance instanceof Mailable) {
                $email = $email ?? $model->email;
                if (empty($email)) {
                    throw new InvalidArgumentException('Email получателя не указан и отсутствует в модели.');
                }

                $mailer = Mail::to($email);
                if ($delaySeconds !== null) {
                    $mailer->later(now()->addSeconds($delaySeconds), $sendableInstance->onQueue($queue));
                } else {
                    $mailer->send($sendableInstance);
                }
            }

            if ($cacheKey) {
                Cache::put($cacheKey, true, now()->addMinutes($cacheMinutes));
            }

            return true;
        } catch (\Throwable $e) {
            Log::error("[SmartMailer] Ошибка отправки: {$e->getMessage()}", [
                'sendable' => is_string($sendable) ? $sendable : get_class($sendable),
                'model_type' => get_class($model),
                'model_id' => $model->getKey(),
                'email' => $email,
            ]);

            return false;
        }
    }

    /**
     * Регистрирует новый класс письма (Mailable).
     *
     * @param string $key
     * @param class-string<Mailable> $mailableClass
     */
    public function registerMailable(string $key, string $mailableClass): void
    {
        $this->mailablesMap[$key] = $mailableClass;
    }

    /**
     * Регистрирует новый класс задачи (Job).
     *
     * @param string $key
     * @param class-string<ShouldQueue> $jobClass
     */
    public function registerJob(string $key, string $jobClass): void
    {
        $this->jobsMap[$key] = $jobClass;
    }

    /**
     * Возвращает объект отправляемого класса.
     *
     * @param string|Mailable|ShouldQueue $sendable
     * @param Model $model
     *
     * @throws InvalidArgumentException
     *
     * @return Mailable|ShouldQueue
     */
    protected function resolveSendable(
        string|Mailable|ShouldQueue $sendable,
        Model $model,
        ?array $data = null
    ): Mailable|ShouldQueue {
        if (is_string($sendable)) {
            if (isset($this->jobsMap[$sendable])) {
                return new $this->jobsMap[$sendable]($model, $data);
            }

            if (isset($this->mailablesMap[$sendable])) {
                return new $this->mailablesMap[$sendable]($model, $data);
            }

            throw new InvalidArgumentException("Отправляемый объект '{$sendable}' не найден в конфигурации.");
        }

        return $sendable;
    }

    /**
     * Проверяет, включена ли отправка писем через конфигурацию.
     *
     * @return bool
     */
    public function isMailEnabled(): bool
    {
        return config('smart-mailer.enabled', true);
    }
}
