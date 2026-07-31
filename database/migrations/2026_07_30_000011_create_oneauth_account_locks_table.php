<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('oneauth_account_locks')) {
            return;
        }

        Schema::create('oneauth_account_locks', function (Blueprint $table): void {
            $table->id();
            $table->string('identifier', 191)->unique();
            $table->timestamp('locked_until')->nullable();
            $table->string('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('locked_until');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('oneauth_account_locks')) {
            Schema::drop('oneauth_account_locks');
        }
    }
};
