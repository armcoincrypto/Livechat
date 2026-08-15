<?php

namespace App\Http\Controllers\Administrator\Tools;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class JobController extends Controller
{
    /**
     * Дополнительные фильтры
     */
    protected array $allowLocaleOptions = [
        'working_online_text',
        'working_offline_text',
        //'working_offline_notify'
    ];

    protected array $allowFiltered = [
        'is_working_manual',
        'type_working_mode',
        'is_working_view_header',
        'is_operator_online'
    ];

    public function index()
    {
        return response()->json([
            'is_working_manual' => (int)iEXSetting('is_working_manual'),
            'type_working_mode' => (int)iEXSetting('type_working_mode'),
            'is_working_view_header' => (int)iEXSetting('is_working_view_header'),
            'working_online_text' => iEXContentLanguage('working_online_text', raw: true),
            'working_offline_text' => iEXContentLanguage('working_offline_text', raw: true),
            'is_operator_online' => (int) iEXSetting('is_operator_online'),
        ]);
    }

    public function update(Request $request)
    {
        $array = [];
        foreach ($this->allowFiltered as $item) {
            $array[$item] = ($request->has($item) ? (int)$request->get($item) : 0);
        }

        $options_locale = [];
        foreach ($this->allowLocaleOptions as $allowLocaleOption) {
            $options_locale[$allowLocaleOption] = $request->{$allowLocaleOption};
        }

        //Для мультиязычности
        $locale_data = collect($options_locale)->only($this->allowLocaleOptions)->all();
        iEXContentLanguage($locale_data);
        iEXSetting($array);

        return response()->json([
            'status' => 0,
            'message' => __('Настройки успешно сохранены')
        ]);
    }
}
