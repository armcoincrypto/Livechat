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
     * Set a real password for OAuth-only accounts (placeholder hash).
     */
    public function setPassword(Request $request): JsonResponse
    {
        try {
            $this->assertEnabled();
            $user = $request->user();
            if (!$user) {
                return response()->json(['status' => 1, 'code' => 'OAUTH_LINK_AUTH_REQUIRED'], 401);
            }
            if (!$this->reauth->userHasPlaceholderPassword($user)) {
                return response()->json(['status' => 1, 'code' => 'OAUTH_PASSWORD_ALREADY_SET'], 422);
            }

            $password = (string) $request->input('password', '');
            $confirm = (string) $request->input('password_confirmation', '');
            if (strlen($password) < 6 || $password !== $confirm) {
                return response()->json(['status' => 1, 'code' => 'OAUTH_PASSWORD_INVALID'], 422);
            }

            $user->forceFill([
                'password' => $password,
                'oauth_password_is_placeholder' => false,
            ])->save();
            $this->reauth->markFresh($request);

            return response()->json(['status' => 0]);
        } catch (RuntimeException $e) {
            return response()->json(['status' => 1, 'code' => $e->getMessage()], 403);
        } catch (Throwable $e) {
            Log::warning('oauth.set_password.failed', ['code' => 'OAUTH_PASSWORD_SET_FAILED']);

            return response()->json(['status' => 1, 'code' => 'OAUTH_PASSWORD_SET_FAILED'], 500);
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
                'password_is_placeholder' => $this->reauth->userHasPlaceholderPassword($user),
                'reauth_fresh' => $this->reauth->isFresh($request),
                'reauth_ttl_seconds' => OAuthReauthGuard::TTL_SECONDS,
                'identities' => $rows->map(static fn (UserOAuthIdentity $row) => [
                    'provider' => $row->provider,
                    'provider_email' => $row->provider_email,
                    'provider_email_verified' => $row->provider_email_verified,
                    'linked_at' => optional($row->created_at)?->toIso8601String(),
                ])->values(),
            ]);
        } catch (RuntimeException $e) {
            $code = $e->getMessage();
            $status = $code === 'OAUTH_FEATURE_DISABLED' ? 403 : 401;

            return response()->json(['status' => 1, 'code' => $code], $status);
        }
    }

    /**
     * Unlink a provider. Refuses if it would leave the account with no login method
     * (no real password and no remaining OAuth identities).
     */
    public function unlink(Request $request, string $provider): JsonResponse
    {
        try {
            $this->assertEnabled();
            $user = $request->user();
            if (!$user) {
                return response()->json(['status' => 1, 'code' => 'OAUTH_LINK_AUTH_REQUIRED'], 401);
            }

            $this->reauth->assertFresh($request);

            $alias = OAuthProviders::normalize($provider);
            if (!OAuthProviders::isSupported($alias)) {
                return response()->json(['status' => 1, 'code' => 'OAUTH_PROVIDER_UNSUPPORTED'], 422);
            }

            $identity = UserOAuthIdentity::query()
                ->where('user_id', $user->id)
                ->where('provider', $alias)
                ->first();
            if (!$identity) {
                return response()->json(['status' => 1, 'code' => 'OAUTH_PROVIDER_NOT_LINKED'], 422);
            }

            $remaining = UserOAuthIdentity::query()
                ->where('user_id', $user->id)
                ->where('provider', '!=', $alias)
                ->count();
            $hasPassword = !$this->reauth->userHasPlaceholderPassword($user);
            if ($remaining === 0 && !$hasPassword) {
                return response()->json(['status' => 1, 'code' => 'OAUTH_UNLINK_LAST_METHOD'], 422);
            }

            $identity->delete();

            if ((string) $user->provider === $alias) {
                $next = UserOAuthIdentity::query()
                    ->where('user_id', $user->id)
                    ->orderBy('id')
                    ->first();
                $user->forceFill([
                    'provider' => $next?->provider,
                    'provider_id' => $next?->provider_subject,
                ])->save();
            }

            $this->audit->log($request, 'oauth_unlink_success', 'success', $user, null, [
                'provider' => $alias,
                'outcome' => 'unlink',
            ]);

            return response()->json(['status' => 0]);
        } catch (RuntimeException $e) {
            $code = $e->getMessage();
            $status = match ($code) {
                'OAUTH_LINK_AUTH_REQUIRED' => 401,
                'OAUTH_FEATURE_DISABLED' => 403,
                'OAUTH_REAUTH_REQUIRED' => 422,
                default => 422,
            };

            return response()->json(['status' => 1, 'code' => $code], $status);
        } catch (Throwable $e) {
            Log::warning('oauth.unlink.failed', ['code' => 'OAUTH_UNLINK_FAILED']);

            return response()->json(['status' => 1, 'code' => 'OAUTH_UNLINK_FAILED'], 500);
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
        $intent = 'login';

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

            $intent = (string) ($tx['intent'] ?? 'login');

            if ($result['outcome'] === 'link_required') {
                $this->audit->log($request, 'oauth_login_failed', 'fail', null, 'OAUTH_LINK_REQUIRED', [
                    'provider' => $alias,
                    'outcome' => 'link_required',
                ]);

                return $this->errorRedirect(
                    $request,
                    'OAUTH_LINK_REQUIRED',
                    $locale,
                    $intent,
                    $tx['return_to']
                );
            }

            if (empty($result['user'])) {
                return $this->errorRedirect($request, 'OAUTH_RESOLVE_FAILED', $locale, $intent);
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
                'intent' => $intent,
            ]);

            return redirect()->away($this->successUrl($locale, $tx['return_to'], $result['outcome']));
        } catch (RuntimeException $e) {
            $this->audit->log($request, 'oauth_login_failed', 'fail', null, $e->getMessage(), [
                'provider' => OAuthProviders::normalize($provider),
            ]);

            return $this->errorRedirect($request, $e->getMessage(), $locale, $intent);
        } catch (Throwable $e) {
            Log::warning('oauth.callback.failed', ['code' => 'OAUTH_CALLBACK_FAILED']);

            return $this->errorRedirect($request, 'OAUTH_CALLBACK_FAILED', $locale, $intent);
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
        string $intent = 'login',
        ?string $returnTo = null,
    ): RedirectResponse {
        $locale = $this->normalizeLocale($locale ?? (string) $request->query('locale', 'en'));
        $site = rtrim((string) env('EXS_PUBLIC_SITE_URL', 'https://exswaping.com'), '/');
        $query = http_build_query([
            'oauth_error' => $code,
        ], '', '&', PHP_QUERY_RFC3986);

        // Link-flow failures return to settings so the user is not bounced to login.
        if ($intent === 'link' && $request->user()) {
            return redirect()->away($site."/{$locale}/account/settings?{$query}");
        }

        return redirect()->away($site."/{$locale}/auth/login?{$query}");
    }
}
