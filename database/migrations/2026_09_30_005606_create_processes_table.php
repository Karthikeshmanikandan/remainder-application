<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('process_template_id')->nullable()->constrained('process_templates')->nullOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->foreignId('responsible_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('frequency'); // daily, weekly, monthly
            $table->unsignedInteger('interval')->default(1);
            $table->boolean('reminder_enabled')->default(true);
            $table->string('reminder_time')->nullable(); // e.g. "17:00"
            $table->dateTime('next_run_at')->nullable()->index();
            $table->dateTime('last_run_at')->nullable();
            $table->boolean('telegram_enabled')->default(false);
            $table->boolean('in_app_enabled')->default(true);
            $table->string('status')->default('active'); // active, paused, completed, cancelled
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processes');
    }
};
