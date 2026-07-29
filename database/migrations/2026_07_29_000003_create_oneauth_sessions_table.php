<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('oneauth_sessions')) {
            return;
        }

        Schema::create('oneauth_sessions', function (Blueprint $table): void {
            $table->id();
            $table->morphs('authenticatable');
            $table->string('session_id', 191)->unique();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('oneauth_sessions')) {
            Schema::drop('oneauth_sessions');
        }
    }
};
