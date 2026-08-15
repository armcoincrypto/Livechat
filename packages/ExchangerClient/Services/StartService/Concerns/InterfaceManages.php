<?php
namespace iEXPackages\ExchangerClient\Services\StartService\Concerns;

trait InterfaceManages
{

    private function getImage(string $name, string $path)
    {
        return (iEXSetting($name)) ?  $path . iEXSetting($name) : null;
    }

    protected function buildTheme(): array
    {

        $response = [
            'logotype' => $this->getImage('logotype_web', '/images/logotype/'),
            'logotype_dark' => $this->getImage('logotype_dark', '/images/logotype/'),
            'logotype_text' => iEXSetting('logotype_text'),
            'logotype_type' => iEXSetting('logotype_type'),
            'is_view_logotype_partner' => (int) iEXSetting('is_view_logotype_partner'),
            'type_view_background' => iEXSetting('type_view_background', 0),
            'visible_partners' => (int)iEXSetting('visible_partners'),
            'brand_icon' => $this->getImage('brand_icon', '/images/icons/'),
            'brand_icon_dark' => $this->getImage('brand_icon_dark', '/images/icons/'),
        ];

        // Вывести фон
        if((int) iEXSetting('exchange_fon_type') == 1) {
            $response['exchange_fon_file'] = (iEXSetting('exchange_fon_file') ? '/images/backgrounds/' .iEXSetting('exchange_fon_file') : '');
            $response['exchange_fon_file_dark'] = (iEXSetting('exchange_fon_file_dark') ? '/images/backgrounds/' .iEXSetting('exchange_fon_file_dark') :  '/' .iEXSetting('exchange_fon_file'));
        }

        return $response;
    }

    protected function exchangeInterface(): array
    {
        $response = [
            'interface_exchange' => iEXSetting('interface_exchange', 1),
            'visible_last_exchange' => (int)iEXSetting('visible_last_exchange'),
            'visible_popular_exchange' => (int) iEXSetting('visible_popular_exchange'),
            'visible_description' => (int)iEXSetting('visible_description'),
            'visible_statistics' => (int)iEXSetting('visible_statistics'),
            'visible_advantage' => (int)iEXSetting('visible_advantage'),
            'visible_banner' => (int)iEXSetting('visible_banner'),
            'visible_banner_mobile' => (int)iEXSetting('visible_banner_mobile'),
            'visible_reviews' => (int) iEXSetting('visible_reviews'),
            'visible_news' => (int) iEXSetting('visible_news'),

            'break_title' => iEXContentLanguage('tech_breach_title'),
            'break_text' => iEXContentLanguage('tech_breach_text'),
        ];

        // Отображение название в шапки
        if ((int) iEXSetting('visible_main_header_title') == 1) {
            $response['main_title_header'] = iEXContentLanguage('main_title_header');
            $response['main_value_header'] = iEXContentLanguage('main_value_header');
        }

        if((int) iEXSetting('visible_description') == 1) {
            $response['welcome_title'] = iEXContentLanguage('welcome_title');
            $response['welcome_description'] = iEXContentLanguage('welcome_description');
        }


        return $response;
    }
}
