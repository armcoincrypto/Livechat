<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem\Services;

use App\Models\User;
use iEXPackages\ReferralSystem\DTO\ReferralCaptureResult;
use iEXPackages\ReferralSystem\Models\ReferralLink;
use iEXPackages\ReferralSystem\ReferralCookieManager;
use Illuminate\Http\Request;

/**
 * Resolves referral code + link id for order creation (attribution only — no commission).
 */
final class ReferralCaptureResolver
{
    /** @var list<string> */
    private const INPUT_KEYS = ['ref', 'partnerId', 'partner', 'referral', 'r'];

    public function __construct(
        private readonly ReferralCookieManager $cookies,
    ) {}

    public function resolve(Request $request, ?User $user = null): ReferralCaptureResult
    {
        if ($user) {
            $user->loadMissing('fromReferral.referralLink');
            $link = $user->fromReferral?->referralLink;
            if ($link) {
                return new ReferralCaptureResult(
                    code: (string) $link->code,
                    linkId: (int) $link->id,
                    source: 'user_relationship',
                    partnerUserId: (int) $link->user_id,
                );
            }
        }

        $cookieRaw = (string) $request->cookie(ReferralCookieManager::COOKIE_NAME, '');
        $fromCookie = $this->cookies->normalizeRefValue($cookieRaw);
        if ($fromCookie !== '') {
            return $this->resolveLinkForValue($fromCookie, 'cookie');
        }

        foreach (self::INPUT_KEYS as $key) {
            if (!$request->filled($key)) {
                continue;
            }
            $raw = $request->input($key);
            $value = $key === 'partnerId' || $key === 'partner'
                ? $this->normalizePartnerInput($raw)
                : $this->normalizeCodeInput($raw);
            if ($value === '') {
                continue;
            }
            $source = $request->isMethod('GET') ? 'query' : 'body';

            return $this->resolveLinkForValue($value, $source);
        }

        $fromReferer = $this->extractFromReferer((string) $request->headers->get('Referer', ''));
        if ($fromReferer !== '') {
            return $this->resolveLinkForValue($fromReferer, 'referer');
        }

        return new ReferralCaptureResult();
    }

    /**
     * Extract referral token from URL query string (for tests and referer parsing).
     */
    public function extractFromReferer(string $referer): string
    {
        if ($referer === '') {
            return '';
        }

        $query = parse_url($referer, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return '';
        }

        parse_str($query, $params);
        if (!is_array($params)) {
            return '';
        }

        foreach (self::INPUT_KEYS as $key) {
            if (!isset($params[$key]) || $params[$key] === '') {
                continue;
            }
            if ($key === 'partnerId' || $key === 'partner') {
                $normalized = $this->normalizePartnerInput($params[$key]);
            } else {
                $normalized = $this->normalizeCodeInput($params[$key]);
            }
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    private function resolveLinkForValue(string $value, string $source): ReferralCaptureResult
    {
        $normalized = $this->cookies->normalizeRefValue($value);
        if ($normalized === '') {
            return new ReferralCaptureResult();
        }

        $link = $this->findReferralLink($normalized);

        if ($link) {
            return new ReferralCaptureResult(
                code: (string) $link->code,
                linkId: (int) $link->id,
                source: $source,
                partnerUserId: (int) $link->user_id,
            );
        }

        return new ReferralCaptureResult(
            code: $normalized,
            linkId: 0,
            source: $source,
        );
    }

    private function findReferralLink(string $normalized): ?ReferralLink
    {
        if (str_starts_with($normalized, 'partnerId:')) {
            $partnerUserId = (int) substr($normalized, strlen('partnerId:'));
            if ($partnerUserId <= 0) {
                return null;
            }

            return ReferralLink::query()->where('user_id', $partnerUserId)->first();
        }

        return ReferralLink::query()->where('code', $normalized)->first();
    }

    private function normalizeCodeInput(mixed $raw): string
    {
        if (!is_scalar($raw)) {
            return '';
        }

        return $this->cookies->normalizeRefValue((string) $raw);
    }

    private function normalizePartnerInput(mixed $raw): string
    {
        if (!is_scalar($raw)) {
            return '';
        }

        $raw = trim((string) $raw);
        if ($raw === '' || !preg_match('/^\d+$/', $raw)) {
            return '';
        }

        $id = (int) $raw;

        return $id > 0 ? 'partnerId:' . $id : '';
    }
}
