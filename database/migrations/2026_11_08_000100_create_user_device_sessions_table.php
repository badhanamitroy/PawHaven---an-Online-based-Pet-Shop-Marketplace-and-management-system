<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_device_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->char('previous_token_hash', 64)->nullable()->index();
            $table->timestamp('previous_token_valid_until')->nullable();
            $table->string('device_name', 120);
            $table->string('browser_name', 60)->nullable();
            $table->string('browser_version', 30)->nullable();
            $table->string('platform', 60)->nullable();
            $table->string('device_type', 20);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('last_activity_at');
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamp('last_rotated_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_device_sessions');
    }
};
