<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $table = 'tasks_status';

        if (!DB::table($table)->where('id', 16)->exists()) {
            DB::table($table)->insert([
                'id'           => 16,
                'name'         => '{"ru":"В очереди на выплату"}',
                'color'        => '#6f42c1',
                'class'        => 'st-payout-queue',
                'is_export'    => 0,
                'updated_at'   => now(),
                'allow_delete' => 0,
                'sorting'      => 14,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('tasks_status')->where('id', 16)->delete();
    }
};
