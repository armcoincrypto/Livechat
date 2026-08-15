<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('direction_exchange', function (Blueprint $table) {
            $table->string('instruction_source_mode', 20)
                ->default('auto')
                ->after('instructions');
        });

        Schema::table('currencies', function (Blueprint $table) {
            $table->string('instruction_source_mode', 20)
                ->default('auto')
                ->after('instruction_exchange');
        });
    }

    public function down(): void
    {
        Schema::table('direction_exchange', function (Blueprint $table) {
            $table->dropColumn('instruction_source_mode');
        });

        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn('instruction_source_mode');
        });
    }
};
