<?php

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
            ->where('group', 'bestchange-blacklist')
            ->get(['name', 'payload', 'created_at', 'updated_at']);

        if ($rows->isEmpty()) {
            return;
        }

        $inserts = [];

        foreach ($rows as $row) {
            $key   = 'bestchange_blacklist.' . (string) $row->name;
            $value = $this->decodePayload($row->payload);

            $inserts[] = [
                'scope_type' => 'plugins',
                'scope_id'   => null,
                'key'        => $key,
                'value'      => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'expires_at' => null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        }

        if ($inserts !== []) {
            DB::table('dynamic_config_settings')->insert($inserts);
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
            ->where('key', 'like', 'bestchange_blacklist.%')
            ->delete();
    }

    private function decodePayload(mixed $payload): mixed
    {
        if (!is_string($payload)) {
            return $payload;
        }

        $trimmed = trim($payload);

        try {
            return json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return $payload;
        }
    }
};
