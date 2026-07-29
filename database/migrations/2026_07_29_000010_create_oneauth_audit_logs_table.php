<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('oneauth_audit_logs')) {
            return;
        }

        Schema::create('oneauth_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->morphs('authenticatable');
            $table->string('event', 100);
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['event', 'occurred_at']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('oneauth_audit_logs')) {
            Schema::drop('oneauth_audit_logs');
        }
    }
};
