<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Storage;

use iEXPackages\DynamicConfig\Contracts\SettingsStorageInterface;
use iEXPackages\DynamicConfig\Models\DynamicConfigSetting;
use iEXPackages\DynamicConfig\ValueObjects\Scope;
use Illuminate\Support\Arr;

final class EloquentSettingsStorage implements SettingsStorageInterface
{
    public function load(Scope $scope): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int,DynamicConfigSetting> $rows */
        $rows = DynamicConfigSetting::query()
            ->where('scope_type', $scope->type)
            ->where('scope_id', $scope->id)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get(['key', 'value']);

        $settings = [];

        foreach ($rows as $row) {
            Arr::set($settings, $row->key, $row->value);
        }

        return $settings;
    }

    public function save(Scope $scope, array $settings): void
    {
        $flat = Arr::dot($settings);

        // удаляем старые (soft-delete через модель)
        DynamicConfigSetting::query()
            ->where('scope_type', $scope->type)
            ->where('scope_id', $scope->id)
            ->delete();

        if ($flat === []) {
            return;
        }

        $now = now();

        $rows = [];

        foreach ($flat as $key => $value) {
            $rows[] = [
                'scope_type' => $scope->type,
                'scope_id'   => $scope->id,
                'key'        => $key,
                'value'      => $value,
                'expires_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // mass insert
        DynamicConfigSetting::query()->insert($rows);
    }

    public function clear(Scope $scope): void
    {
        DynamicConfigSetting::query()
            ->where('scope_type', $scope->type)
            ->where('scope_id', $scope->id)
            ->delete();
    }
}
