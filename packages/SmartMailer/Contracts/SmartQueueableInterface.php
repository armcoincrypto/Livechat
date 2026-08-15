<?php
declare(strict_types=1);

namespace iEXPackages\SmartMailer\Contracts;

/**
 * Единый интерфейс для задач (Jobs), используемых в пакете SmartMailer.
 */
interface SmartQueueableInterface
{
    /**
     * Выполняет задачу в очереди.
     *
     * @return void
     */
    public function handle(): void;
}
