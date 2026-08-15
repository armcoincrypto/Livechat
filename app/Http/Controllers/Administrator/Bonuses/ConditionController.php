<?php

namespace App\Http\Controllers\Administrator\Bonuses;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ConditionController extends Controller
{
    /**
     * Дополнительные фильтры
     */
    protected array $allowLocaleOptions = [
        'referral_system_text',
        'cashback_text',
        'monitoring_text',
    ];

    /**
     * Условия партнерской программы
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        return response()->json([
            'referral_system_text' => iEXContentLanguage('referral_system_text', raw: true),
            'cashback_text' => iEXContentLanguage('cashback_text', raw: true),
            'monitoring_text' => iEXContentLanguage('monitoring_text', raw: true),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'referral_system_text'         => 'array',
            'referral_system_text.*'       => 'nullable|string',
            'cashback_text'                => 'array',
            'cashback_text.*'              => 'nullable|string',
            'monitoring_text'              => 'array',
            'monitoring_text.*'            => 'nullable|string',
        ]);

        $cleanedOptions = collect($validated)
            ->map(function ($value) {
                if (is_array($value)) {
                    return collect($value)
                        ->map(function ($localeValue) {
                            // Доп. защита: nullable
                            return $localeValue !== null
                                ? cleanHtmlContent($localeValue)
                                : null;
                        })
                        ->toArray();
                }

                // Обычная строка
                return $value !== null
                    ? cleanHtmlContent($value)
                    : null;
            })
            ->toArray();

        $localeData = array_intersect_key($cleanedOptions, array_flip($this->allowLocaleOptions));

        iEXContentLanguage($localeData);

        return response()->json([
            'status' => 0,
            'message' => __('Данные успешно обновлены')
        ]);
    }
}
