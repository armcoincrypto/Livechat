<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 6: enforce tasks.public_id uniqueness at the database layer.
 */
return new class extends Migration
{
    public function up(): void
    {
        $dupes = (int) DB::table('tasks')
            ->select('public_id')
            ->whereNotNull('public_id')
            ->groupBy('public_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        if ($dupes > 0) {
            throw new \RuntimeException("Refusing unique index: {$dupes} duplicate public_id group(s) remain");
        }

        $zeros = (int) DB::table('tasks')->where('public_id', 0)->count();
        if ($zeros > 1) {
            throw new \RuntimeException("Refusing unique index: {$zeros} rows with public_id=0");
        }

        Schema::table('tasks', function (Blueprint $table) {
            // Replace non-unique index with unique constraint.
            try {
                $table->dropIndex('tasks_public_id_index');
            } catch (\Throwable) {
                // index name may differ on some hosts
            }
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->unique('public_id', 'tasks_public_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            try {
                $table->dropUnique('tasks_public_id_unique');
            } catch (\Throwable) {
            }
            $table->index('public_id', 'tasks_public_id_index');
        });
    }
};
