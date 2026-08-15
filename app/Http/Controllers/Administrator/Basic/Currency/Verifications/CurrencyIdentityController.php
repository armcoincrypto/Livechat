<?php

namespace App\Http\Controllers\Administrator\Basic\Currency\Verifications;

use App\Models\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrencyIdentityController
{
    public function edit(int $id): JsonResponse
    {
        $item = Currency::query()->findOrFail($id);

        // Правила верификации личности храним в отдельном JSON-поле
        $rules = $item->identity_verification_rules ?? [];

        $mode      = $rules['mode'] ?? 'disabled';
        $minAmount = (float) ($rules['min_amount'] ?? 0);
        $behavior  = (int) ($rules['unverified_behavior'] ?? 0);

        // Attributes текущей валюты для фронта
        $attributes = [
            // Режим и порог для верификации личности на уровне валюты
            'identity_verification_mode'      => (string) $mode,
            'identity_min_amount'             => $minAmount,
            'identity_unverified_behavior'    => $behavior,

            // Тексты по личности (мультиязычные поля модели)
            'identity_text' => $item->getTranslations('identity_text'),
            'identity_info' => $item->getTranslations('identity_info'),
        ];

        return response()->json([
            'options'    => [],
            'attributes' => $attributes,
            'default'    => [
                'tech_name' => $item->tech_name ?? ''
            ],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = Currency::query()->findOrFail($id);

        $data = $request->validate([
            'identity_verification_mode'   => ['required', 'string'],
            'identity_min_amount'          => ['nullable', 'numeric'],
            'identity_unverified_behavior' => ['nullable', 'integer'],
            'identity_text'                => ['nullable', 'array'],
            'identity_info'                => ['nullable', 'array'],
        ]);

        // Собираем JSON-конфиг настроек верификации личности
        $rules = [
            'mode'               => (string) ($data['identity_verification_mode'] ?? 'disabled'),
            'min_amount'         => (float) ($data['identity_min_amount'] ?? 0),
            'unverified_behavior'=> (int) ($data['identity_unverified_behavior'] ?? 0),
        ];

        $item->update([
            'identity_verification_rules' => $rules,
            'identity_text'              => normalizeHtmlFieldValue($data['identity_text'] ?? []) ?? [],
            'identity_info'              => normalizeHtmlFieldValue($data['identity_info'] ?? []) ?? [],
        ]);

        return response()->json([
            'status'  => 0,
            'message' => ('Настройки верификации личности для валюты успешно обновлены.')
        ]);
    }
}
