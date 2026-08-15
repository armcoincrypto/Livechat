<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem\Middleware;

use App\Settings\ReferralConfig;
use Closure;
use iEXPackages\ReferralSystem\Models\ReferralLink;
use iEXPackages\ReferralSystem\ReferralCookieManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CaptureReferralMiddleware
 *
 * Назначение:
 *  - перехватывать ref / partnerId из URL
 *  - сохранять реферальное значение в cookie
 *
 * ВАЖНО:
 *  - не выполняет тяжёлой логики
 *  - не пишет в БД
 *  - не запускает job'ы
 */
final class CaptureReferralMiddleware
{
    public function __construct(
        private readonly ReferralCookieManager $cookies,
        private readonly ReferralConfig $config,
    ) {}

    /**
     * @param Request $request
     * @param Closure(Request):Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Реферальная система выключена — ничего не делаем
        if ($this->config->isEnabled() !== 1) {
            return $next($request);
        }

        // Пытаемся извлечь ref из запроса (?ref или ?partnerId)
        $ref = $this->cookies->extractRef($request);
        if ($ref === null) {
            return $next($request);
        }

        // Нормализуем значение
        $ref = $this->cookies->normalizeRefValue($ref);
        if ($ref === '') {
            return $next($request);
        }

        // Если cookie уже есть и совпадает — не трогаем ответ
        $existing = (string) $request->cookie(ReferralCookieManager::COOKIE_NAME, '');
        if ($existing !== '' && hash_equals($existing, $ref)) {
            return $next($request);
        }

        // Создаём cookie с учётом конфигурации
        $refLink = ReferralLink::with('program')->where('code', $ref)->first();

        $cookie = $this->cookies->makeCookie(
            $ref,
            $refLink?->program
        );

        return $next($request)->withCookie($cookie);
    }
}
