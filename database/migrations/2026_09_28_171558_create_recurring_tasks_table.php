<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority')->default('medium');
            $table->string('frequency');         // daily | weekly | monthly
            $table->unsignedInteger('interval')->default(1);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('next_run_at');
            $table->dateTime('last_run_at')->nullable();
            $table->string('status')->default('active'); // active | paused | completed | cancelled
            $table->boolean('auto_create_reminder')->default(false);
            $table->unsignedInteger('reminder_offset_minutes')->default(60);
            $table->timestamps();

            $table->index('project_id');
            $table->index('assigned_to');
            $table->index('status');
            $table->index('next_run_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_tasks');
    }
};
