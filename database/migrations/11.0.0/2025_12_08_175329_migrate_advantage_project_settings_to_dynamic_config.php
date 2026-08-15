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
        if (!Schema::hasTable('project_settings')) {
            return;
        }

        $rows = DB::table('project_settings')
            ->where('group', 'advantage')
            ->get(['name', 'payload']);

        if ($rows->isEmpty()) {
            return;
        }

        // advantage — глобальные настройки интерфейса
        $scope = Scope::fromString('global', null);

        $map = [
            'is_advantage_style'   => 'advantage.is_advantage_style',
            'advantage_col'        => 'advantage.advantage_col',
            'advantage_row_height' => 'advantage.advantage_row_height',
            'advantage_gutter_size'=> 'advantage.advantage_gutter_size',
        ];

        foreach ($rows as $row) {
            $name    = (string) $row->name;
            $payload = $row->payload;

            if (!array_key_exists($name, $map)) {
                continue;
            }

            $key   = $map[$name];
            $value = $this->decodePayload($payload);

            // force: обход ACL/lock, но с учётом схемы (normalizeAndValidate)
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
                'advantage.is_advantage_style',
                'advantage.advantage_col',
                'advantage.advantage_row_height',
                'advantage.advantage_gutter_size',
            ])
            ->delete();
    }

    private function decodePayload(mixed $payload): mixed
    {
        if (!is_string($payload)) {
            return $payload;
        }

        $trimmed = trim($payload);

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
            return $decoded;
        } catch (\Throwable) {
            return $payload;
        }
    }
};
