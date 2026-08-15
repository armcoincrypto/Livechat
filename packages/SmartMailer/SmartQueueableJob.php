<?php
declare(strict_types=1);

namespace iEXPackages\SmartMailer;

use iEXPackages\SmartMailer\Contracts\SmartQueueableInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Абстрактный базовый класс для всех задач пакета SmartMailer.
 */
abstract class SmartQueueableJob implements ShouldQueue, SmartQueueableInterface
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
}
