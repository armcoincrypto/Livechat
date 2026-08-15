<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faq_category', function (Blueprint $table) {
            if (!Schema::hasColumn('faq_category', 'status')) {
                $table->unsignedTinyInteger('status')
                    ->default(1)
                    ->after('sorting');
            }
        });
    }

    public function down(): void
    {
        Schema::table('faq_category', function (Blueprint $table) {
            if (Schema::hasColumn('faq_category', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
