<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            // code: делаем NOT NULL + индекс уникальности (аккуратно ниже)
            // В MySQL нельзя всегда "change" без doctrine/dbal, поэтому делаем через raw при необходимости.
            // Но UNIQUE индекс можно поставить прямо.
            if (!Schema::hasColumn('promo_codes', 'scope_mode')) {
                $table->string('scope_mode', 16)->default('all')->after('status'); // all | include
            }

            if (!Schema::hasColumn('promo_codes', 'discount_type')) {
                $table->string('discount_type', 16)->default('percent')->after('used'); // percent | fixed (на будущее)
            }

            if (!Schema::hasColumn('promo_codes', 'discount_value')) {
                // универсальное значение скидки: percent или fixed (в валюте направления — если внедришь)
                $table->decimal('discount_value', 18, 2)->default('0')->after('discount_type');
            }
        });

        // 1) Нормализуем discount_percent -> discount_value (percent)
        DB::table('promo_codes')->update([
            'discount_type' => 'percent',
        ]);

        // Перенесём значения discount_percent в discount_value, если discount_value ещё 0.
        // Важно: не теряем данные.
        DB::statement("
            UPDATE promo_codes
            SET discount_value = COALESCE(discount_value, 0) + COALESCE(discount_percent, 0)
            WHERE (discount_value IS NULL OR discount_value = 0)
        ");

        // 2) Поправим нулевые даты (если они есть) на NULL
        // MySQL может хранить '0000-00-00 00:00:00' в timestamp при нестрогих настройках
        DB::statement("
            UPDATE promo_codes
            SET expired_at = NULL
            WHERE expired_at = '0000-00-00 00:00:00'
        ");

        // 3) count_uses: 0 -> NULL (безлимит) — только если ты хочешь такую семантику
        // Если тебе нужно, чтобы 0 означало "нельзя", убери этот UPDATE.
        DB::statement("
            UPDATE promo_codes
            SET count_uses = NULL
            WHERE count_uses = 0
        ");

        // 4) Таблица pivot для направлений
        Schema::create('promo_code_directions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('promo_code_id');
            $table->integer('direction_exchange_id');
            $table->string('mode', 16); // include | exclude
            $table->timestamps();

            $table->index(['promo_code_id', 'mode']);
            $table->index(['direction_exchange_id', 'mode']);

            $table->unique(['promo_code_id', 'direction_exchange_id', 'mode'], 'promo_code_dir_mode_unique');

            $table->foreign('promo_code_id')
                ->references('id')->on('promo_codes')
                ->onDelete('cascade');

            $table->foreign('direction_exchange_id')
                ->references('id')->on('direction_exchange')
                ->onDelete('cascade');
        });

        // 5) Перенос permitted_directions / forbidden_directions в pivot
        // permitted_directions: "1,2,3" => include
        // forbidden_directions: "1,2,3" => exclude
        $this->migrateLegacyDirections('permitted_directions', 'include');
        $this->migrateLegacyDirections('forbidden_directions', 'exclude');

        // 6) Установка scope_mode:
        // - если есть permitted_directions (include) => scope_mode = include
        // - иначе по умолчанию all
        DB::statement("
            UPDATE promo_codes
            SET scope_mode = 'include'
            WHERE permitted_directions IS NOT NULL
              AND TRIM(permitted_directions) <> ''
        ");

        // 7) UNIQUE для code + NOT NULL (аккуратно)
        // Если в таблице есть NULL code — выставим заглушки, чтобы не упасть на NOT NULL/UNIQUE.
        // Лучше в админке запретить пустые коды, но миграция должна проходить.
        $nullCodes = DB::table('promo_codes')->whereNull('code')->count();
        if ($nullCodes > 0) {
            // Генерируем уникальные "LEGACY-<id>" для NULL
            DB::statement("
                UPDATE promo_codes
                SET code = CONCAT('LEGACY-', id)
                WHERE code IS NULL
            ");
        }

        // Приведём code к trim
        DB::statement("UPDATE promo_codes SET code = TRIM(code)");

        // NOT NULL: через raw (чтобы не упираться в doctrine/dbal)
        DB::statement("ALTER TABLE promo_codes MODIFY code VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");

        // UNIQUE индекс (если его ещё нет)
        // MySQL не умеет IF NOT EXISTS для add unique index во всех версиях, проверим через information_schema
        $exists = DB::selectOne("
            SELECT COUNT(1) AS cnt
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'promo_codes'
              AND index_name = 'promo_codes_code_unique'
        ");

        if (!empty($exists) && (int)$exists->cnt === 0) {
            DB::statement("ALTER TABLE promo_codes ADD UNIQUE KEY promo_codes_code_unique (code)");
        }

        // 8) Опционально: удалить старые поля permitted/forbidden и discount_percent
        // Я делаю это В КОНЦЕ, когда перенос уже выполнен.
        Schema::table('promo_codes', function (Blueprint $table) {
            if (Schema::hasColumn('promo_codes', 'permitted_directions')) {
                $table->dropColumn('permitted_directions');
            }
            if (Schema::hasColumn('promo_codes', 'forbidden_directions')) {
                $table->dropColumn('forbidden_directions');
            }
            if (Schema::hasColumn('promo_codes', 'discount_percent')) {
                $table->dropColumn('discount_percent');
            }
        });
    }

    private function migrateLegacyDirections(string $column, string $mode): void
    {
        // Читаем пачками, чтобы не съесть память
        DB::table('promo_codes')
            ->select(['id', $column])
            ->whereNotNull($column)
            ->whereRaw("TRIM($column) <> ''")
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($column, $mode) {
                $now = now();
                $insert = [];

                foreach ($rows as $row) {
                    $raw = (string) $row->{$column};
                    $ids = array_filter(array_map('trim', explode(',', $raw)), static fn($v) => $v !== '');

                    foreach ($ids as $directionId) {
                        if (!ctype_digit($directionId)) {
                            continue;
                        }

                        $insert[] = [
                            'promo_code_id' => (int) $row->id,
                            'direction_exchange_id' => (int) $directionId,
                            'mode' => $mode,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if ($insert) {
                    // вставляем без дублей
                    // MySQL: INSERT IGNORE
                    $values = [];
                    $bindings = [];
                    foreach ($insert as $item) {
                        $values[] = "(?, ?, ?, ?, ?)";
                        $bindings[] = $item['promo_code_id'];
                        $bindings[] = $item['direction_exchange_id'];
                        $bindings[] = $item['mode'];
                        $bindings[] = $item['created_at'];
                        $bindings[] = $item['updated_at'];
                    }

                    DB::statement(
                        "INSERT IGNORE INTO promo_code_directions (promo_code_id, direction_exchange_id, mode, created_at, updated_at) VALUES " . implode(',', $values),
                        $bindings
                    );
                }
            }, 'id');
    }

    public function down(): void
    {
        // Откат: вернём колонки назад (без восстановления строковых списков — это осознанно).
        Schema::table('promo_codes', function (Blueprint $table) {
            if (!Schema::hasColumn('promo_codes', 'discount_percent')) {
                $table->double('discount_percent', 8, 2)->default(0)->after('used');
            }
            if (!Schema::hasColumn('promo_codes', 'permitted_directions')) {
                $table->string('permitted_directions', 191)->nullable();
            }
            if (!Schema::hasColumn('promo_codes', 'forbidden_directions')) {
                $table->string('forbidden_directions', 191)->nullable();
            }

            if (Schema::hasColumn('promo_codes', 'scope_mode')) {
                $table->dropColumn('scope_mode');
            }
            if (Schema::hasColumn('promo_codes', 'discount_type')) {
                $table->dropColumn('discount_type');
            }
            if (Schema::hasColumn('promo_codes', 'discount_value')) {
                $table->dropColumn('discount_value');
            }
        });

        Schema::dropIfExists('promo_code_directions');
    }
};
