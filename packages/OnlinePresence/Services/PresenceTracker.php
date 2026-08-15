<?php
declare(strict_types=1);

namespace iEXPackages\OnlinePresence\Services;

use iEXPackages\OnlinePresence\Jobs\PresencePingJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class PresenceTracker
{
    public function __construct(
        private readonly IdentityResolver $identityResolver,
        private readonly DdosGuard $guard
    ) {}

    public function track(Request $request): void
    {
        if (!config('online_presence.enabled', true)) {
            return;
        }

        if (!$this->shouldTrackRequest($request)) {
            return;
        }

        // Micro-throttle by IP (cheap early-exit) to reduce overhead under spikes
        $ip = (string) ($request->ip() ?? 'unknown');
        if ($ip === '') {
            $ip = 'unknown';
        }

        $ipMicroTtl = max(1, (int) config('online_presence.ip_micro_throttle_seconds', 2));
        if (!Cache::add("presence:ip:micro:{$ip}", 1, $ipMicroTtl)) {
            return;
        }

        // DDoS/IP/global guard
        if (!$this->guard->allow($request)) {
            return;
        }

        $data = $this->identityResolver->resolve($request);

        $identity = trim((string) ($data['identity'] ?? ''));
        if ($identity === '') {
            return;
        }

        // Throttle by identity (so we don't flood the queue)
        $throttle = max(1, (int) config('online_presence.identity_throttle_seconds', 20));
        $thKey = "presence:throttle:identity:{$identity}";

        if (!Cache::add($thKey, 1, $throttle)) {
            return;
        }

        PresencePingJob::dispatch(
            payload: $data,
            ip: config('online_presence.store_ip', false) ? (string) ($request->ip() ?? '') : null,
            userAgent: $request->userAgent()
        )->onQueue((string) config('online_presence.queue', 'presence'));
    }

    private function shouldTrackRequest(Request $request): bool
    {
        // Methods
        if (!in_array($request->method(), ['GET', 'POST'], true)) {
            return false;
        }

        // Skip prefetch
        if ((string) $request->headers->get('Purpose') === 'prefetch') {
            return false;
        }

        // Skip preflight/health-like requests early
        if ($request->isMethod('OPTIONS') || $request->isMethod('HEAD')) {
            return false;
        }

        // Skip common bot/uptime probes by UA (cheap)
        $ua = (string) $request->userAgent();
        if ($ua !== '' && preg_match('~(UptimeRobot|Pingdom|StatusCake|Datadog|NewRelic|HealthCheck|kube-probe)~i', $ua)) {
            return false;
        }

        // Skip typical service paths (but DO track admin + specific APIs)
        $path = '/' . ltrim((string) $request->path(), '/');

        // 1) Admin panel route (dynamic path)
        $adminFolder = (string) config('iexexchanger.admin_folder', 'iexadmin');
        $adminPrefix = '/' . trim($adminFolder, '/');

        // Admin: track ONLY notify polling endpoint to avoid spamming presence by all admin assets
        $isAdminNotify = Str::startsWith($path, $adminPrefix . '/frontend-api/vue/getNotify');

        // 2) Client API route
        $isClientApi = Str::startsWith($path, '/client-api/v1/');

        // 3) Exclude other APIs and service paths
        $isAnyApi = Str::startsWith($path, '/api');
        $isService = (
            Str::startsWith($path, '/_') ||
            Str::startsWith($path, '/vendor') ||
            Str::startsWith($path, '/storage')
        );

        // If it's admin but not notify endpoint — skip
        if (Str::startsWith($path, $adminPrefix) && !$isAdminNotify) {
            return false;
        }

        // If it's /api/* — skip (we use /client-api/v1/* instead)
        if ($isAnyApi) {
            return false;
        }

        // If it's any other service path — skip
        if ($isService) {
            return false;
        }

        // Allow: admin notify endpoint OR client-api/v1/*
        if ($isAdminNotify || $isClientApi) {
            return true;
        }

        // Skip static assets by extension
        $uri = (string) $request->getRequestUri();
        if (preg_match('~\.(css|js|map|png|jpg|jpeg|gif|webp|svg|ico|woff2|woff|ttf|eot)$~i', $uri)) {
            return false;
        }

        // Client API is allowed, but you can optionally require that it comes from browser/front-end
        // (keeps internal service calls from polluting online stats)
        if (Str::startsWith($path, '/client-api/v1/')) {
            $origin = (string) $request->headers->get('Origin');
            $referer = (string) $request->headers->get('Referer');

            // If both are empty, it's likely not a browser request
            if ($origin === '' && $referer === '') {
                return false;
            }
        }

        return true;
    }
}
