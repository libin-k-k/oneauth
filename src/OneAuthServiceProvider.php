<?php

namespace Libinkk\OneAuth;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Libinkk\OneAuth\Commands\CleanupCommand;
use Libinkk\OneAuth\Commands\DoctorCommand;
use Libinkk\OneAuth\Commands\InstallCommand;
use Libinkk\OneAuth\Commands\PublishCommand;
use Libinkk\OneAuth\Contracts\AuthenticationDriverInterface;
use Libinkk\OneAuth\Contracts\DeviceRepositoryInterface;
use Libinkk\OneAuth\Contracts\OTPProviderInterface;
use Libinkk\OneAuth\Contracts\OAuthProviderInterface;
use Libinkk\OneAuth\Contracts\SessionRepositoryInterface;
use Libinkk\OneAuth\Drivers\SessionDriver;
use Libinkk\OneAuth\Middleware\EnsureOtpVerified;
use Libinkk\OneAuth\Middleware\EnsureTwoFactorVerified;
use Libinkk\OneAuth\Middleware\EnsureVerified;
use Libinkk\OneAuth\Middleware\OneAuthAuthenticate;
use Libinkk\OneAuth\Providers\OAuth\AppleOAuthProvider;
use Libinkk\OneAuth\Providers\OAuth\GoogleOAuthProvider;
use Libinkk\OneAuth\Providers\OTP\EmailOtpProvider;
use Libinkk\OneAuth\Providers\OTP\SmsOtpProvider;
use Libinkk\OneAuth\Providers\OTP\WhatsAppOtpProvider;
use Libinkk\OneAuth\Repositories\DeviceRepository;
use Libinkk\OneAuth\Repositories\SessionRepository;

class OneAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/oneauth.php', 'oneauth');

        $this->app->singleton(OneAuthManager::class, fn ($app) => new OneAuthManager($app));

        $this->app->bind(AuthenticationDriverInterface::class, SessionDriver::class);
        $this->app->bind(OTPProviderInterface::class, function ($app) {
            $provider = (string) config('oneauth.otp.provider', 'email');
            return match ($provider) {
                'email' => $app->make(EmailOtpProvider::class),
                'sms' => $app->make(SmsOtpProvider::class),
                'whatsapp' => $app->make(WhatsAppOtpProvider::class),
                default => $app->make(EmailOtpProvider::class),
            };
        });
        $this->app->bind(OAuthProviderInterface::class, GoogleOAuthProvider::class);
        $this->app->bind(SessionRepositoryInterface::class, SessionRepository::class);
        $this->app->bind(DeviceRepositoryInterface::class, DeviceRepository::class);

        $this->app->bind('oneauth.oauth.google', GoogleOAuthProvider::class);
        $this->app->bind('oneauth.oauth.apple', AppleOAuthProvider::class);
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
}
