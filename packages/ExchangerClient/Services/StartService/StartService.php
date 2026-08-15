<?php

namespace iEXPackages\ExchangerClient\Services\StartService;

use App\Models\ContestModel;
use App\Models\PromoCode;
use App\Settings\AdvantageConfig;
use App\Settings\BannerConfig;
use App\Settings\SelectorFeeConfig;
use Illuminate\Foundation\Application;

class StartService
{
    use Concerns\InterfaceManages,
        Concerns\AttributesManages,
        Concerns\ComponentsManages;

    public function __construct(Application $app)
    {
        //
    }


    public function build()
    {
        $response = [

            'sitename' => iEXContentLanguage('sitename'),
            'sitename_text' => iEXContentLanguage('sitename_desc'),
            'base_url' => config('app.frontend_url'),
            'api_url' => config('app.api_url'),
            'seo_description' => iEXContentLanguage('seo_description'),

            'options' => [
                'recaptcha_key' => config('captcha.sitekey'),
                'working_site' => isJobOffline(),
                'is_enabled_captcha_login' => (int) iEXSetting('is_enabled_captcha_login'),
                'is_enabled_captcha_register' => (int) iEXSetting('is_enabled_captcha_register'),
                'is_enabled_captcha_order' => (int) iEXSetting('is_enabled_captcha_order'),
                'is_enabled_captcha_reset' => (int) iEXSetting('is_enabled_captcha_reset'),
                'decimalPlaces' => (int) (int)iEXSetting('max_decimal_places', 18),
                'chat_type' => (string) iEXSetting('chat_type'),
                'chat_app_id' => (string) iEXContentLanguage('chat_app_id'),
                'yandex_metrika_id' => (string) iEXSetting('integration_yandex_metrika'),
                'google_analytics_id' => (string) iEXSetting('integration_google_analytics'),
                'microsoft_clarity_id' => (string) iEXSetting('integration_microsoft_clarity'),
                'bitcoin_network_load' => (bool) iEXSetting('integration_bitcoin_network_load'),
            ],

            'components' => $this->componentBuild(),

            'socket' => [
                'key' => config('broadcasting.connections.reverb.key'),
                'host' => config('broadcasting.connections.reverb.options.host'),
                'port' => config('broadcasting.connections.reverb.options.port'),
                'scheme' => config('broadcasting.connections.reverb.options.scheme'),
                'tls' => config('broadcasting.connections.reverb.options.useTLS'),
            ],


            'attributes' => $this->buildAttributes(),

            'jivosite' => [
                'type_message' => (int) iEXSetting('jivosite_type_message'),
                'text_message' => (string) iEXContentLanguage('jivosite_text_message'),
                'info_user' => (int) iEXSetting('jivosite_info_user'),
            ],

            'lottery' => $this->lottery(),
            'themes'  => $this->buildTheme()
        ];


        if(iEXSetting('is_enabled_telegram_bot_block') == 1)
        {
            $response['telegram_bot'] = [
                'url' => 'tg://resolve?domain='.iEXSetting('link_telegram_bot_block'),
                'title' => iEXContentLanguage('telegram_block_title'),
                'description' => iEXContentLanguage('telegram_block_description'),
                'button' => iEXContentLanguage('telegram_block_button')
            ];
        }


        return $response;
    }

    public function buildExchange()
    {
        $advantageSettings = app(AdvantageConfig::class);
        $bannerSettings    = app(BannerConfig::class);
        $selectorFee = app(SelectorFeeConfig::class);
        return [
            'components' => $this->componentBuildExchange(),
            'themes' => $this->exchangeInterface(),
            'options' => [

                'interval_rates' => (float) iEXSetting('interval_rates', 50) * 1000,
                'promo_codes' => $this->promoCodes(),
                'chunk_count_reserve' => (int) iEXSetting('count_template_block_reserve_2', 0),
                'is_dot_not_remember_data_order' => (int) iEXSetting('is_dot_not_remember_data_order'),
                'currency_exchange_selected_price' => (int) iEXSetting('currency_exchange_selected_price'),
                'auto_fill_min_amount_on_empty_input' => (int) iEXSetting('auto_fill_min_amount_on_empty_input'),
                'is_view_contests_home' => (int) iEXSetting('is_view_contests_home') == 0,
                'iex_interface_text_entry' => (int) iEXSetting('iex_interface_text_entry', 0),
                'is_disabled_email_field_optional' => (int) iEXSetting('is_disabled_email_field_optional', 0),
                'is_style_agreement_checkbox' => (int) iEXSetting('is_style_agreement_checkbox', 0),
                'allow_filter_currency' => iEXSetting('allow_filter_currency'),
                'display_currency_iso_codes' => (int)iEXSetting('display_currency_iso_codes', 0),
                'currency_display_type_dynamic' => iEXSetting('currency_display_type_dynamic', 0),
                'is_mobile_hidden_reserve_out' => (int) iEXSetting('is_mobile_hidden_reserve_out', 0),
                'is_reserves_rounding' => (int) iEXSetting('is_reserves_rounding'),
                'is_hide_currency_reserve' => (int) iEXSetting('is_hide_currency_reserve'),
                'type_view_field_exchange_amount' => (int) iEXSetting('type_view_field_exchange_amount'),
                'block_visible_reserve' => (int) iEXSetting('block_visible_reserve'),
                'template_block_reserve' => (int) iEXSetting('template_block_reserve'),
                'is_advantage_style'    => $advantageSettings->isStyleEnabled() ? 1 : 0,
                'advantage_col'         => $advantageSettings->columns(),
                'advantage_row_height'  => $advantageSettings->rowHeight() ?? '2:1',
                'advantage_gutter_size' => $advantageSettings->gutterSize() ?? '10px',
                'is_banner_autoplay' => (int) $bannerSettings->isAutoplay(),
                'number_banner_autoplay_timeout' => (int) $bannerSettings->timeout(),
                'is_banner_hidden_nav' => (int) $bannerSettings->hideNav(),
                'is_multi_selector_fee' => (int) $selectorFee->isMultiSelector(),

                'readmore_lines_threshold' => (int)iEXSetting('readmore_lines_threshold', 4),
                'allow_readmore_buttons' => (bool)iEXSetting('allow_readmore_buttons') == 1,
            ]
        ];
    }

    /**
     * Получаем текущий язык сайта
     */
    private function getLocale(): string
    {
        return app()->getLocale();
    }

    private function lottery(): array
    {
        $item = ContestModel::where('status', '=', 1)->first();
        if(empty($item)) {
            return [];
        };

        return [
            'title' => str_replace('[amount]', iex_number_format($item->bank, 2, true), $item->title),
            'title_color' => $item->title_color,
            'subtitle' => str_replace('[amount]', iex_number_format($item->bank, 2, true), $item->subtitle),
            'subtitle_color' => $item->subtitle_color,
            'button_name' => $item->button_name,
            'num' => $item->bank,
            'amount' => iex_number_format($item->bank, 0, true).' '.$item->code_sign,
            'icon_url_home' => ! is_null($item->icon_url_home) ? '/storage/contests/'.$item->icon_url_home : '',
            'icon_url_account' => ! is_null($item->icon_url_account) ? '/storage/contests/'.$item->icon_url_account : '',
        ];
    }

    private function promoCodes(): bool
    {
        return PromoCode::where('status', '=', 1)
            ->whereRaw('(now() between started_at and expired_at)')
            ->exists();
    }
}
