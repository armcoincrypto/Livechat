<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use iEXPackages\DynamicConfig\Facades\DynamicConfig;
use iEXPackages\DynamicConfig\ValueObjects\Scope;

return new class extends Migration {
    public function up(): void
    {
        // Читаем строку из language_contents
        $row = DB::table('language_contents')->where('id', 1)->first();

        if (!$row) {
            return;
        }

        // Scope для языковых настроек
        $scope = Scope::fromString('language', 1);

        // Список колонок, которые переносим (можно получить динамически)
        $excluded = ['id', 'created_at', 'updated_at'];
        $columns  = collect((array) $row)->keys()->reject(fn ($col) => in_array($col, $excluded, true));

        foreach ($columns as $column) {
            $rawValue = $row->{$column};

            if ($rawValue === null) {
                continue;
            }

            $decoded = null;
            $isJson  = false;

            if (is_string($rawValue)) {
                try {
                    $tmp = json_decode($rawValue, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($tmp)) {
                        $decoded = $tmp;
                        $isJson  = true;
                    }
                } catch (\Throwable) {
                    // не JSON — оставляем как строку
                }
            }

            $key   = 'language.' . $column;
            $value = $isJson ? $decoded : $rawValue;

            // ВАЖНО: используем force-режим, чтобы обойти ACL/lock, но не обходить schema
            DynamicConfig::setWithMode($key, $value, 'force', $scope);
        }
    }

    public function down(): void
    {
        // При откате можно очистить scope language
        $scope = Scope::fromString('language', 1);

        DB::table('dynamic_config_settings')
            ->where('scope_type', $scope->type)
            ->where('scope_id', $scope->id)
            ->delete();
    }
};
