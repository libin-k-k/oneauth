<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('oneauth_email_verifications')) {
            return;
        }

        Schema::create('oneauth_email_verifications', function (Blueprint $table): void {
            $table->id();
            $table->morphs('authenticatable');
            $table->string('email', 191);
            $table->string('token_hash')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['email', 'verified_at']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('oneauth_email_verifications')) {
            Schema::drop('oneauth_email_verifications');
        }
    }
};
