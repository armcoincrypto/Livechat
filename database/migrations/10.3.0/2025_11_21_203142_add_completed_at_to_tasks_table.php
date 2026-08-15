<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Дата фактического завершения заявки (исполнена/успешна)
            $table->timestamp('completed_at')
                ->nullable()
                ->after('updated_at'); // можешь поставить after('status') если удобнее
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
