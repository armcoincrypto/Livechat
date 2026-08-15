<?php

return [
    /**
     * Глобальный тумблер статистики посещений
     */
    'enabled' => (bool) env('VISITS_ENABLED', true),

    /**
     * Throttle (сильно снижает нагрузку на БД):
     * - ip_throttle_seconds: не писать статистику чаще чем раз в N секунд на один IP
     */
    'ip_throttle_seconds' => (int) env('VISITS_IP_THROTTLE_SECONDS', 2),

    /**
     * Источники, которые МЫ ТРЕКАЕМ (allow-list).
     * Важно: мы не "игнорируем всё подряд", мы "разрешаем только нужное".
     *
     * 1) Клиентский фронт: client-api/v1/*
     * 2) Админка: {admin_folder}/frontend-api/vue/getNotify
     */
    'allow' => [
        'client_api_prefix' => '/client-api/v1/',
        'admin_notify_path' => '/frontend-api/vue/getNotify',
    ],

    /**
     * Фильтры для client-api:
     * Если true — требуем, чтобы запрос был именно из браузера (через фронтенд):
     * должен быть хотя бы один заголовок Origin или Referer.
     *
     * Это защищает от "ложных" запросов сервер-сервер / скриптов / ботов,
     * которые будут портить статистику.
     */
    'client_api_require_browser_headers' => (bool) env('VISITS_CLIENT_API_REQUIRE_BROWSER_HEADERS', true),

    /**
     * Дополнительные глобальные исключения (если нужно).
     * Обычно не требуется, т.к. есть allow-list выше.
     */
    'ignore_paths' => [
        '~^(_nuxt|assets|dist)/~i',
        '~^health$~i',
    ],

    /**
     * Игнорируем ботов/мониторинги по User-Agent
     */
    'bot_regex' => (string) env(
        'VISITS_BOT_REGEX',
        '~(bot|crawler|spider|slurp|curl|wget|python|go-http-client|postman|insomnia|uptime|health|probe|kube-probe|datadog|newrelic|pingdom|statuscake)~i'
    ),
];
