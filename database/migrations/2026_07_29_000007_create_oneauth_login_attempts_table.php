<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('oneauth_login_attempts')) {
            return;
        }

        Schema::create('oneauth_login_attempts', function (Blueprint $table): void {
            $table->id();
            $table->string('identifier', 191);
            $table->string('ip_address', 64)->nullable();
            $table->boolean('successful')->default(false);
            $table->timestamp('attempted_at');
            $table->timestamps();

            $table->index(['identifier', 'attempted_at']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('oneauth_login_attempts')) {
            Schema::drop('oneauth_login_attempts');
        }
    }
};
