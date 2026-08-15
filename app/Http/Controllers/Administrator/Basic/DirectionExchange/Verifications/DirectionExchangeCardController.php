<?php

namespace App\Http\Controllers\Administrator\Basic\DirectionExchange\Verifications;

use App\Models\DirectionExchange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DirectionExchangeCardController
{
    public function edit(int $id): JsonResponse
    {
        $item = DirectionExchange::query()->findOrFail($id);

        $rules = $item->card_verification_rules ?? [];

        $noVerification   = $rules['no_verification']   ?? [];
        $withVerification = $rules['with_verification'] ?? [];
        $direction        = $rules['direction']        ?? [];

        // Attributes текущего DirectionExchange для фронта (обратная совместимость)
        $attributes = [
            // Фронт по-прежнему ожидает type_verification, поэтому маппим из card_verification_type
            'type_verification' => (int) ($item->card_verification_type ?? 0),

            // Без верификации
            'no_verification_min_amount' => (float) ($noVerification['min_amount'] ?? 0),
            'no_verification_max_amount' => (float) ($noVerification['max_amount'] ?? 0),
            'no_verification_fee'        => (string) ($noVerification['fee'] ?? '0'),

            // С верификацией
            'verification_min_amount' => (float) ($withVerification['min_amount'] ?? 0),
            'verification_max_amount' => (float) ($withVerification['max_amount'] ?? 0),
            'verification_fee'        => (string) ($withVerification['fee'] ?? '0'),

            // Описания (мультиязычные)
            'no_verification_description' => $item->getTranslations('no_verification_description'),
            'verification_description'    => $item->getTranslations('verification_description'),

            // Настройки "из направлений" — читаем из блока direction
            'direction_is_enabled_verification' => (int) ($direction['mode'] ?? 0),
            'direction_min_amount_verification' => (float) ($direction['min_amount'] ?? 0),
            'direction_is_verified_cabinet'     => (int) ($direction['is_verified_cabinet'] ?? 0),

            // Тексты по направлению (они как отдельные поля модели)
            'direction_verification_text' => $item->getTranslations('direction_verification_text'),
            'direction_verification_info' => $item->getTranslations('direction_verification_info'),
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
            'type_verification'                  => ['required', 'integer'],
            'no_verification_min_amount'         => ['nullable', 'numeric'],
            'no_verification_max_amount'         => ['nullable', 'numeric'],
            'verification_min_amount'            => ['nullable', 'numeric'],
            'verification_max_amount'            => ['nullable', 'numeric'],
            'no_verification_fee'                => ['nullable', 'string'],
            'verification_fee'                   => ['nullable', 'string'],

            // Мультиязычные / JSON-поля
            'no_verification_description'        => ['nullable', 'array'],
            'verification_description'           => ['nullable', 'array'],

            // Параметры верификации карты на уровне направления
            'direction_is_enabled_verification'  => ['nullable', 'integer'],
            'direction_min_amount_verification'  => ['nullable', 'numeric'],
            'direction_verification_text'        => ['nullable', 'array'],
            'direction_verification_info'        => ['nullable', 'array'],
        ]);

        // Собираем JSON-конфиг настроек верификации карт
        $rules = [
            'no_verification' => [
                'min_amount' => (float) ($data['no_verification_min_amount'] ?? 0),
                'max_amount' => (float) ($data['no_verification_max_amount'] ?? 0),
                'fee'        => (string) ($data['no_verification_fee'] ?? '0'),
            ],
            'with_verification' => [
                'min_amount' => (float) ($data['verification_min_amount'] ?? 0),
                'max_amount' => (float) ($data['verification_max_amount'] ?? 0),
                'fee'        => (string) ($data['verification_fee'] ?? '0'),
            ],
            'direction' => [
                // Режим требования проверки карты из направлений (тот самый список с режимами)
                'mode'               => (int) ($data['direction_is_enabled_verification'] ?? 0),
                'min_amount'         => (float) ($data['direction_min_amount_verification'] ?? 0)
            ],
        ];


        $item->update([
            // сохраняем тип верификации карты в новое поле
            'card_verification_type'   => $data['type_verification'] ?? 0,
            // сохраняем числовые/флаговые настройки в JSON
            'card_verification_rules'  => $rules,
            // описания остаются отдельными полями
            'no_verification_description'  => normalizeHtmlFieldValue($data['no_verification_description']) ?? [],
            'verification_description'     => normalizeHtmlFieldValue($data['verification_description']) ?? [],
            // тексты по направлению — тоже как отдельные поля
            'direction_verification_text'  => normalizeHtmlFieldValue($data['direction_verification_text']) ?? [],
            'direction_verification_info'  => normalizeHtmlFieldValue($data['direction_verification_info']) ?? [],
        ]);

        return response()->json([
            'status'  => 0,
            'message' => __('Настройки верификации карт для направления успешно обновлены'),
        ]);
    }
}
