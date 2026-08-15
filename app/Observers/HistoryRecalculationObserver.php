<?php

namespace App\Observers;

use App\Models\HistoryRecalculation;
use Illuminate\Support\Carbon;

/**
 * Class HistoryRecalculationObserver.
 */
class HistoryRecalculationObserver
{
    /**
     * Слушаем обновленное пользователем событие.
     */
    public function created(HistoryRecalculation $item): void
    {
        $task = $item->tasks_info->update([
            'recalculated_at' => Carbon::now()->format('c'),
        ]);
    }
}
