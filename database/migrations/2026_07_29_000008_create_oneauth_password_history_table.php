<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('oneauth_password_history')) {
            return;
        }

        Schema::create('oneauth_password_history', function (Blueprint $table): void {
            $table->id();
            $table->morphs('authenticatable');
            $table->string('password_hash');
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index('changed_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('oneauth_password_history')) {
            Schema::drop('oneauth_password_history');
        }
    }
};
