<?php

use App\Models\BestChangeDirection;
use Illuminate\Support\Str;

return function () {

    // Получаем все записи из таблицы
    BestChangeDirection::query()->chunkById(500, function ($directions) {
        foreach ($directions as $direction) {
            if (preg_match_all('/\[(.*?)\]/', $direction->name, $matches) && !empty($matches[1])) {
                // Генерируем код, приводя его к нижнему регистру и добавляя id
                $code = sprintf(
                    '[bestchange_%s_%s]',
                    Str::lower(implode('_', $matches[1])),
                    $direction->id
                );

                // Безопасно обновляем запись в базе данных
                $direction->update(['code' => $code]);
            }
        }
    });
};
