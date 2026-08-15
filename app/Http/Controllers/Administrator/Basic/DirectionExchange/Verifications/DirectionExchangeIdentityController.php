<?php

namespace App\Http\Controllers\Administrator\Basic\DirectionExchange\Verifications;

use App\Models\DirectionExchange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DirectionExchangeIdentityController
{
    public function edit(int $id): JsonResponse
    {
        $item = DirectionExchange::query()->findOrFail($id);

        // Правила верификации личности храним в отдельном JSON-поле
        $rules = $item->identity_verification_rules ?? [];

        $mode      = $rules['mode'] ?? 'disabled';
        $minAmount = (float) ($rules['min_amount'] ?? 0);

        // Attributes текущего DirectionExchange для фронта
        $attributes = [
            // Тип верификации личности: 0 — по умолчанию от валюты, 1 — от направления
            'identity_verification_type' => (int) ($item->identity_verification_type ?? 0),

            // Режим и порог для верификации личности на уровне направления
            'identity_verification_mode' => (string) $mode,
            'identity_min_amount'        => $minAmount,

            // Тексты по личности (мультиязычные поля модели)
            'identity_text' => $item->getTranslations('identity_text'),
            'identity_info' => $item->getTranslations('identity_info'),
        ];

        return response()->json([
            'options'    => [],
            'attributes' => $attributes,
            'default'    => [],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = DirectionExchange::query()->findOrFail($id);

        $data = $request->validate([
            'identity_verification_type' => ['required', 'integer'],
            'identity_verification_mode' => ['required', 'string'],
            'identity_min_amount'        => ['nullable', 'numeric'],

            'identity_text'              => ['nullable', 'array'],
            'identity_info'              => ['nullable', 'array'],
        ]);

        // Собираем JSON-конфиг настроек верификации личности
        $rules = [
            'mode'       => (string) ($data['identity_verification_mode'] ?? 'disabled'),
            'min_amount' => (float) ($data['identity_min_amount'] ?? 0),
        ];

        $item->update([
            'identity_verification_type'  => $data['identity_verification_type'] ?? 0,
            'identity_verification_rules' => $rules,

            'identity_text' => normalizeHtmlFieldValue($data['identity_text'] ?? []) ?? [],
            'identity_info' => normalizeHtmlFieldValue($data['identity_info'] ?? []) ?? [],
        ]);

        return response()->json([
            'status'  => 0,
            'message' => ('Настройки верификации личности для направления успешно обновлены.')
        ]);
    }
}
