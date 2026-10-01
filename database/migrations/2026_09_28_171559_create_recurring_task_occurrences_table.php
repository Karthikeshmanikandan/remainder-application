<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_task_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_task_id')->constrained()->cascadeOnDelete();
            $table->string('occurrence_key');      // deterministic: "{id}-{YYYY-MM-DDTHH:mm}"
            $table->dateTime('scheduled_for');
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            // Core idempotency constraint — prevents any duplicate occurrence
            $table->unique(['recurring_task_id', 'occurrence_key']);
            $table->index('recurring_task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_task_occurrences');
    }
};
