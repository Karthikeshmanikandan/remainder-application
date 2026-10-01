<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_escalation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_id')->constrained('processes')->cascadeOnDelete();
            $table->unsignedTinyInteger('level')->default(1);
            $table->unsignedInteger('delay_minutes')->default(30);
            $table->foreignId('escalate_to_user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['process_id', 'level']);
            $table->index(['process_id', 'is_active', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_escalation_rules');
    }
};
