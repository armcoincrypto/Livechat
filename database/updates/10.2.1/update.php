<?php

use App\Models\TaskStatus;

function importTaskStatusInProcess()
{
    $data = [
        "id" => 15,
        "name" => ["ru" => "Выплата в процессе"],
        "is_export" => 0,
        "class" => null,
        "color" => "#a70000",
        "sorting" => 0
    ];

    // Используем Eloquent для обновления или создания
    return TaskStatus::updateOrCreate(
        ['id' => $data['id']],
        [
            "id" => $data['id'],
            'name'      => $data['name'],
            'is_export' => $data['is_export'],
            'class'     => $data['class'],
            'color'     => $data['color'],
            'sorting'   => $data['sorting'],
        ]
    );
}


return function () {
    importTaskStatusInProcess();
};
