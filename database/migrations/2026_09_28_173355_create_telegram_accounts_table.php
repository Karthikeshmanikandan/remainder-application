<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('chat_id')->nullable()->unique();
            $table->string('telegram_user_id')->nullable();
            $table->string('telegram_username')->nullable();
            $table->string('verification_code')->nullable()->unique();
            $table->dateTime('verification_expires_at')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->dateTime('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_accounts');
    }
};
