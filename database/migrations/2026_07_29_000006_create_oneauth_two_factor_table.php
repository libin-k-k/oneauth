<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('oneauth_two_factor')) {
            return;
        }

        Schema::create('oneauth_two_factor', function (Blueprint $table): void {
            $table->id();
            $table->string('authenticatable_type');
            $table->unsignedBigInteger('authenticatable_id');
            $table->index(['authenticatable_type', 'authenticatable_id'], 'oa_2fa_auth_idx');
            $table->boolean('enabled')->default(false);
            $table->string('method', 30)->default('totp');
            $table->text('secret_encrypted')->nullable();
            $table->json('recovery_codes')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('oneauth_two_factor')) {
            Schema::drop('oneauth_two_factor');
        }
    }
};
