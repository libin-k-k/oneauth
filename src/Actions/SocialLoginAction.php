<?php

namespace Libinkk\OneAuth\Actions;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Libinkk\OneAuth\Events\SocialLogin;
use Libinkk\OneAuth\Exceptions\OneAuthException;
use Libinkk\OneAuth\Exceptions\TwoFactorRequiredException;
use Libinkk\OneAuth\Models\SocialAccount;
use Libinkk\OneAuth\Pipelines\LoginPipeline;
use Libinkk\OneAuth\Support\EmailVerificationStatus;
use Libinkk\OneAuth\Support\LoginRateLimiter;

class SocialLoginAction
{
    public function __construct(
        private LoginPipeline $pipeline,
        private LoginRateLimiter $rateLimiter
    ) {
    }

    public function execute(string $provider, array $payload): array
    {
        if (!in_array($provider, ['google', 'apple'], true)) {
            throw new OneAuthException('Unsupported social provider: ' . $provider);
        }

        $providerDriver = app('oneauth.oauth.' . $provider);
        $social = $providerDriver->userFromToken((string) ($payload['token'] ?? ''));
        $userModel = oneauth_user_model();
        $identifier = 'social:' . $provider . ':' . ($social['provider_id'] ?? 'unknown');
        $ip = (string) request()->ip();

        $this->rateLimiter->ensureNotLocked($identifier, $ip);

        try {
            $account = SocialAccount::query()
                ->where('provider', $provider)
                ->where('provider_id', $social['provider_id'])
                ->first();

            $user = $account?->authenticatable;

            if (!$user && !empty($social['email']) && (bool) config('oneauth.social.link_by_email', false)) {
                $candidate = $userModel::query()->where('email', $social['email'])->first();
                if ($candidate && EmailVerificationStatus::isVerified($candidate)) {
                    $user = $candidate;
                } elseif ($candidate) {
                    throw new OneAuthException(
                        'An account with this email exists but is not verified. Verify the email before linking social login.'
                    );
                }
            }

            if (!$user && !empty($social['email']) && !(bool) config('oneauth.social.link_by_email', false)) {
                $existing = $userModel::query()->where('email', $social['email'])->first();
                if ($existing) {
                    throw new OneAuthException(
                        'An account with this email already exists. Sign in and link the provider, or enable social.link_by_email for verified emails only.'
                    );
                }
            }

            if (!$user && (bool) config('oneauth.social.create_user_if_missing', true)) {
                $user = $userModel::query()->create([
                    'name' => $social['name'] ?? ucfirst($provider) . ' User',
                    'email' => $social['email'] ?? null,
                    'password' => Hash::make(Str::random(40)),
                    'email_verified_at' => now(),
                ]);
            }

            if (!$user) {
                throw new OneAuthException('Unable to resolve a user for social login.');
            }

            SocialAccount::query()->updateOrCreate(
                ['provider' => $provider, 'provider_id' => $social['provider_id']],
                [
                    'authenticatable_type' => $user::class,
                    'authenticatable_id' => $user->getKey(),
                    'email' => $social['email'] ?? null,
                    'name' => $social['name'] ?? null,
                    'meta' => $social['meta'] ?? [],
                ]
            );

            Event::dispatch(new SocialLogin($user, ['provider' => $provider]));

            $result = $this->pipeline->authenticateResolvedUser($user, $identifier);
            $this->rateLimiter->clear($identifier, $ip);

            return $result;
        } catch (TwoFactorRequiredException $exception) {
            throw $exception;
        } catch (\Throwable $throwable) {
            $this->rateLimiter->hit($identifier, $ip);
            throw $throwable;
        }
    }
}
