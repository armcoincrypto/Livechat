<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use iEXPackages\DynamicConfig\Facades\DynamicConfig;
use iEXPackages\DynamicConfig\ValueObjects\Scope;

return new class extends Migration {
    public function up(): void
    {
        // Если таблицы project_settings нет — выходим.
        if (!Schema::hasTable('project_settings')) {
            return;
        }

        // Читаем записи только группы banners
        $rows = DB::table('project_settings')
            ->where('group', 'banners')
            ->get(['name', 'payload']);

        if ($rows->isEmpty()) {
            return;
        }

        // scope: global → именно то, что нужно для баннеров
        $scope = Scope::fromString('global', null);

        // Маппинг: имя Spatie → ключ DynamicConfig
        $map = [
            'is_autoplay' => 'banners.is_autoplay',
            'timeout'     => 'banners.timeout',
            'hide_nav'    => 'banners.hide_nav',
        ];

        foreach ($rows as $row) {
            $name    = (string) $row->name;
            $payload = $row->payload;

            if (!array_key_exists($name, $map)) {
                continue;
            }

            $key   = $map[$name];
            $value = $this->decodePayload($payload);

            // Пишем в DynamicConfig с режимом force (обход ACL/lock),
            // но с учётом схемы (normalizeAndValidate сработает).
            DynamicConfig::setWithMode($key, $value, 'force', $scope);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('dynamic_config_settings')) {
            return;
        }

        $scope = Scope::fromString('global', null);

        DB::table('dynamic_config_settings')
            ->where('scope_type', $scope->type)
            ->where('scope_id', $scope->id)
            ->whereIn('key', [
                'banners.is_autoplay',
                'banners.timeout',
                'banners.hide_nav',
            ])
            ->delete();
    }

    /**
     * Преобразование payload из Spatie в нормальное PHP-значение.
     *
     * payload может быть:
     *  - "0", "1", "10"
     *  - "true", "false"
     *  - "\"file\"" (строка в JSON)
     *  - "[1,2,3]"
     *  - "null"
     */
    private function decodePayload(mixed $payload): mixed
    {
        if (!is_string($payload)) {
            return $payload;
        }

        $trimmed = trim($payload);

        // Пытаемся декодировать как JSON
        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
            return $decoded;
        } catch (\Throwable) {
            // Не JSON — возвращаем как есть (schema потом приведёт к типу)
            return $payload;
        }
    }
};
