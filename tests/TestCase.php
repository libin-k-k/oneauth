<?php

namespace Libinkk\OneAuth\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Libinkk\OneAuth\OneAuthServiceProvider;
use Libinkk\OneAuth\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [OneAuthServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('oneauth.user_model', User::class);
        $app['config']->set('oneauth.driver', 'session');
        $app['config']->set('oneauth.routes.enabled', true);
        $app['config']->set('mail.default', 'array');
        $app['config']->set('oneauth.jwt.secret', 'test-jwt-secret-key-for-oneauth');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('username')->nullable()->unique();
            $table->string('phone')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->attachSessionToRequest();
    }

    protected function attachSessionToRequest(): void
    {
        $session = $this->app->make('session.store');
        $session->start();

        $request = $this->app->make('request');
        $request->setLaravelSession($session);
    }
}
