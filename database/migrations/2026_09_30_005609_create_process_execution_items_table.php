<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_execution_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_execution_id')->constrained('process_executions')->cascadeOnDelete();
            $table->foreignId('process_item_id')->constrained('process_items')->cascadeOnDelete();
            $table->text('question_snapshot');
            $table->string('response_type')->default('yes_no');
            $table->text('response')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('answered_at')->nullable();
            $table->string('status')->default('pending'); // pending, answered, skipped
            $table->timestamps();

            $table->unique(['process_execution_id', 'process_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_execution_items');
    }
};
