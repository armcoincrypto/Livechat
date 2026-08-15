<?php
use Spatie\Permission\Models\Permission;

return function () {
    updatePermissionRoles();

    DB::beginTransaction();

    try {
        $run = function (string $command, array $params = []) {
            try {
                Artisan::call($command, $params);
                dump($command . ' OK');
                dump(Artisan::output());
            } catch (\Throwable $e) {
                // Ошибку не выкидываем наружу, просто показываем, что команда упала
                dump($command . ' ERROR: ' . $e->getMessage());
            }
        };

        $run('profit:recalculate-daily');

        $run('reserves:total-snapshot');

        $run('stats:directions-daily', [
            '--from' => now()->subDays(2)->toDateString(),
            '--to'   => now()->toDateString(),
        ]);

        $run('stats:orders-daily');

        $run('stats:currencies-daily');

    } finally {
        // Всегда откатываем изменения, даже если что-то упало
        DB::rollBack();
    }
};


function updatePermissionRoles()
{
    // Ищем id группы прав "Аналитика"
    $analyticsGroupId = DB::table('permissions_group')
        ->where('name', 'Аналитика')
        ->value('id');

    if (! $analyticsGroupId) {
        $analyticsGroupId = 6;
    }

    Permission::where('name', '=', 'admin_analytics')->delete();

    // 1. Доступ к аналитике обменов
    Permission::updateOrCreate(
        ['name' => 'admin_analytics_exchanges'],
        [
            'name'                => 'admin_analytics_exchanges',
            'title'               => 'Доступ к аналитике обменов',
            'text'                => 'Позволяет просматривать и использовать разделы аналитики по обменным операциям в админпанели.',
            'id_permissions_group'=> $analyticsGroupId,
        ]
    );

    // 2. Доступ к аналитике партнёрской программы
    Permission::updateOrCreate(
        ['name' => 'admin_analytics_partners'],
        [
            'name'                => 'admin_analytics_partners',
            'title'               => 'Доступ к аналитике партнёрской программы',
            'text'                => 'Позволяет просматривать отчёты и статистику по партнёрской программе и реферальным начислениям.',
            'id_permissions_group'=> $analyticsGroupId,
        ]
    );

    // 3. Доступ к аналитике пользователей
    Permission::updateOrCreate(
        ['name' => 'admin_analytics_users'],
        [
            'name'                => 'admin_analytics_users',
            'title'               => 'Доступ к аналитике пользователей',
            'text'                => 'Позволяет просматривать расширенную статистику по пользователям, их активности и заявкам.',
            'id_permissions_group'=> $analyticsGroupId,
        ]
    );
}
