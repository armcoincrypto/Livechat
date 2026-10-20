<?php

declare(strict_types=1);

namespace iEXPackages\ExchangerClient\Http\Controller\Auth;

use App\Http\Controllers\Controller;
use App\Models\UserOAuthIdentity;
use App\Services\Auth\OAuth\OAuthAuditLogger;
use App\Services\Auth\OAuth\OAuthConfigRepository;
use App\Services\Auth\OAuth\OAuthIdentityResolver;
use App\Services\Auth\OAuth\OAuthProviders;
use App\Services\Auth\OAuth\OAuthReauthGuard;
use App\Services\Auth\OAuth\OAuthReturnToSanitizer;
use App\Services\Auth\OAuth\OAuthSessionIssuer;
use App\Services\Auth\OAuth\OAuthStateStore;
use App\Services\Auth\OAuth\Providers\GoogleOAuthProvider;
use App\Services\Auth\OAuth\Providers\YandexOAuthProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class OAuthController extends Controller
{
    public function __construct(
        private readonly OAuthConfigRepository $config,
        private readonly OAuthStateStore $stateStore,
        private readonly GoogleOAuthProvider $google,
        private readonly YandexOAuthProvider $yandex,
        private readonly OAuthIdentityResolver $resolver,
        private readonly OAuthSessionIssuer $sessionIssuer,
        private readonly OAuthReauthGuard $reauth,
        private readonly OAuthAuditLogger $audit,
    ) {
    }

    /**
     * Recent password confirmation required before account-linking OAuth redirect.
     */
    public function confirmReauth(Request $request): JsonResponse
    {
        try {
            $this->assertEnabled();
            if (!$request->user()) {
                return response()->json(['status' => 1, 'code' => 'OAUTH_LINK_AUTH_REQUIRED'], 401);
            }

            $password = (string) $request->input('password', '');
            if ($password === '') {
                return response()->json(['status' => 1, 'code' => 'OAUTH_REAUTH_FAILED'], 422);
            }

            $this->reauth->confirmPassword($request, $password);

            return response()->json([
                'status' => 0,
                'expires_in' => OAuthReauthGuard::TTL_SECONDS,
            ]);
        } catch (RuntimeException $e) {
            $code = $e->getMessage();
            $status = $code === 'OAUTH_LINK_AUTH_REQUIRED' ? 401 : 422;

            return response()->json(['status' => 1, 'code' => $code], $status);
        } catch (Throwable $e) {
            Log::warning('oauth.reauth.failed', ['code' => 'OAUTH_REAUTH_FAILED']);

            return response()->json(['status' => 1, 'code' => 'OAUTH_REAUTH_FAILED'], 500);
        }
    }

    /**
     * Linked providers for the authenticated user (no secrets).
     */
    public function identities(Request $request): JsonResponse
    {
        try {
            $this->assertEnabled();
            $user = $request->user();
            if (!$user) {
                return response()->json(['status' => 1, 'code' => 'OAUTH_LINK_AUTH_REQUIRED'], 401);
            }

            $rows = UserOAuthIdentity::query()
                ->where('user_id', $user->id)
                ->get(['provider', 'provider_email', 'provider_email_verified', 'created_at']);

            return response()->json([
                'status' => 0,
                'identities' => $rows->map(static fn (UserOAuthIdentity $row) => [
                    'provider' => $row->provider,
                    'provider_email' => $row->provider_email,
                    'provider_email_verified' => $row->provider_email_verified,
                    'linked_at' => optional($row->created_at)?->toIso8601String(),
                ])->values(),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['status' => 1, 'code' => $e->getMessage()], 403);
        }
    }

    public function redirect(Request $request, string $provider): RedirectResponse|Response
    {
        try {
            $this->assertEnabled();
            $alias = OAuthProviders::normalize($provider);
            if (!OAuthProviders::isSupported($alias)) {
                return $this->errorRedirect($request, 'OAUTH_PROVIDER_UNSUPPORTED');
            }

            // Warm config (throws if disabled/misconfigured).
            $this->config->getEnabledProvider($alias);

            $intent = $request->query('intent', 'login') === 'link' ? 'link' : 'login';
            $locale = $this->normalizeLocale((string) $request->query('locale', app()->getLocale()));
            $returnTo = OAuthReturnToSanitizer::sanitize($request->query('returnTo'));

            $linkUserId = null;
            if ($intent === 'link') {
                $user = $request->user();
                if (!$user) {
                    return $this->errorRedirect($request, 'OAUTH_LINK_AUTH_REQUIRED', $locale);
                }
                $this->reauth->assertFresh($request);
                $linkUserId = (int) $user->id;
            }

            $verifier = OAuthStateStore::createCodeVerifier();
            $challenge = OAuthStateStore::codeChallengeS256($verifier);
            $nonce = Str::random(32);

            $state = $this->stateStore->begin([
                'provider' => $alias,
                'intent' => $intent,
                'locale' => $locale,
                'return_to' => $returnTo,
                'link_user_id' => $linkUserId,
                'code_verifier' => $verifier,
                'nonce' => $nonce,
            ]);

            $url = $alias === OAuthProviders::GOOGLE
                ? $this->google->buildAuthorizationUrl($state, $challenge, $nonce)
                : $this->yandex->buildAuthorizationUrl($state, $challenge);

            return redirect()->away($url);
        } catch (RuntimeException $e) {
            return $this->errorRedirect($request, $e->getMessage());
        } catch (Throwable $e) {
            Log::warning('oauth.redirect.failed', ['code' => 'OAUTH_REDIRECT_FAILED']);

            return $this->errorRedirect($request, 'OAUTH_REDIRECT_FAILED');
        }
    }

    public function callback(Request $request, string $provider): RedirectResponse|Response
    {
        $locale = 'en';

        try {
            $this->assertEnabled();
            $alias = OAuthProviders::normalize($provider);
            if (!OAuthProviders::isSupported($alias)) {
                return $this->errorRedirect($request, 'OAUTH_PROVIDER_UNSUPPORTED');
            }

            if ($request->query('error')) {
                $this->audit->log($request, 'oauth_denied', 'fail', null, 'OAUTH_PROVIDER_DENIED', [
                    'provider' => $alias,
                ]);

                return $this->errorRedirect($request, 'OAUTH_PROVIDER_DENIED');
            }

            $code = (string) $request->query('code', '');
            $state = (string) $request->query('state', '');
            if ($code === '' || $state === '') {
                return $this->errorRedirect($request, 'OAUTH_CALLBACK_INVALID');
            }

            $tx = $this->stateStore->consume($alias, $state);
            $locale = $tx['locale'];

            $profile = $alias === OAuthProviders::GOOGLE
                ? $this->google->exchangeAndProfile($code, $tx['code_verifier'], $tx['nonce'])
                : $this->yandex->exchangeAndProfile($code, $tx['code_verifier']);

            // Drop authorization code from memory scope ASAP (local only).
            unset($code);

            $result = $this->resolver->resolve($profile, $tx, $request);

            if ($result['outcome'] === 'link_required') {
                $this->audit->log($request, 'oauth_login_failed', 'fail', null, 'OAUTH_LINK_REQUIRED', [
                    'provider' => $alias,
                    'outcome' => 'link_required',
                ]);

                return $this->errorRedirect(
                    $request,
                    'OAUTH_LINK_REQUIRED',
                    $locale,
                    $tx['return_to']
                );
            }

            if (empty($result['user'])) {
                return $this->errorRedirect($request, 'OAUTH_RESOLVE_FAILED', $locale);
            }

            // Establish session cookies only after identity validation succeeded.
            $this->sessionIssuer->issue($result['user'], $request);

            $event = match ($result['outcome']) {
                'register' => 'oauth_register_success',
                'link' => 'oauth_link_success',
                default => 'oauth_login_success',
            };
            $this->audit->log($request, $event, 'success', $result['user'], null, [
                'provider' => $alias,
                'outcome' => $result['outcome'],
                'intent' => (string) ($tx['intent'] ?? 'login'),
            ]);

            return redirect()->away($this->successUrl($locale, $tx['return_to'], $result['outcome']));
        } catch (RuntimeException $e) {
            $this->audit->log($request, 'oauth_login_failed', 'fail', null, $e->getMessage(), [
                'provider' => OAuthProviders::normalize($provider),
            ]);

            return $this->errorRedirect($request, $e->getMessage(), $locale);
        } catch (Throwable $e) {
            Log::warning('oauth.callback.failed', ['code' => 'OAUTH_CALLBACK_FAILED']);

            return $this->errorRedirect($request, 'OAUTH_CALLBACK_FAILED', $locale);
        }
    }

    private function assertEnabled(): void
    {
        if (!filter_var(env('EXS_OAUTH_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException('OAUTH_FEATURE_DISABLED');
        }
    }

    private function normalizeLocale(string $locale): string
    {
        $locale = strtolower(substr($locale, 0, 2));

        return in_array($locale, ['en', 'ru', 'uk', 'ka', 'zh'], true) ? $locale : 'en';
    }

    private function successUrl(string $locale, ?string $returnTo, string $outcome): string
    {
        $site = rtrim((string) env('EXS_PUBLIC_SITE_URL', 'https://exswaping.com'), '/');
        $safe = OAuthReturnToSanitizer::sanitize($returnTo);
        if ($safe) {
            return $site.$safe;
        }

        $path = $outcome === 'link'
            ? "/{$locale}/account/settings"
            : "/{$locale}/account";

        return $site.$path;
    }

    private function errorRedirect(
        Request $request,
        string $code,
        ?string $locale = null,
        ?string $returnTo = null,
    ): RedirectResponse {
        $locale = $this->normalizeLocale($locale ?? (string) $request->query('locale', 'en'));
        $site = rtrim((string) env('EXS_PUBLIC_SITE_URL', 'https://exswaping.com'), '/');
        $query = http_build_query([
            'oauth_error' => $code,
        ], '', '&', PHP_QUERY_RFC3986);

        return redirect()->away($site."/{$locale}/auth/login?{$query}");
    }
}
