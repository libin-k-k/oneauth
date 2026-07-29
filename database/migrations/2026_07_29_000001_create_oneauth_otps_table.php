<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('oneauth_otps')) {
            return;
        }

        Schema::create('oneauth_otps', function (Blueprint $table): void {
            $table->id();
            $table->morphs('authenticatable');
            $table->string('purpose', 50);
            $table->string('channel', 20);
            $table->string('target', 191);
            $table->string('code_hash');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('resends')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['purpose', 'target']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('oneauth_otps')) {
            Schema::drop('oneauth_otps');
        }
    }
};
