<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('oneauth_social_accounts')) {
            return;
        }

        Schema::create('oneauth_social_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('authenticatable_type');
            $table->unsignedBigInteger('authenticatable_id');
            $table->index(['authenticatable_type', 'authenticatable_id'], 'oa_social_auth_idx');
            $table->string('provider', 30);
            $table->string('provider_id', 191);
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_id']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('oneauth_social_accounts')) {
            Schema::drop('oneauth_social_accounts');
        }
    }
};
