<?php

namespace App\Http\Middleware;

use App\Services\VisitRecorder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class RecordVisit
{
    public function __construct(private readonly VisitRecorder $recorder) {}

    public function handle(Request $request, Closure $next)
    {
        if (!config('visits.enabled', true)) {
            return $next($request);
        }

        // Решаем заранее (чтобы terminate был максимально дешёвым)
        $request->attributes->set('_visits_should_track', $this->shouldTrack($request));

        return $next($request);
    }

    /**
     * Terminable middleware — выполнится после отправки ответа клиенту.
     */
    public function terminate(Request $request, $response): void
    {
        if (!config('visits.enabled', true)) {
            return;
        }

        if ($request->attributes->get('_visits_should_track') !== true) {
            return;
        }

        try {
            $this->recorder->record($request);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function shouldTrack(Request $request): bool
    {
        // 0) Разрешаем только обычные методы
        $method = $request->method();
        if ($method !== 'GET' && $method !== 'POST') {
            return false;
        }

        // 1) Быстрые отсечения
        if ((string) $request->headers->get('Purpose') === 'prefetch') {
            return false;
        }

        // 2) Боты/мониторинги (по UA)
        $ua = (string) $request->userAgent();
        $botRe = (string) config('visits.bot_regex', '~(bot|crawler|spider|slurp|curl|uptime|health|probe)~i');
        if ($ua !== '' && preg_match($botRe, $ua)) {
            return false;
        }

        // 3) Allow-list: трекаем ТОЛЬКО 2 источника
        $path = '/' . ltrim((string) $request->path(), '/');

        // allow.client_api_prefix
        $clientPrefix = (string) data_get(config('visits.allow', []), 'client_api_prefix', '/client-api/v1/');
        $clientPrefix = '/' . trim($clientPrefix, '/') . '/';

        // allow.admin_notify_path + admin_folder
        $adminFolder = (string) config('iexexchanger.admin_folder', 'iexadmin');
        $adminPrefix = '/' . trim($adminFolder, '/');

        $adminNotifyPath = (string) data_get(config('visits.allow', []), 'admin_notify_path', '/frontend-api/vue/getNotify');
        $adminNotifyPath = '/' . ltrim($adminNotifyPath, '/');

        $isClientApi = Str::startsWith($path, $clientPrefix);
        $isAdminNotify = Str::startsWith($path, $adminPrefix . $adminNotifyPath);

        if (!$isClientApi && !$isAdminNotify) {
            return false;
        }

        // 4) Для client-api/v1/* (если включено) требуем признаки браузера
        if ($isClientApi && (bool) config('visits.client_api_require_browser_headers', true)) {
            $origin  = (string) $request->headers->get('Origin');
            $referer = (string) $request->headers->get('Referer');

            if ($origin === '' && $referer === '') {
                return false;
            }
        }

        // 5) Доп. ignore_paths (обычно почти не нужно при allow-list, но пусть будет)
        $rawPath = (string) $request->path();
        foreach ((array) config('visits.ignore_paths', []) as $re) {
            if (@preg_match($re, "/") !== false && preg_match($re, $rawPath)) {
                return false;
            }
        }

        // 6) Micro-throttle по IP — режем частоту записи, чтобы не долбить БД
        $ip = (string) ($request->ip() ?? 'unknown');
        if ($ip === '') {
            $ip = 'unknown';
        }

        $ipTtl = max(1, (int) config('visits.ip_throttle_seconds', 2));
        if (!Cache::add("visits:ip:{$ip}", 1, $ipTtl)) {
            return false;
        }

        return true;
    }
}
