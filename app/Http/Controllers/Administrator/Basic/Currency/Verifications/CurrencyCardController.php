<?php

namespace App\Http\Controllers\Administrator\Basic\Currency\Verifications;

use App\Models\Currency;
use App\Models\DirectionExchange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrencyCardController
{
    public function edit(int $id): JsonResponse
    {
        $item = Currency::query()->findOrFail($id);

        $attributes = [
            'is_enabled_verification' => (int) ($item->is_enabled_verification ?? 0),
            'min_amount_verification' => (float) ($item->is_verified_cabinet ?? 0),
            // Описания (мультиязычные)
            'verification_text' => $item->getTranslations('verification_text'),
            'verification_info'    => $item->getTranslations('verification_info'),

            // Настройки "из направлений" — читаем из блока direction
            'is_verified_cabinet' => (string)($item->is_verified_cabinet ?? '0'),
        ];

        return response()->json([
            'attributes' => $attributes,
            'default' => [
                'tech_name' => $item->tech_name ?? ''
            ]
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = Currency::query()->findOrFail($id);

        $data = $request->validate([
            'is_enabled_verification'   => ['required', 'integer'],
            'min_amount_verification'   => ['nullable', 'numeric'],
            'verification_text'         => ['nullable', 'array'],
            'verification_info'         => ['nullable', 'array'],
            'is_verified_cabinet'       => ['nullable', 'integer'],
        ]);

        $item->is_enabled_verification = (int) ($data['is_enabled_verification'] ?? 0);
        $item->min_amount_verification = (float) ($data['min_amount_verification'] ?? 0);
        $item->is_verified_cabinet     = (int) ($data['is_verified_cabinet'] ?? 0);

        if (array_key_exists('verification_text', $data)) {
            $item->setTranslations(
                'verification_text',
                normalizeHtmlFieldValue($data['verification_text']) ?? []
            );
        }

        if (array_key_exists('verification_info', $data)) {
            $item->setTranslations(
                'verification_info',
                normalizeHtmlFieldValue($data['verification_info']) ?? []
            );
        }

        $item->save();

        return response()->json([
            'status'  => 0,
            'message' => __('Настройки верификации карт для валюты успешно обновлены'),
        ]);
    }
}
