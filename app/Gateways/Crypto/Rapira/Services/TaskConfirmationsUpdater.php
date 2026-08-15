<?php

namespace App\Gateways\Crypto\Rapira\Services;

use App\Models\TaskSingleLogConfirm;
use iEXPackages\Payments\Gateways\Crypto\Rapira\Messages\FetchPaymentResponse;

final class TaskConfirmationsUpdater
{
    public function handle(FetchPaymentResponse $response): void
    {
        $task = $response->getTask();
        if (!$task) return;

        if (!$response->isFindPayment()) return;

        if ($response->needsMoreConfirmations()) {
            TaskSingleLogConfirm::updateOrCreate(
                ['id_task' => $task->id],
                [
                    'needed_confirm'   => $response->getConfirmationsRequired(),
                    'received_confirm' => $response->getConfirmationsCurrent(),
                ]
            );
        }
    }
}
