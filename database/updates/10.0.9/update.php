<?php

use App\Models\BestChangeDirection;
use App\Models\Currency;
use App\Models\DirectionExchange;
use App\Models\GroupCommission;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;

return function () {
    migrateIdGroupCommission();
};


function migrateIdGroupCommission() {
    DirectionExchange::query()
        ->whereNotNull('id_group_commission')
        ->chunkById(50, function ($directions) {
            foreach ($directions as $direction) {
                $groupCommissionId = $direction->id_group_commission;

                $group = GroupCommission::find($groupCommissionId);
                if ($group) {
                    $group->directions()->syncWithoutDetaching([$direction->id]);
                }

                // Сразу аннулируем старое поле
                $direction->update(['id_group_commission' => 0]);
            }
        });
}
