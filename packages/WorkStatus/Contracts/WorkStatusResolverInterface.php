<?php

declare(strict_types=1);

namespace iEXPackages\WorkStatus\Contracts;

use Carbon\CarbonInterface;
use iEXPackages\WorkStatus\DTO\WorkStatusResult;

/**
 * WorkStatusResolverInterface
 *
 * Контракт источника решения статуса.
 *
 * Резолвер либо:
 * - возвращает WorkStatusResult (если может принять решение),
 * - либо возвращает null (передавая управление следующему резолверу).
 */
interface WorkStatusResolverInterface
{
    /**
     * @param CarbonInterface $now Момент времени расчёта (передаётся сверху единообразно).
     */
    public function resolve(CarbonInterface $now): ?WorkStatusResult;
}
