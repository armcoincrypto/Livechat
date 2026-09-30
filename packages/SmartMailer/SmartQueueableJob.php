<?php

declare(strict_types=1);

namespace iEXPackages\SmartMailer;

use iEXPackages\SmartMailer\Contracts\SmartQueueableInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Абстрактный базовый класс для всех задач пакета SmartMailer.
 *
 * Bounded retry / timeout so transient SMTP failures do not retry forever.
 * deleteWhenMissingModels avoids ModelNotFoundException retry storms.
 */
abstract class SmartQueueableJob implements ShouldQueue, SmartQueueableInterface
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 90];

    public int $timeout = 60;

    public bool $deleteWhenMissingModels = true;

    /**
     * Метод, вызываемый перед выполнением задачи.
     * Может быть переопределён для добавления общей логики (например, логирования).
     */
    protected function beforeHandle(): void
    {
        // Общая логика до выполнения задачи
    }

    /**
     * Метод, вызываемый после выполнения задачи.
     * Может быть переопределён для добавления общей логики (например, отчётности).
     */
    protected function afterHandle(): void
    {
        // Общая логика после выполнения задачи
    }

    /**
     * Абстрактный метод, реализуемый в конкретных задачах.
     */
    abstract protected function run(): void;

    /**
     * Обёртка выполнения задачи с общей логикой.
     */
    final public function handle(): void
    {
        $this->beforeHandle();

        try {
            $this->run();
        } finally {
            $this->afterHandle();
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('smartmailer_job_dead', [
            'job' => static::class,
            'exception_class' => $exception ? $exception::class : null,
            'attempt' => method_exists($this, 'attempts') ? $this->attempts() : null,
            'disposition' => 'exhausted',
        ]);
    }
}
