<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts_groups', function (Blueprint $table) {
            $table
                ->tinyInteger('status')
                ->default(1)
                ->after('sorting')
                ->comment('0 - disabled, 1 - enabled');
        });
    }

    public function down(): void
    {
        Schema::table('contacts_groups', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
