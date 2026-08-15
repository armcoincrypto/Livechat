<?php

namespace iEXPackages\ExchangerClient\Services\StartService\Concerns;

trait AttributesManages
{
    public function buildAttributes(): array
    {
        // Получаем все языки и фильтруем по тем что активно
        $languages = collect(config('app.form_locales'))->filter(fn($value, $key) => in_array($key, explode(',', iEXSetting('app_multilanguage_locale'))))->values();


        return [
            'locale' => app()->getLocale(),
            'languages' => $languages,
            'is_display_exchange_rate' => (int) iEXSetting('is_display_exchange_rate'),
            'exchange_rules_link' => iEXSetting('exchange_rules_link', '/rules'),
            'is_visible_policy_cookie' => (int) iEXSetting('is_visible_policy_cookie', 0),
            'is_discount_disabled' => (int) iEXSetting('is_discount_disabled'),
            'is_working_view_header' => (int) iEXSetting('is_working_view_header'),
            'working_online_text' => !empty(iEXContentLanguage('working_online_text')) ? iEXContentLanguage('working_online_text') : null,
            'working_offline_text' => !empty(iEXContentLanguage('working_offline_text')) ? iEXContentLanguage('working_offline_text') : null,
            'working_site' => isJobOffline(),
            'working_site_offline' => (int)iEXSetting('is_operator_online') == 1,
            'is_enabled_reviews' => (int) iEXSetting('is_enabled_reviews', 0),
            'input_footer_title' => iEXContentLanguage('input_footer_title'),
            'description_footer_text' => iEXContentLanguage('description_footer_text'),
            'is_enabled_discount' => (int)iEXSetting('is_discount_disabled', 0) === 0,
            'is_enabled_module_socket' => (int) iEXSetting('is_enabled_module_socket'),
            'chat_displayed_statuses' => iEXSetting('chat_displayed_statuses') ?? [],
            'input_footer_select' => (int) iEXSetting('input_footer_select'),
            'input_footer_image' => (string) !empty(iEXSetting('input_footer_image')) ? '/storage/' . iEXSetting('input_footer_image') : null,
            'is_enabled_exchange_verify_account' => (int) iEXSetting('is_enabled_exchange_verify_account', 0),
            'iex_interface_dynamic_colors' => (int) iEXSetting('iex_interface_dynamic_colors', 0),

            'is_enable_footer' => (int) iEXSetting('is_enable_footer', 0),
            'iex_interface_toolbar_row_menu' => (int) iEXSetting('iex_interface_toolbar_row_menu', 1),
            'max_time_task' => (int) iEXSetting('max_time_task', 100),
            'is_user_status_operator' => iEXSetting('is_user_status_operator', 0),
            'is_gradient_text_color' => (int) iEXSetting('is_gradient_text_color', 0),
            'working_offline_notify' => iEXContentLanguage('working_offline_notify'),
            'disable_lang' => (int) iEXSetting('is_language_deactivation', 0),

            'is_view_contests_account' => (int) iEXSetting('is_view_contests_account') == 0
        ];
    }
}
