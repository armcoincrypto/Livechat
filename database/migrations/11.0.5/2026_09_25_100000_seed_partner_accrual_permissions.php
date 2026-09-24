<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * Wave C: seed granular partner accrual permissions (additive).
 * Role assignment is a Wave D operator step. admin_users remains a valid manage/view super-set.
 */
return new class extends Migration
{
    public function up(): void
    {
        $groupId = DB::table('permissions_group')->where('name', 'Пользователи')->value('id')
            ?? DB::table('permissions_group')->orderBy('id')->value('id');

        foreach ([
            [
                'name' => 'admin_partner_accrual_view',
                'title' => 'Просмотр партнёрского начисления',
                'text' => 'Просмотр ежемесячных планов партнёрского начисления без права изменения.',
            ],
            [
                'name' => 'admin_partner_accrual_manage',
                'title' => 'Управление партнёрским начислением',
                'text' => 'Создание, пауза, возобновление и отмена ежемесячных планов партнёрского начисления.',
            ],
        ] as $perm) {
            Permission::updateOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'web'],
                [
                    'title' => $perm['title'],
                    'text' => $perm['text'],
                    'id_permissions_group' => $groupId,
                ]
            );
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', [
            'admin_partner_accrual_view',
            'admin_partner_accrual_manage',
        ])->delete();
    }
};
