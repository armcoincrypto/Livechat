<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('project_settings') || !Schema::hasTable('dynamic_config_settings')) {
            return;
        }

        $rows = DB::table('project_settings')
            ->where('group', 'card_info')
            ->get(['name', 'payload', 'created_at', 'updated_at']);

        if ($rows->isEmpty()) {
            return;
        }

        $insert = [];

        foreach ($rows as $row) {
            $name    = (string) $row->name;
            $payload = $row->payload;

            $key   = 'bin_inspector.' . $name;
            $value = $this->decodePayload($payload);

            $insert[] = [
                'scope_type' => 'plugins',
                'scope_id'   => null,
                'key'        => $key,
                'value'      => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'expires_at' => null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        }

        if (!empty($insert)) {
            DB::table('dynamic_config_settings')->insert($insert);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('dynamic_config_settings')) {
            return;
        }

        DB::table('dynamic_config_settings')
            ->where('scope_type', 'plugins')
            ->whereNull('scope_id')
            ->where('key', 'like', 'bin_inspector.%')
            ->delete();
    }

    private function decodePayload(mixed $payload): mixed
    {
        if (!is_string($payload)) {
            return $payload;
        }

        $trimmed = trim($payload);

        // payload в project_settings — JSON, но иногда это просто "0"/"1" или '"строка"'
        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
            return $decoded;
        } catch (\Throwable) {
            // если не JSON, возвращаем как строку — схема потом приведёт к типу
            return $payload;
        }
    }
};
