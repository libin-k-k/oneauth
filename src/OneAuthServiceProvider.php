<?php

namespace Libinkk\OneAuth;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Libinkk\OneAuth\Commands\CleanupCommand;
use Libinkk\OneAuth\Commands\DoctorCommand;
use Libinkk\OneAuth\Commands\InstallCommand;
use Libinkk\OneAuth\Commands\PublishCommand;
use Libinkk\OneAuth\Contracts\AuthenticationDriverInterface;
use Libinkk\OneAuth\Contracts\DeviceRepositoryInterface;
use Libinkk\OneAuth\Contracts\NotificationProviderInterface;
use Libinkk\OneAuth\Contracts\OTPProviderInterface;
use Libinkk\OneAuth\Contracts\OAuthProviderInterface;
use Libinkk\OneAuth\Contracts\SessionRepositoryInterface;
use Libinkk\OneAuth\Events\AccountLocked;
use Libinkk\OneAuth\Events\EmailVerified;
use Libinkk\OneAuth\Events\OTPSent;
use Libinkk\OneAuth\Events\OTPVerified;
use Libinkk\OneAuth\Events\PasswordChanged;
use Libinkk\OneAuth\Events\PasswordReset;
use Libinkk\OneAuth\Events\SocialLogin;
use Libinkk\OneAuth\Events\SuspiciousLoginDetected;
use Libinkk\OneAuth\Events\TwoFactorDisabled;
use Libinkk\OneAuth\Events\TwoFactorEnabled;
use Libinkk\OneAuth\Events\UserLoggedIn;
use Libinkk\OneAuth\Events\UserLoggedOut;
use Libinkk\OneAuth\Events\UserRegistered;
use Libinkk\OneAuth\Exceptions\OneAuthException;
use Libinkk\OneAuth\Middleware\EnsureOtpVerified;
use Libinkk\OneAuth\Middleware\EnsureTwoFactorVerified;
use Libinkk\OneAuth\Middleware\EnsureVerified;
use Libinkk\OneAuth\Middleware\OneAuthAuthenticate;
use Libinkk\OneAuth\Providers\Notification\EmailNotificationProvider;
use Libinkk\OneAuth\Providers\Notification\SmsNotificationProvider;
use Libinkk\OneAuth\Providers\OAuth\SocialiteOAuthProvider;
use Libinkk\OneAuth\Providers\OTP\EmailOtpProvider;
use Libinkk\OneAuth\Providers\OTP\SmsOtpProvider;
use Libinkk\OneAuth\Providers\OTP\WhatsAppOtpProvider;
use Libinkk\OneAuth\Repositories\DeviceRepository;
use Libinkk\OneAuth\Repositories\SessionRepository;
use Libinkk\OneAuth\Support\AuditLogger;

class OneAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/oneauth.php', 'oneauth');

        $this->app->singleton(OneAuthManager::class, fn ($app) => new OneAuthManager($app));
        $this->app->singleton(AuditLogger::class);

        $this->app->bind(AuthenticationDriverInterface::class, function ($app) {
            return $app->make(OneAuthManager::class)->driver();
        });
        $this->app->bind(OTPProviderInterface::class, function ($app) {
            $provider = (string) config('oneauth.otp.provider', 'email');
            return match ($provider) {
                'email' => $app->make(EmailOtpProvider::class),
                'sms' => $app->make(SmsOtpProvider::class),
                'whatsapp' => $app->make(WhatsAppOtpProvider::class),
                default => $app->make(EmailOtpProvider::class),
            };
        });
        $this->app->bind(OAuthProviderInterface::class, function ($app) {
            return $app->make('oneauth.oauth.google');
        });
        $this->app->bind(SessionRepositoryInterface::class, SessionRepository::class);
        $this->app->bind(DeviceRepositoryInterface::class, DeviceRepository::class);
        $this->app->bind(NotificationProviderInterface::class, function ($app) {
            $channel = (string) config('oneauth.otp.provider', 'email');

            return match ($channel) {
                'sms', 'whatsapp' => $app->make(SmsNotificationProvider::class),
                default => $app->make(EmailNotificationProvider::class),
            };
        });

        foreach ((array) config('oneauth.social.providers', ['google', 'apple']) as $provider) {
            $provider = (string) $provider;
            $this->app->bind('oneauth.oauth.' . $provider, fn () => new SocialiteOAuthProvider($provider));
        }
    }

    public function boot(Router $router): void
    {
        $this->publishes([
            __DIR__ . '/../config/oneauth.php' => config_path('oneauth.php'),
        ], 'oneauth-config');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'oneauth-migrations');

            $this->commands([
                InstallCommand::class,
                PublishCommand::class,
                DoctorCommand::class,
                CleanupCommand::class,
            ]);
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->registerRoutes();
        $this->registerAuditListeners();
        $this->registerExceptionRenderer();

        $router->aliasMiddleware('oneauth.auth', OneAuthAuthenticate::class);
        $router->aliasMiddleware('oneauth.verified', EnsureVerified::class);
        $router->aliasMiddleware('oneauth.otp', EnsureOtpVerified::class);
        $router->aliasMiddleware('oneauth.twofactor', EnsureTwoFactorVerified::class);
    }

    protected function registerRoutes(): void
    {
        if (!config('oneauth.routes.enabled', true)) {
            return;
        }

        $this->loadRoutesFrom(__DIR__ . '/../routes/oneauth.php');
    }

    protected function registerAuditListeners(): void
    {
        $events = [
            UserRegistered::class,
            UserLoggedIn::class,
            UserLoggedOut::class,
            EmailVerified::class,
            OTPSent::class,
            OTPVerified::class,
            PasswordChanged::class,
            PasswordReset::class,
            TwoFactorEnabled::class,
            TwoFactorDisabled::class,
            SocialLogin::class,
            SuspiciousLoginDetected::class,
            AccountLocked::class,
        ];

        foreach ($events as $event) {
            Event::listen($event, [AuditLogger::class, 'handle']);
        }
    }

    protected function registerExceptionRenderer(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);
        if (!method_exists($handler, 'renderable')) {
            return;
        }

        $handler->renderable(function (OneAuthException $exception, $request) {
            $prefix = trim((string) config('oneauth.routes.prefix', 'oneauth'), '/');
            $isOneAuthRoute = $prefix !== '' && str_starts_with($request->path(), $prefix);

            if (!$request->expectsJson() && !$isOneAuthRoute) {
                return null;
            }

            return response()->json($exception->toArray(), $exception->status());
        });
    }
}
