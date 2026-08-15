<?php

namespace App\Http\Controllers\Administrator\Vue;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SettingsVueController extends Controller
{
    protected array $optionsInterface = [
        'interface_exchange',
        'is_visible_policy_cookie',
        'visible_last_exchange',
        'visible_advantage',
        'visible_statistics' ,
        'visible_reviews',
        'visible_news',
        'visible_partners',
        'iex_interface_dynamic_colors',
        'block_visible_reserve',
        'visible_popular_exchange',
        'is_enable_footer',
        'template_block_reserve',
        'allow_filter_currency',
        'display_currency_iso_codes',
        'iex_interface_toolbar_row_menu',
        'currency_display_type_dynamic'
    ];

    public function update(Request $request)
    {
        if($request->has('count_views'))
        {
            if(in_array($request->get('count_views')['name'], [
                'count_reviews_exchange', 'count_news_exchange', 'count_last_exchange', 'count_popular_exchange',
                'count_template_block_reserve_2'
            ])) {
                iEXSetting([
                    $request->get('count_views')['name'] => (int)$request->get('count_views')['num']
                ]);
            }
        } else {
            // Обновление конфига
            $array = [];
            if(in_array($request->act, $this->optionsInterface)) {
                $array[$request->act] = ($request->has('value') ? $request->get('value') : null);
            }
            iEXSetting($array);
        }


        return response()->json([
            'status' => 0,
            'message' => __('Настройки применены')
        ]);
    }
}
