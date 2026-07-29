<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('oneauth_devices')) {
            return;
        }

        Schema::create('oneauth_devices', function (Blueprint $table): void {
            $table->id();
            $table->morphs('authenticatable');
            $table->string('device_name')->nullable();
            $table->string('browser')->nullable();
            $table->string('os')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->string('country', 50)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('fingerprint', 191)->nullable();
            $table->boolean('trusted')->default(false);
            $table->timestamp('first_login_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->index('fingerprint');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('oneauth_devices')) {
            Schema::drop('oneauth_devices');
        }
    }
};
