<?php

declare(strict_types=1);

namespace App\Services\Auth\OAuth;

use App\Models\User;
use App\Models\UserOAuthIdentity;
use Carbon\Carbon;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use iEXPackages\ReferralSystem\ReferralSystemFacade;
use RuntimeException;

/**
 * Explicit identity resolution — never silent email takeover.
 */
final class OAuthIdentityResolver
{
    public function __construct(
        private readonly OAuthAccountGate $gate,
        private readonly OAuthReauthGuard $reauth,
    ) {
    }

    /**
     * @return array{outcome: string, user?: User, code?: string, existing_user_id?: int}
     */
    public function resolve(OAuthProfile $profile, array $tx, Request $request): array
    {
        $intent = (string) ($tx['intent'] ?? 'login');

        if ($intent === 'link') {
            return $this->resolveLink($profile, $tx, $request);
        }

        return $this->resolveLoginOrRegister($profile, $request);
    }

    /**
     * @return array{outcome: string, user?: User, code?: string, existing_user_id?: int}
     */
    private function resolveLoginOrRegister(OAuthProfile $profile, Request $request): array
    {
        /** @var UserOAuthIdentity|null $identity */
        $identity = UserOAuthIdentity::query()
            ->where('provider', $profile->provider)
            ->where('provider_subject', $profile->subject)
            ->first();

        if ($identity) {
            /** @var User|null $user */
            $user = User::query()->find($identity->user_id);
            if (!$user) {
                throw new RuntimeException('OAUTH_IDENTITY_ORPHAN');
            }

            $this->gate->assertCanAuthenticate($user, $request);
            $this->touchIdentityEmail($identity, $profile);

            return ['outcome' => 'login', 'user' => $user];
        }

        // Historical / pre-backfill: users.provider + provider_id (no silent email merge).
        $legacy = $this->findLegacyProviderUser($profile);
        if ($legacy) {
            $this->gate->assertCanAuthenticate($legacy, $request);
            $this->ensureIdentityRow($legacy, $profile);

            return ['outcome' => 'login', 'user' => $legacy];
        }

        if ($profile->email) {
            /** @var User|null $byEmail */
            $byEmail = User::query()->where('email', $profile->email)->first();
            if ($byEmail) {
                // CASE C — never auto-merge.
                return [
                    'outcome' => 'link_required',
                    'code' => 'OAUTH_LINK_REQUIRED',
                    'existing_user_id' => (int) $byEmail->id,
                ];
            }
        }

        // CASE B — new account. Google requires verified email; Yandex requires email present.
        $this->assertEmailAcceptableForRegistration($profile);

        $user = $this->createUserWithIdentity($profile, $request);
        $this->gate->assertCanAuthenticate($user, $request);

        return ['outcome' => 'register', 'user' => $user];
    }

    /**
     * @return array{outcome: string, user?: User, code?: string}
     */
    private function resolveLink(OAuthProfile $profile, array $tx, Request $request): array
    {
        $linkUserId = $tx['link_user_id'] ?? null;
        if (!$linkUserId) {
            throw new RuntimeException('OAUTH_LINK_USER_REQUIRED');
        }

        /** @var User|null $authUser */
        $authUser = $request->user();
        if (!$authUser || (int) $authUser->id !== (int) $linkUserId) {
            throw new RuntimeException('OAUTH_LINK_AUTH_REQUIRED');
        }

        $this->reauth->assertFresh($request);
        $this->gate->assertCanAuthenticate($authUser, $request);

        return DB::transaction(function () use ($profile, $authUser, $request) {
            /** @var User $locked */
            $locked = User::query()->whereKey($authUser->id)->lockForUpdate()->firstOrFail();

            $existingSubject = UserOAuthIdentity::query()
                ->where('provider', $profile->provider)
                ->where('provider_subject', $profile->subject)
                ->lockForUpdate()
                ->first();

            if ($existingSubject && (int) $existingSubject->user_id !== (int) $locked->id) {
                throw new RuntimeException('OAUTH_SUBJECT_OWNED_BY_OTHER');
            }

            if ($existingSubject && (int) $existingSubject->user_id === (int) $locked->id) {
                $this->touchIdentityEmail($existingSubject, $profile);
                $this->reauth->clear($request);

                return ['outcome' => 'link', 'user' => $locked];
            }

            $existingProviderForUser = UserOAuthIdentity::query()
                ->where('user_id', $locked->id)
                ->where('provider', $profile->provider)
                ->lockForUpdate()
                ->first();

            if ($existingProviderForUser) {
                throw new RuntimeException('OAUTH_PROVIDER_ALREADY_LINKED');
            }

            try {
                UserOAuthIdentity::create([
                    'user_id' => $locked->id,
                    'provider' => $profile->provider,
                    'provider_subject' => $profile->subject,
                    'provider_email' => $profile->email,
                    'provider_email_verified' => $profile->emailVerified,
                ]);
            } catch (QueryException $e) {
                throw new RuntimeException('OAUTH_SUBJECT_CONFLICT', 0, $e);
            }

            // Preserve legacy columns if empty for this provider slot (do not overwrite other provider).
            if (empty($locked->provider) || $locked->provider === $profile->provider) {
                $locked->forceFill([
                    'provider' => $profile->provider,
                    'provider_id' => $profile->subject,
                ])->save();
            }

            $this->reauth->clear($request);

            return ['outcome' => 'link', 'user' => $locked->fresh()];
        });
    }

    private function findLegacyProviderUser(OAuthProfile $profile): ?User
    {
        /** @var User|null $user */
        $user = User::query()
            ->where('provider', $profile->provider)
            ->where('provider_id', $profile->subject)
            ->first();

        return $user;
    }

    private function ensureIdentityRow(User $user, OAuthProfile $profile): void
    {
        $exists = UserOAuthIdentity::query()
            ->where('provider', $profile->provider)
            ->where('provider_subject', $profile->subject)
            ->exists();

        if ($exists) {
            return;
        }

        $other = UserOAuthIdentity::query()
            ->where('user_id', $user->id)
            ->where('provider', $profile->provider)
            ->first();

        if ($other && $other->provider_subject !== $profile->subject) {
            throw new RuntimeException('OAUTH_PROVIDER_ALREADY_LINKED');
        }
        if ($other) {
            return;
        }

        try {
            UserOAuthIdentity::create([
                'user_id' => $user->id,
                'provider' => $profile->provider,
                'provider_subject' => $profile->subject,
                'provider_email' => $profile->email,
                'provider_email_verified' => $profile->emailVerified,
            ]);
        } catch (QueryException $e) {
            // Concurrent insert of same subject — re-read and continue if ours.
            $row = UserOAuthIdentity::query()
                ->where('provider', $profile->provider)
                ->where('provider_subject', $profile->subject)
                ->first();
            if (!$row || (int) $row->user_id !== (int) $user->id) {
                throw new RuntimeException('OAUTH_SUBJECT_CONFLICT', 0, $e);
            }
        }
    }

    private function assertEmailAcceptableForRegistration(OAuthProfile $profile): void
    {
        if ($profile->email === null || $profile->email === '') {
            throw new RuntimeException('OAUTH_EMAIL_REQUIRED');
        }

        if ($profile->provider === OAuthProviders::GOOGLE && $profile->emailVerified !== true) {
            throw new RuntimeException('OAUTH_EMAIL_UNVERIFIED');
        }

        // Yandex: email present is required; verified claim is unavailable → conservative (null).
        if (function_exists('ban_check_email')) {
            try {
                $ban = ban_check_email($profile->email);
                if (is_array($ban)) {
                    throw new RuntimeException('OAUTH_EMAIL_BANNED');
                }
            } catch (RuntimeException $e) {
                throw $e;
            } catch (\Throwable) {
                // Fail closed if ban subsystem is unavailable during registration.
                throw new RuntimeException('OAUTH_EMAIL_BAN_CHECK_FAILED');
            }
        }
    }

    private function createUserWithIdentity(OAuthProfile $profile, Request $request): User
    {
        try {
            return DB::transaction(function () use ($profile, $request) {
                $name = $profile->name;
                if ($name === null || $name === '') {
                    $name = strstr($profile->email, '@', true) ?: 'User';
                }

                // Google: trust verified claim. Yandex: email is required but no verified
                // claim is exposed — treat present IdP email as mailbox ownership for signup.
                $emailVerifiedAt = null;
                if ($profile->email) {
                    if ($profile->provider === OAuthProviders::GOOGLE) {
                        if ($profile->emailVerified === true) {
                            $emailVerifiedAt = Carbon::now();
                        }
                    } elseif ($profile->provider === OAuthProviders::YANDEX) {
                        $emailVerifiedAt = Carbon::now();
                    }
                }

                /** @var User $user */
                $user = User::create([
                    'name' => mb_substr($name, 0, 191),
                    'email' => $profile->email,
                    // password column is NOT NULL — random unusable secret (user can set via reset/set-password).
                    'password' => Hash::make(Str::random(64)),
                    'oauth_password_is_placeholder' => true,
                    'ip_address' => $request->ip(),
                    'last_login_at' => Carbon::now(),
                    'last_activity_at' => Carbon::now(),
                    'language' => app()->getLocale(),
                    'provider' => $profile->provider,
                    'provider_id' => $profile->subject,
                    'email_verified_at' => $emailVerifiedAt,
                ]);

            $ref = $request->cookie('ref');
            if (class_exists(ReferralSystemFacade::class)) {
                try {
                    ReferralSystemFacade::register($user, $ref);
                } catch (\Throwable) {
                    // Referral is non-critical; OAuth identity must still persist.
                }
            }

            event(new Registered($user));

                UserOAuthIdentity::create([
                    'user_id' => $user->id,
                    'provider' => $profile->provider,
                    'provider_subject' => $profile->subject,
                    'provider_email' => $profile->email,
                    'provider_email_verified' => $profile->emailVerified,
                ]);

                return $user->fresh();
            });
        } catch (QueryException $e) {
            // Race: another request created the same provider subject or email.
            $identity = UserOAuthIdentity::query()
                ->where('provider', $profile->provider)
                ->where('provider_subject', $profile->subject)
                ->first();
            if ($identity) {
                throw new RuntimeException('OAUTH_SUBJECT_CONFLICT', 0, $e);
            }
            throw new RuntimeException('OAUTH_REGISTER_CONFLICT', 0, $e);
        }
    }

    private function touchIdentityEmail(UserOAuthIdentity $identity, OAuthProfile $profile): void
    {
        $dirty = false;
        if ($profile->email && $identity->provider_email !== $profile->email) {
            $identity->provider_email = $profile->email;
            $dirty = true;
        }
        if ($profile->emailVerified !== null
            && $identity->provider_email_verified !== $profile->emailVerified) {
            $identity->provider_email_verified = $profile->emailVerified;
            $dirty = true;
        }
        if ($dirty) {
            $identity->save();
        }
    }
}
