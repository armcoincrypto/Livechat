<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reserves', function (Blueprint $table): void {
            $table->decimal('summa', 65, 18)
                ->default(0)
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('reserves', function (Blueprint $table): void {
            $table->double('summa')
                ->default(0)
                ->change();
        });
    }
};
