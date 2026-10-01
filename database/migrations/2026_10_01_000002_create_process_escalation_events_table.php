<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_escalation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_execution_id')->constrained('process_executions')->cascadeOnDelete();
            $table->foreignId('escalation_rule_id')->constrained('process_escalation_rules')->cascadeOnDelete();
            $table->unsignedTinyInteger('level')->default(1);
            $table->dateTime('triggered_at');
            $table->dateTime('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('triggered'); // triggered, acknowledged, resolved, cancelled
            $table->string('notification_status')->nullable();
            $table->timestamps();

            $table->unique(['process_execution_id', 'escalation_rule_id']);
            $table->index(['status', 'triggered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_escalation_events');
    }
};
