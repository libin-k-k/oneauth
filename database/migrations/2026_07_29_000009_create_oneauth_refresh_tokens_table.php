<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('oneauth_refresh_tokens')) {
            return;
        }

        Schema::create('oneauth_refresh_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('authenticatable_type');
            $table->unsignedBigInteger('authenticatable_id');
            $table->index(['authenticatable_type', 'authenticatable_id'], 'oa_refresh_auth_idx');
            $table->string('token_hash', 191)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['expires_at', 'revoked_at']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('oneauth_refresh_tokens')) {
            Schema::drop('oneauth_refresh_tokens');
        }
    }
};
